<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$search = trim($_GET['search'] ?? '');

// 1. Fetch Key Statistic Counts
$total_users_query = "SELECT COUNT(*) as total FROM user WHERE status != 'Deleted'";
$total_users_res = $conn->query($total_users_query);
$total_users = ($total_users_res && $row = $total_users_res->fetch_assoc()) ? $row['total'] : 0;

$pending_reports_query = "SELECT COUNT(*) as total FROM report WHERE status = 'Pending'";
$pending_reports_res = $conn->query($pending_reports_query);
$pending_reports = ($pending_reports_res && $row = $pending_reports_res->fetch_assoc()) ? $row['total'] : 0;

// 2. Pagination for Recent Logs Table
$logs_limit = 5;
$logs_page = isset($_GET['logs_page']) ? (int)$_GET['logs_page'] : 1;
if ($logs_page < 1) $logs_page = 1;
$logs_offset = ($logs_page - 1) * $logs_limit;

$logs_where = '';
$logs_search_params = [];
$logs_search_types = '';
if ($search !== '') {
    $logs_where = " WHERE sl.logID LIKE ? OR sl.userID LIKE ? OR u.name LIKE ?
                    OR sl.actionType LIKE ? OR sl.refType LIKE ? OR sl.refID LIKE ?
                    OR sl.description LIKE ?";
    $like = "%{$search}%";
    $logs_search_params = array_fill(0, 7, $like);
    $logs_search_types = 'sssssss';
}

