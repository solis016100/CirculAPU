<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function adminDashboardEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminDashboardDate(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
        return 'Today, ' . date('h:i A', $timestamp);
    }
    if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday, ' . date('h:i A', $timestamp);
    }
    return date('d M Y, h:i A', $timestamp);
}

function adminActivityLabel(string $action, string $reference): string
{
    $labels = [
        'Create' => 'Created',
        'Update' => 'Updated',
        'Delete' => 'Removed',
        'Approve' => 'Approved',
        'Reject' => 'Rejected',
        'Submit' => 'Submitted',
        'Assign' => 'Assigned',
        'Login' => 'Signed in to',
        'Logout' => 'Signed out of',
    ];

    return ($labels[$action] ?? $action) . ' ' . $reference;
}

function adminActivityIcon(string $action): string
{
    return match ($action) {
        'Create' => 'create.png',
        'Update' => 'edit.png',
        'Delete', 'Reject' => 'delete.png',
        'Approve' => 'view.png',
        default => 'notification.png',
    };
}

$adminID = (string) $_SESSION['user_id'];
$adminName = trim($_SESSION['user_name'] ?? '') ?: 'System Administrator';
$totalUsers = $activeUsers = $pendingReports = $unreadNotifications = 0;
$recentActivities = [];
$notifications = [];
$databaseError = '';

