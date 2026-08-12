<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function validDate(string $v): bool {
    if ($v === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function showDate($v, bool $time = false): string {
    if (!$v || !strtotime($v)) return '-';
    return date($time ? 'd M Y, h:i A' : 'd M Y', strtotime($v));
}
function pageURL(int $page): string {
    $q = $_GET; $q['page'] = $page;
    return 'manager_transactions.php?' . http_build_query($q);
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['requestStatus'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');

$statuses = ['Pending','Approved','In Progress','Completed','Cancelled','Rejected','Overdue'];
$transactions = [];
$error = '';
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $offset = 0;
$totalPages = 1;

if ($status && !in_array($status, $statuses, true)) $status = '';
if (!validDate($dateFrom) || !validDate($dateTo)) $error = 'Please enter a valid date.';
elseif ($dateFrom && $dateTo && $dateFrom > $dateTo) $error = 'Date From cannot be later than Date To.';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    $where = [];
    $params = [];
    $types = '';

    if ($search) {
        $where[] = '(t.transactionID LIKE ? OR a.assetName LIKE ? OR u.name LIKE ? OR t.requesterID LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like, $like);
        $types .= 'ssss';
    }
    if ($status) {
        $where[] = 't.requestStatus=?';
        $params[] = $status; $types .= 's';
    }
    if ($dateFrom) {
        $where[] = 'DATE(t.requestDate)>=?';
        $params[] = $dateFrom; $types .= 's';
    }
    if ($dateTo) {
        $where[] = 'DATE(t.requestDate)<=?';
        $params[] = $dateTo; $types .= 's';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if (!$error) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) total
            FROM transactionrequests t
            JOIN assets a ON t.assetID=a.assetID
            JOIN user u ON t.requesterID=u.userID
            $whereSQL
        ");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $listParams = [...$params, $perPage, $offset];

        $stmt = $conn->prepare("
            SELECT t.transactionID,t.requestDate,t.requestStatus,
                   a.assetID,a.assetName,
                   u.userID requesterID,u.name requesterName,
                   (SELECT MAX(b.expectedReturnDate)
                    FROM borrowings b
                    WHERE b.transactionID=t.transactionID) expectedReturnDate
            FROM transactionrequests t
            JOIN assets a ON t.assetID=a.assetID
            JOIN user u ON t.requesterID=u.userID
            $whereSQL
            ORDER BY t.requestDate DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types . 'ii', ...$listParams);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
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
        <p>Review and manage Community Member borrowing requests and transaction progress.</p>
    </div>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Transaction updated successfully.</div><?php endif; ?>

<!-- Filters -->
<section class="management-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Transaction</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" value="<?= e($search) ?>"
                   placeholder="Transaction ID, asset or requester">
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

    <div class="management-filter-group">
        <label for="dateFrom">Date From</label>
        <input type="date" id="dateFrom" name="dateFrom" value="<?= e($dateFrom) ?>">
    </div>

    <div class="management-filter-group">
        <label for="dateTo">Date To</label>
        <input type="date" id="dateTo" name="dateTo" value="<?= e($dateTo) ?>">
    </div>

    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn">
            <img src="../assets/icons/filter.png" alt=""> Filter
        </button>
        <a href="manager_transactions.php" class="management-reset-btn">
            <img src="../assets/icons/reset.png" alt=""> Reset
        </a>
    </div>
</form>
</section>

<!-- Transaction List -->
<section class="management-list">
    <div class="management-list-header">
        <h2>Transaction Requests</h2>
        <p><?= $total ?> transaction<?= $total === 1 ? '' : 's' ?> found</p>
    </div>

    <div class="management-table-wrapper">
    <table class="management-table">
        <thead>
        <tr>
            <th>#</th>
            <th>Transaction ID</th>
            <th>Asset Name</th>
            <th>Requester</th>
            <th>Status</th>
            <th>Request Date</th>
            <th>Expected Return Date</th>
            <th>Action</th>
        </tr>
        </thead>

        <tbody>
        <?php if (!$transactions): ?>
            <tr><td colspan="8" class="management-empty-state">No transaction requests found.</td></tr>
        <?php else: foreach ($transactions as $i => $t): ?>
            <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td><strong><?= e($t['transactionID']) ?></strong></td>
                <td><?= e($t['assetName']) ?></td>
                <td><?= e($t['requesterName'] . ' (' . $t['requesterID'] . ')') ?></td>
                <td><span class="status-badge"><?= e($t['requestStatus']) ?></span></td>
                <td><?= e(showDate($t['requestDate'])) ?></td>
                <td><?= e(showDate($t['expectedReturnDate'])) ?></td>
                <td>
                    <div class="management-actions">
                        <a href="manager_transaction_view.php?transactionID=<?= e($t['transactionID']) ?>"
                           class="management-action-btn"
                           title="<?= $t['requestStatus'] === 'Pending' ? 'Review Transaction Request' : 'View Transaction Details' ?>">
                            <img src="../assets/icons/view.png" alt="View">
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <div class="management-table-footer">
        <p>Showing <?= $start ?> - <?= $end ?> of <?= $total ?> transactions</p>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Transaction pagination">
            <?php if ($page > 1): ?><a href="<?= e(pageURL($page - 1)) ?>">Previous</a><?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= e(pageURL($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

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
