<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') { header('Location: ../login.php'); exit(); }

function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function d($v,bool $time=true): string { return $v&&strtotime($v)?date($time?'d M Y, h:i A':'d M Y',strtotime($v)):'-'; }
function nextID(mysqli $c,string $t,string $col,string $p): string {
    $n=(int)$c->query("SELECT COALESCE(MAX(CAST(SUBSTRING($col,4) AS UNSIGNED)),0)+1 n FROM $t")->fetch_assoc()['n'];
    return sprintf($p.'%03d',$n);
}
function logTx(mysqli $c,string $uid,string $action,string $ref,string $text): void {
    $id=nextID($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $s->bind_param('sssss',$id,$uid,$action,$ref,$text);$s->execute();$s->close();
}
function notify(mysqli $c,string $uid,string $title,string $text): void {
    $id=nextID($c,'notification','notificationID','NOT');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$id,$uid,$title,$text);$s->execute();$s->close();
}
function reserve(mysqli $c,array $t,string $manager,string $due): void {
    $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Approved',managerRemark=?,decisionDate=CURRENT_TIMESTAMP WHERE transactionID=?");
    $remark=trim($_POST['managerRemark']??'');$s->bind_param('ss',$remark,$t['transactionID']);$s->execute();$s->close();

    $bid=nextID($c,'borrowings','borrowID','BOR');$due.=' 23:59:59';
    $s=$c->prepare("INSERT INTO borrowings(borrowID,assetID,borrowerID,transactionID,expectedReturnDate,borrowingStatus) VALUES (?,?,?,?,?,'Approved')");
    $s->bind_param('sssss',$bid,$t['assetID'],$t['requesterID'],$t['transactionID'],$due);$s->execute();$s->close();

    $s=$c->prepare("UPDATE assets SET availabilityStatus='Reserved',updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
    $s->bind_param('s',$t['assetID']);$s->execute();$s->close();

    $hid=nextID($c,'assetstatushistory','historyID','ASH');$old=$t['availabilityStatus'];$new='Reserved';$reason="Approved borrow request {$t['transactionID']}";
    $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$hid,$t['assetID'],$manager,$old,$new,$reason);$s->execute();$s->close();
}
function go(string $id): never { header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id).'&updated=1');exit(); }

$manager=$_SESSION['user_id'];$id=trim($_GET['transactionID']??'');
if(!$id){header('Location: manager_transactions.php');exit();}
$t=null;$active=$overdue=$eco=[];$error='';

