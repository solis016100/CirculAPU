<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['missionID'] ?? '';
    $from = $_GET['from'] ?? 'management';

    if (!$id) {
        header("Location: auditor_missions_management.php");
        exit();
    }

    $search = $_GET['search'] ?? '';
    $missionName = $_GET['missionName'] ?? '';
    $ecoPoints = $_GET['ecoPoints'] ?? '';
    $status = $_GET['status'] ?? '';

    $filterQuery = http_build_query(['search' => $search, 'missionName' => $missionName, 'ecoPoints' => $ecoPoints, 'status' => $status]);

    /* Get mission record */
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
        header("Location: auditor_missions_management.php");
        exit();
    }

    /* Update mission */
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = trim($_POST['missionName']);
        $points = $_POST['ecoPointsReward'];
        $status = $_POST['status'];
        $description = trim($_POST['missionDescription']);
        $requirement = trim($_POST['missionRequirement']);

        $stmt = $conn->prepare("
            UPDATE missions
            SET missionName = ?,
                missionDescription = ?,
                missionRequirement = ?,
                ecoPointsReward = ?,
                status = ?
            WHERE missionID = ?
        ");

        $stmt->bind_param(
            "sssiss",
            $name,
            $description,
            $requirement,
            $points,
            $status,
            $id
        );

        $stmt->execute();

        /* System Log */
        $logID = 'LOG' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $userID = $_SESSION['user_id'];
        $logDescription = "Updated mission: " . $name;

        $log = $conn->prepare("
            INSERT INTO systemlog
            (logID, userID, actionType, refType, refID, description)
            VALUES (?, ?, 'Update', 'Mission', ?, ?)
        ");

        $log->bind_param(
            "ssss",
            $logID,
            $userID,
            $id,
            $logDescription
        );

        $log->execute();

        header("Location: auditor_mission_view.php?missionID=" . urlencode($id) . "&success=1");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Edit Mission Details - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body auditor-management-edit-page">

            <div class="breadcrumb">
                <a href="auditor_missions_management.php">
                    Mission Management
                </a>
                &gt;
                Edit Mission Details
            </div>

            <h1>Edit Mission Details</h1>

            <section class="auditor-management-edit-card">
                <form method="POST" class="auditor-management-edit-form">

                    <div class="auditor-management-edit-row">
                        <label>Mission ID</label>
                        <input type="text"
                               value="<?= htmlspecialchars($mission['missionID']) ?>"
                               readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Mission Name <span class="required">*</span></label>
                        <input type="text"
                               name="missionName"
                               value="<?= htmlspecialchars($mission['missionName']) ?>"
                               required>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>EcoPoints <span class="required">*</span></label>
                        <input type="number" name="ecoPointsReward" min="1" max="1000" value="<?= htmlspecialchars($mission['ecoPointsReward']) ?>" required>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Active"
                                <?= $mission['status'] == 'Active' ? 'selected' : '' ?>>
                                Active
                            </option>

                            <option value="Inactive"
                                <?= $mission['status'] == 'Inactive' ? 'selected' : '' ?>>
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="missionDescription"
                                  required><?= htmlspecialchars($mission['missionDescription']) ?></textarea>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Requirement <span class="required">*</span></label>
                        <textarea name="missionRequirement"
                                  required><?= htmlspecialchars($mission['missionRequirement']) ?></textarea>
                    </div>

                    <div class="auditor-management-edit-actions">
                        <a href="<?= $from === 'view' ? 'auditor_mission_view.php?missionID=' . urlencode($id) : 'auditor_missions_management.php' ?> ">
                            Cancel
                        </a>
                        
                        <button type="submit">
                            Save Changes
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <?php include 'auditor_responsive_navigation.php'; ?>
</body>
</html>