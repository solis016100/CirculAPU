<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') { header('Location: ../login.php'); exit(); }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function d($v,bool $time=true): string { return $v&&strtotime($v)?date($time?'d M Y, h:i A':'d M Y',strtotime($v)):'-'; }
function nextID(mysqli $c,string $t,string $col,string $p): string {
    $n=(int)$c->query("SELECT COALESCE(MAX(CAST(SUBSTRING($col,4) AS UNSIGNED)),0)+1 n FROM $t")->fetch_assoc()['n'];
    return sprintf($p.'%03d',$n);
}
function logTx(mysqli $c,string $uid,string $action,string $ref,string $text): void {
    $id=nextID($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $s->bind_param('sssss',$id,$uid,$action,$ref,$text); $s->execute(); $s->close();
}
function notify(mysqli $c,string $uid,string $title,string $text): void {
    $id=nextID($c,'notification','notificationID','NOT');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$id,$uid,$title,$text); $s->execute(); $s->close();
}
function assetStatus(mysqli $c,string $asset,string $manager,string $old,string $new,string $reason): void {
    if($old===$new)return;
    $s=$c->prepare("UPDATE assets SET availabilityStatus=?,updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
    $s->bind_param('ss',$new,$asset); $s->execute(); $s->close();
    $id=nextID($c,'assetstatushistory','historyID','ASH');
    $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$id,$asset,$manager,$old,$new,$reason); $s->execute(); $s->close();
}
function go(string $id,string $q='updated=1'): never {
    header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id)."&$q"); exit();
}

$managerID=$_SESSION['user_id'];
$id=trim($_GET['transactionID']??'');
if(!$id){header('Location: manager_transactions.php');exit();}

$t=$b=null;$logs=[];$summary=['active'=>0,'overdue'=>0,'late'=>0,'redeem'=>0,'penalty'=>0];$error='';
$hasReview=file_exists(__DIR__.'/review_transaction_request.php');
$hasReturn=file_exists(__DIR__.'/inspect_asset_return.php');

