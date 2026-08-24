<?php
    session_start();
    
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
    $is_preview = ($_GET['preview'] ?? '') === 'Auditor';
    
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Auditor' && !($is_admin && $is_preview))) {
        header("Location: ../login.php");
        exit();
    }

    $auditorID= $_SESSION['user_id'];
    $auditorName = $_SESSION['user_name'] ?? 'Auditor';

    $pendingAssets = 0;
    $pendingMissions = 0;
    $lowStockRewards = 0;
    $outOfStockRewards = 0;
    $unreadNotifications = 0;
    $recentActivities = [];
    $notifications = [];
    $databaseError = '';

    function getCount(mysqli $conn, string $sql): int{
        $result = $conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())){
            return (int) $row['total'];
        }
        return 0;
    }

    require_once '../db.php';
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        $conn->set_charset("utf8mb4");
        if ($is_admin && $is_preview) {
            $previewUser = $conn->query("SELECT userID, name FROM user WHERE role = 'Auditor' AND status = 'Active' ORDER BY dateCreated ASC, userID ASC LIMIT 1")->fetch_assoc();
            if ($previewUser) {
                $auditorID = $previewUser['userID'];
                $auditorName = $previewUser['name'];
            }else {
                $auditorName = 'Sustainability Auditor';
            }
        }

        $pendingAssets = getCount($conn, "SELECT COUNT(*) AS total FROM verificationrecords WHERE status = 'Pending' AND targetType = 'Asset'");

        $pendingMissions = getCount($conn, "SELECT COUNT(*) AS total FROM verificationrecords WHERE status = 'Pending' AND targetType = 'MissionSubmission'");

        $lowStockRewards = getCount($conn, "SELECT COUNT(*) AS total FROM rewards WHERE quantity > 0 AND quantity < 3;");

        $outOfStockRewards = getCount($conn, "SELECT COUNT(*) AS total FROM rewards WHERE quantity = 0");

        $unreadStatement = $conn->prepare("SELECT COUNT(*) AS total FROM notification WHERE userID = ? AND status = 'Unread'");
        if ($unreadStatement){
            $unreadStatement->bind_param("s", $auditorID);
            $unreadStatement->execute();
            $unreadResult = $unreadStatement->get_result();
            if ($unreadRow = $unreadResult->fetch_assoc()){
                $unreadNotifications = (int) $unreadRow['total'];
            }
            $unreadStatement->close();
        }

        $activitySQL = "SELECT sl.logID, sl.description, sl.actionType, sl.refType, sl.refID, sl.timeLogged, u.name AS performedBy FROM systemlog sl INNER JOIN user u ON sl.userID = u.userID WHERE sl.userID = ? ORDER BY sl.timeLogged DESC LIMIT 7";
        $activityStatement = $conn->prepare($activitySQL);
        if ($activityStatement) {
            $activityStatement->bind_param("s", $auditorID);
            $activityStatement->execute();
            $activityResult = $activityStatement->get_result();
            while ($activityRow = $activityResult->fetch_assoc()) {
                $recentActivities[] = $activityRow;
            }
            $activityStatement->close();
        }

        $notificationStatement = $conn->prepare("SELECT notificationID, title, content, status, createdDate FROM notification WHERE userID = ? ORDER BY createdDate DESC LIMIT 3");
        if ($notificationStatement){
            $notificationStatement->bind_param("s", $auditorID);
            $notificationStatement->execute();
            $notificationResult = $notificationStatement->get_result();
            while($notificationRow = $notificationResult->fetch_assoc()){
                $notifications[] = $notificationRow;
            }
            $notificationStatement->close();
        }
        $conn->close();
    }

    function formatDashboardDate(?string $date): string{
        if (empty($date)){
            return '-';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false){
            return '-';
        }
        return date('d M Y, h:i A', $timestamp);
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">

    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body auditor-dashboard-page">
                <!-- Welcome Back -->
                <section class="dashboard-welcome">
                    <div>
                        <h1>
                            Welcome back, 
                            <?php
                                echo htmlspecialchars($auditorName);
                            ?>
                        </h1>

                        <p>Here is an overview of  the sustainability activities.</p>
                    </div>
                </section>

                <!-- Database Error Message -->
                <?php if (!empty($databaseError)): ?>
                    <div class="auditor-error-message">
                        <?php
                            echo htmlspecialchars($databaseError);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Overview Cards-->
                <section class="stats-grid" aria-label="Auditor Dashboard Statistics">
                    <!-- Pending Asset Verification -->
                    <a href="auditor_verification_management.php?type=Asset&status=Pending" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/pendingVerification.png" alt="Pending Verification">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $pendingAssets; ?>
                            </strong>
                            <h3>Pending Asset Verification</h3>
                        </div>
                    </a>

                    <!-- Pending Mission Verifications -->
                    <a href="auditor_verification_management.php?type=MissionSubmission&status=Pending" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/pendingMission.png" alt="Pending Mission">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $pendingMissions; ?>
                            </strong>
                            <h3>Pending Mission Verifications</h3>
                        </div>
                    </a>

                    <!-- Low Stock Rewards -->
                    <a href="auditor_rewards_management.php?quantity=low" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/low-stock.png" alt="lowStock">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $lowStockRewards; ?>
                            </strong>
                            <h3>Low Stock Rewards</h3>
                        </div>
                    </a>

                    <!-- Out of Stock Rewards -->
                    <a href="auditor_rewards_management.php?quantity=out_of_stock" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/out-of-stock.png" alt="OutofStock">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $outOfStockRewards; ?>
                            </strong>
                            <h3>Out of Stock Rewards</h3>
                        </div>
                    </a>
                </section>
                
                <section class="dashboard-lower-grid">
                    <!-- Recent Activities -->
                    <article class="content-card">
                        <div class="card-header">
                            <div>
                                <h2>Recent Activities</h2>
                            </div>
                            <a href="auditor_recent_activities.php">View All</a>
                        </div>

                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Related To</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (empty($recentActivities)): ?>
                                        <tr>
                                            <td colspan="3" class="auditor-empty-state">
                                                No system activities have been recorded yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <tr>
                                                <!-- Activity -->
                                                <td class="activity-description">
                                                    <?php
                                                        echo htmlspecialchars($activity['description']);
                                                    ?>
                                                </td>

                                                <!-- Related To -->
                                                <td>
                                                    <div class="auditor-reference">
                                                        <strong>
                                                            <?php
                                                                echo htmlspecialchars($activity['refType']);
                                                            ?>
                                                        </strong>

                                                        <?php if (!empty($activity['refID'])): ?>

                                                            <span>
                                                                <?php
                                                                    echo htmlspecialchars($activity['refID']);
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <!-- Date -->
                                                <td>
                                                    <?php
                                                        echo htmlspecialchars(
                                                            formatDashboardDate($activity['timeLogged'])
                                                        );
                                                    ?>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <!-- Notification Panel -->
                    <article class="content-card">
                        <div class="card-header">
                            <div>
                                <h2>Notifications</h2>
                            </div>

                            <a href="auditor_notification.php">View All</a>
                        </div>

                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                                <div class="auditor-empty-notification">
                                    <p>No notification found</p>
                                </div>
                            <?php else: ?>
                                <?php
                                    foreach($notifications as $notification):
                                ?>
                                    <a href="auditor_notification.php" class="notification-item <?php echo ($notification['status'] === 'Unread') ? 'notification-unread' : ''; ?>">
                                        <div class="notification-content">
                                            <div class="notification-title-row">
                                                <strong>
                                                    <?php
                                                        echo htmlspecialchars($notification['title']);
                                                    ?>
                                                </strong>

                                                <?php if ($notification['status'] === 'Unread'): ?>

                                                    <span
                                                        class="unread-indicator"
                                                        title="Unread">
                                                    </span>

                                                <?php endif; ?>
                                            </div>

                                            <p>
                                                <?php
                                                    echo htmlspecialchars($notification['content']);
                                                ?>
                                            </p>

                                            <time>
                                                <?php
                                                    echo htmlspecialchars(
                                                        formatDashboardDate($notification['createdDate'])
                                                    );
                                                ?>
                                            </time>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>

</body>
</html>
