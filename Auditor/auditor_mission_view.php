<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';
    require_once '../delete.php';

    $id = $_GET['missionID'] ?? '';

    $stmt = $conn->prepare("
        SELECT missionID, missionName, missionDescription,
            missionRequirement, ecoPointsReward, status
        FROM missions
        WHERE missionID = ?
    ");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $mission = $stmt->get_result()->fetch_assoc();

    if (!$mission) {
        header("Location: auditor_mission_management.php");
        exit();
    }

    $search = $_GET['search'] ?? '';
    $missionName = $_GET['missionName'] ?? '';
    $ecoPoints = $_GET['ecoPoints'] ?? '';
    $status = $_GET['status'] ?? '';

    $filterQuery = http_build_query(['search' => $search, 'missionName' => $missionName, 'ecoPoints' => $ecoPoints, 'status' => $status]);

    /* DELETE MISSION */
    if (isset($_POST['delete_mission'])) {
        $stmt = $conn->prepare("SELECT missionName FROM missions WHERE missionID = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $mission = $stmt->get_result()->fetch_assoc();

        if ($mission) {
            /* Delete mission */
            $stmt = $conn->prepare("DELETE FROM missions WHERE missionID = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();

            /* System Log */
            $result = $conn->query("
                SELECT COALESCE(MAX(CAST(SUBSTRING(logID,4) AS UNSIGNED)),0)+1 AS nextID
                FROM systemlog
            ");

            $nextID = $result->fetch_assoc()['nextID'];
            $logID = 'LOG' . str_pad($nextID, 3, '0', STR_PAD_LEFT);

            $description = "Deleted mission: " . $mission['missionName'];

            $stmt = $conn->prepare("
                INSERT INTO systemlog
                (logID, userID, actionType, refType, refID, description)
                VALUES (?, ?, 'Delete', 'Mission', ?, ?)
            ");

            $stmt->bind_param(
                "ssss",
                $logID,
                $_SESSION['user_id'],
                $id,
                $description
            );

            $stmt->execute();

            header("Location: auditor_missions_management.php?deleted=1");
            exit();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Auditor Mission Details - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">

            <?php include '../header.php'; ?>

            <main class="page-body auditor-management-detail-page">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="auditor_missions_management.php">
                        Mission Management
                    </a>
                    &gt;
                    Mission Details
                </div>

                <h1>
                    Mission Details
                </h1>

                <!-- Green bar showing Success Updation -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Mission updated successfully.
                    </div>
                <?php endif; ?>

                <div class="management-view-card">
                    <div class="management-view-body">
                        <div class="auditor-management-detail-row">
                            <label>Mission ID</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($mission['missionID']) ?>
                            </div>
                        </div>

                        <div class="auditor-management-detail-row">
                            <label>Mission Name</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($mission['missionName']) ?>
                            </div>
                        </div>

                        <div class="auditor-management-detail-row">
                            <label>EcoPoints</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($mission['ecoPointsReward']) ?>
                            </div>
                        </div>

                        <div class="auditor-management-detail-row">
                            <label>Status</label>
                            <div class="auditor-management-detail-value">
                                <span class="mission-status <?= strtolower($mission['status']) ?>">
                                    <?= htmlspecialchars($mission['status']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="auditor-management-detail-row">
                            <label>Description</label>
                            <div class="auditor-management-detail-value remarks">
                                <?= nl2br(htmlspecialchars($mission['missionDescription'])) ?>
                            </div>
                        </div>

                        <div class="auditor-management-detail-row">
                            <label>Requirement</label>
                            <div class="auditor-management-detail-value remarks">
                                <?= nl2br(htmlspecialchars($mission['missionRequirement'])) ?>
                            </div>
                        </div>

                    </div>

                    <div class="auditor-management-detail-side">
                        <a href="auditor_mission_edit.php?missionID=<?= urlencode($id) ?>&from=view" class="auditor-management-detail-action" title="Edit">
                            <img src="../assets/icons/edit.png" alt="Edit">
                            Edit Mission
                        </a>

                        <?php if (isset($_GET['print'])): ?>
                            <script>
                                window.addEventListener('afterprint', function () {
                                    window.location.href = 'auditor_missions_management.php';
                                });

                                window.onload = function () {
                                    window.print();
                                };
                            </script>
                        <?php endif; ?>
                        <?php printButton('Print', '../assets/icons/print.png', 'auditor-management-detail-action'); ?>

                        <button type="button" class="auditor-management-detail-action delete" onclick="openDeletePopout()">
                            <img src="../assets/icons/delete.png" alt="">
                            Delete
                        </button>

                        <div class="auditor-management-detail-back">
                            <a href="auditor_missions_management.php?<?= $filterQuery ?>">
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>


    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        <script>
            window.onload = function() {
                window.print();
            };

            window.onafterprint = function() {
                window.location.href = 'auditor_missions_management.php';
            };
        </script>
    <?php endif; ?>

    <script>
        function openDeletePopout() {
            document.getElementById('deletePopout').classList.add('show');
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').classList.remove('show');
        }
    </script>

    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Mission Confirmation</h2>
            <p>Are you sure you want to delete this mission?</p>
            <div class="auditor-popout-actions">

                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <button type="submit" name="delete_mission" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'auditor_responsive_navigation.php'; ?>
</body>
</html>