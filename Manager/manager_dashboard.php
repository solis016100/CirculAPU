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
    <link rel="stylesheet" href="../darktheme.css">
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
<section class="manager-stat-grid" aria-label="Resource Manager statistics">

    <!-- Total Assets -->
    <a href="manager_assets.php" class="manager-stat-card">
        <div class="manager-stat-icon" aria-hidden="true">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
        </div>

        <div class="manager-stat-content">
            <span class="manager-stat-label">Total Assets</span>

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
        <div class="manager-stat-icon" aria-hidden="true">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>

        <div class="manager-stat-content">
            <span class="manager-stat-label">Pending Listings</span>

            <strong>
                <?php echo $pendingListings; ?>
            </strong>

            <small>Awaiting verification</small>
        </div>
    </a>

    <!-- Active Transactions -->
    <a href="manager_transactions.php" class="manager-stat-card">
        <div class="manager-stat-icon" aria-hidden="true">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="17 1 21 5 17 9"></polyline>
                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                <polyline points="7 23 3 19 7 15"></polyline>
                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
            </svg>
        </div>

        <div class="manager-stat-content">
            <span class="manager-stat-label">Active Transactions</span>

            <strong>
                <?php echo $activeTransactions; ?>
            </strong>

            <small>Ongoing lending and borrowing</small>
        </div>
    </a>

    <!-- Currently Borrowed -->
    <a
        href="manager_assets.php?availabilityStatus=Borrowed"
        class="manager-stat-card"
    >
        <div class="manager-stat-icon" aria-hidden="true">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
        </div>

        <div class="manager-stat-content">
            <span class="manager-stat-label">Currently Borrowed</span>

            <strong>
                <?php echo $currentBorrowed; ?>
            </strong>

            <small>Assets held by borrowers</small>
        </div>
    </a>

</section>

            </main>

        </div>
    </div>

</body>
</html>