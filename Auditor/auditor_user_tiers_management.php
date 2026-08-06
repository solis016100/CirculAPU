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

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");
    }

    // Get filter values
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $tierFilter = isset($_GET['tier']) ? $_GET['tier'] : '';
    $dateFilter = isset($_GET['assignedDate']) ? $_GET['assignedDate'] : '';


    // Get tiers for filter dropdown
    $tierQuery = "SELECT tierID, tierName FROM Tiers ORDER BY tierName ASC";
    $tierResult = mysqli_query($conn, $tierQuery);


    // Build filter condition
    $whereConditions = [];

    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($conn, $search);
        $whereConditions[] = "
            (UserTier.userTierID LIKE '%$safeSearch%'
            OR UserTier.userID LIKE '%$safeSearch%')
        ";
    }

    if ($tierFilter !== '') {
        $safeTier = mysqli_real_escape_string($conn, $tierFilter);
        $whereConditions[] = "UserTier.tierID = '$safeTier'";
    }

    if ($dateFilter !== '') {
        $safeDate = mysqli_real_escape_string($conn, $dateFilter);
        $whereConditions[] = "DATE(UserTier.assignedDate) = '$safeDate'";
    }


    // Combine filter conditions
    $whereSQL = '';

    if (!empty($whereConditions)) {
        $whereSQL = "WHERE " . implode(" AND ", $whereConditions);
    }

    // Number of user tiers record displayed per page
    $recordsPerPage = 8;

    // Get current page number
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Prevent invalid page number
    if ($page < 1) {
        $page = 1;
    }

    // Calculate offset
    $offset = ($page - 1) * $recordsPerPage;

    // Count total user tiers
    $countQuery = "
    SELECT COUNT(*) AS total
    FROM UserTier
    $whereSQL
    ";
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRecords = $countRow['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Get user tier records
    $userTierQuery = "
    SELECT UserTier.userTierID,
        UserTier.userID,
        UserTier.assignedDate,
        Tiers.tierName
    FROM UserTier
    INNER JOIN Tiers
        ON UserTier.tierID = Tiers.tierID
    $whereSQL
    ORDER BY UserTier.assignedDate DESC
    LIMIT $recordsPerPage OFFSET $offset
    ";

    $userTierResult = mysqli_query($conn, $userTierQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor User Tiers Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body auditor-usertier-page">

                <!-- Page Title -->
                <div class="management-heading management-heading-action">
                    <div class="management-heading-text">
                        <h1>User Tier Management</h1>
                        <p>View and monitor all user tiers on the platform</p>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="management-filter">
                    <form method="GET" action="">

                        <!-- Search User Tiers -->
                        <div class="management-filter-group search">
                            <label for="search">Search User Tiers</label>
                            <div class="management-search-input">
                                <img src="../assets/icons/search.png" alt="Search">
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    placeholder="Search by User Tier ID, User ID..."
                                    value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                >
                            </div>
                        </div>


                        <!-- Filter by Tier -->
                        <div class="management-filter-group">
                            <label for="tier">Tier</label>
                            <select id="tier" name="tier">
                                <option value="">All Tiers</option>
                                <?php while ($tier = mysqli_fetch_assoc($tierResult)): ?>
                                    <option
                                        value="<?php echo htmlspecialchars($tier['tierID']); ?>"
                                        <?php echo (($tierFilter ?? '') == $tier['tierID']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($tier['tierName']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>


                        <!-- Filter by Date Assigned -->
                        <div class="management-filter-group">
                            <label for="assignedDate">Date Assigned</label>
                            <input
                                type="date"
                                id="assignedDate"
                                name="assignedDate"
                                value="<?php echo htmlspecialchars($dateFilter ?? ''); ?>"
                            >
                        </div>


                        <!-- Filter Buttons -->
                        <div class="management-filter-buttons">
                            <button type="submit" class="management-filter-btn">
                                <img src="../assets/icons/filter.png" alt="Filter">
                                Filter
                            </button>
                            <a href="auditor_user_tiers_management.php" class="management-reset-btn">
                                <img src="../assets/icons/reset.png" alt="Reset">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- User Tier List -->
                <div class="management-list">

                    <!-- List Header -->
                    <div class="management-list-header">
                        <h2>User Tier List</h2>
                        <p>
                            Total User Tiers:
                            <strong><?php echo $totalRecords; ?></strong>
                        </p>
                    </div>


                    <!-- User Tier Table -->
                    <div class="management-table-wrapper">
                        <table class="management-table usertier-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User Tier ID</th>
                                    <th>User ID</th>
                                    <th>Tier</th>
                                    <th>Date Assigned</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (mysqli_num_rows($userTierResult) > 0): ?>
                                    <?php $number = $offset + 1; while ($userTier = mysqli_fetch_assoc($userTierResult)): ?>
                                        <tr>
                                            <!-- Number -->
                                            <td>
                                                <?php echo $number++; ?>
                                            </td>

                                            <!-- User Tier ID -->
                                            <td>
                                                <?php echo htmlspecialchars($userTier['userTierID']); ?>
                                            </td>

                                            <!-- User ID -->
                                            <td>
                                                <?php echo htmlspecialchars($userTier['userID']); ?>
                                            </td>

                                            <!-- Tier -->
                                            <td>
                                                <?php echo htmlspecialchars($userTier['tierName']); ?>
                                            </td>

                                            <!-- Date Assigned -->
                                            <td>
                                                <?php
                                                    echo date('j F Y', strtotime($userTier['assignedDate']));
                                                ?>
                                            </td>
                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="management-empty-state">
                                            No user tier records found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Footer -->
                    <div class="management-table-footer">
                        <p>
                            Showing
                            <?php echo ($totalRecords > 0) ? $offset + 1 : 0; ?>
                            to
                            <?php echo min($offset + $recordsPerPage, $totalRecords); ?>
                            of
                            <?php echo $totalRecords; ?>
                            entries
                        </p>


                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">

                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>">
                                        &lsaquo;
                                    </a>
                                <?php endif; ?>


                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a
                                        href="?page=<?php echo $i; ?>"
                                        class="<?php echo ($i == $page) ? 'active' : ''; ?>"
                                    >
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>


                                <!-- Next Page -->
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>">
                                        &rsaquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>