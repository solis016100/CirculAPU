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

    $totalBadges = 0;
    $badgeResult = null;

    // Get search and filter values
    $search = trim($_GET['search'] ?? '');
    $badgeFilter = trim($_GET['badge'] ?? '');

    if ($conn->connect_error) {
        $databaseError = "Unable to connect to the database.";
    } else {
        // Allow database to handle different languages
        $conn->set_charset("utf8mb4");

        // Retrieve achievement badges for filter dropdown
        $badgeFilterQuery = "
            SELECT achieveBadgeID, achieveBadgeName
            FROM achievementbadges
            ORDER BY achieveBadgeName ASC
        ";

        $badgeFilterResult = mysqli_query($conn, $badgeFilterQuery);

        // Base query
        $badgeQuery = "
            SELECT
                achieveBadgeID,
                achieveBadgeName,
                achieveBadgeDescription,
                achieveBadgeRequirement,
                achieveBadgeIcon
            FROM achievementbadges
            WHERE 1=1
        ";


        // Search
        if ($search !== '') {
            $safeSearch = mysqli_real_escape_string($conn, $search);
            $badgeQuery .= "
                AND (
                    achieveBadgeName LIKE '%$safeSearch%'
                    OR achieveBadgeRequirement LIKE '%$safeSearch%'
                )
            ";
        }


        // Filter by badge
        if ($badgeFilter !== '') {
            $safeBadgeFilter = mysqli_real_escape_string($conn, $badgeFilter);
            $badgeQuery .= "
                AND achieveBadgeID = '$safeBadgeFilter'
            ";
        }


        $badgeQuery .= " ORDER BY achieveBadgeID ASC";

        // Run query
        $badgeResult = mysqli_query($conn, $badgeQuery);

        // Total displayed badges
        $totalBadges = mysqli_num_rows($badgeResult);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Achievement Badges Management Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

                <main class="page-body auditor-badge-page">

                    <div class="management-heading management-heading-action">
                        <div class="management-heading-text">
                            <h1>Achievement Badges Management</h1>
                            <p>Manage all Achievement Badges on the platform</p>
                        </div>

                        <a href="auditor_badge_create.php" class="management-create-btn">
                            <img src="../assets/icons/create.png" alt="Create">
                            Create New Achievement Badge
                        </a>
                    </div>

                    <!-- Search and Filter -->
                    <div class="management-filter">
                        <form method="GET" action="auditor_achievement_badges_management.php">

                            <!-- Search Achievement Badges -->
                            <div class="management-filter-group search">
                                <label for="search">Search Achievement Badges</label>
                                <div class="management-search-input">
                                    <img src="../assets/icons/search.png" alt="Search">
                                    <input
                                        type="text"
                                        id="search"
                                        name="search"
                                        placeholder="Search by badge name, requirement..."
                                        value="<?php echo htmlspecialchars($search); ?>"
                                    >
                                </div>
                            </div>


                            <!-- Filter by Badge Name -->
                            <div class="management-filter-group">
                                <label for="badge">Badge Name</label>
                                <select id="badge" name="badge">
                                    <option value="">All Badges</option>
                                    <?php while ($badge = mysqli_fetch_assoc($badgeFilterResult)): ?>
                                        <option
                                            value="<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>"
                                            <?php echo (($badgeFilter ?? '') == $badge['achieveBadgeID']) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($badge['achieveBadgeName']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>


                            <!-- Filter Buttons -->
                            <div class="management-filter-buttons">
                                <button type="submit" class="management-filter-btn">
                                    <img src="../assets/icons/filter.png" alt="Filter">
                                    Filter
                                </button>

                                <a href="auditor_achievement_badges_management.php" class="management-reset-btn">
                                    <img src="../assets/icons/reset.png" alt="Reset">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Achievement Badges List -->
                    <div class="management-list">
                        <div class="management-list-header">
                            <h2>Achievement Badges List</h2>
                            <p>
                                Total Achievement Badges:
                                <strong><?php echo $totalBadges ?? 0; ?></strong>
                            </p>
                        </div>


                        <!-- Achievement Badge Table -->
                        <div class="management-table-wrapper">
                            <table class="management-table badge-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Badge ID</th>
                                        <th>Badge Icon</th>
                                        <th>Badge Name</th>
                                        <th>Requirement</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (isset($badgeResult) && $badgeResult->num_rows > 0): ?>
                                        <?php $number = 1; while ($badge = $badgeResult->fetch_assoc()): ?>
                                            <tr>
                                                <!-- Number -->
                                                <td>
                                                    <?php echo $number; ?>
                                                </td>


                                                <!-- Badge ID -->
                                                <td>
                                                    <?php echo htmlspecialchars($badge['achieveBadgeID']); ?>
                                                </td>


                                                <!-- Badge Icon -->
                                                <td>
                                                    <img
                                                        src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>"
                                                        alt="<?php echo htmlspecialchars($badge['achieveBadgeName']); ?>"
                                                        class="badge-icon"
                                                    >
                                                </td>


                                                <!-- Badge Name -->
                                                <td>
                                                    <?php echo htmlspecialchars($badge['achieveBadgeName']); ?>
                                                </td>


                                                <!-- Requirement -->
                                                <td>
                                                    <?php echo htmlspecialchars($badge['achieveBadgeRequirement']); ?>
                                                </td>


                                                <!-- Actions -->
                                                <td>
                                                    <div class="management-actions">
                                                        <!-- View -->
                                                        <a
                                                            href="auditor_badge_view.php?id=<?php echo urlencode($badge['achieveBadgeID']); ?>"
                                                            class="management-action-btn"
                                                            title="View"
                                                        >
                                                            <img
                                                                src="../assets/icons/view.png"
                                                                alt="View"
                                                            >
                                                        </a>


                                                        <!-- Edit -->
                                                        <a
                                                            href="auditor_badge_edit.php?id=<?php echo urlencode($badge['achieveBadgeID']); ?>"
                                                            class="management-action-btn"
                                                            title="Edit"
                                                        >
                                                            <img
                                                                src="../assets/icons/edit.png"
                                                                alt="Edit"
                                                            >

                                                        </a>


                                                        <!-- Print -->
                                                        <a
                                                            href="auditor_badge_print.php?id=<?php echo urlencode($badge['achieveBadgeID']); ?>"
                                                            class="management-action-btn"
                                                            title="Print"
                                                        >
                                                            <img
                                                                src="../assets/icons/print.png"
                                                                alt="Print"
                                                            >

                                                        </a>


                                                        <!-- Delete -->
                                                        <a
                                                            href="auditor_badge_delete.php?id=<?php echo urlencode($badge['achieveBadgeID']); ?>"
                                                            class="management-action-btn delete"
                                                            title="Delete"
                                                        >
                                                            <img
                                                                src="../assets/icons/delete.png"
                                                                alt="Delete"
                                                            >
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>

                                            <?php $number++; ?>

                                        <?php endwhile; ?>


                                    <?php else: ?>

                                        <tr>

                                            <td colspan="6" class="management-empty-state">
                                                No achievement badges found.
                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                        <!-- Table Footer -->
                        <div class="management-table-footer">

                            <p>
                                Showing 1 to 1 of 1 entries
                            </p>


                            <div class="pagination">

                                <a href="#" aria-label="Previous">
                                    &lsaquo;
                                </a>

                                <a href="#" class="active">
                                    1
                                </a>

                                <a href="#" aria-label="Next">
                                    &rsaquo;
                                </a>

                            </div>

                        </div>

                    </div>
                </main>
        </div>
    </div>
</body>
</html>