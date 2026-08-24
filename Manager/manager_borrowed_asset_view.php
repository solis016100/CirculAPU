<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if(!isset($_SESSION['user_id'])||$_SESSION['user_role']!=='Manager'){header('Location: ../login.php');exit();}

require_once '../db.php';

function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function d($v,bool $time=false):string{return $v&&strtotime($v)?date($time?'d M Y, h:i A':'d M Y',strtotime($v)):'-';}
function nextID(mysqli $c,string $t,string $col,string $p):string{
    $start=strlen($p)+1;$like=$p.'%';
    $s=$c->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($col,$start) AS UNSIGNED)),0)+1 n FROM $t WHERE $col LIKE ?");
    $s->bind_param('s',$like);$s->execute();
    $n=(int)$s->get_result()->fetch_assoc()['n'];$s->close();
    return sprintf($p.'%03d',$n);
}
function notify(mysqli $c,string $uid,string $title,string $text):void{
    $nid=nextID($c,'notification','notificationID','N');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$nid,$uid,$title,$text);$s->execute();$s->close();
}
function logReminder(mysqli $c,string $uid,string $ref,string $text):void{
    $lid=nextID($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Update','Transaction',?,?)");
    $s->bind_param('ssss',$lid,$uid,$ref,$text);$s->execute();$s->close();
}

$manager=$_SESSION['user_id'];$id=trim($_GET['transactionID']??'');$back=trim($_GET['back']??'');$backURL='manager_borrowed_assets.php'.($back?'?'.$back:'');$backParam=$back?'&back='.rawurlencode($back):'';
if(!$id){header('Location: manager_borrowed_assets.php');exit();}

$t=null;$b=null;$error='';$remindedToday=false;$reminderCount=0;$lastReminder=null;

try{
    $c=$conn;$c->set_charset('utf8mb4');

    $s=$c->prepare("SELECT t.transactionID,t.requestStatus,a.assetID,a.assetName,a.assetCondition,a.availabilityStatus,
            u.userID borrowerID,u.name borrowerName,u.email borrowerEmail,u.ecopoint
        FROM transactionrequests t
        JOIN assets a ON a.assetID=t.assetID
        JOIN user u ON u.userID=t.requesterID
        WHERE t.transactionID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$t=$s->get_result()->fetch_assoc();$s->close();

    if(!$t)$error='The selected borrowing could not be found.';
    else{
        $s=$c->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $s->bind_param('s',$id);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();

        $active=$b&&!$b['actualReturnDate']&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
        if(!$active){header('Location: manager_borrowed_assets.php');exit();}

        $dueLabel=d($b['expectedReturnDate']);
        $txMarker="%Transaction $id:%";
        $legacyMarker="%borrowed asset {$t['assetName']} was due on $dueLabel%";
        $s=$c->prepare("SELECT COUNT(*) total,MAX(createdDate) lastReminder,
            COALESCE(SUM(DATE(createdDate)=CURDATE()),0) todayCount
            FROM notification
            WHERE userID=? AND title LIKE '%Return Reminder%'
            AND (content LIKE ? OR content LIKE ?)");
        $s->bind_param('sss',$t['borrowerID'],$txMarker,$legacyMarker);$s->execute();
        $rem=$s->get_result()->fetch_assoc();$s->close();
        $reminderCount=(int)($rem['total']??0);
        $lastReminder=$rem['lastReminder']??null;
        $remindedToday=(int)($rem['todayCount']??0)>0;

        $overdue=$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');

        if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='remind'){
            if(!$overdue)$error='Return reminders are only available for overdue borrowings.';
            elseif($remindedToday)$error='A return reminder has already been sent for this borrowing today.';
            else{
                $due=d($b['expectedReturnDate']);
                notify($c,$t['borrowerID'],'Overdue Asset Return Reminder',
                    "Transaction $id: Your borrowed asset {$t['assetName']} was due on $due. Please return the asset as soon as possible.");
                logReminder($c,$manager,$id,"Return reminder sent to {$t['borrowerName']} for overdue asset {$t['assetName']}.");
                header('Location: manager_borrowed_asset_view.php?transactionID='.rawurlencode($id).$backParam.'&reminded=1');exit();
            }
        }
    }
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to load the borrowing details. Please try again.';}

$overdue=$b&&$b['expectedReturnDate']&&date('Y-m-d',strtotime($b['expectedReturnDate']))<date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Borrowed Asset Details - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css"><link rel="stylesheet" href="manager_mobile.css">
<style>
.return-actions{display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-top:22px}
.return-actions form{margin:0}
</style>

<style>
.flow-dialog{
    width:min(520px, calc(100vw - 32px));
    border:none;
    border-radius:16px;
    padding:0;
    box-shadow:0 18px 50px rgba(0,0,0,.22);
}
.flow-dialog::backdrop{background:rgba(15,23,42,.45);}
.flow-dialog-inner{padding:24px 24px 20px;}
.flow-dialog h2{
    margin:0 0 10px;
    font-size:18px;
    font-weight:700;
    color:#0f172a;
}
.flow-dialog p{
    margin:0 0 16px;
    color:#475569;
    line-height:1.55;
}
.flow-dialog-summary{
    display:grid;
    grid-template-columns:140px 1fr;
    gap:10px 14px;
    margin-bottom:20px;
    font-size:15px;
}
.flow-dialog-summary strong{color:#0f172a;}
.flow-dialog-summary span{color:#334155;}
.flow-dialog-actions{
    display:flex;
    justify-content:flex-end;
    gap:12px;
}
.dialog-btn{
    min-width:110px;
    border-radius:10px;
    padding:10px 18px;
    font-size:15px;
    cursor:pointer;
    border:1px solid #cbd5e1;
    background:#fff;
    color:#0f172a;
}
.dialog-btn-primary{
    background:#0ea5e9;
    border-color:#0ea5e9;
    color:#fff;
}
.dialog-btn:disabled{
    opacity:.6;
    cursor:not-allowed;
}
</style>

</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><a href="<?=e($backURL)?>">Manage Returns</a><span>›</span><span class="breadcrumb-current">Borrowed Asset Details</span></div>
<h1>Borrowed Asset Details</h1><p>Review the active borrowing, send overdue reminders and process the returned asset.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<?php if(($_GET['reminded']??'')==='1'):?><div class="alert alert-success">Return reminder sent successfully.</div><?php endif;?>

<?php if($t&&$b):?>
<?php if($overdue):?>
<div class="alert alert-error"><strong>OVERDUE</strong> · Return was due <?=e(d($b['expectedReturnDate']))?>.</div>
<?php else:?>
<div class="asset-form-note"><strong>Status: Borrowed</strong><p>Expected return: <?=e(d($b['expectedReturnDate']))?>.</p></div>
<?php endif;?>

<section class="category-detail-layout">
<article class="content-card category-info-card">
<h2>Asset Information</h2><div class="category-info-fields">
<?php foreach([
'Asset ID'=>$t['assetID'],'Asset Name'=>$t['assetName'],'Condition Before Borrowing'=>$t['assetCondition'],
'Availability'=>$t['availabilityStatus']
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>

<article class="content-card category-info-card">
<h2>Borrower Information</h2><div class="category-info-fields">
<?php foreach([
'Member'=>$t['borrowerName'].' ('.$t['borrowerID'].')','Email'=>$t['borrowerEmail'],'EcoPoints'=>$t['ecopoint']
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div></article>
</section>

<section class="content-card category-info-card">
<h2>Borrowing Information</h2><div class="category-info-fields">
<?php foreach([
'Transaction ID'=>$id,'Pickup Date'=>d($b['borrowDate']),'Expected Return'=>d($b['expectedReturnDate']),
'Status'=>$overdue?'Overdue':'Borrowed','Reminders Sent'=>$reminderCount,
'Last Reminder'=>$lastReminder?d($lastReminder,true):'-'
] as $k=>$v):?><div class="category-info-row"><strong><?=e($k)?></strong><span><?=e($v)?></span></div><?php endforeach;?>
</div>

<div class="return-actions">
<a href="<?=e($backURL)?>" class="category-form-back">Back</a>
<?php if($overdue):?>
<form method="POST" id="remindForm">
<input type="hidden" name="action" value="remind">
<button type="button" class="category-form-reset" <?=$remindedToday?'disabled':''?> <?=$remindedToday?'':'onclick="openReminderDialog()"'?>>
<?=$remindedToday?'Reminder Sent Today':'Send Return Reminder'?>
</button>
</form>
<?php endif;?>
<a href="inspect_asset_return.php?transactionID=<?=e($id)?><?=e($backParam)?>" class="category-form-submit">Process Return</a>
</div>
</section>
<?php endif;?>

<?php if($overdue && !$remindedToday):?>
<dialog id="reminderDialog" class="flow-dialog">
    <div class="flow-dialog-inner">
        <h2>Confirm Return Reminder</h2>
        <p>Are you sure you want to send a return reminder to this borrower?</p>

        <div class="flow-dialog-summary">
            <strong>Transaction ID</strong><span><?=e($id)?></span>
            <strong>Borrower</strong><span><?=e($t['borrowerName'])?> (<?=e($t['borrowerID'])?>)</span>
            <strong>Asset</strong><span><?=e($t['assetName'])?> (<?=e($t['assetID'])?>)</span>
            <strong>Expected Return</strong><span><?=e(d($b['expectedReturnDate']))?></span>
        </div>

        <div class="flow-dialog-actions">
            <button type="button" class="dialog-btn" onclick="closeReminderDialog()">Cancel</button>
            <button type="button" class="dialog-btn dialog-btn-primary" onclick="submitReminderForm()">Yes, Send</button>
        </div>
    </div>
</dialog>

<script>
function openReminderDialog(){
    const dlg = document.getElementById('reminderDialog');
    if(dlg) dlg.showModal();
}
function closeReminderDialog(){
    const dlg = document.getElementById('reminderDialog');
    if(dlg) dlg.close();
}
function submitReminderForm(){
    document.getElementById('remindForm').submit();
}
</script>
<?php endif;?>

</main>
</div></div>
</body></html>
