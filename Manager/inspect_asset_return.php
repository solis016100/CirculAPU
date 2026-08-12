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
function logTx(mysqli $c,string $uid,string $ref,string $text): void {
    $id=nextID($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Update','Transaction',?,?)");
    $s->bind_param('ssss',$id,$uid,$ref,$text);$s->execute();$s->close();
}
function notify(mysqli $c,string $uid,string $title,string $text): void {
    $id=nextID($c,'notification','notificationID','NOT');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$id,$uid,$title,$text);$s->execute();$s->close();
}

$manager=$_SESSION['user_id'];$id=trim($_GET['transactionID']??'');
if(!$id){header('Location: manager_transactions.php');exit();}

$conditions=['New','Like New','Good','Fair','Poor'];$t=$b=null;$error='';

try{
    $c=new mysqli('127.0.0.1','root','','circulapu_db',3306);$c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT t.transactionID,t.requestStatus,t.requesterID,a.assetID,a.assetName,a.assetCondition,a.availabilityStatus,
               u.name borrowerName,u.ecopoint
        FROM transactionrequests t JOIN assets a ON t.assetID=a.assetID JOIN user u ON t.requesterID=u.userID
        WHERE t.transactionID=? LIMIT 1");
    $s->bind_param('s',$id);$s->execute();$t=$s->get_result()->fetch_assoc();$s->close();

    if(!$t)$error='The selected transaction could not be found.';
    else{
        $s=$c->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $s->bind_param('s',$id);$s->execute();$b=$s->get_result()->fetch_assoc();$s->close();

        $active=$b&&!$b['actualReturnDate']&&(
            $t['availabilityStatus']==='Borrowed'||
            in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true)||
            in_array($t['requestStatus'],['In Progress','Overdue'],true)
        );
        if(!$active){header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id));exit();}

        if($_SERVER['REQUEST_METHOD']==='POST'){
            $returned=trim($_POST['returnedCondition']??'');
            $outcome=trim($_POST['returnOutcome']??'');
            $deduct=max(0,(int)($_POST['ecoDeduction']??0));
            $reason=trim($_POST['reason']??'');
            $before=array_search($t['assetCondition'],$conditions,true);
            $after=array_search($returned,$conditions,true);
            $worse=$before!==false&&$after!==false&&$after>$before;
            $problem=$worse||$outcome==='Unavailable';

            if($after===false)$error='Please select a valid returned condition.';
            elseif(!in_array($outcome,['Available','Unavailable'],true))$error='Please select a valid return outcome.';
            elseif($before!==false&&$after<$before)$error='Returned condition cannot be better than the condition before borrowing.';
            elseif($deduct>(int)$t['ecopoint'])$error='EcoPoint deduction cannot exceed the member current EcoPoints.';
            elseif($problem&&$deduct<1)$error='Enter an EcoPoint deduction for a damaged/worse return.';
            elseif($problem&&!$reason)$error='Please enter the reason for the deduction.';
            elseif(!$problem&&$deduct>0)$error='EcoPoints should only be deducted when the returned asset is worse or unavailable.';
            elseif(strlen($reason)>255)$error='Reason must not exceed 255 characters.';
            else{
                $c->begin_transaction();
                try{
                    $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Completed' WHERE transactionID=?");
                    $s->bind_param('s',$id);$s->execute();$s->close();

                    $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Completed',actualReturnDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                    $s->bind_param('s',$b['borrowID']);$s->execute();$s->close();

                    $old=$t['availabilityStatus'];
                    $s=$c->prepare("UPDATE assets SET assetCondition=?,availabilityStatus=?,updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                    $s->bind_param('sss',$returned,$outcome,$t['assetID']);$s->execute();$s->close();

                    if($old!==$outcome){
                        $hid=nextID($c,'assetstatushistory','historyID','ASH');
                        $why="Return inspection completed for $id";
                        $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
                        $s->bind_param('ssssss',$hid,$t['assetID'],$manager,$old,$outcome,$why);$s->execute();$s->close();
                    }

                    if($deduct){
                        $s=$c->prepare("UPDATE user SET ecopoint=ecopoint-? WHERE userID=?");
                        $s->bind_param('is',$deduct,$t['requesterID']);$s->execute();$s->close();

                        $eid=nextID($c,'ecopointstransaction','transactionID','EPT');$points=-$deduct;$type='Spent';$source='BorrowingPenalty';
                        $s=$c->prepare("INSERT INTO ecopointstransaction(transactionID,userID,points,transactionType,sourceType,referenceID) VALUES (?,?,?,?,?,?)");
                        $s->bind_param('ssisss',$eid,$t['requesterID'],$points,$type,$source,$id);$s->execute();$s->close();
                    }

                    $late=$b['expectedReturnDate']&&time()>strtotime($b['expectedReturnDate']);
                    $text="Return completed for {$t['assetName']}: {$t['assetCondition']} → $returned; asset $outcome";
                    if($deduct)$text.="; deducted $deduct EcoPoints. Reason: $reason";
                    if($late)$text.='; returned overdue.';
                    logTx($c,$manager,$id,$text);

                    $msg="Return for {$t['assetName']} completed. Returned condition: $returned. Asset status: $outcome.";
                    if($deduct)$msg.=" $deduct EcoPoints were deducted. Reason: $reason";
                    notify($c,$t['requesterID'],'Asset Return Completed',$msg);

                    $c->commit();
                    header('Location: manager_transaction_view.php?transactionID='.rawurlencode($id).'&returned=1');exit();
                }catch(Throwable $z){$c->rollback();throw $z;}
            }
        }
    }
    $c->close();
}catch(Throwable $z){error_log($z->getMessage());$error='Unable to process the asset return. Please try again.';}

