<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function validDate($v): bool {
    if (!$v) return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function showDate($v): string { return $v && strtotime($v) ? date('d M Y', strtotime($v)) : '-'; }
function pickupPriority($pickup,string $requestStatus): array {
    if($requestStatus!=='Pending'||!$pickup||!strtotime($pickup))return ['', ''];
    $date=date('Y-m-d',strtotime($pickup));
    $today=date('Y-m-d');
    $tomorrow=date('Y-m-d',strtotime('+1 day'));
    if($date===$today)return ['URGENT — Pickup Today','urgent'];
    if($date===$tomorrow)return ['Pickup Tomorrow','tomorrow'];
    if($date<$today)return ['Pickup Date Passed','passed'];
    return ['', ''];
}
function pageURL($p): string {
    $q = $_GET; $q['page'] = $p;
    return 'manager_transactions.php?' . http_build_query($q);
}
function viewAllURL(bool $viewAll): string {
    $q=$_GET; unset($q['page']);
    if($viewAll) unset($q['viewAll']); else $q['viewAll']='1';
    $query=http_build_query($q);
    return 'manager_transactions.php'.($query?'?'.$query:'');
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['requestStatus'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');
$activeOnly = ($_GET['active'] ?? '') === '1';
$activeTransaction = ($_GET['activeTransaction'] ?? '') === '1';
$pickupOnly = ($_GET['pickup'] ?? '') === '1';
$viewAll = ($_GET['viewAll'] ?? '') === '1';

$statuses = ['Pending','Approved','In Progress','Overdue','Completed','Cancelled','Rejected'];
$transactions=[];$nextPending=$nextPickup='';
$stats=['pending'=>0,'urgent'=>0,'pickup'=>0,'active'=>0,'overdue'=>0,'completed'=>0];
$error='';
$perPage = $viewAll ? 100000 : 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $offset = 0; $totalPages = 1;

if ($status && !in_array($status, $statuses, true)) $status = '';
if (!validDate($dateFrom) || !validDate($dateTo)) $error = 'Please enter a valid date.';
elseif ($dateFrom && $dateTo && $dateFrom > $dateTo) $error = 'Date From cannot be later than Date To.';

try {
    $conn->set_charset('utf8mb4');

    $row = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM transactionrequests WHERE requestStatus='Pending') pending,
            (SELECT COUNT(DISTINCT t.transactionID) FROM transactionrequests t
             JOIN borrowings b ON b.transactionID=t.transactionID
             WHERE t.requestStatus='Pending' AND DATE(b.borrowDate)=CURDATE()) urgent,
            (SELECT COUNT(*) FROM transactionrequests t
             JOIN borrowings b ON b.transactionID=t.transactionID
             JOIN assets a ON a.assetID=t.assetID
             WHERE t.requestStatus='Approved'
             AND b.actualReturnDate IS NULL
             AND b.borrowingStatus='Approved'
             AND DATE(b.borrowDate)>=CURDATE()
             AND a.availabilityStatus<>'Unavailable'
) pickup,
            (SELECT COUNT(*) FROM borrowings
             WHERE actualReturnDate IS NULL AND borrowingStatus IN ('Borrowed','Active','Overdue')) active,
            (SELECT COUNT(*) FROM borrowings
             WHERE DATE(expectedReturnDate)<CURDATE() AND actualReturnDate IS NULL
             AND borrowingStatus IN ('Borrowed','Active','Overdue')) overdue,
            (SELECT COUNT(*) FROM transactionrequests WHERE requestStatus='Completed') completed,
            (SELECT t.transactionID
             FROM transactionrequests t
             LEFT JOIN borrowings b ON b.transactionID=t.transactionID
             WHERE t.requestStatus='Pending'
             ORDER BY CASE
                 WHEN DATE(b.borrowDate)=CURDATE() THEN 0
                 WHEN DATE(b.borrowDate)=DATE_ADD(CURDATE(),INTERVAL 1 DAY) THEN 1
                 WHEN DATE(b.borrowDate)>CURDATE() THEN 2
                 ELSE 3
             END,
             CASE WHEN DATE(b.borrowDate)>=CURDATE() THEN DATE(b.borrowDate) END ASC,
             t.requestDate ASC
             LIMIT 1) nextPending,
            (SELECT t.transactionID
             FROM transactionrequests t
             JOIN borrowings b ON b.transactionID=t.transactionID
             JOIN assets a ON a.assetID=t.assetID
             WHERE t.requestStatus='Approved'
             AND b.actualReturnDate IS NULL
             AND b.borrowingStatus='Approved'
             AND DATE(b.borrowDate)>=CURDATE()
             AND a.availabilityStatus<>'Unavailable'

             ORDER BY DATE(b.borrowDate) ASC,t.requestDate ASC
             LIMIT 1) nextPickup
    ")->fetch_assoc();
    foreach ($stats as $k => $v) $stats[$k]=(int)($row[$k]??0);
    $nextPending=$row['nextPending']??'';
    $nextPickup=$row['nextPickup']??'';

    $where = []; $params = []; $types = '';
    if ($search) {
        $where[] = "(t.transactionID LIKE ? OR a.assetName LIKE ? OR u.name LIKE ? OR t.requesterID LIKE ?)";
        $like = "%$search%"; array_push($params, $like, $like, $like, $like); $types .= 'ssss';
    }

    $overdueSQL = "EXISTS(
        SELECT 1 FROM borrowings b
        WHERE b.transactionID=t.transactionID
        AND DATE(b.expectedReturnDate)<CURDATE()
        AND b.actualReturnDate IS NULL
        AND b.borrowingStatus IN ('Borrowed','Active','Overdue')
    )";

    if ($status === 'Overdue') {
        $where[] = $overdueSQL;
    } elseif ($status === 'In Progress') {
        $where[] = "t.requestStatus='In Progress' AND NOT $overdueSQL";
    } elseif ($status) {
        $where[] = 't.requestStatus=?'; $params[] = $status; $types .= 's';
    }

    $dateField=$pickupOnly?'(SELECT MAX(DATE(bd.borrowDate)) FROM borrowings bd WHERE bd.transactionID=t.transactionID)':'DATE(t.requestDate)';
    if ($dateFrom) { $where[] = "$dateField>=?"; $params[] = $dateFrom; $types .= 's'; }
    if ($dateTo) { $where[] = "$dateField<=?"; $params[] = $dateTo; $types .= 's'; }

    if ($activeOnly) {
        $where[] = "EXISTS(
            SELECT 1 FROM borrowings b
            WHERE b.transactionID=t.transactionID
            AND b.actualReturnDate IS NULL
            AND b.borrowingStatus IN ('Borrowed','Active','Overdue')
        )";
    }

    if ($activeTransaction) $where[] = "t.requestStatus IN ('Approved','In Progress','Overdue')";
    if ($pickupOnly) $where[] = "t.requestStatus='Approved' AND a.availabilityStatus<>'Unavailable' AND EXISTS(
        SELECT 1 FROM borrowings b WHERE b.transactionID=t.transactionID
        AND b.actualReturnDate IS NULL AND b.borrowingStatus='Approved'
        AND DATE(b.borrowDate)>=CURDATE()
    )";

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if (!$error) {
        $stmt = $conn->prepare("SELECT COUNT(*) total
            FROM transactionrequests t
            JOIN assets a ON t.assetID=a.assetID
            JOIN user u ON t.requesterID=u.userID
            $whereSQL");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;

        $order=$pickupOnly?'pickupDate ASC,t.requestDate ASC':($status==='Pending'?"CASE WHEN DATE(pickupDate)=CURDATE() THEN 0 WHEN DATE(pickupDate)=DATE_ADD(CURDATE(),
                                                            INTERVAL 1 DAY) THEN 1 WHEN DATE(pickupDate)>CURDATE() THEN 2 ELSE 3 END,
                                                            CASE WHEN DATE(pickupDate)>=CURDATE() THEN DATE(pickupDate) END ASC, t.requestDate ASC":'t.requestDate DESC');
        $stmt = $conn->prepare("
            SELECT t.transactionID,t.requestDate,t.requestStatus,a.assetName,
                   u.userID requesterID,u.name requesterName,
                   (SELECT MAX(b.borrowDate) FROM borrowings b WHERE b.transactionID=t.transactionID) pickupDate,
                   (SELECT MAX(b.expectedReturnDate) FROM borrowings b WHERE b.transactionID=t.transactionID) dueDate,
                   $overdueSQL isOverdue
            FROM transactionrequests t
            JOIN assets a ON t.assetID=a.assetID
            JOIN user u ON t.requesterID=u.userID
            $whereSQL
            ORDER BY $order LIMIT ? OFFSET ?
        ");
        $list = [...$params, $perPage, $offset];
        $stmt->bind_param($types . 'ii', ...$list);
        $stmt->execute(); $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }

} catch (Throwable $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load transaction records. Please try again.';
}

$start = $total ? $offset + 1 : 0;
$end = min($offset + $perPage, $total);
$listTitle=$pickupOnly?'Pickup Reservations':($status==='Pending'?'Pending Requests':'Transaction Requests');
$resetURL=$pickupOnly?'manager_transactions.php?pickup=1':($status==='Pending'?'manager_transactions.php?requestStatus=Pending':'manager_transactions.php');
$back=http_build_query(array_filter([
    'search'=>$search,'requestStatus'=>$status,'dateFrom'=>$dateFrom,'dateTo'=>$dateTo,
    'active'=>$activeOnly?'1':'','activeTransaction'=>$activeTransaction?'1':'',
    'pickup'=>$pickupOnly?'1':'','viewAll'=>$viewAll?'1':'','page'=>$page
],fn($v)=>$v!==''&&$v!==1));
$backParam=$back?'&back='.rawurlencode($back):'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Transaction Management - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css"><link rel="stylesheet" href="manager_mobile.css">
<style>
.transaction-list-meta{display:flex;align-items:center;gap:10px}
.transaction-list-meta a{color:var(--primary-color);font-size:13px;font-weight:600;text-decoration:none}
.transaction-list-meta a:hover{text-decoration:underline}
.transaction-heading-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.queue-action-link{display:inline-flex;padding:6px 10px;border-radius:6px;background:var(--primary-color);color:#fff;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
.queue-action-link:hover{background:var(--primary-hover)}
.request-priority{display:inline-flex;margin-top:5px;padding:4px 7px;border-radius:999px;font-size:10px;font-weight:800;line-height:1.1;white-space:nowrap}
.request-priority.urgent{background:#fee2e2;color:#b91c1c}
.request-priority.tomorrow{background:#ffedd5;color:#c2410c}
.request-priority.passed{background:#fef3c7;color:#92400e}
@media(max-width:650px){.transaction-heading-actions{justify-content:stretch}.transaction-heading-actions .management-create-btn{flex:1}}
</style>
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?><div class="main-content"><?php include '../header.php'; ?>
<main class="page-body">

<section class="management-heading management-heading-action">
<div class="management-heading-text">
<h1>Transaction Management</h1>
<p>Review borrowing requests, active borrowings, overdue returns and completed transactions.</p>
</div>
<?php if($nextPending||$nextPickup):?>
<div class="transaction-heading-actions">
<?php if($nextPending):?>
<a href="manager_transaction_view.php?transactionID=<?=e($nextPending)?>&from=pending" class="management-create-btn">
<img src="../assets/icons/pendingVerification.png" alt="">
<?=$stats['urgent']>0?'Review Urgent Requests ('.$stats['urgent'].')':'Review Pending Requests ('.$stats['pending'].')'?>
</a>
<?php endif;?>
<?php if($nextPickup):?>
<a href="manager_transactions.php?pickup=1" class="management-create-btn">
<img src="../assets/icons/borrowed-assets.png" alt="">
Pickup Reservations (<?=$stats['pickup']?>)
</a>
<?php endif;?>
</div>
<?php endif;?>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Transaction updated successfully.</div><?php endif; ?>

<section class="stats-grid">
<a href="manager_transactions.php?requestStatus=Pending" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/pendingVerification.png" alt=""></div>
<div class="stat-content"><h3>Pending Requests</h3><strong class="stat-value"><?= $stats['pending'] ?></strong><p class="stat-description">Awaiting manager review</p></div>
</a>

<a href="manager_borrowed_assets.php" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/active-transactions.png" alt=""></div>
<div class="stat-content"><h3>Active Borrowings</h3><strong class="stat-value"><?= $stats['active'] ?></strong><p class="stat-description">Currently borrowed assets</p></div>
</a>

<a href="manager_borrowed_assets.php?status=Overdue" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/notification.png" alt=""></div>
<div class="stat-content"><h3>Overdue Returns</h3><strong class="stat-value"><?= $stats['overdue'] ?></strong><p class="stat-description">Past due and not returned</p></div>
</a>

<a href="manager_transactions.php?requestStatus=Completed" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/mark_as_read.png" alt=""></div>
<div class="stat-content"><h3>Completed</h3><strong class="stat-value"><?= $stats['completed'] ?></strong><p class="stat-description">Finished transactions</p></div>
</a>
</section>

<section class="mobile-transaction-ui">
<details class="mobile-filter-card" <?=($search||$status||$dateFrom||$dateTo||$pickupOnly)?'open':''?>>
<summary><span>Filter Transactions</span><?php if($search||$status||$dateFrom||$dateTo||$pickupOnly):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<label><span>Search</span><input name="search" placeholder="ID, asset or requester" value="<?=e($search)?>"></label>
<?php if(!$pickupOnly):?>
<div class="mobile-filter-grid">
<label><span>Status</span><select name="requestStatus">
<option value="">All</option>
<?php foreach($statuses as $v):?><option value="<?=e($v)?>" <?=$status===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?>
</select></label>
<label><span>Date From</span><input type="date" name="dateFrom" value="<?=e($dateFrom)?>"></label>
</div>
<?php else:?><label><span>Pickup From</span><input type="date" name="dateFrom" value="<?=e($dateFrom)?>"></label><?php endif;?>
<label><span><?=$pickupOnly?'Pickup To':'Date To'?></span><input type="date" name="dateTo" value="<?=e($dateTo)?>"></label>
<?php if($activeOnly):?><input type="hidden" name="active" value="1"><?php endif;?>
<?php if($activeTransaction):?><input type="hidden" name="activeTransaction" value="1"><?php endif;?>
<?php if($pickupOnly):?><input type="hidden" name="pickup" value="1"><?php endif;?>
<div class="mobile-filter-actions"><a href="<?=e($resetURL)?>">Reset</a><button>Apply Filters</button></div>
</form>
</details>

<div class="mobile-section-heading">
<div><h2><?=e($listTitle)?></h2><p><?=$total?> transaction<?=$total===1?'':'s'?></p></div>
<span class="mobile-page-count"><?=$page?> / <?=$totalPages?></span>
</div>

<?php if(!$transactions):?>
<div class="mobile-empty-card">No transaction requests found.</div>
<?php else:foreach($transactions as $t):
$pickupDay=$t['pickupDate']&&strtotime($t['pickupDate'])?date('Y-m-d',strtotime($t['pickupDate'])):'';
$displayStatus=$pickupOnly?($pickupDay===date('Y-m-d')?'Awaiting Pickup':'Upcoming Pickup'):($t['isOverdue']?'Overdue':$t['requestStatus']);
[$priorityLabel,$priorityClass]=pickupPriority($t['pickupDate'],$t['requestStatus']);?>
<article class="mobile-transaction-card">
<div class="mobile-transaction-top">
<div>
<span class="mobile-record-id"><?=e($t['transactionID'])?></span>
<h3><?=e($t['assetName'])?></h3>
<p><?=e($t['requesterName'])?> · <?=e($t['requesterID'])?></p>
</div>
<div class="mobile-transaction-status-stack">
<span class="mobile-status-pill <?=$t['isOverdue']?'mobile-overdue':''?>" data-status="<?=e($displayStatus)?>"><?=e($displayStatus)?></span>
<?php if($priorityLabel):?><span class="request-priority <?=e($priorityClass)?>"><?=e($priorityLabel)?></span><?php endif;?>
</div>
</div>

<div class="mobile-transaction-meta">
<div><span><?=$pickupOnly||$status==='Pending'?'Pickup':'Requested'?></span><strong><?=e(showDate(($pickupOnly||$status==='Pending')?$t['pickupDate']:$t['requestDate']))?></strong></div>
<div><span>Expected Return</span><strong><?=e(showDate($t['dueDate']))?></strong></div>
</div>

<div class="mobile-card-actions">
<a class="primary" href="manager_transaction_view.php?transactionID=<?=e($t['transactionID'])?><?=$pickupOnly?'&from=pickup':($status?'&from='.rawurlencode($status):'')?><?=$backParam?>">
<?=$pickupOnly?($pickupDay===date('Y-m-d')?'Confirm Pickup':'View Reservation'):($t['requestStatus']==='Pending'?'Review Request':'View Details')?>
</a>
</div>
</article>
<?php endforeach;endif;?>

<?php if(!$viewAll&&$totalPages>1):?>
<nav class="mobile-pagination" aria-label="Transaction pagination">
<?php if($page>1):?><a href="<?=e(pageURL($page-1))?>">‹</a><?php endif;?>
<?php for($p=1;$p<=$totalPages;$p++):?><a href="<?=e(pageURL($p))?>" class="<?=$p===$page?'active':''?>"><?=$p?></a><?php endfor;?>
<?php if($page<$totalPages):?><a href="<?=e(pageURL($page+1))?>">›</a><?php endif;?>
</nav>
<?php endif;?>
</section>

<section class="management-filter desktop-transaction-filter">
<form method="GET">
<?php if($pickupOnly):?><input type="hidden" name="pickup" value="1"><?php endif;?>
<div class="management-filter-group search">
<label for="search">Search Transaction</label>
<div class="management-search-input"><img src="../assets/icons/search.png" alt="">
<input id="search" name="search" value="<?= e($search) ?>" placeholder="Transaction ID, asset or requester"></div>
</div>

<?php if(!$pickupOnly):?>
<div class="management-filter-group">
<label for="requestStatus">Status</label>
<select id="requestStatus" name="requestStatus">
<option value="">All Statuses</option>
<?php foreach ($statuses as $v): ?>
<option value="<?= e($v) ?>" <?= $status === $v ? 'selected' : '' ?>><?= e($v) ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif;?>
<div class="management-filter-group"><label for="dateFrom"><?=$pickupOnly?'Pickup From':'Date From'?></label><input type="date" id="dateFrom" name="dateFrom" value="<?= e($dateFrom) ?>"></div>
<div class="management-filter-group"><label for="dateTo"><?=$pickupOnly?'Pickup To':'Date To'?></label><input type="date" id="dateTo" name="dateTo" value="<?= e($dateTo) ?>"></div>

<div class="management-filter-buttons">
<button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
<a href="<?=e($resetURL)?>" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
</div>
</form>
</section>

<section class="management-list desktop-transaction-list">
<div class="management-list-header">
<h2><?=e($listTitle)?></h2>
<div class="transaction-list-meta">
<span><?= $total ?> transaction<?= $total===1?'':'s' ?></span>
<a href="<?=e(viewAllURL($viewAll))?>"><?=$viewAll?'Paginated View':'View All'?></a>
</div>
</div>
<div class="management-table-wrapper">
<table class="management-table">
<thead><tr><th>#</th><th>Transaction ID</th><th>Asset Name</th><th>Requester</th><th>Status</th><th><?=$pickupOnly||$status==='Pending'?'Pickup Date':'Request Date'?></th><th>Expected Return</th><th>Action</th></tr></thead>
<tbody>
<?php if (!$transactions): ?>
<tr><td colspan="8" class="management-empty-state">No transaction requests found.</td></tr>
<?php else: foreach ($transactions as $i => $t): $pickupDay=$t['pickupDate']&&strtotime($t['pickupDate'])?date('Y-m-d',strtotime($t['pickupDate'])):''; $displayStatus=$pickupOnly?($pickupDay===date('Y-m-d')?'Awaiting Pickup':'Upcoming Pickup'):($t['isOverdue']?'Overdue':$t['requestStatus']); [$priorityLabel,$priorityClass]=pickupPriority($t['pickupDate'],$t['requestStatus']); ?>
<tr>
<td><?= $offset + $i + 1 ?></td>
<td><strong><?= e($t['transactionID']) ?></strong></td>
<td><?= e($t['assetName']) ?></td>
<td><?= e($t['requesterName'] . ' (' . $t['requesterID'] . ')') ?></td>
<td><span class="status-badge" data-status="<?=e($displayStatus)?>"><?=e($displayStatus)?></span><?php if($priorityLabel):?><br><span class="request-priority <?=e($priorityClass)?>"><?=e($priorityLabel)?></span><?php endif;?></td>
<td><?= e(showDate(($pickupOnly||$status==='Pending')?$t['pickupDate']:$t['requestDate'])) ?></td>
<td><?= e(showDate($t['dueDate'])) ?></td>
<td>
<?php if($pickupOnly||$status==='Pending'):?>
<a href="manager_transaction_view.php?transactionID=<?=e($t['transactionID'])?>&from=<?=rawurlencode($pickupOnly?'pickup':$status)?><?=$backParam?>" class="queue-action-link"><?=$pickupOnly?($pickupDay===date('Y-m-d')?'Confirm Pickup':'View Reservation'):'Review Request'?></a>
<?php else:?>
<a href="manager_transaction_view.php?transactionID=<?=e($t['transactionID'])?><?=$status?'&from='.rawurlencode($status):''?><?=$backParam?>" class="management-action-btn"
title="<?=$t['requestStatus']==='Pending'?'Review Transaction Request':'View Transaction Details'?>">
<img src="../assets/icons/view.png" alt="View"></a>
<?php endif;?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<div class="management-table-footer">
<p>Showing <?= $start ?> - <?= $end ?> of <?= $total ?> transactions</p>
<?php if (!$viewAll && $totalPages > 1): ?>
<nav class="pagination">
<?php if ($page > 1): ?><a href="<?= e(pageURL($page - 1)) ?>">Previous</a><?php endif; ?>
<?php for ($p=1; $p<=$totalPages; $p++): ?><a href="<?= e(pageURL($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a><?php endfor; ?>
<?php if ($page < $totalPages): ?><a href="<?= e(pageURL($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
</div>
</section>

<?php if(($pickupDone=trim($_GET['pickupDone']??''))!==''):?>
<dialog id="pickupDoneDialog" class="flow-dialog">
<h2>Pickup Confirmed Successfully</h2>
<p>The reservation has been converted into an active borrowing.</p>

<div class="flow-dialog-summary">
<div><strong>Transaction ID</strong><span><?=e($pickupDone)?></span></div>
<div><strong>Transaction Status</strong><span>In Progress</span></div>
<div><strong>Borrowing Status</strong><span>Borrowed</span></div>
<div><strong>Asset Availability</strong><span>Borrowed</span></div>
</div>

<?php if($nextPickup):?>
<p style="margin-top:12px">There <?=$stats['pickup']===1?'is':'are'?> still <strong><?=$stats['pickup']?></strong> pickup reservation<?=$stats['pickup']===1?'':'s'?> scheduled for today or a future date.</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="pickupDoneDialog.close()">Done</button>
<button type="button" class="category-form-submit"
onclick="location.href='manager_transaction_view.php?transactionID=<?=e($nextPickup)?>&from=pickup'">View Next Reservation</button>
</div>
<?php else:?>
<p style="margin-top:12px">There are no more pickup reservations scheduled for today or a future date.</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-submit" onclick="pickupDoneDialog.close()">Done</button>
</div>
<?php endif;?>
</dialog>
<script>pickupDoneDialog.showModal();</script>
<?php endif;?>

<?php if(in_array($_GET['reviewDone']??'',['approve','reject'],true)):?>
<dialog id="nextRequestDialog" class="flow-dialog">
<h2>Request Review Completed</h2>
<p><?=($_GET['reviewDone']==='approve')
    ?'The request was approved and the future reservation has been confirmed.'
    :'The borrowing request was rejected successfully.'?></p>

<?php if($nextPending):?>
<p style="margin-top:12px">There are <strong><?=$stats['pending']?></strong> pending request<?=$stats['pending']===1?'':'s'?> remaining. Review another?</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="nextRequestDialog.close()">Done</button>
<?php if($nextPickup):?>
<button type="button" class="category-form-back"
onclick="location.href='manager_transaction_view.php?transactionID=<?=e($nextPickup)?>&from=pickup'">View Pickup Reservations (<?=$stats['pickup']?>)</button>
<?php endif;?>
<button type="button" class="category-form-submit"
onclick="location.href='manager_transaction_view.php?transactionID=<?=e($nextPending)?>&from=pending'">Review Next Request</button>
</div>
<?php else:?>
<p style="margin-top:12px">There are no more pending requests to review.</p>
<?php if($nextPickup):?>
<p style="margin-top:12px"><strong><?=$stats['pickup']?></strong> approved pickup reservation<?=$stats['pickup']===1?' is':'s are'?> scheduled for today or a future date.</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="nextRequestDialog.close()">Done</button>
<button type="button" class="category-form-submit"
onclick="location.href='manager_transaction_view.php?transactionID=<?=e($nextPickup)?>&from=pickup'">View Pickup Reservations</button>
</div>
<?php else:?>
<div class="flow-dialog-actions">
<button type="button" class="category-form-submit" onclick="nextRequestDialog.close()">Done</button>
</div>
<?php endif;?>
<?php endif;?>
</dialog>
<script>nextRequestDialog.showModal()</script>
<?php endif;?>

</main>
</div></div>
</body></html>
