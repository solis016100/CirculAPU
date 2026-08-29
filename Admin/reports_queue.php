<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
require_once '../db.php';

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

 
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_filter = $_GET['date_reported'] ?? '';
if (!in_array($status_filter, ['Pending', 'Resolved'], true)) $status_filter = '';

 
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

 
$sql = "SELECT r.*, u.name AS reporterName, v.targetType, v.targetID
        FROM report r 
        LEFT JOIN user u ON r.reporterID = u.userID
        LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID
        WHERE 1=1";

$count_sql = "SELECT COUNT(*) as total 
              FROM report r 
              LEFT JOIN user u ON r.reporterID = u.userID
              LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID
              WHERE 1=1";

$params = [];
$types = "";

 
if (!empty($search)) {
    $sql .= " AND (r.reportID LIKE ? OR r.reporterID LIKE ? OR u.name LIKE ? OR r.verificationID LIKE ?
                    OR v.targetType LIKE ? OR v.targetID LIKE ? OR r.reason LIKE ?)";
    $count_sql .= " AND (r.reportID LIKE ? OR r.reporterID LIKE ? OR u.name LIKE ? OR r.verificationID LIKE ?
                          OR v.targetType LIKE ? OR v.targetID LIKE ? OR r.reason LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $types .= "sssssss";
}

if ($status_filter !== '') {
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

 
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_reports = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_reports / $limit));
$stmt_count->close();
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

 
$sql .= " ORDER BY r.timeCreated DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

 
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports_result = $stmt->get_result();

function reportQueueUrl(int $target_page): string {
    global $search, $status_filter, $date_filter;
    $query = array_filter([
        'page' => $target_page,
        'search' => $search,
        'status' => $status_filter,
        'date_reported' => $date_filter
    ], static fn($value) => $value !== '');
    return '?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Queue Dashboard - CirculAPU</title>
     
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
                        <p class="breadcrumb">Reports Queue</p>
                        <h1 class="header-title">Reports Queue Dashboard</h1>
                    </div>
                </div>

                 
                <div class="content-card admin-filter-card">
                    <form method="GET" action="reports_queue.php" class="admin-filter-form">
                        
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
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
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
                            <a href="reports_queue.php" class="btn-filter-reset">
                                <img src="../assets/icons/reset.png" alt="Reset"> Reset
                            </a>
                        </div>
                    </form>
                </div>

                 
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
                                        <td><?php echo htmlspecialchars($report['reporterName'] ?: 'Unknown User'); ?></td>
                                        <td><?php echo htmlspecialchars($report['targetType'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($report['targetID'] ?: '-'); ?></td>
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

                     
                    <div class="admin-pagination-footer">
                        <span class="pagination-info">
                            Showing <?php echo ($total_reports > 0) ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_reports); ?> of <?php echo $total_reports; ?> reports
                        </span>
                        
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo htmlspecialchars(reportQueueUrl($page - 1)); ?>" class="pagination-btn">&lt;</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="<?php echo htmlspecialchars(reportQueueUrl($i)); ?>" class="pagination-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo htmlspecialchars(reportQueueUrl($page + 1)); ?>" class="pagination-btn">&gt;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
