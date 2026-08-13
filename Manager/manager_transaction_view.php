<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if(!isset($_SESSION['user_id'])||$_SESSION['user_role']!=='Manager'){header('Location: ../login.php');exit();}

function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function d($v,bool $time=false):string{return $v&&strtotime($v)?date($time?'d M Y, h:i A':'d M Y',strtotime($v)):'-';}
function nid(mysqli $c,string $t,string $col,string $p):string{
    $start=strlen($p)+1;$like=$p.'%';
    $s=$c->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($col,$start) AS UNSIGNED)),0)+1 n FROM $t WHERE $col LIKE ?");
    $s->bind_param('s',$like);$s->execute();
    $n=(int)$s->get_result()->fetch_assoc()['n'];$s->close();
    return sprintf($p.'%03d',$n);
}
function logTx(mysqli $c,string $uid,string $action,string $ref,string $text):void{
    $x=nid($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $s->bind_param('sssss',$x,$uid,$action,$ref,$text);$s->execute();$s->close();
}
function notify(mysqli $c,string $uid,string $title,string $text):void{
    $x=nid($c,'notification','notificationID','N');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$x,$uid,$title,$text);$s->execute();$s->close();
}
function assetStatus(mysqli $c,string $asset,string $manager,string $old,string $new,string $why):void{
    if($old===$new)return;
    $s=$c->prepare("UPDATE assets SET availabilityStatus=?,updatedDate=NOW() WHERE assetID=?");
    $s->bind_param('ss',$new,$asset);$s->execute();$s->close();
    $x=nid($c,'assetstatushistory','historyID','H');
    $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$x,$asset,$manager,$old,$new,$why);$s->execute();$s->close();
}
function approvalIssue(array $t,?array $b,bool $blocked,array $summary,string $status):string{
    if($status!=='Pending')return 'Only Pending requests can be approved and reserved.';
    if($blocked)return ($summary['overdue']??0)>0
        ?'Approval blocked: borrower has an overdue unreturned asset.'
        :'Approval blocked: borrower already has 5 active borrowings.';
    if(!$b||empty($b['borrowDate'])||empty($b['expectedReturnDate']))
        return 'Approval blocked: the member request is missing pickup or return date.';
    $pickup=date('Y-m-d',strtotime($b['borrowDate']));
    $return=date('Y-m-d',strtotime($b['expectedReturnDate']));
    if($pickup<date('Y-m-d'))return 'Approval blocked: the requested pickup date has already passed.';
    if($return<=$pickup)return 'Requested return date must be after the pickup date.';
    if($t['listingStatus']!=='Published'||$t['verificationStatus']!=='Approved'||$t['availabilityStatus']!=='Available')
        return 'Approval blocked: this asset is not currently available for borrowing.';
    return '';
}
function go(string $id):never{header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id).'&updated=1');exit();}

$manager=$_SESSION['user_id'];$id=trim($_GET['transactionID']??'');
if(!$id){header('Location: manager_transactions.php');exit();}

