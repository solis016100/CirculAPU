<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
$is_preview = isset($_GET['preview']);
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Manager' && !($is_admin && $is_preview))) {
    header("Location: ../login.php");
    exit();
}

$managerID = $_SESSION['user_id'];
$managerName = $_SESSION['user_name'] ?? 'Resource Manager';
$totalAssets = $pendingListings = $activeTransactions = $currentBorrowed = 0;
$recentActivities = $notifications = [];
$databaseError = '';

function managerActivityLabel(string $action, string $ref): string {
    $labels = ['Create'=>'Created', 'Update'=>'Updated', 'Delete'=>'Removed',
               'Approve'=>'Approved', 'Reject'=>'Rejected', 'Submit'=>'Submitted'];
    return $ref . ' ' . ($labels[$action] ?? $action);
}

function managerActivityIcon(string $action): string {
    $icons = ['Create'=>'create.png', 'Update'=>'edit.png', 'Delete'=>'delete.png'];
    return $icons[$action] ?? 'notification.png';
}

function managerDate(?string $date): string {
    return $date && strtotime($date) ? date('d M Y, h:i A', strtotime($date)) : '-';
}

$conn = null;

try {
    $conn = new mysqli("127.0.0.1", "root", "", "circulapu_db", 3306);
    $conn->set_charset("utf8mb4");

    // Dashboard counts
    $result = $conn->query("
        SELECT
            SUM(listingStatus <> 'Removed') AS totalAssets,
            SUM(listingStatus = 'Pending Review') AS pendingListings,
            SUM(availabilityStatus = 'Borrowed' AND listingStatus <> 'Removed') AS currentBorrowed
        FROM assets
    ");
    $row = $result->fetch_assoc();

    $totalAssets = (int)($row['totalAssets'] ?? 0);
    $pendingListings = (int)($row['pendingListings'] ?? 0);
    $currentBorrowed = (int)($row['currentBorrowed'] ?? 0);

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM transactionrequests
        WHERE requestStatus IN ('Approved', 'In Progress', 'Overdue')
    ");
    $activeTransactions = (int)($result->fetch_assoc()['total'] ?? 0);

    // Recent activities
    $result = $conn->query("
        SELECT sl.actionType, sl.refType, sl.refID, sl.description, sl.timeLogged, u.name AS performedBy
        FROM systemlog sl
        INNER JOIN user u ON sl.userID = u.userID
        WHERE sl.refType IN ('Asset', 'Category')
        ORDER BY sl.timeLogged DESC
        LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) $recentActivities[] = $row;

    // Manager notifications
    $stmt = $conn->prepare("
        SELECT notificationID, title, content, status, createdDate
        FROM notification
        WHERE userID = ?
        ORDER BY createdDate DESC
        LIMIT 5
    ");
    $stmt->bind_param("s", $managerID);
    $stmt->execute();

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $notifications[] = $row;
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $databaseError = "Unable to load dashboard data. Please make sure MySQL is running and the database is available.";
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
                    <h1>Welcome back, <?php echo htmlspecialchars($managerName); ?>!</h1>
                    <p>Review community asset listings, manage transactions and oversee resource-sharing activities.</p>
                </div>
            </section>

            <?php if ($databaseError): ?>
                <div class="dashboard-error-message"><?php echo htmlspecialchars($databaseError); ?></div>
            <?php endif; ?>

            <!-- 2. Statistics -->
            <section class="stats-grid" aria-label="Resource Manager statistics">

                <a href="manager_assets.php" class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/totalassets.png" alt=""></div>
                    <div class="stat-content">
                        <h3>Total Assets</h3>
                        <strong class="stat-value"><?php echo $totalAssets; ?></strong>
                        <p class="stat-description">Community asset listings</p>
                    </div>
                </a>

                <a href="manager_assets.php?listingStatus=Pending+Review" class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/pendingVerification.png" alt=""></div>
                    <div class="stat-content">
                        <h3>Pending Listings</h3>
                        <strong class="stat-value"><?php echo $pendingListings; ?></strong>
                        <p class="stat-description">Awaiting manager review</p>
                    </div>
                </a>

                <a href="manager_transactions.php" class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/active-transactions.png" alt=""></div>
                    <div class="stat-content">
                        <h3>Active Transactions</h3>
                        <strong class="stat-value"><?php echo $activeTransactions; ?></strong>
                        <p class="stat-description">Ongoing lending and borrowing</p>
                    </div>
                </a>

                <a href="manager_assets.php?availabilityStatus=Borrowed" class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/borrowed-assets.png" alt=""></div>
                    <div class="stat-content">
                        <h3>Current Borrowings</h3>
                        <strong class="stat-value"><?php echo $currentBorrowed; ?></strong>
                        <p class="stat-description">Currently borrowed assets</p>
                    </div>
                </a>

            </section>

            <!-- 3. Activities and Notifications -->
            <section class="dashboard-lower-grid">

                <article class="content-card dashboard-panel">
                    <div class="card-header">
                        <h2>Recent Activities</h2>
                        <a href="manager_recent_activities.php">View All</a>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table manager-activity-table">
                            <thead>
                                <tr><th>Activity</th><th>Details</th><th>Time</th></tr>
                            </thead>
                            <tbody>

                            <?php if (!$recentActivities): ?>
                                <tr><td colspan="3" class="dashboard-empty-state">No recent activities available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="activity-name activity-flex">
                                                <span class="activity-icon">
                                                    <img src="../assets/icons/<?php echo htmlspecialchars(managerActivityIcon($activity['actionType'])); ?>" alt="">
                                                </span>
                                                <strong>
                                                    <?php echo htmlspecialchars(managerActivityLabel(
                                                        $activity['actionType'],
                                                        $activity['refType']
                                                    )); ?>
                                                </strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo htmlspecialchars(managerDate($activity['timeLogged'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="content-card dashboard-panel">
                    <div class="card-header">
                        <h2>Notifications</h2>
                        <a href="manager_notifications.php">View All</a>
                    </div>

                    <div class="notification-list">

                    <?php if (!$notifications): ?>
                        <div class="notification-empty-state">
                            <img src="../assets/icons/notification.png" alt="">
                            <p>No notifications available.</p>
                        </div>
                    <?php else: ?>

                        <?php foreach ($notifications as $notification): ?>
                            <a href="manager_notifications.php"
                               class="notification-item <?php echo $notification['status'] === 'Unread' ? 'notification-unread' : ''; ?>">

                                <div class="notification-icon">
                                    <img src="../assets/icons/notification.png" alt="">
                                </div>

                                <div class="notification-content">
                                    <div class="notification-title-row">
                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>

                                        <?php if ($notification['status'] === 'Unread'): ?>
                                            <span class="unread-indicator" title="Unread notification"></span>
                                        <?php endif; ?>
                                    </div>

                                    <p><?php echo htmlspecialchars($notification['content']); ?></p>
                                    <time><?php echo htmlspecialchars(managerDate($notification['createdDate'])); ?></time>
                                </div>
                            </a>
                        <?php endforeach; ?>

                    <?php endif; ?>

                    </div>
                </article>

            </section>

            <!-- 4. Quick Actions -->
            <section class="content-card quick-actions">
                <div class="card-header"><h2>Quick Actions</h2></div>

                <div class="quick-action-grid">

                    <a href="manager_assets.php?listingStatus=Pending+Review" class="quick-action">
                        <img src="../assets/icons/view.png" alt="">
                        <span>Review Asset Submissions</span>
                    </a>

                    <a href="manager_transactions.php" class="quick-action">
                        <img src="../assets/icons/view.png" alt="">
                        <span>View Transaction Requests</span>
                    </a>

                    <a href="manager_reports.php" class="quick-action">
                        <img src="../assets/icons/report.png" alt="">
                        <span>Generate Report</span>
                    </a>

                    <a href="manager_categories.php" class="quick-action">
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