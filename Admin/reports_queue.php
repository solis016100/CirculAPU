<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date_reported'] ?? '';

// Pagination Setup
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base SQL Query joining report and user tables
$sql = "SELECT r.*, u.name as reporterName 
        FROM report r 
        LEFT JOIN user u ON r.reporterID = u.userID 
        WHERE 1=1";

$count_sql = "SELECT COUNT(*) as total 
              FROM report r 
              LEFT JOIN user u ON r.reporterID = u.userID 
              WHERE 1=1";

$params = [];
$types = "";

// Apply Dynamic Filters
if (!empty($search)) {
    $sql .= " AND (r.reportID LIKE ? OR u.name LIKE ? OR r.verificationID LIKE ?)";
    $count_sql .= " AND (r.reportID LIKE ? OR u.name LIKE ? OR r.verificationID LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= "sss";
}

if (!empty($status_filter) && $status_filter !== 'All Status') {
    $sql .= " AND r.status = ?";
    $count_sql .= " AND r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(r.timeCreated) = ?";
    $count_sql .= " AND DATE(r.timeCreated) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

// Get Total Records for Pagination
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_reports = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_reports / $limit));
$stmt_count->close();

// Append Ordering and Pagination Limits
$sql .= " ORDER BY r.timeCreated DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Fetch Report Records
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Queue Dashboard - CirculAPU</title>
    <!-- Primary Global Stylesheet -->
    <link rel="stylesheet" href="../style.css">
    <!-- Secondary Admin Stylesheet -->
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
        <!-- Reusable Sidebar Component from parent directory -->
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <!-- Global Header Component from parent directory -->
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                <!-- Page Breadcrumb & Title Bar -->
                <div class="admin-page-header">
                    <div>
                        <p class="header-breadcrumb">Reports Queue</p>
                        <h1 class="header-title">Reports Queue Dashboard</h1>
                    </div>
                </div>

                <!-- Search and Filter Bar -->
                <div class="content-card admin-filter-card">
                    <form method="GET" action="report_queue.php" class="admin-filter-form">
                        
                        <div class="field-box field-box-search">
                            <label>Search</label>
                            <div class="input-wrapper">
                                <img src="../assets/icons/search.png" alt="Search" class="search-icon">
                                <input type="text" name="search" class="filter-input" placeholder="Search by ReportID, Reporter, Target..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <div class="field-box field-box-select">
                            <label>Status</label>
                            <select name="status" class="filter-select">
                                <option>All Status</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="Disputed" <?php echo $status_filter === 'Disputed' ? 'selected' : ''; ?>>Disputed</option>
                                <option value="Under Investigation" <?php echo $status_filter === 'Under Investigation' ? 'selected' : ''; ?>>Under Investigation</option>
                            </select>
                        </div>

                        <div class="field-box field-box-date">
                            <label>Date Reported</label>
                            <input type="date" name="date_reported" class="filter-input filter-date-input" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>

                        <div class="admin-filter-actions">
                            <button type="submit" class="btn-filter-submit">
                                <img src="../assets/icons/filter.png" alt="Filter"> Filter
                            </button>
                            <a href="report_queue.php" class="btn-filter-reset">
                                <img src="../assets/icons/reset.png" alt="Reset"> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Reports Data Table -->
                <div class="content-card admin-table-card">
                    <div class="table-header-bar">
                        <h2>Report List</h2>
                        <span class="total-count">Total Report: <?php echo $total_reports; ?></span>
                    </div>

                    <table class="admin-user-table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Reporter</th>
                                <th>Target Type</th>
                                <th>Target ID</th>
                                <th>Status</th>
                                <th>Date Reported</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($reports_result->num_rows > 0): ?>
                                <?php while ($report = $reports_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="col-userid"><?php echo htmlspecialchars($report['reportID']); ?></td>
                                        <td><?php echo htmlspecialchars($report['reporterName'] ?? 'Unknown'); ?></td>
                                        <td>Asset Verification</td>
                                        <td><?php echo htmlspecialchars($report['verificationID'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                                                <?php echo htmlspecialchars($report['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('j M Y', strtotime($report['timeCreated'])); ?></td>
                                        <td>
                                            <a href="admin_report_details.php?id=<?php echo urlencode($report['reportID']); ?>" title="Edit / Process Report" class="action-icon edit-icon">
                                                <img src="../assets/icons/edit.png" alt="Edit">
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="table-empty-msg">No reports found matching your search criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Navigation -->
                    <div class="admin-pagination-footer">
                        <span class="pagination-info">
                            Showing <?php echo ($total_reports > 0) ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_reports); ?> of <?php echo $total_reports; ?> reports
                        </span>
                        
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_reported=<?php echo urlencode($date_filter); ?>" class="pagination-btn">&lt;</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= min(3, $total_pages); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_reported=<?php echo urlencode($date_filter); ?>" class="pagination-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($total_pages > 4): ?>
                                <span class="pagination-ellipsis">...</span>
                                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_reported=<?php echo urlencode($date_filter); ?>" class="pagination-btn <?php echo $page == $total_pages ? 'active' : ''; ?>"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_reported=<?php echo urlencode($date_filter); ?>" class="pagination-btn">&gt;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>