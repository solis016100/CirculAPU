<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$roles=['Admin','Auditor','Manager','Member'];
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role']??'', $roles, true)){
    header('Location: login.php'); exit();
}

require_once 'db.php';

function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function go(string $q=''):never{header('Location: notifications.php'.($q?'?'.$q:''));exit();}

$uid=$_SESSION['user_id'];
$role=$_SESSION['user_role'];
$search=trim($_GET['search']??'');
$status=trim($_GET['status']??'');
if(!in_array($status,['','Read','Unread'],true))$status='';

$roleInfo=[
    'Admin'=>['System Administrator Notifications','System and administration updates.','Admin'],
    'Auditor'=>['Sustainability Auditor Notifications','Verification and sustainability programme updates.','Auditor'],
    'Manager'=>['Resource Manager Notifications','Asset, borrowing and return management updates.','Manager'],
    'Member'=>['Community Member Notifications','Updates about your listings, requests and activities.','Members']
];
[$title,$subtitle,$folder]=$roleInfo[$role];

$scriptDir=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
$appRoot=$scriptDir==='/'?'':$scriptDir;
$roleBase=$appRoot.'/'.$folder.'/';

$rows=[];$total=$unread=0;$error='';

try{
    $conn->set_charset('utf8mb4');

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=$_POST['action']??'';
        if($action==='read'){
            $id=trim($_POST['notificationID']??'');
            $s=$conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            $s->bind_param('ss',$id,$uid);$s->execute();$s->close();go('updated=1');
        }
        if($action==='all'){
            $s=$conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $s->bind_param('s',$uid);$s->execute();$s->close();go('allread=1');
        }
    }

    $s=$conn->prepare("SELECT COUNT(*) total,COALESCE(SUM(status='Unread'),0) unread FROM notification WHERE userID=?");
    $s->bind_param('s',$uid);$s->execute();$x=$s->get_result()->fetch_assoc();$s->close();
    $total=(int)$x['total'];$unread=(int)$x['unread'];

    $sql="SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=?";
    $types='s';$params=[$uid];
    if($search){$sql.=" AND (title LIKE ? OR content LIKE ?)";$like="%$search%";array_push($params,$like,$like);$types.='ss';}
    if($status){$sql.=" AND status=?";$params[]=$status;$types.='s';}
    $sql.=" ORDER BY createdDate DESC";

    $s=$conn->prepare($sql);$s->bind_param($types,...$params);$s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}catch(Throwable $z){
    error_log($z->getMessage());$error='Unable to load notifications. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($title)?> - CirculAPU</title>
<base href="<?=e($roleBase)?>">
<link rel="stylesheet" href="<?=e($appRoot)?>/style.css">
</head>
<body class="role-<?=e(strtolower($role))?>">
<div class="dashboard-container">
<?php include 'sidebar.php';?>
<div class="main-content">
<?php include 'header.php';?>
<main class="page-body">

<section class="management-heading">
<h1><?=e($title)?></h1>
<p><?=e($subtitle)?></p>
</section>

<?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?>
<?php if(($_GET['updated']??'')==='1'):?><div class="alert alert-success">Notification marked as read.</div><?php endif;?>
<?php if(($_GET['allread']??'')==='1'):?><div class="alert alert-success">All notifications marked as read.</div><?php endif;?>

<section class="stats-grid">
<a href="<?=e($appRoot)?>/notifications.php" class="stat-card">
<div class="stat-content"><h3>Total Notifications</h3><strong class="stat-value"><?=$total?></strong><p class="stat-description">For your account</p></div>
</a>
<a href="<?=e($appRoot)?>/notifications.php?status=Unread" class="stat-card">
<div class="stat-content"><h3>Unread</h3><strong class="stat-value"><?=$unread?></strong><p class="stat-description">Require your attention</p></div>
</a>
</section>

<section class="management-filter">
<form method="GET" action="<?=e($appRoot)?>/notifications.php">
<div class="management-filter-group search">
<label for="search">Search Notification</label>
<input id="search" name="search" value="<?=e($search)?>" placeholder="Title or message">
</div>
<div class="management-filter-group">
<label for="status">Status</label>
<select id="status" name="status">
<option value="">All Statuses</option>
<option value="Unread" <?=$status==='Unread'?'selected':''?>>Unread</option>
<option value="Read" <?=$status==='Read'?'selected':''?>>Read</option>
</select>
</div>
<div class="management-filter-buttons">
<button class="management-filter-btn">Filter</button>
<a href="<?=e($appRoot)?>/notifications.php" class="management-reset-btn">Reset</a>
</div>
</form>
</section>

<section class="content-card">
<div class="card-header">
<h2>Notifications</h2>
<?php if($unread):?>
<form method="POST" action="<?=e($appRoot)?>/notifications.php">
<input type="hidden" name="action" value="all">
<button class="management-create-btn">Mark All as Read</button>
</form>
<?php endif;?>
</div>

<div class="table-wrapper">
<table class="data-table">
<thead><tr><th>Notification</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
<tbody>
<?php if(!$rows):?>
<tr><td colspan="5" class="dashboard-empty-state">No notifications found.</td></tr>
<?php else:foreach($rows as $n):?>
<tr class="<?=$n['status']==='Unread'?'notification-unread':''?>">
<td><strong><?=e($n['title'])?></strong></td>
<td><?=e($n['content'])?></td>
<td><?=e($n['status'])?></td>
<td><?=e(date('d M Y, h:i A',strtotime($n['createdDate'])))?></td>
<td>
<?php if($n['status']==='Unread'):?>
<form method="POST" action="<?=e($appRoot)?>/notifications.php">
<input type="hidden" name="action" value="read">
<input type="hidden" name="notificationID" value="<?=e($n['notificationID'])?>">
<button class="management-action-btn" title="Mark as Read">✓</button>
</form>
<?php else:?>-<?php endif;?>
</td>
</tr>
<?php endforeach;endif;?>
</tbody>
</table>
</div>
</section>

</main>
</div></div>
</body></html>