$t=$b=null;$summary=[];$past=$eco=$logs=[];$error='';

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
            SELECT u.ecopoint,
              (SELECT COUNT(*) FROM transactionrequests x WHERE x.requesterID=u.userID) requests,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NULL AND x.borrowingStatus IN ('Approved','Borrowed','Active','Overdue')) active,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NULL AND DATE(x.expectedReturnDate)<CURDATE() AND x.borrowingStatus IN ('Borrowed','Active','Overdue')) overdue,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND x.actualReturnDate IS NOT NULL) completed,
              (SELECT COUNT(*) FROM borrowings x WHERE x.borrowerID=u.userID AND DATE(x.actualReturnDate)>DATE(x.expectedReturnDate)) late
            FROM user u WHERE u.userID=?");
        $s->bind_param('s',$t['requesterID']);$s->execute();$summary=$s->get_result()->fetch_assoc();$s->close();

        $s=$c->prepare("SELECT b.transactionID,a.assetName,b.expectedReturnDate,b.actualReturnDate,b.borrowingStatus FROM borrowings b JOIN assets a ON b.assetID=a.assetID WHERE b.borrowerID=? AND b.transactionID<>? ORDER BY COALESCE(b.actualReturnDate,b.borrowDate) DESC LIMIT 5");
        $s->bind_param('ss',$t['requesterID'],$id);$s->execute();$past=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $s=$c->prepare("SELECT points,sourceType,referenceID,transactionDate FROM ecopointstransaction WHERE userID=? ORDER BY transactionDate DESC LIMIT 5");
        $s->bind_param('s',$t['requesterID']);$s->execute();$eco=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();

        $borrowed=$b&&!$b['actualReturnDate']&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
        $overdue=$borrowed&&$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');
        $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)?$t['requestStatus']:($overdue?'Overdue':$t['requestStatus']);
        $blocked=($summary['active']??0)>=5||($summary['overdue']??0)>0;
        $approvalIssue=approvalIssue($t,$b,$blocked,$summary,$status);

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $action=$_POST['action']??'';$remark=trim($_POST['managerRemark']??'');

            if($action==='approve'){
                $pickup=$b['borrowDate']??'';$return=$b['expectedReturnDate']??'';
                if($approvalIssue)$error=$approvalIssue;
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Approved',managerRemark=?,decisionDate=NOW() WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Approved' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$manager,'Available','Reserved',"Approved and reserved for request $id");
                        logTx($c,$manager,'Approve',$id,"Approved borrowing request and reserved {$t['assetName']}");
                        notify($c,$t['requesterID'],'Request Approved & Asset Reserved',"Your request $id for {$t['assetName']} was approved and the asset is reserved for you. Pickup: ".d($pickup).". Return by: ".d($return).'.');
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='reject'){
                if($status!=='Pending')$error='Only Pending requests can be rejected.';
                elseif(!$remark)$error='Please enter a rejection reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Rejected',managerRemark=?,decisionDate=NOW() WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        if($b){$s=$c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");$s->bind_param('s',$b['borrowID']);$s->execute();$s->close();}
                        logTx($c,$manager,'Reject',$id,"Rejected borrowing request for {$t['assetName']}. Reason: $remark");
                        notify($c,$t['requesterID'],'Borrowing Request Rejected',"Your request $id for {$t['assetName']} was rejected. Reason: $remark");
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='start'){
                if($status!=='Approved'||!$b||$t['availabilityStatus']!=='Reserved')$error='Pickup can only be confirmed for an approved request with a Reserved asset.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='In Progress' WHERE transactionID=?");
                        $s->bind_param('s',$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Borrowed' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$manager,'Reserved','Borrowed',"Asset picked up for $id");
                        logTx($c,$manager,'Assign',$id,"Pickup confirmed; borrowing started for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Pickup Confirmed',"Pickup for {$t['assetName']} was confirmed. Your borrowing has started. Return by: ".d($b['expectedReturnDate']).'.');
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }

            if($action==='cancel'){
                if($status!=='Approved'||!$b)$error='Only an approved reservation can be cancelled before pickup.';
                elseif(!$remark)$error='Please enter a cancellation reason.';
                else{
                    $c->begin_transaction();
                    try{
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Cancelled',managerRemark=? WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$id);$s->execute();$s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Cancelled' WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();
                        assetStatus($c,$t['assetID'],$manager,'Reserved','Available',"Cancelled reservation $id");
                        logTx($c,$manager,'Update',$id,"Cancelled reservation before pickup. Reason: $remark");
                        notify($c,$t['requesterID'],'Reservation Cancelled',"The reservation for transaction $id was cancelled before pickup. Reason: $remark");
                        $c->commit();go($id);
                    }catch(Throwable $z){$c->rollback();throw $z;}
                }
            }
        }

        $s=$c->prepare("SELECT sl.actionType,sl.description,sl.timeLogged,u.name userName FROM systemlog sl LEFT JOIN user u ON sl.userID=u.userID WHERE sl.refType='Transaction' AND sl.refID=? ORDER BY sl.timeLogged DESC");
        $s->bind_param('s',$id);$s->execute();$logs=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    }
    $c->close();
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to process the transaction. Please try again.';}

if($t){
    $borrowed=$b&&!$b['actualReturnDate']&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
    $overdue=$borrowed&&$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');
    $status=in_array($t['requestStatus'],['Completed','Rejected','Cancelled'],true)?$t['requestStatus']:($overdue?'Overdue':$t['requestStatus']);
    $blocked=($summary['active']??0)>=5||($summary['overdue']??0)>0;
    $approvalIssue=approvalIssue($t,$b,$blocked,$summary,$status);
    $personLabel=in_array($status,['Pending','Rejected','Cancelled'],true)?'Requester':'Borrower';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Transaction Details - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css">
<style>
.tx-dialog{position:fixed;inset:0;margin:auto;width:min(760px,calc(100% - 32px));max-height:85vh;overflow:auto;border:1px solid #bfe4fb;border-radius:12px;padding:24px}
.tx-dialog-small{width:min(520px,calc(100% - 32px))}
.tx-dialog::backdrop{background:rgba(0,0,0,.55)}
.tx-dialog h2{margin-top:0}
#borrowerHistory h2{margin-bottom:18px}
.tx-side-actions button:disabled{
    background:#d9dee5;
    border-color:#c7cdd4;
    color:#6f7782;
    cursor:not-allowed;
    opacity:1;
}
.tx-dialog .asset-form-row{align-items:start}
.tx-dialog .category-form-actions{margin-top:18px}
.tx-top{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:24px;align-items:start;margin-bottom:24px}
.tx-support{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
.tx-support .content-card{margin:0}
.tx-profile .category-form-actions{justify-content:flex-start;margin-top:8px}
.tx-profile .management-filter-btn{padding:7px 12px;font-size:13px}
.tx-activity{padding:24px}
.tx-activity .card-header{margin:0 0 12px}
.tx-activity .table-wrapper{margin-top:0}
.tx-side-actions{display:flex;flex-direction:column;gap:12px}
.tx-side-actions form{margin:0}
.tx-side-actions a,.tx-side-actions button{width:100%;justify-content:center}
@media print{
@page{size:A4 landscape;margin:10mm}
.sidebar,.app-header,.breadcrumb,.management-heading p,.tx-side-actions,.tx-dialog,.tx-profile .category-form-actions{display:none!important}
.main-content{width:100%!important;margin-left:0!important}
.page-body{padding:0!important}
.management-heading{margin:0 0 10px!important}
.management-heading h1{font-size:20px!important;margin:0!important}
.alert,.asset-form-note{box-shadow:none!important}
.tx-top{display:block!important;margin-bottom:10px!important}
.tx-support{grid-template-columns:1fr 1fr!important;gap:10px!important;margin-bottom:10px!important}
.tx-top .content-card,.tx-support .content-card,.tx-activity{padding:12px!important;box-shadow:none!important;border:1px solid #cbd5e1!important;break-inside:avoid}
.category-info-card h2,.tx-activity h2{font-size:14px!important;margin:0 0 8px!important}
.category-info-fields{gap:5px!important}
.category-info-row{grid-template-columns:125px 1fr!important;gap:8px!important;font-size:10px!important}
.tx-activity .data-table th,.tx-activity .data-table td{padding:5px 7px!important;font-size:9px!important}
.tx-activity .table-wrapper{overflow:visible!important}
}
@media(max-width:850px){.tx-top,.tx-support{grid-template-columns:1fr}.tx-side-actions{flex-direction:row;flex-wrap:wrap}.tx-side-actions a,.tx-side-actions button{width:auto}}
</style>
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><span class="breadcrumb-current">Transaction Details</span></div>
<h1>Transaction Details</h1><p>Review the request, borrower background and borrowing progress.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<?php if(($_GET['updated']??'')==='1'):?><div class="alert alert-success">Transaction updated successfully.</div><?php endif;?>

<?php if($t):?>
<?php if($overdue):?>
<div class="alert alert-error"><strong>OVERDUE</strong> · Return was due <?=e(d($b['expectedReturnDate']))?></div>
<?php elseif($status==='Pending'&&$approvalIssue):?>
<div class="alert alert-error"><strong>Approval Blocked</strong> · <?=e(preg_replace('/^Approval blocked:\s*/i','',$approvalIssue))?></div>
<?php elseif($status==='Completed'):?>
<div class="alert alert-success"><strong>Completed</strong> · Asset returned <?=e(d($b['actualReturnDate']??null,true))?></div>
<?php else:?>
<div class="asset-form-note"><strong>Status: <?=e($status)?></strong></div>
<?php endif;?>

<div class="tx-top">
<article class="content-card category-info-card">
<h2>Transaction Information</h2><div class="category-info-fields">
<?php foreach([
'Transaction ID'=>$id,'Request Date'=>d($t['requestDate'],true),
'Requested Pickup'=>d($b['borrowDate']??null),'Requested Return'=>d($b['expectedReturnDate']??null),
'Actual Return'=>d($b['actualReturnDate']??null,true),
'Borrowing Status'=>$b['borrowingStatus']??'-',
'Purpose / Notes'=>$t['requestMessage']?:'-','Manager Remark'=>$t['managerRemark']?:'-'
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>
<div class="tx-side-actions">
<button type="button" class="category-form-back" onclick="window.print()">Print Details</button>
<a href="manager_transactions.php" class="category-form-back">Back</a>
<?php if($status==='Pending'):?>
<button type="button" class="category-form-reset" onclick="rejectBox.showModal()">Reject Request</button>
<form method="POST"><input type="hidden" name="action" value="approve"><button class="category-form-submit" <?=$approvalIssue?'disabled title="'.e($approvalIssue).'"':''?> onclick="return confirm('Approve this request and reserve the asset for this member?')">Approve &amp; Reserve</button></form>
<?php elseif($status==='Approved'):?>
<button type="button" class="category-form-reset" onclick="cancelBox.showModal()">Cancel Reservation</button>
<form method="POST"><input type="hidden" name="action" value="start"><button class="category-form-submit" onclick="return confirm('Confirm that the member has picked up this asset? Borrowing will start immediately.')">Confirm Pickup</button></form>
<?php endif;?>
</div>
</div>

<div class="tx-support">
<article class="content-card category-info-card">
<h2>Asset Information</h2><div class="category-info-fields">
<?php foreach([
'Asset ID'=>$t['assetID'],'Asset Name'=>$t['assetName'],'Category'=>$t['categoryName'],
'Condition'=>$t['assetCondition'],'Availability'=>$t['availabilityStatus'],'Owner'=>$t['ownerName'].' ('.$t['ownerID'].')'
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>
<section class="content-card tx-profile category-info-card">
<h2><?=e($personLabel)?> Profile</h2>
<div class="category-info-fields">
<div class="category-info-row"><strong>Member</strong><span><?=e($t['requesterName'].' ('.$t['requesterID'].')')?></span></div>
<div class="category-info-row"><strong>Email</strong><span><?=e($t['requesterEmail'])?></span></div>
<div class="category-info-row"><strong>EcoPoints</strong><span><?=e($summary['ecopoint']??$t['ecopoint'])?></span></div>
<div class="category-info-row"><strong>Active Borrowings</strong><span><?=e(($summary['active']??0).' / 5')?></span></div>
<div class="category-info-row"><strong>Current Overdue</strong><span><?=e($summary['overdue']??0)?></span></div>
</div>
<div class="category-form-actions">
<button type="button" class="management-filter-btn" onclick="borrowerHistory.showModal()">
<img src="../assets/icons/view.png" alt=""> View <?=e($personLabel)?> History
</button>
</div>
</section>
</div>


<section class="content-card tx-activity">
<div class="card-header"><h2>Transaction Activity<?= $logs?' ('.count($logs).')':''?></h2></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Date & Time</th><th>Action</th><th>Done By</th><th>Activity</th></tr></thead><tbody>
<?php if(!$logs):?><tr><td colspan="4" class="dashboard-empty-state">No activity recorded for this transaction yet.</td></tr>
<?php else:foreach($logs as $x):?><tr><td><?=e(d($x['timeLogged'],true))?></td><td><?=e($x['actionType'])?></td><td><?=e($x['userName']?:'System')?></td><td><?=e($x['description'])?></td></tr><?php endforeach;endif;?>
</tbody></table></div>
</section>

<dialog id="borrowerHistory" class="content-card tx-dialog">
<h2><?=e($personLabel)?> History</h2>

<div class="asset-form-fields">
<div class="asset-form-row"><label>Member</label><input value="<?=e($t['requesterName'].' ('.$t['requesterID'].')')?>" readonly></div>
<div class="asset-form-row"><label>Email</label><input value="<?=e($t['requesterEmail'])?>" readonly></div>
<div class="asset-form-row"><label>EcoPoints</label><input value="<?=e($summary['ecopoint']??$t['ecopoint'])?>" readonly></div>
<div class="asset-form-row"><label>Active Borrowings</label><input value="<?=e(($summary['active']??0).' / 5')?>" readonly></div>
<div class="asset-form-row"><label>Current Overdue</label><input value="<?=e($summary['overdue']??0)?>" readonly></div>
<div class="asset-form-row"><label>Completed Borrowings</label><input value="<?=e($summary['completed']??0)?>" readonly></div>
<div class="asset-form-row"><label>Previous Late Returns</label><input value="<?=e($summary['late']??0)?>" readonly></div>
<div class="asset-form-row"><label>Total Requests</label><input value="<?=e($summary['requests']??0)?>" readonly></div>
</div>

<div class="card-header"><h3>Recent Borrowings</h3></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Transaction</th><th>Asset</th><th>Due</th><th>Returned</th><th>Status</th></tr></thead><tbody>
<?php if(!$past):?><tr><td colspan="5" class="dashboard-empty-state">No previous borrowing records.</td></tr>
<?php else:foreach($past as $x):?><tr><td><?=e($x['transactionID'])?></td><td><?=e($x['assetName'])?></td><td><?=e(d($x['expectedReturnDate']))?></td><td><?=e(d($x['actualReturnDate']))?></td><td><?=e($x['borrowingStatus'])?></td></tr><?php endforeach;endif;?>
</tbody></table></div>

<div class="card-header"><h3>Recent EcoPoint Activity</h3></div>
<div class="table-wrapper"><table class="data-table">
<thead><tr><th>Date</th><th>Points</th><th>Source</th><th>Reference</th></tr></thead><tbody>
<?php if(!$eco):?><tr><td colspan="4" class="dashboard-empty-state">No EcoPoint activity.</td></tr>
<?php else:foreach($eco as $x):?><tr><td><?=e(d($x['transactionDate']))?></td><td><?=e($x['points'])?></td><td><?=e($x['sourceType'])?></td><td><?=e($x['referenceID']?:'-')?></td></tr><?php endforeach;endif;?>
</tbody></table></div>

<form method="dialog" class="category-form-actions"><button class="category-form-back">Close</button></form>
</dialog>

<?php if($status==='Pending'):?>
<dialog id="rejectBox" class="content-card tx-dialog tx-dialog-small">
<h2>Reject Request</h2>
<form method="POST">
<input type="hidden" name="action" value="reject">
<div class="asset-form-fields">
<div class="asset-form-row asset-form-textarea">
<label for="rejectReason">Rejection Reason *</label>
<textarea id="rejectReason" name="managerRemark" maxlength="255" rows="4" placeholder="Explain why this borrowing request is rejected" required></textarea>
</div>
</div>
<div class="category-form-actions">
<button type="button" class="category-form-back" onclick="rejectBox.close()">Cancel</button>
<button class="category-form-reset">Reject Request</button>
</div>
</form>
</dialog>
<?php elseif($status==='Approved'):?>
<dialog id="cancelBox" class="content-card tx-dialog tx-dialog-small">
<h2>Cancel Reservation</h2>
<form method="POST">
<input type="hidden" name="action" value="cancel">
<div class="asset-form-fields">
<div class="asset-form-row asset-form-textarea">
<label for="cancelReason">Cancellation Reason *</label>
<textarea id="cancelReason" name="managerRemark" maxlength="255" rows="4" placeholder="Explain why this reservation is being cancelled before pickup" required></textarea>
</div>
</div>
<div class="category-form-actions">
<button type="button" class="category-form-back" onclick="cancelBox.close()">Back</button>
<button class="category-form-reset">Cancel Reservation</button>
</div>
</form>
</dialog>
<?php endif;?>

<?php endif;?>
</main></div></div>
</body></html>
