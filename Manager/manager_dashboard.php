<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function activityLabel(string $action, string $ref): string {
    $labels = [
        'Create'=>'Created', 'Update'=>'Updated', 'Delete'=>'Removed',
        'Approve'=>'Approved', 'Reject'=>'Rejected', 'Submit'=>'Submitted'
    ];
    return $ref . ' ' . ($labels[$action] ?? $action);
}

function activityIcon(string $action): string {
    $icons = ['Create'=>'create.png', 'Update'=>'edit.png', 'Delete'=>'delete.png'];
    return $icons[$action] ?? 'notification.png';
}

function managerDate(?string $date): string {
    return $date && strtotime($date)
        ? date('d M Y, h:i A', strtotime($date))
        : '-';
}

$managerID = $_SESSION['user_id'];
$managerName = $_SESSION['user_name'] ?? 'Resource Manager';

$totalAssets = $pendingListings = $activeTransactions = $currentBorrowed = 0;
$recentActivities = $notifications = [];
$databaseError = '';

$hasTransactions = file_exists(__DIR__ . '/manager_transactions.php');
$hasNotifications = file_exists(__DIR__ . '/manager_notifications.php');
$hasReports = file_exists(__DIR__ . '/manager_reports.php');
$hasCategories = file_exists(__DIR__ . '/manager_categories.php');

