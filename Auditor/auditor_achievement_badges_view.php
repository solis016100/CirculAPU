<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';
    require_once '../delete.php';

    $id = $_GET['achieveBadgeID'] ?? '';

    // Delete Badge
    if (isset($_POST['delete_badge'])) {
        $badgeID = $_POST['badgeID'];
        // Get badge name and icon
        $get = $conn->prepare("SELECT achieveBadgeName, achieveBadgeIcon FROM achievementbadges WHERE achieveBadgeID = ?");
        $get->bind_param("s", $badgeID);
        $get->execute();
        $badgeData = $get->get_result()->fetch_assoc();
        $get->close();

        if ($badgeData) {
            // Delete Badge
            $delete = $conn->prepare("DELETE FROM achievementbadges WHERE achieveBadgeID = ?");
            $delete->bind_param("s", $badgeID);

            if ($delete->execute()) {
                // Delete Badge Icon
                if (!empty($badgeData['achieveBadgeIcon']) && file_exists("../assets/images/" . $badgeData['achieveBadgeIcon'])) {
                    unlink("../assets/images/" . $badgeData['achieveBadgeIcon']);
                }

                // Create System Log ID
                $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)), 0) + 1 AS nextID FROM systemlog");
                $row = $result->fetch_assoc();
                $logID = "LOG" . str_pad($row['nextID'], 3, "0", STR_PAD_LEFT);

                // Create System Log
                $description = "Deleted achievement badge: " . $badgeData['achieveBadgeName'];
                $log = $conn->prepare("INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Delete', 'Badge', ?, ?) ");
                $log->bind_param("ssss", $logID, $_SESSION['user_id'],$badgeID,$description);
                $log->execute();
                $log->close();

                // Back to Management
                header("Location: auditor_achievement_badges_management.php?deleted=1");
                exit();
            }else {
                $delete->close();
            }
            $delete->close();
        }
    }

    $stmt = $conn->prepare("SELECT achieveBadgeID, achieveBadgeName, achieveBadgeDescription,
            achieveBadgeRequirement, achieveBadgeIcon FROM achievementbadges WHERE achieveBadgeID = ?");

    $stmt->bind_param("s", $id);
    $stmt->execute();
    $badge = $stmt->get_result()->fetch_assoc();

    if (!$badge) {
        header("Location: auditor_achievement_badges_management.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Achievement Badge Details - CirculAPU</title>
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
                    <a href="auditor_achievement_badges_management.php">
                        Achievement Badge Management
                    </a>
                    &gt; Achievement Badge Details
                </div>

                <h1>Achievement Badge Details</h1>

                <!-- Badge Details -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="management-view-card">
                        <!-- Badge Information -->
                        <div class="management-view-body">
                            <!-- Badge ID -->
                            <div class="auditor-management-detail-row">
                                <label>Badge ID</label>
                                <div class="auditor-management-detail-value">
                                    <?php echo htmlspecialchars($badge['achieveBadgeID']); ?>
                                </div>
                            </div>

                            <!-- Badge Name -->
                            <div class="auditor-management-detail-row">
                                <label>Badge Name</label>
                                <div class="auditor-management-detail-value">
                                    <?php echo htmlspecialchars($badge['achieveBadgeName']); ?>
                                </div>
                            </div>

                            <!-- Badge Icon -->
                            <div class="auditor-management-detail-row">
                                <label>Badge Icon</label>
                                <div class="badge-edit-icon">
                                    <div class="badge-view-icon-box">
                                        <img src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>" alt="Badge Icon">
                                    </div>
                                    <a href="#" class="auditor-management-view-image" onclick="openBadgeImage(); return false;">
                                        View Image
                                    </a>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="auditor-management-detail-row">
                                <label>Description</label>
                                <div class="auditor-management-detail-value remarks">
                                    <?php echo nl2br(htmlspecialchars($badge['achieveBadgeDescription'])); ?>
                                </div>
                            </div>

                            <!-- Requirement -->
                            <div class="auditor-management-detail-row">
                                <label>Requirement</label>
                                <div class="auditor-management-detail-value remarks">
                                    <?php echo nl2br(htmlspecialchars($badge['achieveBadgeRequirement'])); ?>
                                </div>
                            </div>
                        </div>


                        <!-- Action Buttons -->
                        <div class="auditor-management-detail-side">
                            <!-- Edit -->
                            <a href="auditor_achievement_badges_edit.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>" class="auditor-management-detail-action">
                                <img src="../assets/icons/edit.png" alt="Edit">
                                Edit
                            </a>

                            <!-- Print -->
                            <a href="auditor_achievement_badges_view.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>&print=1" class="auditor-management-detail-action">
                                <img src="../assets/icons/print.png" alt="Print">
                                Print
                            </a>

                            <!-- Delete -->
                            <button type="button" class="auditor-management-detail-action delete" onclick="openDeletePopout()">
                                <img src="../assets/icons/delete.png" alt="Delete">
                                Delete
                            </button>

                            <!-- Back Button -->
                            <div class="auditor-management-detail-back">
                                <a href="auditor_achievement_badges_management.php">
                                    Back
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Print -->
    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        <script>
            window.onload = function() {
                window.print();
            };
            window.onafterprint = function() {
                <?php if (isset($_GET['from']) && $_GET['from'] === 'management'): ?>
                    window.location.href = 'auditor_achievement_badges_management.php';
                <?php endif; ?>
            };
        </script>
    <?php endif; ?>

    <!-- Delete Popout -->
    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Achievement Badge Confirmation</h2>
            <p>Are you sure you want to delete this achievement badge?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <input type="hidden" name="badgeID" value="<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>">
                    <button type="submit" name="delete_badge" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>


    <script>
        function openDeletePopout() {
            document.getElementById('deletePopout').classList.add('show');
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').classList.remove('show');
        }

        function openBadgeImage() {
            document.getElementById('badgeImagePopout').style.display = 'flex';
        }

        function closeBadgeImage() {
            document.getElementById('badgeImagePopout').style.display = 'none';
        }

    </script>

    <!-- Badge Image Pop Out -->
    <div id="badgeImagePopout" class="image-popout" onclick="closeBadgeImage()">
        <span class="image-popout-close" onclick="event.stopPropagation(); closeBadgeImage();">
            &times;
        </span>
        <img src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>" alt="Badge Icon" onclick="event.stopPropagation();">
    </div>

</body>
</html>
