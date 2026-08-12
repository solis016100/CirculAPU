<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function validDate($v): bool {
    if (!$v) return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function showDate($v): string { return $v && strtotime($v) ? date('d M Y', strtotime($v)) : '-'; }
function pageURL($p): string {
    $q = $_GET; $q['page'] = $p;
    return 'manager_transactions.php?' . http_build_query($q);
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['requestStatus'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');
$overdueOnly = ($_GET['overdue'] ?? '') === '1';
$activeOnly = ($_GET['active'] ?? '') === '1';

$statuses = ['Pending','Approved','In Progress','Completed','Cancelled','Rejected','Overdue'];
$transactions = [];
$stats = ['pending'=>0,'active'=>0,'overdue'=>0,'completed'=>0];
$error = '';
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $offset = 0; $totalPages = 1;

if ($status && !in_array($status, $statuses, true)) $status = '';
if (!validDate($dateFrom) || !validDate($dateTo)) $error = 'Please enter a valid date.';
elseif ($dateFrom && $dateTo && $dateFrom > $dateTo) $error = 'Date From cannot be later than Date To.';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    /* Statistics */
    $row = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM transactionrequests WHERE requestStatus='Pending') pending,
            (SELECT COUNT(*) FROM borrowings WHERE borrowingStatus IN ('Approved','Borrowed','Overdue') AND actualReturnDate IS NULL) active,
            (SELECT COUNT(*) FROM borrowings WHERE expectedReturnDate<NOW() AND actualReturnDate IS NULL AND borrowingStatus IN ('Approved','Borrowed','Overdue')) overdue,
            (SELECT COUNT(*) FROM transactionrequests WHERE requestStatus='Completed') completed
    ")->fetch_assoc();
    foreach ($stats as $k => $v) $stats[$k] = (int)($row[$k] ?? 0);

    /* Filters */
    $where = []; $params = []; $types = '';
    if ($search) {
        $where[] = "(t.transactionID LIKE ? OR a.assetName LIKE ? OR u.name LIKE ? OR t.requesterID LIKE ?)";
        $like = "%$search%"; array_push($params, $like, $like, $like, $like); $types .= 'ssss';
    }
    if ($status) { $where[] = 't.requestStatus=?'; $params[] = $status; $types .= 's'; }
    if ($dateFrom) { $where[] = 'DATE(t.requestDate)>=?'; $params[] = $dateFrom; $types .= 's'; }
    if ($dateTo) { $where[] = 'DATE(t.requestDate)<=?'; $params[] = $dateTo; $types .= 's'; }
    if ($activeOnly) $where[] = "EXISTS(SELECT 1 FROM borrowings b WHERE b.transactionID=t.transactionID AND b.borrowingStatus IN ('Approved','Borrowed','Overdue') AND b.actualReturnDate IS NULL)";
    if ($overdueOnly) $where[] = "EXISTS(SELECT 1 FROM borrowings b WHERE b.transactionID=t.transactionID AND b.expectedReturnDate<NOW() AND b.actualReturnDate IS NULL AND b.borrowingStatus IN ('Approved','Borrowed','Overdue'))";
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if (!$error) {
        $stmt = $conn->prepare("SELECT COUNT(*) total FROM transactionrequests t JOIN assets a ON t.assetID=a.assetID JOIN user u ON t.requesterID=u.userID $whereSQL");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;

        $stmt = $conn->prepare("
            SELECT t.transactionID,t.requestDate,t.requestStatus,a.assetName,
                   u.userID requesterID,u.name requesterName,
                   (SELECT MAX(b.expectedReturnDate) FROM borrowings b WHERE b.transactionID=t.transactionID) dueDate,
                   EXISTS(SELECT 1 FROM borrowings b WHERE b.transactionID=t.transactionID AND b.expectedReturnDate<NOW()
                          AND b.actualReturnDate IS NULL AND b.borrowingStatus IN ('Approved','Borrowed','Overdue')) isOverdue
            FROM transactionrequests t
            JOIN assets a ON t.assetID=a.assetID
            JOIN user u ON t.requesterID=u.userID
            $whereSQL
            ORDER BY t.requestDate DESC LIMIT ? OFFSET ?
        ");
        $list = [...$params, $perPage, $offset];
        $stmt->bind_param($types . 'ii', ...$list);
        $stmt->execute(); $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }

    $conn->close();
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load transaction records. Please try again.';
}

$start = $total ? $offset + 1 : 0;
$end = min($offset + $perPage, $total);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction Management - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<section class="management-heading">
    <div class="management-heading-text">
        <h1>Transaction Management</h1>
        <p>Review borrowing requests, active borrowings, overdue returns and completed transactions.</p>
    </div>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Transaction updated successfully.</div><?php endif; ?>

<!-- Statistics -->
<section class="stats-grid">
    <a href="manager_transactions.php?requestStatus=Pending" class="stat-card">
        <div class="stat-icon"><img src="../assets/icons/pendingVerification.png" alt=""></div>
        <div class="stat-content"><h3>Pending Requests</h3><strong class="stat-value"><?= $stats['pending'] ?></strong><p class="stat-description">Awaiting manager review</p></div>
    </a>

    <a href="manager_transactions.php?active=1" class="stat-card">
        <div class="stat-icon"><img src="../assets/icons/active-transactions.png" alt=""></div>
        <div class="stat-content"><h3>Active Borrowings</h3><strong class="stat-value"><?= $stats['active'] ?></strong><p class="stat-description">Currently ongoing</p></div>
    </a>

    <a href="manager_transactions.php?overdue=1" class="stat-card alert-error">
        <div class="stat-icon"><img src="../assets/icons/notification.png" alt=""></div>
        <div class="stat-content"><h3>Overdue Returns</h3><strong class="stat-value"><?= $stats['overdue'] ?></strong><p class="stat-description">Past due and not returned</p></div>
    </a>

    <a href="manager_transactions.php?requestStatus=Completed" class="stat-card">
        <div class="stat-icon"><img src="../assets/icons/mark_as_read.png" alt=""></div>
        <div class="stat-content"><h3>Completed</h3><strong class="stat-value"><?= $stats['completed'] ?></strong><p class="stat-description">Finished transactions</p></div>
    </a>
</section>

<!-- Filters -->
<section class="management-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Transaction</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" value="<?= e($search) ?>" placeholder="Transaction ID, asset or requester">
        </div>
    </div>

    <div class="management-filter-group">
        <label for="requestStatus">Status</label>
        <select id="requestStatus" name="requestStatus">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $v): ?>
            <option value="<?= e($v) ?>" <?= $status === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="management-filter-group"><label for="dateFrom">Date From</label><input type="date" id="dateFrom" name="dateFrom" value="<?= e($dateFrom) ?>"></div>
    <div class="management-filter-group"><label for="dateTo">Date To</label><input type="date" id="dateTo" name="dateTo" value="<?= e($dateTo) ?>"></div>

    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
        <a href="manager_transactions.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
    </div>
</form>
</section>

<!-- List -->
<section class="management-list">
    <div class="management-list-header"><h2>Transaction Requests</h2><p><?= $total ?> transaction<?= $total === 1 ? '' : 's' ?> found</p></div>

    <div class="management-table-wrapper">
    <table class="management-table">
        <thead><tr><th>#</th><th>Transaction ID</th><th>Asset Name</th><th>Requester</th><th>Status</th><th>Request Date</th><th>Expected Return</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$transactions): ?>
            <tr><td colspan="8" class="management-empty-state">No transaction requests found.</td></tr>
        <?php else: foreach ($transactions as $i => $t): $displayStatus = $t['isOverdue'] ? 'Overdue' : $t['requestStatus']; ?>
            <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td><strong><?= e($t['transactionID']) ?></strong></td>
                <td><?= e($t['assetName']) ?></td>
                <td><?= e($t['requesterName'] . ' (' . $t['requesterID'] . ')') ?></td>
                <td><span class="status-badge <?= $t['isOverdue'] ? 'alert-error' : '' ?>"><?= e($displayStatus) ?></span></td>
                <td><?= e(showDate($t['requestDate'])) ?></td>
                <td><?= e(showDate($t['dueDate'])) ?></td>
                <td>
                    <a href="manager_transaction_view.php?transactionID=<?= e($t['transactionID']) ?>" class="management-action-btn"
                       title="<?= $t['requestStatus'] === 'Pending' ? 'Review Transaction Request' : 'View Transaction Details' ?>">
                        <img src="../assets/icons/view.png" alt="View">
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <div class="management-table-footer">
        <p>Showing <?= $start ?> - <?= $end ?> of <?= $total ?> transactions</p>
        <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?><a href="<?= e(pageURL($page - 1)) ?>">Previous</a><?php endif; ?>
            <?php for ($p=1; $p<=$totalPages; $p++): ?><a href="<?= e(pageURL($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a><?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(pageURL($page + 1)) ?>">Next</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</section>

</main>
</div>
</div>
</body>
</html>
