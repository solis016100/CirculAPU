<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logPageUrl(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

$search = trim($_GET['search'] ?? '');
$action_type = trim($_GET['action_type'] ?? '');
$ref_type = trim($_GET['ref_type'] ?? '');
$date_logged = trim($_GET['date_logged'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;

$action_types = ['Create', 'Submit', 'Approve', 'Reject', 'Redeem', 'Assign', 'Update', 'Delete', 'Login', 'Logout'];
$ref_types = ['Mission', 'Asset', 'Reward', 'Badge', 'Tier', 'Verification', 'User', 'Category', 'Transaction', 'Borrowing'];

if (!in_array($action_type, $action_types, true)) {
    $action_type = '';
}
if (!in_array($ref_type, $ref_types, true)) {
    $ref_type = '';
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(sl.logID LIKE ? OR sl.userID LIKE ? OR u.name LIKE ? OR sl.refID LIKE ? OR sl.description LIKE ?)';
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}
if ($action_type !== '') {
    $where[] = 'sl.actionType = ?';
    $params[] = $action_type;
    $types .= 's';
}
if ($ref_type !== '') {
    $where[] = 'sl.refType = ?';
    $params[] = $ref_type;
    $types .= 's';
}
if ($date_logged !== '') {
    $where[] = 'DATE(sl.timeLogged) = ?';
    $params[] = $date_logged;
    $types .= 's';
}

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$base_from = ' FROM systemlog sl LEFT JOIN user u ON sl.userID = u.userID';
$error = '';
$logs = [];
$total_logs = 0;
$total_pages = 1;

try {
    $conn->set_charset('utf8mb4');

    $count_stmt = $conn->prepare('SELECT COUNT(*) AS total' . $base_from . $where_sql);
    if ($types !== '') {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_logs = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $count_stmt->close();

    $total_pages = max(1, (int)ceil($total_logs / $limit));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $limit;

    $sql = 'SELECT sl.logID, sl.userID, u.name AS userName, u.role AS userRole,
                   sl.actionType, sl.refType, sl.refID, sl.description, sl.timeLogged'
         . $base_from . $where_sql
         . ' ORDER BY sl.timeLogged DESC, sl.logID DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    $query_params = array_merge($params, [$limit, $offset]);
    $query_types = $types . 'ii';
    $stmt->bind_param($query_types, ...$query_params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Unable to load system logs. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <?php include '../header.php'; ?>

        <main class="page-body">
            <div class="admin-page-header">
                <div>
                    <p class="header-breadcrumb"><a href="admin_dashboard.php">Dashboard</a> &gt; System Logs</p>
                    <h1 class="header-title">System Logs</h1>
                    <p class="header-subtitle">Review database-backed activity recorded across the platform.</p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="content-card admin-filter-card">
                <form method="GET" action="system_logs.php" class="admin-filter-form">
                    <div class="field-box" style="flex: 2; min-width: 220px;">
                        <label for="log-search">Search</label>
                        <div class="input-wrapper">
                            <img src="../assets/icons/search.png" alt="" class="search-icon">
                            <input id="log-search" type="search" name="search" class="filter-input" placeholder="Log ID, user, reference or details" value="<?php echo e($search); ?>">
                        </div>
                    </div>

                    <div class="field-box" style="flex: 1; min-width: 145px;">
                        <label for="action-type">Action</label>
                        <select id="action-type" name="action_type" class="filter-select">
                            <option value="">All Actions</option>
                            <?php foreach ($action_types as $value): ?>
                                <option value="<?php echo e($value); ?>" <?php echo $action_type === $value ? 'selected' : ''; ?>><?php echo e($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-box" style="flex: 1; min-width: 145px;">
                        <label for="reference-type">Module</label>
                        <select id="reference-type" name="ref_type" class="filter-select">
                            <option value="">All Modules</option>
                            <?php foreach ($ref_types as $value): ?>
                                <option value="<?php echo e($value); ?>" <?php echo $ref_type === $value ? 'selected' : ''; ?>><?php echo e($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-box" style="flex: 1; min-width: 145px;">
                        <label for="date-logged">Date</label>
                        <input id="date-logged" type="date" name="date_logged" class="filter-input" value="<?php echo e($date_logged); ?>">
                    </div>

                    <div class="admin-filter-actions">
                        <button type="submit" class="btn-filter-submit"><img src="../assets/icons/filter.png" alt=""> Filter</button>
                        <a href="system_logs.php" class="btn-filter-reset"><img src="../assets/icons/reset.png" alt=""> Reset</a>
                    </div>
                </form>
            </div>

            <div class="content-card admin-table-card">
                <div class="table-header-bar">
                    <h2>Activity History</h2>
                    <span class="total-count">Total Logs: <?php echo $total_logs; ?></span>
                </div>

                <table class="admin-user-table">
                    <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Activity</th>
                        <th>Performed By</th>
                        <th>Reference</th>
                        <th>Details</th>
                        <th>Date &amp; Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$logs): ?>
                        <tr><td colspan="6" class="table-empty-msg">No system logs match the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="col-userid"><?php echo e($log['logID']); ?></td>
                                <td>
                                    <div class="activity-cell activity-flex">
                                        <img src="../assets/icons/activity.svg" alt="" class="cell-icon">
                                        <span><?php echo e($log['actionType'] . ' ' . $log['refType']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo e(($log['userName'] ?: 'Unknown User') . ' (' . $log['userID'] . ')'); ?>
                                    <?php if ($log['userRole']): ?><span class="status-badge active"><?php echo e($log['userRole']); ?></span><?php endif; ?>
                                </td>
                                <td><?php echo e($log['refID'] ?: '-'); ?></td>
                                <td><?php echo e($log['description']); ?></td>
                                <td class="col-time"><?php echo e(date('j M Y, h:i A', strtotime($log['timeLogged']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <div class="admin-pagination-footer">
                    <span class="pagination-info">
                        Showing <?php echo $total_logs ? (($page - 1) * $limit) + 1 : 0; ?> to <?php echo min($page * $limit, $total_logs); ?> of <?php echo $total_logs; ?> logs
                    </span>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?><a href="<?php echo e(logPageUrl($page - 1)); ?>" class="pagination-btn">&lt;</a><?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="<?php echo e(logPageUrl($i)); ?>" class="pagination-btn <?php echo $page === $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?><a href="<?php echo e(logPageUrl($page + 1)); ?>" class="pagination-btn">&gt;</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
