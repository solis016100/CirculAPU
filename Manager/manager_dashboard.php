<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control Guard for Resource Manager Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

// Get logged-in Resource Manager information
$managerID = $_SESSION['user_id'];
$managerName = $_SESSION['user_name'] ?? 'Resource Manager';

// Default dashboard values
$totalAssets = 0;
$pendingListings = 0;
$activeTransactions = 0;
$currentBorrowed = 0;

$recentActivities = [];
$notifications = [];

$databaseError = '';

// Function used to retrieve a single dashboard count
function getManagerDashboardCount(mysqli $conn, string $query): int
{
    $result = $conn->query($query);

    if ($result && $row = $result->fetch_assoc()) {
        return (int) $row['total'];
    }

    return 0;
}

// Convert database action into a readable dashboard label
function getManagerActivityLabel(
    string $actionType,
    string $referenceType
): string {
    $actionLabels = [
        'Create' => 'Created',
        'Update' => 'Updated',
        'Delete' => 'Removed',
        'Approve' => 'Approved',
        'Reject' => 'Rejected',
        'Submit' => 'Submitted'
    ];

    $action = $actionLabels[$actionType] ?? $actionType;

    return $referenceType . ' ' . $action;
}


// Select a local icon based on the activity
function getManagerActivityIcon(string $actionType): string
{
    switch ($actionType) {
        case 'Create':
            return 'create.png';

        case 'Update':
            return 'edit.png';

        case 'Delete':
            return 'delete.png';

        default:
            return 'notification.png';
    }
}

// Display a readable date and time
function formatManagerDashboardDate(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y, h:i A', $timestamp);
}

// Database connection
$conn = null;

