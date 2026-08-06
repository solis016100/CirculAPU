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

        // Default Values
        $databaseError  = '';
        $notifications  = [];
        $totalRecords   = 0;
        $totalPages     = 1;
        $currentPage    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        // Connect to database
        $conn = new mysqli("localhost", "root", "", "circulapu_db");
        if ($conn->connect_error){
            $databaseError = "Unable to connect to the database.";
        }else {
            // (UTF-8) allows the database to handle different languages
            $conn->set_charset("utf8mb4");

            // Actions logics
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['mark_selected_read']) && !empty($_POST['selected_notifications'])) {
                $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
                foreach ($_POST['selected_notifications'] as $id) {
                    $stmt->bind_param("ss", $id, $auditorID);
                    $stmt->execute();
                }
                $stmt->close();
            }

            if (isset($_POST['mark_all_read'])) {
                $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
                $stmt->bind_param("s", $auditorID);
                $stmt->execute();
                $stmt->close();
            }

            if (isset($_POST['delete_selected']) && !empty($_POST['selected_notifications'])) {
                $stmt = $conn->prepare("DELETE FROM notification WHERE notificationID=? AND userID=?");
                foreach ($_POST['selected_notifications'] as $id) {
                    $stmt->bind_param("ss", $id, $auditorID);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Redirect so a page refresh doesn't resubmit the form
            header("Location: auditor_notification.php" . ($currentQueryString ? '?' . $currentQueryString : ''));
            exit();
        }

            // Number of notifications shown on each page
            $recordsPerPage = 8;

            // Get current page from URL
            $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;

            // Prevent invalid page numbers
            if ($currentPage < 1) {
                $currentPage = 1;
            }

            // Count total notifications
            $countSQL = "SELECT COUNT(*) AS total
                        FROM notification
                        WHERE userID = ?";

            $countStmt = mysqli_prepare($conn, $countSQL);

            mysqli_stmt_bind_param($countStmt, "s", $auditorID);

            mysqli_stmt_execute($countStmt);

            $countResult = mysqli_stmt_get_result($countStmt);

            $countRow = mysqli_fetch_assoc($countResult);

            $totalRecords = $countRow['total'];


            // Count total pages
            $totalPages = ceil($totalRecords / $recordsPerPage);

            // Prevent current page from exceeding total pages
            if ($totalPages > 0 && $currentPage > $totalPages) {
                $currentPage = $totalPages;
            }

            // Calculate offset
            $offset = ($currentPage - 1) * $recordsPerPage;

            // Get notification data for current page
            $search = trim($_GET['search'] ?? '');
            $typeFilter = $_GET['type'] ?? '';
            $statusFilter = $_GET['status'] ?? '';

            $whereSql = "WHERE userID = ?";
            $params = [$auditorID];
            $types = "s";

            if ($typeFilter !== '') {
                $whereSql .= " AND type = ?";
                $params[] = $typeFilter;
                $types .= "s";
            }
            if ($statusFilter === 'Read' || $statusFilter === 'Unread') {
                $whereSql .= " AND status = ?";
                $params[] = $statusFilter;
                $types .= "s";
            }
            if ($search !== '') {
                $whereSql .= " AND (title LIKE CONCAT('%',?,'%') OR content LIKE CONCAT('%',?,'%'))";
                $params[] = $search;
                $params[] = $search;
                $types .= "ss";
            }

            $sql = "SELECT notificationID, userID, title, content, type, relatedTo, status, createdDate FROM notification $whereSql ORDER BY createdDate DESC LIMIT ? OFFSET ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "sii",
                $auditorID,
                $recordsPerPage,
                $offset
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
        }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Notification Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="notification-page">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <span>Sustainability Auditor Dashboard</span>
                    <span>&gt;</span>
                    <span>Notifications</span>
                </div>

                <!-- Page Heading -->
                <div class="notification-heading">
                    <h1>Notification Center</h1>
                    <p>View and manage all system notifications.</p>
                </div>

                <!-- Database Error -->
                <?php if (!empty($databaseError)): ?>
                    <div class="notification-error-message"><?php echo htmlspecialchars($databaseError); ?></div>
                <?php endif; ?>


                <!-- Search and Filter Section -->
                <form class="notification-filter-section" method="get" action="auditor_notification.php">
                    <div class="notification-filter-group notification-search-group">
                        <label for="notificationSearch">Search Notification</label>
                        <div class="notification-search-box">
                            <img src="assets/icons/search.png" alt="Search">
                            <input
                                type="text"
                                id="notificationSearch"
                                name="search"
                                placeholder="Search by title, asset ID, exchange ID..."
                            >
                        </div>
                    </div>

                    <!-- Filter by Type -->
                    <div class="notification-filter-group">
                        <label for="notificationType">Type</label>
                        <select id="notificationType" name="type">
                            <option value="">All Types</option>
                            <option value="Verification">Verification</option>
                            <option value="Mission">Mission</option>
                            <option value="Reward">Reward</option>
                            <option value="Badge">Badge</option>
                            <option value="User Tier">User Tier</option>
                            <option value="System">System</option>
                        </select>
                    </div>

                    <!-- Filter by Status -->
                    <div class="notification-filter-group">
                        <label for="notificationStatus">Status</label>
                        <select id="notificationStatus" name="status">
                            <option value="">All Status</option>
                            <option value="Unread">Unread</option>
                            <option value="Read">Read</option>
                        </select>
                    </div>

                    <!-- Reset Button -->
                    <div class="notification-filter-buttons">
                        <button type="button" class="notification-filter-btn" id="filterNotification">
                            <img src="assets/icons/filter.png" alt="">
                            <span>Filter</span>
                        </button>
                        <button type="button" class="notification-reset-btn" id="resetNotification">
                            <img src="assets/icons/reset.png" alt="">
                            <span>Reset</span>
                        </button>
                    </div>
                </form>


                 <!-- Notification Content -->
                <div class="notification-content">

                    <!-- Notification Table -->
                    <div class="notification-table-area">
                        <div class="notification-table-wrapper">
                            <table class="notification-table">

                                <!-- Table Heading -->
                                <thead>
                                    <tr>
                                        <th class="notification-checkbox-column"></th>
                                        <th>#</th>
                                        <th>Notification</th>
                                        <th>Type</th>
                                        <th>Related To</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th class="notification-action-column">
                                        </th>
                                    </tr>
                                </thead>

                                <!-- Database Records -->
                                <tbody>
                                    <?php $number = 1; if (mysqli_num_rows($result) > 0): while ($notification = mysqli_fetch_assoc($result)): ?>

                                    <tr class="<?= $notification['status'] === 'Unread' ? 'unread-row' : '' ?>" >
                                        <!-- Checkbox -->
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="notification-checkbox"
                                                name="selected_notifications[]"
                                                value="<?=
                                                    htmlspecialchars(
                                                        $notification['notificationID']
                                                    )
                                                ?>"
                                            >
                                        </td>


                                        <!-- Number -->
                                        <td>
                                            <?= $number ?>
                                        </td>


                                        <!-- Notification Details -->
                                        <td>
                                            <div class="notification-title-cell">
                                                <div class="notification-message">
                                                    <strong>
                                                        <?= htmlspecialchars($notification['title'])?>
                                                    </strong>

                                                    <p>
                                                        <?= htmlspecialchars($notification['content'])?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>


                                        <!-- Type -->
                                        <td>
                                            <span class="notification-type">
                                                <?= htmlspecialchars($notification['type'])?>
                                            </span>
                                        </td>

                                        <!-- Related To -->
                                        <td class="notification-related">
                                            <?= htmlspecialchars($notification['relatedTo'] ?? '-') ?>
                                        </td>

                                        <!-- Date and Time -->
                                        <td>
                                            <?= date('d M Y', strtotime($notification['createdDate']))?>
                                            <br>
                                            <?= date('h:i A', strtotime($notification['createdDate']))?>
                                        </td>


                                        <!-- Status -->
                                        <td>
                                            <span class="notification-status <?= strtolower($notification['status'])?>">
                                                <?= htmlspecialchars($notification['status'])?>
                                            </span>
                                        </td>
                                    </tr>


                                <!-- No Notifications -->
                                <?php $number++; endwhile; else: ?>
                                    <tr>
                                        <td colspan="7" class="no-notification" >
                                            No notifications found.
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                </tbody>
                            </table>
                        </div>


                        <!-- TABLE FOOTER -->
                        <div class="notification-table-footer">
                            <?php $rangeStart = $totalRecords === 0 ? 0 : (($currentPage - 1) * $recordsPerPage) + 1; $rangeEnd   = min($totalRecords, $currentPage * $recordsPerPage);?>
                                <p>
                                    Showing <?= $rangeStart ?> to <?= $rangeEnd ?> of <?= $totalRecords ?> entries
                                </p>


                            <!-- Pagination Footer -->
                            <div class="notification-pagination">

                                <?php if ($totalPages > 1): ?>

                                    <!-- Previous -->
                                    <?php if ($currentPage > 1): ?>
                                        <a href="?page=<?= $currentPage - 1 ?>" class="pagination-arrow" >
                                            &lsaquo;
                                        </a>

                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                        <?php $pageParams = array_filter(['search' => $search, 'type' => $typeFilter, 'status' => $statusFilter]); function pageLink($p, $params) { $params['page'] = $p; return '?' . http_build_query($params); }?>
                                        <a href="?page=<?= $page ?>" class="pagination-number <?= $page == $currentPage ? 'active' : '' ?>" ><?= $page ?></a>

                                    <?php endfor; ?>

                                    <!-- Next -->
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="?page=<?= $currentPage + 1 ?>" class="pagination-arrow" >
                                            &rsaquo;
                                        </a>

                                    <?php endif; ?>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                    <!-- Actions Button -->
                    <form method="POST" action="auditor_notification.php<?= $currentQueryString ? '?' . htmlspecialchars($currentQueryString) : '' ?>" id="notificationActionForm">
                        <aside class="notification-actions">

                            <!-- Mark Selected as Read -->
                            <button type="button" id="markSelectedRead">
                                <img src="assets/icons/mark_as_read.png" alt="Mark Selected as Read" >
                                <span>Mark Selected as Read</span>
                            </button>

                            <!-- Mark All as Read -->
                            <button type="button" id="markAllRead">
                                <img src="assets/icons/envelope.png" alt="Mark All as Read">
                                <span>Mark All as Read</span>
                            </button>

                            <!-- Print -->
                            <button type="button" id="printNotifications">
                                <img  src="assets/icons/print.png" alt="Print">
                                <span>Print</span>
                            </button>

                            <!-- Delete -->
                            <button type="button" id="deleteNotifications">
                                <img src="assets/icons/delete.png" alt="Delete Selected">
                                <span>Delete Selected</span>
                            </button>

                            <!-- Back -->
                            <button type="button" class="notification-back-btn" onclick="history.back()">
                                Back
                            </button>
                        </aside>
                    </form>

                    <!-- If there is no notification selected -->
                    <script>
                        function requireSelection(event, action) {
                            const checked = document.querySelectorAll('.notification-checkbox:checked');
                            if (checked.length === 0) {
                                event.preventDefault();
                                alert('Please select at least one notification to ' + action + '.');
                                return false;
                            }
                            return true;
                        }
                        </script>
                </div>


            </main>
        </div>
    </div>
</body>
</html>