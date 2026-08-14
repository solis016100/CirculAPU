<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['badgeID'] ?? '';
    $from = $_GET['from'] ?? 'management';

    if (!$id) {
        header("Location: auditor_achievement_badges_management.php");
        exit();
    }

    /* Get achievement badge record */
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Edit Achievement Badge Details - CirculAPU</title>
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
                            <select name="ecoPointsReward" required>
                                <?php foreach ([50, 100, 150, 200, 250] as $points): ?>
                                    <option value="<?= $points ?>"
                                        <?= $mission['ecoPointsReward'] == $points ? 'selected' : '' ?>>
                                        <?= $points ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <button type="submit">
                                Save Changes
                            </button>

                            <a href="<?= $from === 'view' ? 'auditor_mission_view.php?missionID=' . urlencode($id) : 'auditor_missions_management.php' ?>">
                                Cancel
                            </a>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>
</body>
</html>