$overdue=$b&&$b['expectedReturnDate']&&strtotime($b['expectedReturnDate'])<time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inspect Asset Return - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?><div class="main-content"><?php include '../header.php';?>
<main class="page-body">

<section class="management-heading">
<div class="breadcrumb"><a href="manager_transactions.php">Transaction Management</a><span>›</span><a href="manager_transaction_view.php?transactionID=<?=e($id)?>">Transaction Details</a><span>›</span><span class="breadcrumb-current">Return Inspection</span></div>
<h1>Return Inspection</h1><p>Inspect the returned asset before completing the borrowing transaction.</p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>

<?php if($t&&$b):?>
<?php if($overdue):?>
<div class="alert alert-error"><strong>Overdue Return</strong><br>Expected return was <?=e(d($b['expectedReturnDate'],false))?>.</div>
<?php else:?>
<div class="asset-form-note"><strong>Return Accepted Before / On Due Date</strong><p>Expected return: <?=e(d($b['expectedReturnDate'],false))?>. The member may return the asset at any time while borrowing is active.</p></div>
<?php endif;?>

<section class="content-card asset-form-card">
<form method="POST">
<div class="asset-form-layout">

<div class="asset-form-fields">
<div class="asset-form-row"><label>Transaction ID</label><input value="<?=e($id)?>" readonly></div>
<div class="asset-form-row"><label>Asset</label><input value="<?=e($t['assetName'].' ('.$t['assetID'].')')?>" readonly></div>
<div class="asset-form-row"><label>Borrower</label><input value="<?=e($t['borrowerName'].' ('.$t['requesterID'].')')?>" readonly></div>
<div class="asset-form-row"><label>Condition Before Borrowing</label><input value="<?=e($t['assetCondition'])?>" readonly></div>
<div class="asset-form-row"><label>Expected Return</label><input value="<?=e(d($b['expectedReturnDate']))?>" readonly></div>
<div class="asset-form-row"><label>Current EcoPoints</label><input value="<?=e($t['ecopoint'])?>" readonly></div>

<div class="asset-form-row">
<label for="returnedCondition">Returned Condition *</label>
<select id="returnedCondition" name="returnedCondition" required>
<?php foreach($conditions as $v):?><option value="<?=e($v)?>" <?=($_POST['returnedCondition']??$t['assetCondition'])===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?>
</select>
</div>

<div class="asset-form-row">
<label for="returnOutcome">Asset After Return *</label>
<select id="returnOutcome" name="returnOutcome" required>
<option value="Available" <?=($_POST['returnOutcome']??'Available')==='Available'?'selected':''?>>Available / Usable</option>
<option value="Unavailable" <?=($_POST['returnOutcome']??'')==='Unavailable'?'selected':''?>>Unavailable / Damaged</option>
</select>
</div>

<div class="asset-form-row">
<label for="ecoDeduction">EcoPoint Deduction</label>
<input type="number" min="0" max="<?=e($t['ecopoint'])?>" id="ecoDeduction" name="ecoDeduction" value="<?=e($_POST['ecoDeduction']??0)?>">
</div>

<div class="asset-form-row asset-form-textarea">
<label for="reason">Deduction / Damage Reason</label>
<textarea id="reason" name="reason" maxlength="255" rows="4" placeholder="Required if the asset condition became worse or the asset is unavailable"><?=e($_POST['reason']??'')?></textarea>
</div>
</div>

<aside class="asset-form-side">
<div class="asset-form-note">
<strong>Return Rule</strong>
<p>Same condition: no penalty. Worse/damaged condition: EcoPoint deduction and reason are required. Seriously damaged assets should be marked Unavailable.</p>
</div>
<div class="asset-form-actions">
<button type="submit" class="asset-form-submit" onclick="return confirm('Confirm this return inspection and complete the transaction?')">Confirm Return</button>
<a href="manager_transaction_view.php?transactionID=<?=e($id)?>" class="asset-form-back">Back</a>
</div>
</aside>

</div>
</form>
</section>
<?php endif;?>

</main></div></div>
</body></html>