$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    // 1. Dashboard Counts
    $row = $conn->query("
        SELECT
            SUM(listingStatus <> 'Removed') totalAssets,
            SUM(listingStatus = 'Pending Review') pendingListings,
            SUM(
                availabilityStatus = 'Borrowed'
                AND listingStatus <> 'Removed'
            ) currentBorrowed
        FROM assets
    ")->fetch_assoc();

    $totalAssets = (int)($row['totalAssets'] ?? 0);
    $pendingListings = (int)($row['pendingListings'] ?? 0);
    $currentBorrowed = (int)($row['currentBorrowed'] ?? 0);

    // 2. Active Transactions
    $row = $conn->query("
        SELECT COUNT(*) total
        FROM transactionrequests
        WHERE requestStatus IN ('Approved', 'In Progress', 'Overdue')
    ")->fetch_assoc();

    $activeTransactions = (int)($row['total'] ?? 0);

    // 3. Recent Activities
    $result = $conn->query("
        SELECT sl.actionType, sl.refType, sl.refID,
               sl.description, sl.timeLogged
        FROM systemlog sl
        WHERE sl.refType IN ('Asset', 'Category', 'Transaction', 'Borrowing')
        ORDER BY sl.timeLogged DESC
        LIMIT 5
    ");

    while ($row = $result->fetch_assoc()) $recentActivities[] = $row;

    // 4. Manager Notifications
    $stmt = $conn->prepare("
        SELECT notificationID, title, content, status, createdDate
        FROM notification
        WHERE userID = ?
        ORDER BY createdDate DESC
        LIMIT 5
    ");
    $stmt->bind_param('s', $managerID);
    $stmt->execute();

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $notifications[] = $row;

    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $databaseError = 'Unable to load dashboard data. Please make sure MySQL is running.';
}

if ($conn instanceof mysqli) $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Manager Dashboard - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">

<?php include '../header.php'; ?>

<main class="page-body dashboard-page">

    <!-- 1. Welcome -->
    <section class="dashboard-welcome">
        <div>
            <h1>Welcome back, <?= e($managerName) ?>!</h1>
            <p>Review community asset listings, manage transactions and oversee resource-sharing activities.</p>
        </div>
    </section>

    <?php if ($databaseError): ?>
        <div class="dashboard-error-message"><?= e($databaseError) ?></div>
    <?php endif; ?>

    <!-- 2. Statistics -->
    <section class="stats-grid" aria-label="Resource Manager statistics">

        <a href="manager_assets.php" class="stat-card">
            <div class="stat-icon">
                <img src="../assets/icons/totalassets.png" alt="">
            </div>

            <div class="stat-content">
                <h3>Total Assets</h3>
                <strong class="stat-value"><?= $totalAssets ?></strong>
                <p class="stat-description">Community asset listings</p>
            </div>
        </a>

        <a href="manager_assets.php?listingStatus=Pending+Review" class="stat-card">
            <div class="stat-icon">
                <img src="../assets/icons/pendingVerification.png" alt="">
            </div>

            <div class="stat-content">
                <h3>Pending Listings</h3>
                <strong class="stat-value"><?= $pendingListings ?></strong>
                <p class="stat-description">Awaiting manager review</p>
            </div>
        </a>

        <a href="<?= $hasTransactions ? 'manager_transactions.php' : '#' ?>"
           class="stat-card"
           <?php if (!$hasTransactions): ?>
               onclick="alert('Transaction Management will be built next.'); return false;"
           <?php endif; ?>
        >
            <div class="stat-icon">
                <img src="../assets/icons/active-transactions.png" alt="">
            </div>

            <div class="stat-content">
                <h3>Active Transactions</h3>
                <strong class="stat-value"><?= $activeTransactions ?></strong>
                <p class="stat-description">Ongoing lending and borrowing</p>
            </div>
        </a>

        <a href="manager_assets.php?availabilityStatus=Borrowed" class="stat-card">
            <div class="stat-icon">
                <img src="../assets/icons/borrowed-assets.png" alt="">
            </div>

            <div class="stat-content">
                <h3>Current Borrowings</h3>
                <strong class="stat-value"><?= $currentBorrowed ?></strong>
                <p class="stat-description">Currently borrowed assets</p>
            </div>
        </a>

    </section>

    <!-- 3. Activities and Notifications -->
    <section class="dashboard-lower-grid">

        <!-- Recent Activities -->
        <article class="content-card dashboard-panel">

        <div class="card-header">
            <h2>Recent Activities</h2>
            <a href="manager_recent_activities.php">View All</a>
        </div>

            <div class="table-wrapper">

                <table class="data-table manager-activity-table">

                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$recentActivities): ?>

                        <tr>
                            <td colspan="3" class="dashboard-empty-state">
                                No recent activities available.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentActivities as $activity): ?>

                            <tr>
                                <td>
                                    <div class="activity-name activity-flex">

                                        <span class="activity-icon">
                                            <img
                                                src="../assets/icons/<?= e(activityIcon($activity['actionType'])) ?>"
                                                alt=""
                                            >
                                        </span>

                                        <strong>
                                            <?= e(activityLabel(
                                                $activity['actionType'],
                                                $activity['refType']
                                            )) ?>
                                        </strong>

                                    </div>
                                </td>

                                <td><?= e($activity['description']) ?></td>
                                <td><?= e(managerDate($activity['timeLogged'])) ?></td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </article>

        <!-- Notifications -->
        <article class="content-card dashboard-panel">

            <div class="card-header">

                <h2>Notifications</h2>

                <?php if ($hasNotifications): ?>
                    <a href="manager_notifications.php">View All</a>
                <?php endif; ?>

            </div>

            <div class="notification-list">

            <?php if (!$notifications): ?>

                <div class="notification-empty-state">
                    <img src="../assets/icons/notification.png" alt="">
                    <p>No notifications available.</p>
                </div>

            <?php else: ?>

                <?php foreach ($notifications as $notification): ?>

                    <?php if ($hasNotifications): ?>
                        <a
                            href="manager_notifications.php"
                            class="notification-item <?= $notification['status'] === 'Unread'
                                ? 'notification-unread'
                                : '' ?>"
                        >
                    <?php else: ?>
                        <div
                            class="notification-item <?= $notification['status'] === 'Unread'
                                ? 'notification-unread'
                                : '' ?>"
                        >
                    <?php endif; ?>

                            <div class="notification-icon">
                                <img src="../assets/icons/notification.png" alt="">
                            </div>

                            <div class="notification-content">

                                <div class="notification-title-row">

                                    <strong><?= e($notification['title']) ?></strong>

                                    <?php if ($notification['status'] === 'Unread'): ?>
                                        <span
                                            class="unread-indicator"
                                            title="Unread notification"
                                        ></span>
                                    <?php endif; ?>

                                </div>

                                <p><?= e($notification['content']) ?></p>
                                <time><?= e(managerDate($notification['createdDate'])) ?></time>

                            </div>

                    <?php if ($hasNotifications): ?>
                        </a>
                    <?php else: ?>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>

            <?php endif; ?>

            </div>

        </article>

    </section>

    <!-- 4. Quick Actions -->
    <section class="content-card quick-actions">

        <div class="card-header">
            <h2>Quick Actions</h2>
        </div>

        <div class="quick-action-grid">

            <a href="manager_assets.php?listingStatus=Pending+Review" class="quick-action">
                <img src="../assets/icons/view.png" alt="">
                <span>Review Asset Submissions</span>
            </a>

            <a href="<?= $hasTransactions ? 'manager_transactions.php?requestStatus=Pending' : '#' ?>"
               class="quick-action"
               <?php if (!$hasTransactions): ?>
                   onclick="alert('Transaction Management will be built next.'); return false;"
               <?php endif; ?>
            >
                <img src="../assets/icons/view.png" alt="">
                <span>View Transaction Requests</span>
            </a>

            <a href="<?= $hasReports ? 'manager_reports.php' : '#' ?>"
               class="quick-action"
               <?php if (!$hasReports): ?>
                   onclick="alert('Reports will be built later.'); return false;"
               <?php endif; ?>
            >
                <img src="../assets/icons/report.png" alt="">
                <span>Generate Report</span>
            </a>

            <a href="<?= $hasCategories ? 'manager_categories.php' : '#' ?>"
               class="quick-action"
               <?php if (!$hasCategories): ?>
                   onclick="alert('Category Management will be built later.'); return false;"
               <?php endif; ?>
            >
                <img src="../assets/icons/edit.png" alt="">
                <span>Manage Categories</span>
            </a>

        </div>

    </section>

</main>

</div>
</div>

</body>
</html>