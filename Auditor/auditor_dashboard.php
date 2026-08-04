<?php
    session_start();

    // Access Control Guard for Sustainability Auditor Role
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit();
    }

    // Get Auditor's information from login
    $auditorID= $_SESSION['user_id'];
    $auditorName = $_SESSION['user_name'] ?? 'Auditor';

    // Default Dashboard Values
    $pendingAssets = 0;
    $pendingMissions = 0;
    $rewardRedemptions = 0;
    $userTierUpgrades = 0;
    $unreadNotifications = 0;

    $recentActivities = [];
    $notifications = [];

    $databaseError = '';

    // Count Function
    function getCount(mysqli $conn, string $sql): int{
        $result = $conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())){
            return (int) $row['total'];
        }
        return 0;
    }

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");


        // Count pending asset verification
        $pendingAssets = getCount($conn, "SELECT COUNT(*) AS total FROM assets WHERE verificationStatus = 'Pending'");

        // Count pending mission submission
        $pendingMissions = getCount($conn, "SELECT COUNT(*) AS total FROM missionsubmission WHERE submissionStatus = 'Pending'");

        // Count reward redemption
        $rewardRedemptions = getCount($conn, "SELECT COUNT(*) AS total FROM rewardredemption");

        // Count user tier upgrades
        $userTierUpgrades = getCount($conn, "SELECT COUNT(*) AS total FROM usertier");

        // Count unread notification
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

        // Recent Activities from SystemLog
        $activitySQL = "SELECT sl.logID, sl.description, sl.actionType, sl.refType, sl.refID, sl.timeLogged, u.name AS performedBy
        FROM systemlog sl
        INNER JOIN user u ON sl.userID = u.userID
        WHERE sl.refType IN('Mission', 'Asset', 'Reward', 'Badge', 'Tier', 'Verification')
        ORDER BY sl.timeLogged DESC
        LIMIT 5";

        $activityResult = $conn->query($activitySQL);
        if ($activityResult){
            while($activityRow = $activityResult->fetch_assoc()){
                $recentActivities[] = $activityRow;
            }
        }

        // Notifications
        $notificationStatement = $conn->prepare("SELECT notificationID, title, content, status, createdDate
        FROM notification WHERE userID = ?
        ORDER BY createdDate DESC
        LIMIT 4");
        if ($notificationStatement){
            $notificationStatement->bind_param("s", $auditorID);
            $notificationStatement->execute();
            $notificationResult = $notificationStatement->get_result();
            while($notificationRow = $notificationResult->fetch_assoc()){
                $notifications[] = $notificationRow;
            }
            $notificationStatement->close();
        }

        // Disconnect Database
        $conn->close();
    }

    // Action Type function 
    function getActionClass(string $actionType): string{
        switch ($actionType){
            case 'Approve':
                return 'action-approved';
            case 'Reject':
                return 'action-rejected';
            case 'Submit':
                return 'action-submitted';
            case 'Create':
                return 'action-created';
            case 'Update':
                return 'action-updated';
            case 'Delete':
                return 'action-deleted';
            case 'Redeem':
                return 'action-redeemed';
            case 'Assign':
                return 'action-assigned';
            default:
                return 'action-default';
        }
    }

    // Date Display function
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
    <link rel="stylesheet" href="../darktheme.css">
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

                    <!-- Pending Mission Submissions -->
                    <a href="auditor_mission_management.php?type=Mission&status=Pending" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/pendingMission.png" alt="Pending Mission">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $pendingMissions; ?>
                            </strong>
                            <h3>Pending Mission Submissions</h3>
                        </div>
                    </a>

                    <!-- Reward Redemptions -->
                    <a href="auditor_rewards.php" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/reward.png" alt="Reward">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $rewardRedemptions; ?>
                            </strong>
                            <h3>Reward Redemptions</h3>
                        </div>
                    </a>

                    <!-- User Tier Upgrades -->
                    <a href="auditor_usertiers.php" class="stat-card">
                        <div class="stat-icon">
                            <img src="../assets/icons/tier.png" alt="User Tier">
                        </div>

                        <div class="stat-content">
                            <strong class="stat-value">
                                <?php echo $userTierUpgrades; ?>
                            </strong>
                            <h3>User Tier Upgrades</h3>
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
                                <p>
                                    <?php
                                        echo $unreadNotifications . ' unread notification' .
                                            ($unreadNotifications === 1 ? '' : 's');
                                    ?>
                                </p>
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

