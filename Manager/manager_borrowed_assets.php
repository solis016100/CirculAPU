<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

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
    $c=new mysqli('127.0.0.1','root','','circulapu_db',3306);$c->set_charset('utf8mb4');

    $where=["b.actualReturnDate IS NULL","b.borrowingStatus IN ('Borrowed','Active','Overdue')"];
    $params=[];$types='';

    if($search){
        $where[]="(a.assetName LIKE ? OR a.assetID LIKE ? OR u.name LIKE ? OR u.userID LIKE ? OR t.transactionID LIKE ?)";
        $like="%$search%";array_push($params,$like,$like,$like,$like,$like);$types.='sssss';
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
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();$c->close();
}catch(Throwable $z){
    error_log($z->getMessage());$error='Unable to load borrowed assets. Please try again.';
}

$start=$total?$offset+1:0;$end=min($offset+$perPage,$total);
$nextReturn=$rows[0]['transactionID']??'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Returns - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css">
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

<section class="management-filter">
<form method="GET">
<div class="management-filter-group search">
<label for="search">Search Borrowing</label>
<div class="management-search-input"><img src="../assets/icons/search.png" alt="">
<input id="search" name="search" value="<?=e($search)?>" placeholder="Asset, borrower or transaction ID"></div>
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

<section class="management-list">
<div class="management-list-header">
<h2><?= $status==='Overdue'?'Overdue Returns':'Borrowed Assets' ?></h2>
<p><?= $total ?> active borrowing<?= $total===1?'':'s' ?> found</p>
</div>
<div class="management-table-wrapper">
<table class="management-table">
<thead><tr><th>#</th><th>Asset</th><th>Borrower</th><th>Pickup Date</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php if(!$rows):?>
<tr><td colspan="7" class="management-empty-state">No active borrowings found.</td></tr>
<?php else:foreach($rows as $i=>$r):$display=$r['isOverdue']?'Overdue':'Borrowed';?>
<tr>
<td><?=$offset+$i+1?></td>
<td><strong><?=e($r['assetName'])?></strong><br><small><?=e($r['assetID'])?></small></td>
<td><?=e($r['borrowerName'].' ('.$r['borrowerID'].')')?></td>
<td><?=e(d($r['borrowDate']))?></td>
<td><?=e(d($r['expectedReturnDate']))?></td>
<td><span class="status-badge <?=$r['isOverdue']?'alert-error':''?>"><?=e($display)?></span></td>
<td>
<a href="manager_borrowed_asset_view.php?transactionID=<?=e($r['transactionID'])?>" class="management-action-btn" title="View Borrowing">
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
<dialog id="nextReturnDialog" class="flow-dialog">
<h2>Return Completed Successfully</h2>
<p>The borrowing transaction has been completed and the asset status has been updated.</p>

<?php if($nextReturn):?>
<p style="margin-top:12px">There are <strong><?=$total?></strong> active borrowing<?=$total===1?'':'s'?> remaining. Handle another return?</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-back" onclick="nextReturnDialog.close()">Done</button>
<button type="button" class="category-form-submit"
onclick="location.href='manager_borrowed_asset_view.php?transactionID=<?=e($nextReturn)?>'">Handle Another</button>
</div>
<?php else:?>
<p style="margin-top:12px">There are no more active borrowings to process.</p>
<div class="flow-dialog-actions">
<button type="button" class="category-form-submit" onclick="nextReturnDialog.close()">Done</button>
</div>
<?php endif;?>
</dialog>
<script>nextReturnDialog.showModal()</script>
<?php endif;?>

</main></div></div>
</body></html>
