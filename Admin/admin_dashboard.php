<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

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

$total_logs_res = $conn->query("SELECT COUNT(*) as total FROM systemlog");
$total_logs = ($total_logs_res && $row = $total_logs_res->fetch_assoc()) ? $row['total'] : 0;
$total_logs_pages = max(1, ceil($total_logs / $logs_limit));

$logs_query = "SELECT * FROM systemlog ORDER BY timeLogged DESC LIMIT ? OFFSET ?";
$stmt_logs = $conn->prepare($logs_query);
$stmt_logs->bind_param("ii", $logs_limit, $logs_offset);
$stmt_logs->execute();
$logs_result = $stmt_logs->get_result();

// 3. Pagination for Reports Queue Table
$reports_limit = 5;
$reports_page = isset($_GET['reports_page']) ? (int)$_GET['reports_page'] : 1;
if ($reports_page < 1) $reports_page = 1;
$reports_offset = ($reports_page - 1) * $reports_limit;

$total_rep_res = $conn->query("SELECT COUNT(*) as total FROM report");
$total_rep_count = ($total_rep_res && $row = $total_rep_res->fetch_assoc()) ? $row['total'] : 0;
$total_reports_pages = max(1, ceil($total_rep_count / $reports_limit));

$reports_query = "SELECT r.*, v.targetType 
                 FROM report r 
                 LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID 
                 ORDER BY r.timeCreated DESC LIMIT ? OFFSET ?";
$stmt_reports = $conn->prepare($reports_query);
$stmt_reports->bind_param("ii", $reports_limit, $reports_offset);
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
                    <h1 class="welcome-title">Welcome back, System Admin!</h1>
                    <p class="welcome-subtitle">Good afternoon! Here's today's overview</p>
                </div>

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
                                    <a href="?logs_page=<?php echo $logs_page - 1; ?>&reports_page=<?php echo $reports_page; ?>" class="pagination-btn">&lt;</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= min(3, $total_logs_pages); $i++): ?>
                                    <a href="?logs_page=<?php echo $i; ?>&reports_page=<?php echo $reports_page; ?>" class="pagination-btn <?php echo $logs_page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($logs_page < $total_logs_pages): ?>
                                    <a href="?logs_page=<?php echo $logs_page + 1; ?>&reports_page=<?php echo $reports_page; ?>" class="pagination-btn">&gt;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Table 2: Reports Queue -->
                    <div class="content-card admin-dashboard-table-card">
                        <div class="table-card-header">
                            <h2>Reports Queue</h2>
                            <a href="report_queue.php" class="view-all-link">View All</a>
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
                                                    <img src="../assets/icons/file-text.svg" alt="Report" class="cell-icon">
                                                    <span class="col-userid"><?php echo htmlspecialchars($report['reportID']); ?></span>
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
                                    <a href="?reports_page=<?php echo $reports_page - 1; ?>&logs_page=<?php echo $logs_page; ?>" class="pagination-btn">&lt;</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= min(3, $total_reports_pages); $i++): ?>
                                    <a href="?reports_page=<?php echo $i; ?>&logs_page=<?php echo $logs_page; ?>" class="pagination-btn <?php echo $reports_page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($reports_page < $total_reports_pages): ?>
                                    <a href="?reports_page=<?php echo $reports_page + 1; ?>&logs_page=<?php echo $logs_page; ?>" class="pagination-btn">&gt;</a>
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