try {
    $conn = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "circulapu_db",
        3306
    );

    $conn->set_charset("utf8mb4");

   // Count all asset listings except removed records
    $totalAssets = getManagerDashboardCount(
        $conn,
        "SELECT COUNT(*) AS total
        FROM assets
        WHERE listingStatus <> 'Removed'"
    );

    // Count listings waiting for Sustainability Auditor verification
    $pendingListings = getManagerDashboardCount(
        $conn,
        "SELECT COUNT(*) AS total
        FROM assets
        WHERE verificationStatus = 'Pending'"
    );

    // Count transactions that are currently active
    $activeTransactions = getManagerDashboardCount(
        $conn,
        "SELECT COUNT(*) AS total
        FROM transactionrequests
        WHERE requestStatus IN ('Approved', 'In Progress', 'Overdue')"
    );

    // Count assets currently borrowed
    $currentBorrowed = getManagerDashboardCount(
        $conn,
        "SELECT COUNT(*) AS total
        FROM assets
        WHERE availabilityStatus = 'Borrowed'"
    );
    
    // Retrieve recent Asset and Category activities
    $activityQuery = "
        SELECT
            sl.logID,
            sl.actionType,
            sl.refType,
            sl.refID,
            sl.description,
            sl.timeLogged,
            u.name AS performedBy
        FROM systemlog sl
        INNER JOIN user u
            ON sl.userID = u.userID
        WHERE sl.refType IN ('Asset', 'Category')
        ORDER BY sl.timeLogged DESC
        LIMIT 5
    ";

    $activityResult = $conn->query($activityQuery);

    while ($activity = $activityResult->fetch_assoc()) {
        $recentActivities[] = $activity;
    }


    // Retrieve notifications belonging to the logged-in Manager
    $notificationStatement = $conn->prepare(
        "SELECT
            notificationID,
            title,
            content,
            status,
            createdDate
        FROM notification
        WHERE userID = ?
        ORDER BY createdDate DESC
        LIMIT 5"
    );

    $notificationStatement->bind_param("s", $managerID);
    $notificationStatement->execute();

    $notificationResult = $notificationStatement->get_result();

    while ($notification = $notificationResult->fetch_assoc()) {
        $notifications[] = $notification;
    }

    $notificationStatement->close();

} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to load the dashboard data. Please make sure MySQL is running and the database is available.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Manager Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager.css">
</head>
<body class="role-manager">

    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">

            <?php include '../header.php'; ?>

            <main class="page-body manager-dashboard-page">

                <section class="manager-welcome-section">
                    <div>
                        <h1>
                            Welcome back,
                            <?php echo htmlspecialchars($managerName); ?>!
                        </h1>

                        <p>
                            Here is an overview of resource-sharing
                            activities in the CirculAPU community.
                        </p>
                    </div>
                </section>

                <?php if (!empty($databaseError)): ?>
                    <div class="manager-error-message">
                        <?php echo htmlspecialchars($databaseError); ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Overview Statistics -->
                <section
                    class="manager-stat-grid"
                    aria-label="Resource Manager statistics"
                >

                    <!-- Total Assets -->
                    <a href="manager_assets.php" class="manager-stat-card">
                        <div class="manager-stat-icon">
                            <img
                                src="../assets/icons/totalassets.png"
                                alt=""
                            >
                        </div>

                        <div class="manager-stat-content">
                            <span class="manager-stat-label">
                                Total Assets
                            </span>

                            <strong>
                                <?php echo $totalAssets; ?>
                            </strong>

                            <small>All listed assets</small>
                        </div>
                    </a>

                    <!-- Pending Listings -->
                    <a
                        href="manager_assets.php?verificationStatus=Pending"
                        class="manager-stat-card"
                    >
                        <div class="manager-stat-icon">
                            <img
                                src="../assets/icons/pendingVerification.png"
                                alt=""
                            >
                        </div>

                        <div class="manager-stat-content">
                            <span class="manager-stat-label">
                                Pending Listings
                            </span>

                            <strong>
                                <?php echo $pendingListings; ?>
                            </strong>

                            <small>Awaiting verification</small>
                        </div>
                    </a>

                    <!-- Active Transactions -->
                    <a
                        href="manager_transactions.php"
                        class="manager-stat-card"
                    >
                        <div class="manager-stat-icon">
                            <img
                                src="../assets/icons/active-transactions.png"
                                alt=""
                            >
                        </div>

                        <div class="manager-stat-content">
                            <span class="manager-stat-label">
                                Active Transactions
                            </span>

                            <strong>
                                <?php echo $activeTransactions; ?>
                            </strong>

                            <small>Ongoing lending and borrowing</small>
                        </div>
                    </a>

                    <!-- Borrowings -->
                    <a
                        href="manager_assets.php?availabilityStatus=Borrowed"
                        class="manager-stat-card"
                    >
                        <div class="manager-stat-icon">
                            <img
                                src="../assets/icons/borrowed-assets.png"
                                alt=""
                            >
                        </div>

                        <div class="manager-stat-content">
                            <span class="manager-stat-label">
                                Borrowings
                            </span>

                            <strong>
                                <?php echo $currentBorrowed; ?>
                            </strong>

                            <small>Currently borrowed</small>
                        </div>
                    </a>
                </section>

                <!-- Dashboard Activities and Notifications -->
                <section class="manager-dashboard-lower-grid">

                    <!-- Recent Activities -->
                    <article class="content-card manager-dashboard-panel">

                        <div class="card-header">
                            <h2>Recent Activities</h2>

                            <a href="manager_recent_activities.php">
                                View All
                            </a>
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

                                    <?php if (empty($recentActivities)): ?>

                                        <tr>
                                            <td
                                                colspan="3"
                                                class="manager-empty-state"
                                            >
                                                No recent activities available.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($recentActivities as $activity): ?>

                                            <tr>
                                                <td>
                                                    <div class="manager-activity-name">

                                                        <span class="manager-activity-icon">
                                                            <img
                                                                src="../assets/icons/<?php
                                                                    echo htmlspecialchars(
                                                                        getManagerActivityIcon(
                                                                            $activity['actionType']
                                                                        )
                                                                    );
                                                                ?>"
                                                                alt=""
                                                            >
                                                        </span>

                                                        <strong>
                                                            <?php
                                                                echo htmlspecialchars(
                                                                    getManagerActivityLabel(
                                                                        $activity['actionType'],
                                                                        $activity['refType']
                                                                    )
                                                                );
                                                            ?>
                                                        </strong>

                                                    </div>
                                                </td>

                                                <td>
                                                    <?php
                                                        echo htmlspecialchars(
                                                            $activity['description']
                                                        );
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                        echo htmlspecialchars(
                                                            formatManagerDashboardDate(
                                                                $activity['timeLogged']
                                                            )
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


                    <!-- Notifications -->
                    <article class="content-card manager-dashboard-panel">

                        <div class="card-header">
                            <h2>Notifications</h2>

                            <a href="manager_notifications.php">
                                View All
                            </a>
                        </div>

                        <div class="notification-list">

                            <?php if (empty($notifications)): ?>

                                <div class="manager-notification-empty">
                                    <img
                                        src="../assets/icons/notification.png"
                                        alt=""
                                    >

                                    <p>No notifications available.</p>
                                </div>

                            <?php else: ?>

                                <?php foreach ($notifications as $notification): ?>

                                    <a
                                        href="manager_notifications.php"
                                        class="notification-item <?php
                                            echo $notification['status'] === 'Unread'
                                                ? 'notification-unread'
                                                : '';
                                        ?>"
                                    >
                                        <div class="notification-icon">
                                            <img
                                                src="../assets/icons/notification.png"
                                                alt=""
                                            >
                                        </div>

                                        <div class="notification-content">

                                            <div class="notification-title-row">
                                                <strong>
                                                    <?php
                                                        echo htmlspecialchars(
                                                            $notification['title']
                                                        );
                                                    ?>
                                                </strong>

                                                <?php
                                                    if (
                                                        $notification['status'] === 'Unread'
                                                    ):
                                                ?>
                                                    <span
                                                        class="unread-indicator"
                                                        title="Unread notification"
                                                    ></span>
                                                <?php endif; ?>
                                            </div>

                                            <p>
                                                <?php
                                                    echo htmlspecialchars(
                                                        $notification['content']
                                                    );
                                                ?>
                                            </p>

                                            <time>
                                                <?php
                                                    echo htmlspecialchars(
                                                        formatManagerDashboardDate(
                                                            $notification['createdDate']
                                                        )
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

                <!-- Quick Actions -->
                <section class="content-card manager-quick-actions">

                    <div class="card-header">
                        <h2>Quick Actions</h2>
                    </div>

                    <div class="manager-quick-action-grid">

                        <a
                            href="manager_asset_create.php"
                            class="manager-quick-action"
                        >
                            <img
                                src="../assets/icons/create.png"
                                alt=""
                            >

                            <span>Add New Asset</span>
                        </a>

                        <a
                            href="manager_transactions.php"
                            class="manager-quick-action"
                        >
                            <img
                                src="../assets/icons/view.png"
                                alt=""
                            >

                            <span>View Transaction Requests</span>
                        </a>

                        <a
                            href="manager_reports.php"
                            class="manager-quick-action"
                        >
                            <img
                                src="../assets/icons/report.png"
                                alt=""
                            >

                            <span>Generate Report</span>
                        </a>

                        <a
                            href="manager_categories.php"
                            class="manager-quick-action"
                        >
                            <img
                                src="../assets/icons/edit.png"
                                alt=""
                            >

                            <span>Manage Categories</span>
                        </a>

                    </div>
                </section>

            </main>

        </div>
    </div>

</body>
</html>