$stmt_total_logs = $conn->prepare(
    "SELECT COUNT(*) AS total FROM systemlog sl LEFT JOIN user u ON sl.userID = u.userID" . $logs_where
);
if ($logs_search_types !== '') {
    $stmt_total_logs->bind_param($logs_search_types, ...$logs_search_params);
}
$stmt_total_logs->execute();
$total_logs = (int)($stmt_total_logs->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_total_logs->close();
$total_logs_pages = max(1, ceil($total_logs / $logs_limit));
$logs_page = min($logs_page, $total_logs_pages);
$logs_offset = ($logs_page - 1) * $logs_limit;

$logs_query = "SELECT sl.* FROM systemlog sl
               LEFT JOIN user u ON sl.userID = u.userID" . $logs_where .
              " ORDER BY sl.timeLogged DESC LIMIT ? OFFSET ?";
$stmt_logs = $conn->prepare($logs_query);
$logs_params = array_merge($logs_search_params, [$logs_limit, $logs_offset]);
$logs_types = $logs_search_types . 'ii';
$stmt_logs->bind_param($logs_types, ...$logs_params);
$stmt_logs->execute();
$logs_result = $stmt_logs->get_result();

// 3. Pagination for Reports Queue Table
$reports_limit = 5;
$reports_page = isset($_GET['reports_page']) ? (int)$_GET['reports_page'] : 1;
if ($reports_page < 1) $reports_page = 1;
$reports_offset = ($reports_page - 1) * $reports_limit;

$reports_where = '';
$reports_search_params = [];
$reports_search_types = '';
if ($search !== '') {
    $reports_where = " WHERE r.reportID LIKE ? OR r.reporterID LIKE ? OR reporter.name LIKE ?
                       OR r.verificationID LIKE ? OR r.reason LIKE ? OR r.status LIKE ?
                       OR v.targetType LIKE ? OR v.targetID LIKE ?";
    $like = "%{$search}%";
    $reports_search_params = array_fill(0, 8, $like);
    $reports_search_types = 'ssssssss';
}

$stmt_total_reports = $conn->prepare(
    "SELECT COUNT(*) AS total FROM report r
     LEFT JOIN user reporter ON r.reporterID = reporter.userID
     LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID" . $reports_where
);
if ($reports_search_types !== '') {
    $stmt_total_reports->bind_param($reports_search_types, ...$reports_search_params);
}
$stmt_total_reports->execute();
$total_rep_count = (int)($stmt_total_reports->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_total_reports->close();
$total_reports_pages = max(1, ceil($total_rep_count / $reports_limit));
$reports_page = min($reports_page, $total_reports_pages);
$reports_offset = ($reports_page - 1) * $reports_limit;

$reports_query = "SELECT r.*, v.targetType, v.targetID
                 FROM report r 
                 LEFT JOIN user reporter ON r.reporterID = reporter.userID
                 LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID" . $reports_where .
                 " ORDER BY r.timeCreated DESC LIMIT ? OFFSET ?";
$stmt_reports = $conn->prepare($reports_query);
$reports_params = array_merge($reports_search_params, [$reports_limit, $reports_offset]);
$reports_types = $reports_search_types . 'ii';
$stmt_reports->bind_param($reports_types, ...$reports_params);
$stmt_reports->execute();
$reports_result = $stmt_reports->get_result();

// Date formatting helper for tables
function formatDashboardTime($datetime_str) {
    if (empty($datetime_str)) return '-';
    $time = strtotime($datetime_str);
    if (date('Y-m-d', $time) === date('Y-m-d')) {
        return 'Today, ' . date('h:i A', $time);
    } elseif (date('Y-m-d', $time) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday, ' . date('h:i A', $time);
    }
    return date('j M Y, h:i A', $time);
}

function dashboardPageUrl(array $overrides = []): string {
    global $search, $logs_page, $reports_page;
    $query = array_merge([
        'search' => $search,
        'logs_page' => $logs_page,
        'reports_page' => $reports_page
    ], $overrides);
    if ($query['search'] === '') {
        unset($query['search']);
    }
    return '?' . http_build_query($query);
}

$hour = (int)date('G');
$day_period = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$admin_name = trim($_SESSION['user_name'] ?? '') ?: 'System Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - CirculAPU</title>
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
                
                <!-- Welcome Banner -->
                <div class="admin-welcome-banner">
                    <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
                    <p class="welcome-subtitle">Good <?php echo $day_period; ?>! Here's today's overview</p>
                </div>

                <?php if ($search !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        Showing dashboard results matching “<?php echo htmlspecialchars($search); ?>”.
                        <a href="admin_dashboard.php">Clear search</a>
                    </div>
                <?php endif; ?>

                <!-- Top KPI Stat Cards -->
                <div class="admin-stats-grid">
                    <div class="admin-stat-card">
                        <div class="stat-icon-box">
                            <img src="../assets/icons/users.svg" alt="Users">
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">Total Users</span>
                            <strong class="stat-number"><?php echo $total_users; ?></strong>
                        </div>
                    </div>

                    <div class="admin-stat-card">
                        <div class="stat-icon-box">
                            <img src="../assets/icons/pendingVerification.png" alt="Pending Reports">
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">Pending Reports</span>
                            <strong class="stat-number"><?php echo $pending_reports; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Side-by-Side Tables Section -->
                <div class="admin-tables-row">
                    
                    <!-- Table 1: Recent Logs -->
                    <div class="content-card admin-dashboard-table-card">
                        <div class="table-card-header">
                            <h2>Recent Logs</h2>
                            <a href="system_logs.php" class="view-all-link">View All</a>
                        </div>

                        <table class="admin-user-table dashboard-mini-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Details</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($logs_result->num_rows > 0): ?>
                                    <?php while ($log = $logs_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="col-activity">
                                                <div class="activity-cell activity-flex">
                                                    <img src="../assets/icons/activity.svg" alt="Log" class="cell-icon">
                                                    <span><?php echo htmlspecialchars($log['actionType'] . ' ' . $log['refType']); ?></span>
                                                </div>
                                            </td>
                                            <td class="col-details"><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td class="col-time"><?php echo formatDashboardTime($log['timeLogged']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="table-empty-msg">No recent logs recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Logs Pagination -->
                        <div class="admin-pagination-footer mini-footer">
                            <div class="pagination-controls">
                                <?php if ($logs_page > 1): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['logs_page' => $logs_page - 1])); ?>" class="pagination-btn">&lt;</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= min(3, $total_logs_pages); $i++): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['logs_page' => $i])); ?>" class="pagination-btn <?php echo $logs_page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($logs_page < $total_logs_pages): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['logs_page' => $logs_page + 1])); ?>" class="pagination-btn">&gt;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Table 2: Reports Queue -->
                    <div class="content-card admin-dashboard-table-card">
                        <div class="table-card-header">
                            <h2>Reports Queue</h2>
                            <a href="reports_queue.php" class="view-all-link">View All</a>
                        </div>

                        <table class="admin-user-table dashboard-mini-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Target Type</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($reports_result->num_rows > 0): ?>
                                    <?php while ($report = $reports_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="col-reportid">
                                                <div class="activity-cell activity-flex">
                                                    <img src="../assets/icons/report.png" alt="Report" class="cell-icon">
                                                    <a href="admin_report_details.php?id=<?php echo urlencode($report['reportID']); ?>" class="col-userid"><?php echo htmlspecialchars($report['reportID']); ?></a>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['targetType'] ?? 'Asset Verification'); ?></td>
                                            <td class="col-time"><?php echo formatDashboardTime($report['timeCreated']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="table-empty-msg">No reports in queue.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Reports Pagination -->
                        <div class="admin-pagination-footer mini-footer">
                            <div class="pagination-controls">
                                <?php if ($reports_page > 1): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['reports_page' => $reports_page - 1])); ?>" class="pagination-btn">&lt;</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= min(3, $total_reports_pages); $i++): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['reports_page' => $i])); ?>" class="pagination-btn <?php echo $reports_page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($reports_page < $total_reports_pages): ?>
                                    <a href="<?php echo htmlspecialchars(dashboardPageUrl(['reports_page' => $reports_page + 1])); ?>" class="pagination-btn">&gt;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>
</body>
</html>
