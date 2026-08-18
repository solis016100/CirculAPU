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
    require_once '../db.php';

    $totalBadges = 0;
    $badgeResult = null;

    // Get search and filter values
    $search = trim($_GET['search'] ?? '');
    $badgeFilter = trim($_GET['badge'] ?? '');

    $perPage = 8;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $badgeFilterURL = http_build_query(['search' => $search, 'badge' => $badgeFilter, 'page' => $page]);

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

        // Delete Badge
        if (isset($_POST['delete_badge'])) {
            $badgeID = $_POST['badgeID'];
            $get = $conn->prepare("SELECT achieveBadgeName, achieveBadgeIcon FROM achievementbadges WHERE achieveBadgeID = ?");

            $get->bind_param("s", $badgeID);
            $get->execute();

            $badgeData = $get->get_result()->fetch_assoc();
            $get->close();

            if ($badgeData) {
                $delete = $conn->prepare("DELETE FROM achievementbadges WHERE achieveBadgeID = ?");

                $delete->bind_param("s", $badgeID);

                if ($delete->execute()) {
                    // Delete Badge Icon
                    if (!empty($badgeData['achieveBadgeIcon']) && file_exists("../assets/images/" . $badgeData['achieveBadgeIcon'])) {
                        unlink("../assets/images/" . $badgeData['achieveBadgeIcon']);
                    }

                    // System Log
                    $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)), 0) + 1 AS nextID FROM systemlog");

                    $row = $result->fetch_assoc();
                    $logID = "LOG" . str_pad($row['nextID'], 3, "0", STR_PAD_LEFT);
                    $description = "Deleted achievement badge: " . $badgeData['achieveBadgeName'];
                    $log = $conn->prepare("INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Delete', 'Badge', ?, ?)");
                    $log->bind_param("ssss", $logID, $_SESSION['user_id'], $badgeID, $description);
                    $log->execute();
                    $log->close();

                    header("Location: auditor_achievement_badges_management.php?deleted=1");

                    exit();
                }
                $delete->close();
            }
        }

        // Base query
        $badgeQuery = "SELECT achieveBadgeID, achieveBadgeName, achieveBadgeDescription, achieveBadgeRequirement, achieveBadgeIcon FROM achievementbadges WHERE 1=1";

        // Search
        if ($search !== '') {
            $safeSearch = mysqli_real_escape_string($conn, $search);
            $badgeQuery .= "AND (achieveBadgeName LIKE '%$safeSearch%' OR achieveBadgeRequirement LIKE '%$safeSearch%')";
        }

        // Filter by badge
        if ($badgeFilter !== '') {
            $safeBadgeFilter = mysqli_real_escape_string($conn, $badgeFilter);
            $badgeQuery .= "AND achieveBadgeID = '$safeBadgeFilter'";
        }

        // Total badges records
        $countQuery = "SELECT COUNT(*) AS total FROM achievementbadges WHERE 1=1";

        if ($search !== '') {
            $safeSearch = mysqli_real_escape_string($conn, $search);
            $countQuery .= "AND (achieveBadgeName LIKE '%$safeSearch%' OR achieveBadgeRequirement LIKE '%$safeSearch%')";
        }

        if ($badgeFilter !== '') {
            $safeBadgeFilter = mysqli_real_escape_string($conn, $badgeFilter);
            $countQuery .= "AND achieveBadgeID = '$safeBadgeFilter'";
        }

        $countResult = mysqli_query($conn, $countQuery);
        $totalRecords = mysqli_fetch_assoc($countResult)['total'];

        $totalPages = max(1, ceil($totalRecords / $perPage));

        // Get current page records
        $badgeQuery .= " ORDER BY achieveBadgeID ASC LIMIT $perPage OFFSET $offset";
        $badgeResult = mysqli_query($conn, $badgeQuery);
        $totalBadges = $totalRecords;
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

                        <a href="auditor_achievement_badges_create.php" class="management-create-btn">
                            <img src="../assets/icons/create.png" alt="Create">
                            Create New Achievement Badge
                        </a>
                    </div>

                    <!-- Green bar showing Success Creation -->
                    <?php if (isset($_GET['created'])): ?>
                        <div class="alert alert-success">
                            Achievement badge created successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Green bar showing Success Delete -->
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success">
                            Achievement badge deleted successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Green bar showing Success Updation -->
                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success">
                            Achievement Badge updated successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter -->
                    <div class="management-filter">
                        <form method="GET" action="auditor_achievement_badges_management.php">
                            <!-- Search Achievement Badges -->
                            <div class="management-filter-group search">
                                <label for="search">Search Achievement Badges</label>
                                <div class="management-search-input">
                                    <img src="../assets/icons/search.png" alt="Search">
                                    <input type="text" id="search" name="search" placeholder="Search by badge name, requirement..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>

                            <!-- Filter by Badge Name -->
                            <div class="management-filter-group">
                                <label for="badge">Badge Name</label>
                                <select id="badge" name="badge">
                                    <option value="">All Badges</option>
                                    <?php while ($badge = mysqli_fetch_assoc($badgeFilterResult)): ?>
                                        <option value="<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>" <?php echo (($badgeFilter ?? '') == $badge['achieveBadgeID']) ? 'selected' : ''; ?>>
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
                                        <?php $number = $offset + 1; while ($badge = $badgeResult->fetch_assoc()): ?>
                                            <tr ondblclick="window.location.href='auditor_achievement_badges_view.php?achieveBadgeID=<?= urlencode($badge['achieveBadgeID']) ?>&from=management&<?= $badgeFilterURL ?>'">
                                                <!-- Number -->
                                                <td><?php echo $number; ?></td>

                                                <!-- Badge ID -->
                                                <td><?php echo htmlspecialchars($badge['achieveBadgeID']); ?></td>

                                                <!-- Badge Icon -->
                                                <td>
                                                    <img src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>" alt="<?php echo htmlspecialchars($badge['achieveBadgeName']); ?>" class="management-table-image">
                                                </td>

                                                <!-- Badge Name -->
                                                <td><?php echo htmlspecialchars($badge['achieveBadgeName']); ?></td>

                                                <!-- Requirement -->
                                                <td><?php echo htmlspecialchars($badge['achieveBadgeRequirement']); ?></td>

                                                <!-- Actions -->
                                                <td>
                                                    <div class="management-actions">
                                                        <!-- View -->
                                                        <a href="auditor_achievement_badges_view.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>"class="management-action-btn" title="View">
                                                            <img src="../assets/icons/view.png" alt="View">
                                                        </a>

                                                        <!-- Edit -->
                                                        <a href="auditor_achievement_badges_edit.php?badgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>&from=management" class="management-action-btn" title="Edit">
                                                            <img src="../assets/icons/edit.png" alt="Edit">
                                                        </a>

                                                        <!-- Print -->
                                                        <a href="auditor_achievement_badges_view.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>&print=1&from=management" class="management-action-btn" title="Print">
                                                            <img src="../assets/icons/print.png" alt="Print">
                                                        </a>

                                                        <!-- Delete -->
                                                        <button type="button" class="management-action-btn delete" title="Delete" onclick="openDeletePopout('<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>')">
                                                            <img src="../assets/icons/delete.png" alt="Delete">
                                                        </button>
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
                                Showing <?php echo $totalRecords > 0 ? $offset + 1 : 0; ?>
                                to <?php echo min($offset + $perPage, $totalRecords); ?>
                                of <?php echo $totalRecords; ?>
                                entries
                            </p>

                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&badge=<?php echo urlencode($badgeFilter); ?>">
                                        &lsaquo;
                                    </a>
                                <?php else: ?>
                                    <a href="#" class="disabled">
                                        &lsaquo;
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&badge=<?php echo urlencode($badgeFilter); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&badge=<?php echo urlencode($badgeFilter); ?>">
                                        &rsaquo;
                                    </a>
                                <?php else: ?>
                                    <a href="#" class="disabled">
                                        &rsaquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
        </div>
    </div>

    <!-- Delete Popout -->
    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Achievement Badge</h2>
            <p>Are you sure you want to delete this achievement badge?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <input type="hidden" name="badgeID" id="deleteBadgeID">
                    <button type="submit" name="delete_badge" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeletePopout(id) {
            document.getElementById('deleteBadgeID').value = id;
            document.getElementById('deletePopout').classList.add('show');
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').classList.remove('show');
        }

        document.querySelectorAll('.management-table tbody tr').forEach(row => {
            row.addEventListener('click', () => {
                if (window.innerWidth <= 768) row.ondblclick?.();
            });
        });
    </script>

</body>
</html>
