<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';
    require_once '../delete.php';


    $id = $_GET['rewardID'] ?? '';

    /* Get Reward */
    $stmt = mysqli_prepare($conn, "SELECT rewardID, rewardName, rewardDescription, rewardImage, ecoPointsCost, quantity, availability FROM rewards WHERE rewardID = ?");

    mysqli_stmt_bind_param($stmt, "s", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $reward = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$reward) {
        header("Location: auditor_rewards_management.php");
        exit();
    }

    /* Delete Reward */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reward'])) {
        $deleteID = $_POST['rewardID'] ?? '';
        $deleteStmt = mysqli_prepare($conn, " DELETE FROM rewards WHERE rewardID = ?");

        mysqli_stmt_bind_param($deleteStmt, "s", $deleteID);
        mysqli_stmt_execute($deleteStmt);

        mysqli_stmt_close($deleteStmt);

        header("Location: auditor_rewards_management.php?deleted=1");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Reward Details - CirculAPU</title>
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
                    <a href="auditor_rewards_management.php">
                        Reward Management
                    </a>
                    &gt; Reward Details
                </div>

                <h1>Reward Details</h1>

                <!-- Green bar showing Success Updation -->
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">
                        Reward updated successfully.
                    </div>
                <?php endif; ?>

                <!-- Reward Details -->
                <div class="management-view-card">
                    <!-- Reward Information -->
                    <div class="management-view-body">
                        <!-- Reward ID -->
                        <div class="auditor-management-detail-row">
                            <label>Reward ID</label>
                            <div class="auditor-management-detail-value">
                                <?php echo htmlspecialchars($reward['rewardID']); ?>
                            </div>
                        </div>

                        <!-- Reward Name -->
                        <div class="auditor-management-detail-row">
                            <label>Reward Name</label>
                            <div class="auditor-management-detail-value">
                                <?php echo htmlspecialchars($reward['rewardName']); ?>
                            </div>
                        </div>

                        <!-- Reward Image -->
                        <div class="auditor-management-detail-row">
                            <label>Reward Image</label>
                                <div class="badge-edit-icon">
                                    <div class="badge-view-icon-box">
                                        <?php if (!empty($reward['rewardImage'])): ?>
                                            <img src="../assets/images/<?php echo htmlspecialchars($reward['rewardImage']); ?>" alt="<?php echo htmlspecialchars($reward['rewardName']); ?>">
                                        <?php else: ?>
                                            <div class="auditor-management-no-image">
                                                No Image
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($reward['rewardImage'])): ?>
                                        <a href="#" class="auditor-management-view-image" onclick="openRewardImage(); return false;">
                                            View Image
                                        </a>
                                    <?php endif; ?>
                                </div>
                        </div>

                        <!-- Description -->
                        <div class="auditor-management-detail-row">
                            <label>Description</label>
                            <div class="auditor-management-detail-value remarks">
                                <?php echo nl2br(htmlspecialchars($reward['rewardDescription']));?>
                            </div>
                        </div>

                        <!-- EcoPoints Cost -->
                        <div class="auditor-management-detail-row">
                            <label>EcoPoints Cost</label>
                            <div class="auditor-management-detail-value">
                                <?php echo htmlspecialchars($reward['ecoPointsCost']);?>
                                EcoPoints
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="auditor-management-detail-row">
                            <label>Quantity</label>
                            <div class="auditor-management-detail-value">
                                <?php echo htmlspecialchars($reward['quantity']);?>
                            </div>
                        </div>

                        <!-- Availability -->
                        <div class="auditor-management-detail-row">
                            <label>Availability</label>
                            <div class="auditor-management-detail-value" >
                                <?php $availabilityClass = $reward['availability'] === 'Available' ? 'availability-available' : 'availability-unavailable';?>

                                <span class="reward-availability <?php echo $availabilityClass; ?>">
                                    <?php echo htmlspecialchars($reward['availability']);?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side Actions -->
                    <div class="auditor-management-detail-side">
                        <!-- Edit -->
                        <a href="auditor_reward_edit.php?rewardID=<?php echo urlencode($reward['rewardID']); ?>&from=view" class="auditor-management-detail-action">
                            <img src="../assets/icons/edit.png" alt="Edit">
                            Edit
                        </a>

                        <!-- Print -->
                        <a href="auditor_reward_view.php?rewardID=<?php echo urlencode($reward['rewardID']); ?>&print=1&from=view" class="auditor-management-detail-action">
                            <img src="../assets/icons/print.png" alt="Print">
                            Print
                        </a>

                        <!-- Delete -->
                        <button type="button" class="auditor-management-detail-action delete" onclick="openDeletePopout()">
                            <img src="../assets/icons/delete.png" alt="Delete">
                            Delete
                        </button>

                        <!-- Back -->
                        <div class="auditor-management-detail-back">
                            <a href="auditor_rewards_management.php">
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Print -->
    <?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
        <script>
            window.onload = function() {
                window.print();
            };

            window.onafterprint = function() {
                <?php if (isset($_GET['from']) && $_GET['from'] === 'management'): ?>
                    window.location.href = 'auditor_rewards_management.php';
                <?php else: ?>
                    window.location.href = 'auditor_reward_view.php?rewardID=<?php echo urlencode($reward['rewardID']); ?>';
                <?php endif; ?>
            };
        </script>
    <?php endif; ?>

    <!-- Delete Popout -->
    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Reward Confirmation</h2>
            <p>Are you sure you want to delete this reward?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <input type="hidden" name="rewardID" value="<?php echo htmlspecialchars($reward['rewardID']); ?>">
                    <button type="submit" name="delete_reward" class="auditor-popout-confirm">
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

        function openRewardImage() {
            document.getElementById('rewardImagePopout').style.display = 'flex';
        }

        function closeRewardImage() {
            document.getElementById('rewardImagePopout').style.display = 'none';
        }

    </script>

    <!-- Reward Image Pop Out -->
    <div id="rewardImagePopout" class="image-popout" onclick="closeRewardImage()">
        <span class="image-popout-close" onclick="event.stopPropagation(); closeRewardImage();">
            &times;
        </span>
        <img src="../assets/images/<?php echo htmlspecialchars($reward['rewardImage']); ?>" alt="<?php echo htmlspecialchars($reward['rewardName']); ?>" onclick="event.stopPropagation();">
    </div>
</body>
</html>