try {
    $conn->set_charset('utf8mb4');

    $userCounts = $conn->query(
        "SELECT COUNT(*) AS totalUsers,
                COALESCE(SUM(status='Active'), 0) AS activeUsers
         FROM user WHERE status<>'Deleted'"
    )->fetch_assoc();
    $totalUsers = (int) ($userCounts['totalUsers'] ?? 0);
    $activeUsers = (int) ($userCounts['activeUsers'] ?? 0);

    $pendingReports = (int) ($conn->query(
        "SELECT COUNT(*) AS total FROM report WHERE status='Pending'"
    )->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM notification WHERE userID=? AND status='Unread'"
    );
    $stmt->bind_param('s', $adminID);
    $stmt->execute();
    $unreadNotifications = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $activityResult = $conn->query(
        "SELECT sl.actionType, sl.refType, sl.refID, sl.description, sl.timeLogged,
                COALESCE(u.name, sl.userID) AS performedBy
         FROM systemlog sl
         LEFT JOIN user u ON u.userID=sl.userID
         ORDER BY sl.timeLogged DESC LIMIT 5"
    );
    while ($activity = $activityResult->fetch_assoc()) {
        $recentActivities[] = $activity;
    }

    $stmt = $conn->prepare(
        "SELECT notificationID, title, content, status, createdDate
         FROM notification WHERE userID=?
         ORDER BY createdDate DESC, notificationID DESC LIMIT 5"
    );
    $stmt->bind_param('s', $adminID);
    $stmt->execute();
    $notificationResult = $stmt->get_result();
    while ($notification = $notificationResult->fetch_assoc()) {
        $notifications[] = $notification;
    }
    $stmt->close();
} catch (Throwable $exception) {
    error_log('Unable to load Admin dashboard: ' . $exception->getMessage());
    $databaseError = 'Unable to load dashboard data. Please check the database connection.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css?v=<?= filemtime(__DIR__ . '/admin_style.css') ?>">
</head>
<body class="role-admin">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body dashboard-page admin-dashboard-page">
    <section class="dashboard-welcome">
        <div>
            <h1>Welcome back, <?= adminDashboardEscape($adminName) ?>!</h1>
            <p>Monitor platform activity, manage user access, and review system reports.</p>
        </div>
    </section>

    <?php if ($databaseError !== ''): ?>
        <div class="dashboard-error-message"><?= adminDashboardEscape($databaseError) ?></div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="System Administrator statistics">
        <a href="user_management.php" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/users.svg" alt=""></div>
            <div class="stat-content">
                <h3>Total Users</h3>
                <strong class="stat-value"><?= $totalUsers ?></strong>
                <p class="stat-description">Registered platform accounts</p>
            </div>
        </a>

        <a href="user_management.php?status=Active" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/user_badge.png" alt=""></div>
            <div class="stat-content">
                <h3>Active Users</h3>
                <strong class="stat-value"><?= $activeUsers ?></strong>
                <p class="stat-description">Accounts currently active</p>
            </div>
        </a>

        <a href="reports_queue.php?status=Pending" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/pendingVerification.png" alt=""></div>
            <div class="stat-content">
                <h3>Pending Reports</h3>
                <strong class="stat-value"><?= $pendingReports ?></strong>
                <p class="stat-description">Waiting for administrator review</p>
            </div>
        </a>

        <a href="admin_notifications.php?status=Unread" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/notification.png" alt=""></div>
            <div class="stat-content">
                <h3>Unread Notifications</h3>
                <strong class="stat-value"><?= $unreadNotifications ?></strong>
                <p class="stat-description">Updates requiring attention</p>
            </div>
        </a>
    </section>

    <section class="dashboard-lower-grid">
        <article class="content-card dashboard-panel">
            <div class="card-header">
                <h2>Recent Activities</h2>
                <a href="system_logs.php">View All</a>
            </div>

            <div class="table-wrapper">
                <table class="data-table admin-dashboard-activity-table">
                    <thead><tr><th>Activity</th><th>Details</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php if (!$recentActivities): ?>
                        <tr><td colspan="3" class="dashboard-empty-state">No recent system activities available.</td></tr>
                    <?php else: foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td>
                                <div class="activity-name activity-flex">
                                    <span class="activity-icon"><img src="../assets/icons/<?= adminDashboardEscape(adminActivityIcon($activity['actionType'])) ?>" alt=""></span>
                                    <strong><?= adminDashboardEscape(adminActivityLabel($activity['actionType'], $activity['refType'])) ?></strong>
                                </div>
                            </td>
                            <td>
                                <?= adminDashboardEscape($activity['description']) ?>
                                <small class="admin-activity-meta">By <?= adminDashboardEscape($activity['performedBy']) ?></small>
                            </td>
                            <td class="admin-dashboard-time"><?= adminDashboardEscape(adminDashboardDate($activity['timeLogged'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="content-card dashboard-panel">
            <div class="card-header">
                <h2>Notifications</h2>
                <a href="admin_notifications.php">View All</a>
            </div>

            <div class="notification-list">
            <?php if (!$notifications): ?>
                <div class="notification-empty-state">
                    <img src="../assets/icons/notification.png" alt="">
                    <p>No notifications available.</p>
                </div>
            <?php else: foreach ($notifications as $notification): ?>
                <a href="admin_notifications.php" class="notification-item <?= $notification['status'] === 'Unread' ? 'notification-unread' : '' ?>">
                    <div class="notification-icon"><img src="../assets/icons/notification.png" alt=""></div>
                    <div class="notification-content">
                        <div class="notification-title-row">
                            <strong><?= adminDashboardEscape($notification['title']) ?></strong>
                            <?php if ($notification['status'] === 'Unread'): ?><span class="unread-indicator" title="Unread notification"></span><?php endif; ?>
                        </div>
                        <p><?= adminDashboardEscape($notification['content']) ?></p>
                        <time><?= adminDashboardEscape(adminDashboardDate($notification['createdDate'])) ?></time>
                    </div>
                </a>
            <?php endforeach; endif; ?>
            </div>
        </article>
    </section>

    <section class="content-card quick-actions">
        <div class="card-header"><h2>Quick Actions</h2></div>
        <div class="quick-action-grid">
            <a href="user_management.php" class="quick-action"><img src="../assets/icons/users.svg" alt=""><span>Manage Users</span></a>
            <a href="reports_queue.php?status=Pending" class="quick-action"><img src="../assets/icons/report.png" alt=""><span>Review Reports</span></a>
            <a href="analytics.php" class="quick-action"><img src="../assets/icons/activity.svg" alt=""><span>View Analytics</span></a>
            <a href="system_logs.php" class="quick-action"><img src="../assets/icons/view.png" alt=""><span>View System Logs</span></a>
        </div>
    </section>
</main>
</div>
</div>
</body>
</html>
