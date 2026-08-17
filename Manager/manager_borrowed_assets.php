<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function d($v): string { return $v && strtotime($v) ? date('d M Y', strtotime($v)) : '-'; }
function pageURL($p): string {
    $q=$_GET; $q['page']=$p;
    return 'manager_borrowed_assets.php?'.http_build_query($q);
}

$search=trim($_GET['search']??'');
$status=trim($_GET['status']??'');
if (!in_array($status,['','Borrowed','Overdue'],true)) $status='';

$rows=[];$error='';$perPage=8;$page=max(1,(int)($_GET['page']??1));
$total=$offset=0;$totalPages=1;

try{
    $c=$conn;$c->set_charset('utf8mb4');

    $where=["b.actualReturnDate IS NULL","b.borrowingStatus IN ('Borrowed','Active','Overdue')"];
    $params=[];$types='';

    if($search){
        $where[]="(b.borrowID LIKE ? OR a.assetName LIKE ? OR a.assetID LIKE ? OR u.name LIKE ? OR u.userID LIKE ? OR t.transactionID LIKE ?)";
        $like="%$search%";array_push($params,$like,$like,$like,$like,$like,$like);$types.='ssssss';
    }
    if($status==='Overdue'){
        $where[]="DATE(b.expectedReturnDate)<CURDATE()";
    }elseif($status==='Borrowed'){
        $where[]="(DATE(b.expectedReturnDate)>=CURDATE() OR b.expectedReturnDate IS NULL)";
    }
    $whereSQL='WHERE '.implode(' AND ',$where);

    $s=$c->prepare("SELECT COUNT(*) total
        FROM borrowings b
        JOIN transactionrequests t ON t.transactionID=b.transactionID
        JOIN assets a ON a.assetID=b.assetID
        JOIN user u ON u.userID=b.borrowerID
        $whereSQL");
    if($params)$s->bind_param($types,...$params);
    $s->execute();$total=(int)$s->get_result()->fetch_assoc()['total'];$s->close();

    $totalPages=max(1,(int)ceil($total/$perPage));$page=min($page,$totalPages);$offset=($page-1)*$perPage;

    $s=$c->prepare("SELECT b.borrowID,b.transactionID,b.borrowDate,b.expectedReturnDate,b.borrowingStatus,
            a.assetID,a.assetName,a.assetCondition,
            u.userID borrowerID,u.name borrowerName,
            (DATE(b.expectedReturnDate)<CURDATE()) isOverdue
        FROM borrowings b
        JOIN transactionrequests t ON t.transactionID=b.transactionID
        JOIN assets a ON a.assetID=b.assetID
        JOIN user u ON u.userID=b.borrowerID
        $whereSQL
        ORDER BY isOverdue DESC,b.expectedReturnDate ASC
        LIMIT ? OFFSET ?");
    $list=[...$params,$perPage,$offset];
    $s->bind_param($types.'ii',...$list);$s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}catch(Throwable $z){
    error_log($z->getMessage());$error='Unable to load borrowed assets. Please try again.';
}

$start=$total?$offset+1:0;$end=min($offset+$perPage,$total);
$back=http_build_query(array_filter(['search'=>$search,'status'=>$status,'page'=>$page],fn($v)=>$v!==''&&$v!==1));
$backParam=$back?'&back='.rawurlencode($back):'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Returns - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css?v=<?=filemtime(__DIR__.'/manager_style.css')?>"><link rel="stylesheet" href="manager_mobile.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><span class="breadcrumb-current">Manage Returns</span></div>
<h1>Manage Returns</h1>
<p>View assets currently with borrowers, identify overdue returns and process returned assets.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>


<section class="mobile-borrowing-ui">
<details class="mobile-filter-card" <?=($search||$status)?'open':''?>>
<summary><span>Filter Borrowings</span><?php if($search||$status):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<label><span>Search</span><input name="search" value="<?=e($search)?>" placeholder="Borrow ID, asset, borrower or transaction"></label>
<label><span>Return Status</span><select name="status">
<option value="">All Active Borrowings</option>
<option value="Borrowed" <?=$status==='Borrowed'?'selected':''?>>Borrowed</option>
<option value="Overdue" <?=$status==='Overdue'?'selected':''?>>Overdue</option>
</select></label>
<div class="mobile-filter-actions">
<a href="manager_borrowed_assets.php">Reset</a>
<button>Apply Filters</button>
</div>
</form>
</details>

<div class="mobile-section-heading">
<div>
<h2><?=$status==='Overdue'?'Overdue Returns':'Borrowed Assets'?></h2>
<p><?=$total?> active borrowing<?=$total===1?'':'s'?></p>
</div>
<span class="mobile-page-count"><?=$page?> / <?=$totalPages?></span>
</div>

<?php if(!$rows):?>
<div class="mobile-empty-card">No active borrowings found.</div>
<?php else:foreach($rows as $r):$display=$r['isOverdue']?'Overdue':'Borrowed';?>
<article class="mobile-borrowing-card">
<div class="mobile-borrowing-top">
<div>
<span class="mobile-record-id"><?=e($r['borrowID'])?></span>
<h3><?=e($r['assetName'])?></h3>
<p><?=e($r['assetID'])?> · <?=e($r['borrowerName'])?> (<?=e($r['borrowerID'])?>)</p>
</div>
<span class="mobile-status-pill <?=$r['isOverdue']?'mobile-overdue':''?>" data-status="<?=e($display)?>"><?=e($display)?></span>
</div>

<div class="mobile-borrowing-meta">
<div><span>Pickup Date</span><strong><?=e(d($r['borrowDate']))?></strong></div>
<div><span>Due Date</span><strong><?=e(d($r['expectedReturnDate']))?></strong></div>
</div>

<div class="mobile-card-actions">
<a class="primary" href="manager_borrowed_asset_view.php?transactionID=<?=e($r['transactionID'])?><?=$backParam?>">Manage Return</a>
</div>
</article>
<?php endforeach;endif;?>

<?php if($totalPages>1):?>
<nav class="mobile-pagination" aria-label="Borrowing pagination">
<?php if($page>1):?><a href="<?=e(pageURL($page-1))?>">‹</a><?php endif;?>
<?php for($p=1;$p<=$totalPages;$p++):?><a href="<?=e(pageURL($p))?>" class="<?=$p===$page?'active':''?>"><?=$p?></a><?php endfor;?>
<?php if($page<$totalPages):?><a href="<?=e(pageURL($page+1))?>">›</a><?php endif;?>
</nav>
<?php endif;?>
</section>

<section class="management-filter desktop-borrowing-filter">
<form method="GET">
<div class="management-filter-group search">
<label for="search">Search Borrowing</label>
<div class="management-search-input"><img src="../assets/icons/search.png" alt="">
<input id="search" name="search" value="<?=e($search)?>" placeholder="Borrow ID, asset, borrower or transaction ID"></div>
</div>
<div class="management-filter-group">
<label for="status">Return Status</label>
<select id="status" name="status">
<option value="">All Active Borrowings</option>
<option value="Borrowed" <?=$status==='Borrowed'?'selected':''?>>Borrowed</option>
<option value="Overdue" <?=$status==='Overdue'?'selected':''?>>Overdue</option>
</select>
</div>
<div class="management-filter-buttons">
<button class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
<a href="manager_borrowed_assets.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
</div>
</form>
</section>

<section class="management-list desktop-borrowing-list">
<div class="management-list-header">
<h2><?= $status==='Overdue'?'Overdue Returns':'Borrowed Assets' ?></h2>
<p><?= $total ?> active borrowing<?= $total===1?'':'s' ?> found</p>
</div>
<div class="management-table-wrapper">
<table class="management-table">
<thead><tr><th>#</th><th>Borrow ID</th><th>Asset</th><th>Borrower</th><th>Pickup Date</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php if(!$rows):?>
<tr><td colspan="8" class="management-empty-state">No active borrowings found.</td></tr>
<?php else:foreach($rows as $i=>$r):$display=$r['isOverdue']?'Overdue':'Borrowed';?>
<tr>
<td><?=$offset+$i+1?></td>
<td><strong><?=e($r['borrowID'])?></strong></td>
<td><strong><?=e($r['assetName'])?></strong><br><small><?=e($r['assetID'])?></small></td>
<td><?=e($r['borrowerName'].' ('.$r['borrowerID'].')')?></td>
<td><?=e(d($r['borrowDate']))?></td>
<td><?=e(d($r['expectedReturnDate']))?></td>
<td><span class="status-badge" data-status="<?=e($display)?>"><?=e($display)?></span></td>
<td>
<a href="manager_borrowed_asset_view.php?transactionID=<?=e($r['transactionID'])?><?=$backParam?>" class="management-action-btn" title="View Borrowing">
<img src="../assets/icons/view.png" alt="View">
</a>
</td>
</tr>
<?php endforeach;endif;?>
</tbody>
</table>
</div>

<div class="management-table-footer">
<p>Showing <?=$start?> - <?=$end?> of <?=$total?> borrowings</p>
<?php if($totalPages>1):?><nav class="pagination">
<?php if($page>1):?><a href="<?=e(pageURL($page-1))?>">Previous</a><?php endif;?>
<?php for($p=1;$p<=$totalPages;$p++):?><a href="<?=e(pageURL($p))?>" class="<?=$p===$page?'active':''?>"><?=$p?></a><?php endfor;?>
<?php if($page<$totalPages):?><a href="<?=e(pageURL($page+1))?>">Next</a><?php endif;?>
</nav><?php endif;?>
</div>
</section>

<?php if(($_GET['returnDone']??'')==='1'):?>
<dialog id="returnDoneDialog" class="flow-dialog">
<div class="alert alert-success" style="margin:0">
    <strong>Return Completed Successfully</strong><br>
    The borrowing transaction has been completed and the asset status has been updated.
</div>

<div class="flow-dialog-actions">
<button type="button" class="category-form-submit" onclick="returnDoneDialog.close()">Done</button>
</div>
</dialog>
<script>returnDoneDialog.showModal();</script>
<?php endif;?>

</main>
<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span></a>
<a href="manager_assets.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span></a>
<a href="manager_categories.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span></a>
<a href="manager_transactions.php" class="active" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span></a>
<a href="manager_reports.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span></a>
</nav>
</div></div>
</body></html>
