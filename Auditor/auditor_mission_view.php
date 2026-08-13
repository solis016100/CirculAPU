<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['id'] ?? '';

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
                    <a href="auditor_mission_management.php">
                        Mission Management
                    </a>
                    &gt;
                    Mission Details
                </div>

                <h1>
                    Mission Details
                </h1>

                <div class="auditor-management-detail-layout">
                    <div class="auditor-management-detail-information">
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
                        <a class="auditor-management-detail-action"
                        href="auditor_mission_edit.php?id=<?= urlencode($id) ?>">
                            <img src="../assets/icons/edit.png" alt="">
                            Edit Mission
                        </a>
                        
                        <a class="auditor-management-detail-action"
                        href="auditor_mission_view.php?id=<?= urlencode($id) ?>&print=1">
                            <img src="../assets/icons/print.png" alt="">
                            Print
                        </a>

                        <a class="auditor-management-detail-action delete"
                        href="auditor_mission_delete.php?id=<?= urlencode($id) ?>"
                        onclick="return confirm('Are you sure you want to delete this mission?');">
                            <img src="../assets/icons/delete.png" alt="">
                            Delete
                        </a>
                    </div>
                </div>

                <div class="auditor-management-detail-back">
                    <a href="auditor_mission_management.php">
                        Back
                    </a>
                </div>
            </main>
        </div>
    </div>
    <?php if (isset($_GET['print'])): ?>
        <script>
            window.onload = () => window.print();
        </script>
    <?php endif; ?>
</body>
</html>