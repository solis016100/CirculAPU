<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function d($v, $time=true): string { return $v && strtotime($v) ? date($time?'d M Y, h:i A':'d M Y', strtotime($v)) : '-'; }
function nextID(mysqli $c, string $table, string $col, string $prefix): string {
    $n = (int)$c->query("SELECT COALESCE(MAX(CAST(SUBSTRING($col,4) AS UNSIGNED)),0)+1 n FROM $table")->fetch_assoc()['n'];
    return sprintf($prefix.'%03d', $n);
}
function logAction(mysqli $c, string $uid, string $action, string $ref, string $text): void {
    $id=nextID($c,'systemlog','logID','LOG');
    $s=$c->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $s->bind_param('sssss',$id,$uid,$action,$ref,$text); $s->execute(); $s->close();
}
function notify(mysqli $c, string $uid, string $title, string $text): void {
    $id=nextID($c,'notification','notificationID','NOT');
    $s=$c->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $s->bind_param('ssss',$id,$uid,$title,$text); $s->execute(); $s->close();
}
function assetStatus(mysqli $c,string $asset,string $manager,string $old,string $new,string $reason): void {
    if ($old===$new) return;
    $s=$c->prepare("UPDATE assets SET availabilityStatus=?,updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
    $s->bind_param('ss',$new,$asset); $s->execute(); $s->close();

    $id=nextID($c,'assetstatushistory','historyID','ASH');
    $s=$c->prepare("INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason) VALUES (?,?,?,?,?,?)");
    $s->bind_param('ssssss',$id,$asset,$manager,$old,$new,$reason); $s->execute(); $s->close();
}
function go(string $id, string $q='updated=1'): never {
    header("Location: manager_transaction_view.php?transactionID=".rawurlencode($id)."&$q"); exit();
}

$managerID=$_SESSION['user_id'];
$transactionID=trim($_GET['transactionID']??'');
if (!$transactionID) { header('Location: manager_transactions.php'); exit(); }

$t=$b=null; $active=$logs=[]; $error=''; $reminderSent=false;

try {
    $c=new mysqli('127.0.0.1','root','','circulapu_db',3306);
    $c->set_charset('utf8mb4');

    $s=$c->prepare("
        SELECT t.*,a.assetName,a.assetCondition,a.availabilityStatus,a.listingStatus,a.verificationStatus,
               cat.categoryName,r.name requesterName,r.email requesterEmail,
               o.userID ownerID,o.name ownerName
        FROM transactionrequests t
        JOIN assets a ON t.assetID=a.assetID
        JOIN categories cat ON a.categoryID=cat.categoryID
        JOIN user r ON t.requesterID=r.userID
        JOIN user o ON a.ownerID=o.userID
        WHERE t.transactionID=? LIMIT 1
    ");
    $s->bind_param('s',$transactionID); $s->execute(); $t=$s->get_result()->fetch_assoc(); $s->close();

    if (!$t) $error='The selected transaction could not be found.';
    else {
        $s=$c->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $s->bind_param('s',$transactionID); $s->execute(); $b=$s->get_result()->fetch_assoc(); $s->close();

        /* Other active borrowings for approval-limit checks */
        $s=$c->prepare("
            SELECT b.borrowID,b.transactionID,b.borrowingStatus,b.expectedReturnDate,a.assetName
            FROM borrowings b JOIN assets a ON b.assetID=a.assetID
            WHERE b.borrowerID=? AND b.transactionID<>?
              AND b.borrowingStatus IN ('Approved','Borrowed','Overdue')
              AND b.actualReturnDate IS NULL
            ORDER BY b.expectedReturnDate
        ");
        $s->bind_param('ss',$t['requesterID'],$transactionID); $s->execute();
        $active=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

        $activeCount=count($active);
        $overdue=array_values(array_filter($active,fn($x)=>!empty($x['expectedReturnDate']) && strtotime($x['expectedReturnDate'])<time()));
        $blocked=$activeCount>=5 || $overdue;

        /* One reminder per transaction per day */
        if ($b) {
            $like="%$transactionID%";
            $s=$c->prepare("SELECT 1 FROM notification WHERE userID=? AND content LIKE ? AND title LIKE '%Reminder%' AND DATE(createdDate)=CURDATE() LIMIT 1");
            $s->bind_param('ss',$t['requesterID'],$like); $s->execute();
            $reminderSent=(bool)$s->get_result()->fetch_row(); $s->close();
        }

        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $action=trim($_POST['action']??'');
            $remark=trim($_POST['managerRemark']??'');
            $due=trim($_POST['expectedReturnDate']??'');

            if (strlen($remark)>255) $error='Manager Remark must not exceed 255 characters.';

            if (!$error && $action==='approve') {
                if ($t['requestStatus']!=='Pending') $error='Only Pending requests can be approved.';
                elseif ($overdue) $error='This member has an overdue asset and cannot receive another approval yet.';
                elseif ($activeCount>=5) $error='This member has reached the maximum of 5 active borrowings.';
                elseif ($t['listingStatus']!=='Published' || $t['verificationStatus']!=='Approved' || $t['availabilityStatus']!=='Available')
                    $error='This asset is not currently eligible for borrowing.';
                elseif (!$due || !DateTime::createFromFormat('Y-m-d',$due) || $due<date('Y-m-d'))
                    $error='Please select a valid expected return date.';
                else {
                    $c->begin_transaction();
                    try {
                        $due.=' 23:59:59';
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Approved',managerRemark=?,decisionDate=CURRENT_TIMESTAMP WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$transactionID); $s->execute(); $s->close();

                        $id=nextID($c,'borrowings','borrowID','BOR');
                        $s=$c->prepare("INSERT INTO borrowings(borrowID,assetID,borrowerID,transactionID,expectedReturnDate,borrowingStatus) VALUES (?,?,?,?,?,'Approved')");
                        $s->bind_param('sssss',$id,$t['assetID'],$t['requesterID'],$transactionID,$due); $s->execute(); $s->close();

                        assetStatus($c,$t['assetID'],$managerID,$t['availabilityStatus'],'Reserved',"Approved borrow request $transactionID");
                        logAction($c,$managerID,'Approve',$transactionID,"Approved borrowing request for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Borrowing Request Approved',"Your request $transactionID for {$t['assetName']} was approved. Expected return: ".d($due,false).'.');

                        $c->commit(); go($transactionID);
                    } catch(Throwable $x){ $c->rollback(); throw $x; }
                }
            }

            if (!$error && $action==='reject') {
                if ($t['requestStatus']!=='Pending') $error='Only Pending requests can be rejected.';
                elseif (!$remark) $error='Please enter a manager remark explaining the rejection.';
                else {
                    $c->begin_transaction();
                    try {
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Rejected',managerRemark=?,decisionDate=CURRENT_TIMESTAMP WHERE transactionID=?");
                        $s->bind_param('ss',$remark,$transactionID); $s->execute(); $s->close();
                        logAction($c,$managerID,'Reject',$transactionID,"Rejected borrowing request for {$t['assetName']}. Reason: $remark");
                        notify($c,$t['requesterID'],'Borrowing Request Rejected',"Your request $transactionID for {$t['assetName']} was rejected. Reason: $remark");
                        $c->commit(); go($transactionID);
                    } catch(Throwable $x){ $c->rollback(); throw $x; }
                }
            }

            if (!$error && $action==='start') {
                if ($t['requestStatus']!=='Approved' || !$b || $t['availabilityStatus']!=='Reserved') $error='Only a Reserved approved borrowing can be started.';
                else {
                    $c->begin_transaction();
                    try {
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='In Progress' WHERE transactionID=?");
                        $s->bind_param('s',$transactionID); $s->execute(); $s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Borrowed',borrowDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']); $s->execute(); $s->close();

                        assetStatus($c,$t['assetID'],$managerID,'Reserved','Borrowed',"Borrowing started for $transactionID");
                        logAction($c,$managerID,'Assign',$transactionID,"Borrowing started for {$t['assetName']}");
                        notify($c,$t['requesterID'],'Borrowing Started',"Borrowing for {$t['assetName']} has started. Expected return: ".d($b['expectedReturnDate'],false).'.');

                        $c->commit(); go($transactionID);
                    } catch(Throwable $x){ $c->rollback(); throw $x; }
                }
            }

            if (!$error && $action==='remind') {
                $isCurrentOverdue=$b && !$b['actualReturnDate'] && $b['expectedReturnDate'] && strtotime($b['expectedReturnDate'])<time();
                if (!$b || !in_array($t['requestStatus'],['In Progress','Overdue'],true) || $b['actualReturnDate']) $error='A reminder can only be sent for an active borrowed item.';
                elseif ($reminderSent) $error='A return reminder has already been sent for this transaction today.';
                else {
                    $title=$isCurrentOverdue?'Overdue Return Reminder':'Return Reminder';
                    $text=$isCurrentOverdue
                        ? "Transaction $transactionID: {$t['assetName']} was due on ".d($b['expectedReturnDate'],false).". Please return it as soon as possible."
                        : "Transaction $transactionID: Please remember to return {$t['assetName']} by ".d($b['expectedReturnDate'],false).'.';
                    notify($c,$t['requesterID'],$title,$text);
                    logAction($c,$managerID,'Update',$transactionID,"Sent return reminder to {$t['requesterName']} for {$t['assetName']}");
                    go($transactionID,'reminded=1');
                }
            }

            if (!$error && $action==='complete') {
                if (!$b || !in_array($t['requestStatus'],['In Progress','Overdue'],true)) $error='Only an active borrowing can be completed.';
                else {
                    $c->begin_transaction();
                    try {
                        $s=$c->prepare("UPDATE transactionrequests SET requestStatus='Completed' WHERE transactionID=?");
                        $s->bind_param('s',$transactionID); $s->execute(); $s->close();
                        $s=$c->prepare("UPDATE borrowings SET borrowingStatus='Completed',actualReturnDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                        $s->bind_param('s',$b['borrowID']); $s->execute(); $s->close();

                        assetStatus($c,$t['assetID'],$managerID,$t['availabilityStatus'],'Available',"Borrowing completed for $transactionID");
                        logAction($c,$managerID,'Update',$transactionID,"Completed borrowing and returned {$t['assetName']}");
                        notify($c,$t['requesterID'],'Borrowing Completed',"Transaction $transactionID for {$t['assetName']} has been completed.");

                        $c->commit(); go($transactionID);
                    } catch(Throwable $x){ $c->rollback(); throw $x; }
                }
            }
        }

        $s=$c->prepare("SELECT sl.actionType,sl.description,sl.timeLogged,u.name userName FROM systemlog sl LEFT JOIN user u ON sl.userID=u.userID WHERE sl.refType='Transaction' AND sl.refID=? ORDER BY sl.timeLogged DESC");
        $s->bind_param('s',$transactionID); $s->execute(); $logs=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    }
    $c->close();
} catch(Throwable $x){
    error_log($x->getMessage());
    $error='Unable to process the transaction. Please try again.';
}

$activeCount=$t ? count($active) : 0;
$overdue=$t ? array_values(array_filter($active,fn($x)=>!empty($x['expectedReturnDate']) && strtotime($x['expectedReturnDate'])<time())) : [];
$blocked=$activeCount>=5 || $overdue;
$currentOverdue=$b && !$b['actualReturnDate'] && $b['expectedReturnDate'] && strtotime($b['expectedReturnDate'])<time();

$tx=$t ? [
    'Transaction ID'=>$t['transactionID'],'Status'=>$currentOverdue?'Overdue':$t['requestStatus'],
    'Request Date'=>d($t['requestDate']),'Decision Date'=>d($t['decisionDate']),
    'Expected Return'=>d($b['expectedReturnDate']??null),'Actual Return'=>d($b['actualReturnDate']??null),
    'Request Message'=>$t['requestMessage']?:'-','Manager Remark'=>$t['managerRemark']?:'-'
] : [];
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
    <p>Review the borrowing request and manage its borrowing lifecycle.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated']??'')==='1'): ?><div class="alert alert-success">Transaction updated successfully.</div><?php endif; ?>
<?php if (($_GET['reminded']??'')==='1'): ?><div class="alert alert-success">Return reminder sent successfully.</div><?php endif; ?>

<?php if ($t): ?>

<?php if ($t['requestStatus']==='Pending'): ?>
<div class="asset-form-note">
    <strong>Borrowing Status: <?= $activeCount ?> / 5 active</strong>
    <p><?= $overdue ? 'This member has overdue borrowing(s). Approval is blocked until they are returned.' : ($activeCount>=5 ? 'Maximum borrowing limit reached. Approval is blocked.' : (5-$activeCount).' borrowing slot(s) remaining.') ?></p>
</div>

<?php if ($overdue): ?>
<div class="alert alert-error">
    <strong>Overdue borrowing detected.</strong>
    <?php foreach ($overdue as $x): ?>
        <div><?= e($x['borrowID'].' - '.$x['assetName'].' - due '.d($x['expectedReturnDate'],false)) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<section class="content-card asset-detail-card">
<div class="asset-detail-layout">

<div class="asset-detail-information">
    <h2>Transaction Information</h2>
    <?php foreach ($tx as $label=>$value): ?>
    <div class="asset-detail-row <?= in_array($label,['Request Message','Manager Remark'],true)?'description-row':'' ?>">
        <span class="asset-detail-label"><?= e($label) ?></span><div class="asset-detail-value"><?= e($value) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<aside class="asset-detail-side">
    <div>
        <h2>Asset Information</h2>
        <?php foreach ([
            'Asset ID'=>$t['assetID'],'Asset Name'=>$t['assetName'],'Category'=>$t['categoryName'],
            'Condition'=>$t['assetCondition'],'Availability'=>$t['availabilityStatus'],
            'Owner'=>$t['ownerName'].' ('.$t['ownerID'].')'
        ] as $label=>$value): ?>
        <div class="asset-detail-row"><span class="asset-detail-label"><?= e($label) ?></span><div class="asset-detail-value"><?= e($value) ?></div></div>
        <?php endforeach; ?>
    </div>

    <div>
        <h2>Requester Information</h2>
        <?php foreach (['Member ID'=>$t['requesterID'],'Name'=>$t['requesterName'],'Email'=>$t['requesterEmail']] as $label=>$value): ?>
        <div class="asset-detail-row"><span class="asset-detail-label"><?= e($label) ?></span><div class="asset-detail-value"><?= e($value) ?></div></div>
        <?php endforeach; ?>
    </div>

    <div class="asset-detail-actions">
    <?php if ($t['requestStatus']==='Pending'): ?>
        <form method="POST">
            <label for="expectedReturnDate">Expected Return Date *</label>
            <input type="date" id="expectedReturnDate" name="expectedReturnDate" min="<?= date('Y-m-d') ?>" required>
            <label for="managerRemark">Manager Remark</label>
            <textarea id="managerRemark" name="managerRemark" maxlength="255" rows="3"></textarea>

            <button type="submit" name="action" value="approve" class="asset-form-submit"
                    <?= $blocked?'disabled title="Approval blocked by borrowing limit or overdue item."':'' ?>
                    onclick="return confirm('Approve this borrowing request?')">Approve Request</button>

            <button type="submit" name="action" value="reject" class="asset-form-reset" formnovalidate
                    onclick="return confirm('Reject this borrowing request?')">Reject Request</button>
        </form>

    <?php elseif ($t['requestStatus']==='Approved'): ?>
        <form method="POST" onsubmit="return confirm('Confirm that the asset has been handed over?');">
            <input type="hidden" name="action" value="start">
            <button class="asset-form-submit">Start Borrowing</button>
        </form>

    <?php elseif (in_array($t['requestStatus'],['In Progress','Overdue'],true)): ?>
        <form method="POST" onsubmit="return confirm('Send a return reminder to this member?');">
            <input type="hidden" name="action" value="remind">
            <button class="asset-form-reset" <?= $reminderSent?'disabled title="Reminder already sent today."':'' ?>>
                <?= $currentOverdue?'Send Overdue Reminder':'Send Return Reminder' ?>
            </button>
        </form>

        <form method="POST" onsubmit="return confirm('Confirm the asset has been returned and complete this transaction?');">
            <input type="hidden" name="action" value="complete">
            <button class="asset-form-submit">Complete Return</button>
        </form>
    <?php endif; ?>

        <a href="manager_transactions.php" class="asset-detail-back-btn">Back</a>
    </div>
</aside>

</div>
</section>

<section class="content-card">
    <div class="card-header"><h2>Transaction History</h2></div>
    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Date & Time</th><th>Action</th><th>Done By</th><th>Activity</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?><tr><td colspan="4" class="dashboard-empty-state">No transaction activity recorded yet.</td></tr>
        <?php else: foreach ($logs as $x): ?>
        <tr><td><?= e(d($x['timeLogged'])) ?></td><td><?= e($x['actionType']) ?></td><td><?= e($x['userName']?:'System') ?></td><td><?= e($x['description']) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php endif; ?>

</main>
</div>
</div>
</body>
</html>