try{
    $c=new mysqli('127.0.0.1','root','','circulapu_db',3306);$c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT t.*,a.assetName,a.assetCondition,a.availabilityStatus,a.listingStatus,a.verificationStatus,
               cat.categoryName,r.name requesterName,r.email requesterEmail,r.ecopoint,
               o.userID ownerID,o.name ownerName
        FROM transactionrequests t
        JOIN assets a ON t.assetID=a.assetID
        JOIN categories cat ON a.categoryID=cat.categoryID
        JOIN user r ON t.requesterID=r.userID
        JOIN user o ON a.ownerID=o.userID
        WHERE t.transactionID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$t=$s->get_result()->fetch_assoc();$s->close();

    if(!$t)$error='The selected transaction could not be found.';
    else{
        $s=$c->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $s->bind_param('s',$id);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();

        $s=$c->prepare("
            SELECT
              SUM(b.actualReturnDate IS NULL AND b.borrowingStatus NOT IN ('Completed','Cancelled','Returned')) active,
              SUM(b.actualReturnDate IS NULL AND b.expectedReturnDate<NOW()
                  AND (a.availabilityStatus='Borrowed' OR b.borrowingStatus IN ('Borrowed','Active','Overdue'))) overdue,
              SUM(b.actualReturnDate>b.expectedReturnDate) late
            FROM borrowings b JOIN assets a ON b.assetID=a.assetID WHERE b.borrowerID=?");
        $s->bind_param('s',$t['requesterID']);$s->execute();$x=$s->get_result()->fetch_assoc();$s->close();
        foreach(['active','overdue','late'] as $k)$summary[$k]=(int)($x[$k]??0);

        $s=$c->prepare("
            SELECT SUM(sourceType='RewardRedemption') redeem,
                   SUM(sourceType='BorrowingPenalty' AND points<0) penalty
            FROM ecopointstransaction WHERE userID=?");
        $s->bind_param('s',$t['requesterID']);$s->execute();$x=$s->get_result()->fetch_assoc();$s->close();
        $summary['redeem']=(int)($x['redeem']??0);$summary['penalty']=(int)($x['penalty']??0);

        $borrowed=$b&&!$b['actualReturnDate']&&(
            $t['availabilityStatus']==='Borrowed'||
            in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true)||
            in_array($t['requestStatus'],['In Progress','Overdue'],true)
        );
        $overdue=$borrowed&&$b['expectedReturnDate']&&strtotime($b['expectedReturnDate'])<time();
        $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)
            ?$t['requestStatus']:($overdue?'Overdue':($borrowed?'In Progress':$t['requestStatus']));

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=$_POST['action']??'';$remark=trim($_POST['managerRemark']??'');

            if($action==='start'){
                if($status!=='Approved'||!$b||$t['availabilityStatus']!=='Reserved')$error='Only a Reserved approved borrowing can be started.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='In Progress' WHERE transactionID=?");
                        $s->bind_param('s',$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Borrowed',borrowDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$managerID,'Reserved','Borrowed',"Borrowing started for $id");
                        logTx($c,$managerID,'Assign',$id,"Borrowing started for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Borrowing Started',"Borrowing for {$t['assetName']} has started. Expected return: ".d($b['expectedReturnDate'],false).'.');
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='cancel'){
                if($status!=='Approved'||!$b||$t['availabilityStatus']==='Borrowed')$error='Only an approved borrowing can be cancelled before handover.';
                elseif(!$remark)$error='Please enter a cancellation reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Cancelled',managerRemark=? WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$managerID,$t['availabilityStatus'],'Available',"Cancelled approved borrowing $id");
                        logTx($c,$managerID,'Reject',$id,"Cancelled approval for {$t['assetName']}. Reason: $remark");
                        notify($c,$t['requesterID'],'Borrowing Approval Cancelled',"Transaction $id for {$t['assetName']} was cancelled. Reason: $remark");
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='remind'){
                $like="%$id%";
                $s=$c->prepare("SELECT 1 FROM notification WHERE userID=? AND content LIKE ? AND title LIKE '%Reminder%' AND DATE(createdDate)=CURDATE() LIMIT 1");
                $s->bind_param('ss',$t['requesterID'],$like);$s->execute();$sent=(bool)$s->get_result()->fetch_row();$s->close();

                if(!$borrowed)$error='A reminder can only be sent while the asset is borrowed.';
                elseif($sent)$error='A reminder has already been sent for this transaction today.';
                else{
                    $title=$overdue?'Overdue Return Reminder':'Return Reminder';
                    $text=$overdue
                        ?"Transaction $id: {$t['assetName']} was due on ".d($b['expectedReturnDate'],false).'. Please return it as soon as possible.'
                        :"Transaction $id: Please remember to return {$t['assetName']} by ".d($b['expectedReturnDate'],false).'.';
                    notify($c,$t['requesterID'],$title,$text);
                    logTx($c,$managerID,'Update',$id,"Sent return reminder to {$t['requesterName']} for {$t['assetName']}");
                    go($id,'reminded=1');
                }
            }
        }

        $s=$c->prepare("SELECT sl.actionType,sl.description,sl.timeLogged,u.name userName FROM systemlog sl LEFT JOIN user u ON sl.userID=u.userID WHERE sl.refType='Transaction' AND sl.refID=? ORDER BY sl.timeLogged DESC");
        $s->bind_param('s',$id);$s->execute();$logs=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    }
    $c->close();
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to load the transaction. Please try again.';}

if($t){
    $borrowed=$b&&!$b['actualReturnDate']&&(
        $t['availabilityStatus']==='Borrowed'||
        in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true)||
        in_array($t['requestStatus'],['In Progress','Overdue'],true)
    );
    $overdue=$borrowed&&$b['expectedReturnDate']&&strtotime($b['expectedReturnDate'])<time();
    $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)
        ?$t['requestStatus']:($overdue?'Overdue':($borrowed?'In Progress':$t['requestStatus']));
    $days=$overdue?max(1,(int)floor((time()-strtotime($b['expectedReturnDate']))/86400)):0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Transaction Details - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>
<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><span class="breadcrumb-current">Transaction Details</span></div>
    <h1>Transaction Details</h1>
    <p>Review transaction information and manage the borrowing progress.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<?php if(($_GET['updated']??'')==='1'):?><div class="alert alert-success">Transaction updated successfully.</div><?php endif;?>
<?php if(($_GET['reminded']??'')==='1'):?><div class="alert alert-success">Return reminder sent successfully.</div><?php endif;?>

<?php if($t):?>

<?php if($overdue):?>
<div class="alert alert-error"><strong>OVERDUE</strong><br>Expected Return: <?=e(d($b['expectedReturnDate'],false))?> · <?=$days?> day<?=$days===1?'':'s'?> overdue</div>
<?php else:?>
<div class="asset-form-note"><strong>Status: <?=e($status)?></strong><p><?=$b&&$b['expectedReturnDate']?'Expected Return: '.e(d($b['expectedReturnDate'],false)):'No return date assigned yet.'?></p></div>
<?php endif;?>

<section class="category-detail-layout">
<article class="content-card category-info-card">
<h2>Transaction Information</h2><div class="category-info-fields">
<?php foreach([
    'Transaction ID'=>$t['transactionID'],'Request Date'=>d($t['requestDate']),'Decision Date'=>d($t['decisionDate']),
    'Expected Return'=>d($b['expectedReturnDate']??null),'Actual Return'=>d($b['actualReturnDate']??null),
    'Request Message'=>$t['requestMessage']?:'-','Manager Remark'=>$t['managerRemark']?:'-'
] as $label=>$value):?>
<div class="category-info-row"><strong><?=e($label)?></strong><span><?=e($value)?></span></div>
<?php endforeach;?>
</div></article>

