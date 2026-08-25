<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function notificationReturn(string $flag): never {
    $q=array_filter(['search'=>trim($_GET['search']??''),'status'=>trim($_GET['status']??'')],fn($v)=>$v!=='');
    $q[$flag]='1'; header('Location: manager_notifications.php?'.http_build_query($q)); exit();
}
function referencedID(mysqli $conn,string $table,string $column,string $content):string {
    $allowed=[
        'assets'=>['assetID'],
        'user'=>['userID'],
        'transactionrequests'=>['transactionID']
    ];
    if(!isset($allowed[$table])||!in_array($column,$allowed[$table],true))return '';
    $stmt=$conn->prepare("SELECT $column refID FROM $table WHERE ? LIKE CONCAT('%',$column,'%') ORDER BY CHAR_LENGTH($column) DESC LIMIT 1");
    $stmt->bind_param('s',$content);$stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();$stmt->close();
    return $row['refID']??'';
}
function notificationTarget(mysqli $conn,array $notification,string $back):string {
    $title=strtolower(trim((string)($notification['title']??'')));
    $content=(string)($notification['content']??'');
    $assetReview=str_contains($title,'new asset for review')
        ||str_contains($title,'asset resubmitted for review')
        ||str_contains($title,'new asset listing submission');
    if($assetReview){
        $assetID=referencedID($conn,'assets','assetID',$content);
        if(!$assetID)return '';
        return 'review_asset_submission.php?'.http_build_query(['assetID'=>$assetID,'source'=>'notifications','back'=>$back]);
    }
    $borrowRequest=str_contains($title,'borrow request')
        ||str_contains($title,'borrowing request')
        ||str_contains($title,'urgent borrowing request')
        ||str_contains($title,'new borrowing request');
    if($borrowRequest){
        $transactionID=referencedID($conn,'transactionrequests','transactionID',$content);
        if(!$transactionID){
            $assetID=referencedID($conn,'assets','assetID',$content);
            $requesterID=referencedID($conn,'user','userID',$content);
            if($assetID){
                if($requesterID){
                    $stmt=$conn->prepare("SELECT transactionID FROM transactionrequests WHERE assetID=? AND requesterID=? ORDER BY CASE WHEN requestStatus='Pending' THEN 0 ELSE 1 END,requestDate DESC LIMIT 1");
                    $stmt->bind_param('ss',$assetID,$requesterID);
                }else{
                    $stmt=$conn->prepare("SELECT transactionID FROM transactionrequests WHERE assetID=? ORDER BY CASE WHEN requestStatus='Pending' THEN 0 ELSE 1 END,requestDate DESC LIMIT 1");
                    $stmt->bind_param('s',$assetID);
                }
                $stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
                $transactionID=$row['transactionID']??'';
            }
        }
        if(!$transactionID)return '';
        return 'manager_transaction_view.php?'.http_build_query(['transactionID'=>$transactionID,'source'=>'notifications','back'=>$back]);
    }
    if(str_contains($title,'overdue return alert')){
        $transactionID=referencedID($conn,'transactionrequests','transactionID',$content);
        if(!$transactionID)return '';
        $stmt=$conn->prepare("SELECT borrowingStatus,actualReturnDate FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $stmt->bind_param('s',$transactionID);$stmt->execute();$b=$stmt->get_result()->fetch_assoc();$stmt->close();
        $active=$b&&empty($b['actualReturnDate'])&&in_array($b['borrowingStatus'],['Borrowed','Active','Overdue'],true);
        $file=$active?'manager_borrowed_asset_view.php':'manager_transaction_view.php';
        return $file.'?'.http_build_query(['transactionID'=>$transactionID,'source'=>'notifications','back'=>$back]);
    }
    if(str_contains($title,'reservation protection attention')||str_contains($title,'reservation auto-cancelled')){
        $transactionID=referencedID($conn,'transactionrequests','transactionID',$content);
        if(!$transactionID)return '';
        return 'manager_transaction_view.php?'.http_build_query(['transactionID'=>$transactionID,'source'=>'notifications','back'=>$back]);
    }
    return '';
}

$managerID = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$notifications = [];
$total = $unread = 0;
$error = '';
$notificationBack=http_build_query(array_filter(['search'=>$search,'status'=>$status],fn($v)=>$v!==''));

try {
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'open_notification') {
            $notificationID = trim($_POST['notificationID'] ?? '');
            $stmt = $conn->prepare("SELECT notificationID,title,content,status,createdDate FROM notification WHERE notificationID=? AND userID=? LIMIT 1");
            $stmt->bind_param('ss', $notificationID, $managerID);
            $stmt->execute(); $notification = $stmt->get_result()->fetch_assoc(); $stmt->close();

            if ($notification) {
                $target = notificationTarget($conn, $notification, $notificationBack);
                if ($target !== '') {
                    $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=? AND status='Unread'");
                    $stmt->bind_param('ss', $notificationID, $managerID);
                    $stmt->execute(); $stmt->close();
                    header('Location: ' . $target); exit();
                }
            }
            $error = 'This notification is informational and does not have a related page to open.';
        }

        if ($action === 'mark_read') {
            $notificationID = trim($_POST['notificationID'] ?? '');
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            $stmt->bind_param('ss', $notificationID, $managerID);
            $stmt->execute(); $stmt->close();

            notificationReturn('updated');
        }

        if ($action === 'mark_all') {
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $stmt->bind_param('s', $managerID);
            $stmt->execute(); $stmt->close();

            notificationReturn('allread');
        }
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) total,SUM(status='Unread') unread
        FROM notification WHERE userID=?
    ");
    $stmt->bind_param('s', $managerID);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int)($counts['total'] ?? 0);
    $unread = (int)($counts['unread'] ?? 0);

    $sql = "SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=?";
    $types = 's';
    $params = [$managerID];

    if ($search) {
        $sql .= " AND (title LIKE ? OR content LIKE ?)";
        $like = "%$search%";
        array_push($params, $like, $like);
        $types .= 'ss';
    }

    if (in_array($status, ['Read','Unread'], true)) {
        $sql .= " AND status=?";
        $params[] = $status;
        $types .= 's';
    }

    $sql .= " ORDER BY createdDate DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach($notifications as &$notification){
        $notification['actionable']=notificationTarget($conn,$notification,$notificationBack)!=='';
    }
    unset($notification);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load notifications. Please try again.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css?v=<?=filemtime(__DIR__.'/manager_style.css')?>">
    <link rel="stylesheet" href="manager_mobile.css">
    <style>
    .notification-actionable{cursor:pointer}
    .notification-actionable:hover td{background:#f8fbfd}
    .notification-action-buttons{display:flex;align-items:center;justify-content:flex-start;gap:7px}
    .mobile-notification-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
    .mobile-notification-actions.single{grid-template-columns:1fr}
    .mobile-notification-actions form{margin:0}
    .mobile-notification-actions button{width:100%;min-height:40px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font:inherit;font-size:12px;font-weight:700}
    .mobile-notification-actions .open-notification{border-color:var(--primary-color);background:var(--primary-color);color:#fff}
    </style>
</head>

<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb">
        <a href="manager_dashboard.php">Dashboard</a><span>›</span>
        <span class="breadcrumb-current">Notifications</span>
    </div>
    <h1>Notifications</h1>
    <p>View updates and important notifications related to Resource Manager activities.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Notification marked as read.</div><?php endif; ?>
<?php if (($_GET['allread'] ?? '') === '1'): ?><div class="alert alert-success">All notifications marked as read.</div><?php endif; ?>

<section class="stats-grid">
    <a href="manager_notifications.php" class="stat-card">
        <div class="stat-icon"><img src="../assets/icons/notification.png" alt=""></div>
        <div class="stat-content">
            <h3>Total Notifications</h3><strong class="stat-value"><?= $total ?></strong>
            <p class="stat-description">All manager notifications</p>
        </div>
    </a>

    <a href="manager_notifications.php?status=Unread" class="stat-card">
        <div class="stat-icon"><img src="../assets/icons/envelope.png" alt=""></div>
        <div class="stat-content">
            <h3>Unread</h3><strong class="stat-value"><?= $unread ?></strong>
            <p class="stat-description">Require your attention</p>
        </div>
    </a>
</section>

<section class="mobile-notifications-ui">
<details class="mobile-filter-card" <?=($search||$status)?'open':''?>>
<summary><span>Filter Notifications</span><?php if($search||$status):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<label><span>Search</span><input name="search" value="<?=e($search)?>" placeholder="Notification title or content"></label>
<label><span>Status</span><select name="status">
<option value="">All Statuses</option>
<option value="Unread" <?=$status==='Unread'?'selected':''?>>Unread</option>
<option value="Read" <?=$status==='Read'?'selected':''?>>Read</option>
</select></label>
<div class="mobile-filter-actions"><a href="manager_notifications.php">Reset</a><button>Apply Filters</button></div>
</form>
</details>

<div class="mobile-section-heading">
<div><h2>All Notifications</h2><p><?=count($notifications)?> shown · <?=$unread?> unread</p></div>
<?php if($unread>0):?>
<form method="POST" onsubmit="return confirm('Mark all notifications as read?');">
<input type="hidden" name="action" value="mark_all">
<button type="submit" class="mobile-mark-all">Mark All Read</button>
</form>
<?php endif;?>
</div>

<?php if(!$notifications):?>
<div class="mobile-empty-card">No notifications found.</div>
<?php else:foreach($notifications as $n):?>
<article class="mobile-notification-card <?=$n['status']==='Unread'?'unread':''?>">
<div class="mobile-notification-top">
<div>
<h3><?=e($n['title'])?></h3>
<p><?=e(date('d M Y, h:i A',strtotime($n['createdDate'])))?></p>
</div>
<span class="mobile-status-pill" data-status="<?=e($n['status'])?>"><?=e($n['status'])?></span>
</div>

<p class="mobile-notification-message"><?=e($n['content'])?></p>

<?php if($n['actionable']||$n['status']==='Unread'):?>
<div class="mobile-notification-actions <?=($n['actionable']&&$n['status']==='Unread')?'':'single'?>">
<?php if($n['actionable']):?>
<form method="POST">
<input type="hidden" name="action" value="open_notification">
<input type="hidden" name="notificationID" value="<?=e($n['notificationID'])?>">
<button type="submit" class="open-notification">Open</button>
</form>
<?php endif;?>
<?php if($n['status']==='Unread'):?>
<form method="POST">
<input type="hidden" name="action" value="mark_read">
<input type="hidden" name="notificationID" value="<?=e($n['notificationID'])?>">
<button type="submit">Mark as Read</button>
</form>
<?php endif;?>
</div>
<?php endif;?>
</article>
<?php endforeach;endif;?>
</section>

<section class="management-filter desktop-notification-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Notification</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" value="<?= e($search) ?>" placeholder="Notification title or content">
        </div>
    </div>

    <div class="management-filter-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All Statuses</option>
            <option value="Unread" <?= $status === 'Unread' ? 'selected' : '' ?>>Unread</option>
            <option value="Read" <?= $status === 'Read' ? 'selected' : '' ?>>Read</option>
        </select>
    </div>

    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
        <a href="manager_notifications.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
    </div>
</form>
</section>

<section class="content-card desktop-notification-list">
    <div class="card-header">
        <h2>All Notifications</h2>

        <?php if ($unread > 0): ?>
        <form method="POST" onsubmit="return confirm('Mark all notifications as read?');">
            <input type="hidden" name="action" value="mark_all">
            <button type="submit" class="management-create-btn">
                <img src="../assets/icons/mark_as_read.png" alt=""> Mark All as Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Notification</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$notifications): ?>
            <tr><td colspan="5" class="dashboard-empty-state">No notifications found.</td></tr>
        <?php else: foreach ($notifications as $n): ?>
            <tr class="<?= $n['status'] === 'Unread' ? 'notification-unread ' : '' ?><?= $n['actionable'] ? 'notification-actionable' : '' ?>" <?=$n['actionable']?'ondblclick="openManagerNotification(\''.e($n['notificationID']).'\')" title="Double-click to open related page"':''?>>
                <td><strong><?= e($n['title']) ?></strong></td>
                <td><?= e($n['content']) ?></td>
                <td><span class="status-badge" data-status="<?=e($n['status'])?>"><?=e($n['status'])?></span></td>
                <td><?= e(date('d M Y, h:i A', strtotime($n['createdDate']))) ?></td>
                <td onclick="event.stopPropagation()" ondblclick="event.stopPropagation()">
                    <?php if ($n['actionable'] || $n['status'] === 'Unread'): ?>
                    <div class="notification-action-buttons">
                        <?php if ($n['actionable']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="open_notification">
                            <input type="hidden" name="notificationID" value="<?= e($n['notificationID']) ?>">
                            <button type="submit" class="management-action-btn" title="Open related page">
                                <img src="../assets/icons/view.png" alt="Open">
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($n['status'] === 'Unread'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notificationID" value="<?= e($n['notificationID']) ?>">
                            <button type="submit" class="management-action-btn" title="Mark as Read">
                                <img src="../assets/icons/mark_as_read.png" alt="Mark as Read">
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>-<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

</main>

</div>
</div>
<script>
function openManagerNotification(id){
    const form=document.createElement('form');
    form.method='POST';
    form.style.display='none';
    const action=document.createElement('input');
    action.type='hidden';action.name='action';action.value='open_notification';
    const notification=document.createElement('input');
    notification.type='hidden';notification.name='notificationID';notification.value=id;
    form.append(action,notification);
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