try{
    $c=new mysqli('127.0.0.1','root','','circulapu_db',3306);$c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT t.*,a.assetName,a.assetCondition,a.availabilityStatus,a.listingStatus,a.verificationStatus,
               u.name requesterName,u.email requesterEmail,u.ecopoint
        FROM transactionrequests t JOIN assets a ON t.assetID=a.assetID JOIN user u ON t.requesterID=u.userID
        WHERE t.transactionID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$t=$s->get_result()->fetch_assoc();$s->close();

    if(!$t)$error='The selected transaction could not be found.';
    elseif($t['requestStatus']!=='Pending'){header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id));exit();}
    else{
        $s=$c->prepare("
            SELECT b.borrowID,b.transactionID,b.borrowingStatus,b.expectedReturnDate,a.assetName,a.availabilityStatus
            FROM borrowings b JOIN assets a ON b.assetID=a.assetID
            WHERE b.borrowerID=? AND b.actualReturnDate IS NULL
              AND b.borrowingStatus NOT IN ('Completed','Cancelled','Returned')
            ORDER BY b.expectedReturnDate");
        $s->bind_param('s',$t['requesterID']);$s->execute();$active=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $overdue=array_values(array_filter($active,fn($x)=>
            $x['expectedReturnDate']&&strtotime($x['expectedReturnDate'])<time()&&
            ($x['availabilityStatus']==='Borrowed'||in_array($x['borrowingStatus'],['Borrowed','Active','Overdue'],true))
        ));

        $s=$c->prepare("SELECT points,transactionType,sourceType,referenceID,transactionDate FROM ecopointstransaction WHERE userID=? ORDER BY transactionDate DESC LIMIT 6");
        $s->bind_param('s',$t['requesterID']);$s->execute();$eco=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=$_POST['action']??'';$remark=trim($_POST['managerRemark']??'');$due=trim($_POST['expectedReturnDate']??'');
            $blocked=count($active)>=5||$overdue;

            if($action==='approve'){
                if($blocked)$error=$overdue?'Approval blocked: this member has an overdue unreturned asset.':'Approval blocked: this member already has 5 active borrowings.';
                elseif($t['listingStatus']!=='Published'||$t['verificationStatus']!=='Approved'||$t['availabilityStatus']!=='Available')$error='This asset is not currently eligible for borrowing.';
                elseif(!$due||!DateTime::createFromFormat('Y-m-d',$due)||$due<date('Y-m-d'))$error='Please select a valid expected return date.';
                else{
                    $c->begin_transaction();
                    try{
                        reserve($c,$t,$manager,$due);
                        logTx($c,$manager,'Approve',$id,"Approved borrowing request for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Borrowing Request Approved',"Your request $id for {$t['assetName']} was approved. Expected return: ".d($due,false).'.');
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='reject'){
                if(!$remark)$error='Please enter a rejection reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Rejected',managerRemark=?,decisionDate=CURRENT_TIMESTAMP WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        logTx($c,$manager,'Reject',$id,"Rejected borrowing request for {$t['assetName']}. Reason: $remark");
                        notify($c,$t['requesterID'],'Borrowing Request Rejected',"Your request $id for {$t['assetName']} was rejected. Reason: $remark");
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }
        }
    }
    $c->close();
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to review the transaction request. Please try again.';}

$count=count($active);$blocked=$count>=5||$overdue;
$redeem=count(array_filter($eco,fn($x)=>$x['sourceType']==='RewardRedemption'));
$penalty=count(array_filter($eco,fn($x)=>$x['sourceType']==='BorrowingPenalty'&&$x['points']<0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Review Transaction Request - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><a href="manager_transaction_view.php?transactionID=<?=e($id)?>">Transaction Details</a><span>›</span><span class="breadcrumb-current">Review Request</span></div>
<h1>Review Transaction Request</h1><p>Check borrower eligibility and recent EcoPoint activity before making a decision.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>

<?php if($t):?>
<?php if($overdue):?>
<div class="alert alert-error"><strong>Approval Blocked: Overdue Asset</strong><br>This member has <?=count($overdue)?> overdue unreturned asset<?=count($overdue)===1?'':'s'?>.</div>
<?php elseif($count>=5):?>
<div class="alert alert-error"><strong>Approval Blocked: Borrowing Limit Reached</strong><br>This member already has 5 active borrowings.</div>
<?php else:?>
<div class="asset-form-note"><strong>Eligible for Review</strong><p><?=$count?> / 5 active borrowings · <?=5-$count?> slot<?=5-$count===1?'':'s'?> remaining · no current overdue item.</p></div>
<?php endif;?>

<section class="category-detail-layout">
<article class="content-card category-info-card">
<h2>Request Information</h2><div class="category-info-fields">
<?php foreach(['Transaction ID'=>$id,'Asset'=>$t['assetName'],'Condition'=>$t['assetCondition'],'Request Date'=>d($t['requestDate']),'Request Message'=>$t['requestMessage']?:'-'] as $k=>$v):?>
<div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div>
<?php endforeach;?>
</div></article>

<article class="content-card category-stats-card">
<h2>Borrower Review</h2><div class="category-stats-grid">
<div class="category-stat"><strong><?=$count?> / 5</strong><span>Active Borrowings</span></div>
<div class="category-stat"><strong><?=count($overdue)?></strong><span>Current Overdue</span></div>
<div class="category-stat"><strong><?=(int)$t['ecopoint']?></strong><span>EcoPoints</span></div>
<div class="category-stat"><strong><?=$redeem?></strong><span>Recent Redemptions</span></div>
<div class="category-stat"><strong><?=$penalty?></strong><span>Recent Penalties</span></div>
<div class="category-stat"><strong><?=e($t['requesterName'])?></strong><span><?=e($t['requesterID'])?></span></div>
</div></article>
</section>

<?php if($overdue):?>
<section class="content-card">
<div class="card-header"><h2>Current Overdue Borrowings</h2></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Borrow ID</th><th>Asset</th><th>Status</th><th>Expected Return</th></tr></thead><tbody>
<?php foreach($overdue as $x):?><tr><td><?=e($x['borrowID'])?></td><td><?=e($x['assetName'])?></td><td>Overdue</td><td><?=e(d($x['expectedReturnDate'],false))?></td></tr><?php endforeach;?>
</tbody></table></div>
</section>
<?php endif;?>

<section class="content-card">
<div class="card-header"><h2>Recent EcoPoint Activity</h2></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Date</th><th>Points</th><th>Type</th><th>Source</th><th>Reference</th></tr></thead><tbody>
<?php if(!$eco):?><tr><td colspan="5" class="dashboard-empty-state">No EcoPoint activity found.</td></tr>
<?php else:foreach($eco as $x):?><tr><td><?=e(d($x['transactionDate']))?></td><td><?=e($x['points'])?></td><td><?=e($x['transactionType'])?></td><td><?=e($x['sourceType'])?></td><td><?=e($x['referenceID']?:'-')?></td></tr><?php endforeach;endif;?>
</tbody></table></div>
</section>

<section class="content-card asset-form-card">
<form method="POST"><div class="asset-form-fields">
<div class="asset-form-row"><label>Requester</label><input value="<?=e($t['requesterName'].' ('.$t['requesterID'].')')?>" readonly></div>
<div class="asset-form-row"><label for="expectedReturnDate">Expected Return *</label><input type="date" id="expectedReturnDate" name="expectedReturnDate" min="<?=date('Y-m-d')?>" required></div>
<div class="asset-form-row asset-form-textarea"><label for="managerRemark">Manager Remark</label><textarea id="managerRemark" name="managerRemark" maxlength="255" rows="3"></textarea></div>
</div>
<div class="category-form-actions">
<a href="manager_transaction_view.php?transactionID=<?=e($id)?>" class="category-form-back">Back</a>
<button type="submit" name="action" value="reject" class="category-form-reset" formnovalidate onclick="return confirm('Reject this borrowing request?')">Reject Request</button>
<button type="submit" name="action" value="approve" class="category-form-submit" <?=$blocked?'disabled title="Approval blocked by borrowing rule."':''?> onclick="return confirm('Approve this borrowing request?')">Approve Request</button>
</div></form>
</section>
<?php endif;?>

</main></div></div>
</body></html>