<article class="content-card category-info-card">
<h2>Asset Information</h2><div class="category-info-fields">
<?php foreach([
    'Asset ID'=>$t['assetID'],'Asset Name'=>$t['assetName'],'Category'=>$t['categoryName'],'Condition'=>$t['assetCondition'],
    'Availability'=>$t['availabilityStatus'],'Owner'=>$t['ownerName'].' ('.$t['ownerID'].')'
] as $label=>$value):?>
<div class="category-info-row"><strong><?=e($label)?></strong><span><?=e($value)?></span></div>
<?php endforeach;?>
</div></article>
</section>

<section class="category-detail-layout">
<article class="content-card category-info-card">
<h2>Requester Information</h2><div class="category-info-fields">
<?php foreach(['Member ID'=>$t['requesterID'],'Name'=>$t['requesterName'],'Email'=>$t['requesterEmail']] as $label=>$value):?>
<div class="category-info-row"><strong><?=e($label)?></strong><span><?=e($value)?></span></div>
<?php endforeach;?>
</div></article>

<article class="content-card category-stats-card">
<h2>Borrower Summary</h2><div class="category-stats-grid">
<div class="category-stat"><strong><?=$summary['active']?> / 5</strong><span>Active Borrowings</span></div>
<div class="category-stat"><strong><?=$summary['overdue']?></strong><span>Current Overdue</span></div>
<div class="category-stat"><strong><?=(int)$t['ecopoint']?></strong><span>EcoPoints</span></div>
<div class="category-stat"><strong><?=$summary['late']?></strong><span>Previous Late Returns</span></div>
<div class="category-stat"><strong><?=$summary['redeem']?></strong><span>Reward Redemptions</span></div>
<div class="category-stat"><strong><?=$summary['penalty']?></strong><span>Damage Deductions</span></div>
</div></article>
</section>

<section class="content-card">
<div class="card-header"><h2>Management Actions</h2></div>
<div class="asset-detail-actions">

<?php if($status==='Pending'):?>
<a href="<?=$hasReview?'review_transaction_request.php?transactionID='.e($id):'#'?>" class="asset-detail-action-btn"
   <?=!$hasReview?'onclick="alert(\'Review Request page will be built next.\');return false;"':''?>>
<img src="../assets/icons/view.png" alt=""> Review Request</a>

<?php elseif($status==='Approved'):?>
<form method="POST" onsubmit="return confirm('Confirm that the asset has been handed over?');">
<input type="hidden" name="action" value="start"><button class="asset-form-submit">Start Borrowing</button></form>
<form method="POST" onsubmit="return confirm('Cancel this approved borrowing?');">
<textarea name="managerRemark" maxlength="255" rows="3" placeholder="Cancellation reason" required></textarea>
<input type="hidden" name="action" value="cancel"><button class="asset-form-reset">Cancel Approval</button></form>

<?php elseif(in_array($status,['In Progress','Overdue'],true)):?>
<form method="POST" onsubmit="return confirm('Send a return reminder to this member?');">
<input type="hidden" name="action" value="remind"><button class="asset-form-reset"><?=$overdue?'Send Overdue Reminder':'Send Return Reminder'?></button></form>
<a href="<?=$hasReturn?'inspect_asset_return.php?transactionID='.e($id):'#'?>" class="asset-detail-action-btn"
   <?=!$hasReturn?'onclick="alert(\'Return Inspection page will be built next.\');return false;"':''?>>
<img src="../assets/icons/edit.png" alt=""> Inspect Returned Asset</a>
<?php endif;?>

<a href="manager_transactions.php" class="asset-detail-back-btn">Back</a>
</div>
</section>

<section class="content-card">
<div class="card-header"><h2>Transaction History</h2></div><div class="table-wrapper">
<table class="data-table"><thead><tr><th>Date & Time</th><th>Action</th><th>Done By</th><th>Activity</th></tr></thead><tbody>
<?php if(!$logs):?><tr><td colspan="4" class="dashboard-empty-state">No transaction activity recorded yet.</td></tr>
<?php else:foreach($logs as $x):?>
<tr><td><?=e(d($x['timeLogged']))?></td><td><?=e($x['actionType'])?></td><td><?=e($x['userName']?:'System')?></td><td><?=e($x['description'])?></td></tr>
<?php endforeach;endif;?>
</tbody></table></div>
</section>

<?php endif;?>
</main>
</div></div>
</body></html>
