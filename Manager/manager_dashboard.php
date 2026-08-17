<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$isAdminPreview = ($_SESSION['user_role'] ?? '') === 'Admin' && ($_GET['preview'] ?? '') === 'Manager';
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'Manager' && !$isAdminPreview)) {
    header('Location: ../login.php');
    exit();
}

function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function activityLabel(string $a,string $r): string {
    $m=['Create'=>'Created','Update'=>'Updated','Delete'=>'Removed','Approve'=>'Approved','Reject'=>'Rejected','Submit'=>'Submitted','Assign'=>'Assigned'];
    return $r.' '.($m[$a]??$a);
}
function activityIcon(string $a): string {
    return ['Create'=>'create.png','Update'=>'edit.png','Delete'=>'delete.png'][$a]??'notification.png';
}
function managerDate(?string $v): string { return $v&&strtotime($v)?date('d M Y, h:i A',strtotime($v)):'-'; }

$managerID=$_SESSION['user_id'];
$managerName=$_SESSION['user_name']??'Resource Manager';
$totalAssets=$pendingListings=$activeTransactions=$currentBorrowed=0;
$recentActivities=$notifications=[];$databaseError='';

require_once '../db.php';

try{
    $conn->set_charset('utf8mb4');

    if ($isAdminPreview) {
        $previewUser = $conn->query(
            "SELECT userID, name FROM user
             WHERE role = 'Manager' AND status = 'Active'
             ORDER BY dateCreated ASC, userID ASC LIMIT 1"
        )->fetch_assoc();
        if ($previewUser) {
            $managerID = $previewUser['userID'];
            $managerName = $previewUser['name'];
        } else {
            $managerName = 'Resource Manager';
        }
    }

    $x=$conn->query("
        SELECT SUM(listingStatus<>'Removed') totalAssets,
               SUM(listingStatus='Pending Review') pendingListings
        FROM assets")->fetch_assoc();
    $totalAssets=(int)($x['totalAssets']??0);
    $pendingListings=(int)($x['pendingListings']??0);

    $currentBorrowed=(int)$conn->query("
        SELECT COUNT(*) total FROM borrowings
        WHERE actualReturnDate IS NULL
        AND borrowingStatus IN ('Borrowed','Active','Overdue')
    ")->fetch_assoc()['total'];

    $activeTransactions=(int)$conn->query("
        SELECT COUNT(*) total FROM transactionrequests
        WHERE requestStatus IN ('Approved','In Progress','Overdue')
    ")->fetch_assoc()['total'];

    $r=$conn->query("
        SELECT actionType,refType,refID,description,timeLogged
        FROM systemlog
        WHERE refType IN ('Asset','Category','Transaction','Borrowing')
        ORDER BY timeLogged DESC LIMIT 5");
    while($x=$r->fetch_assoc())$recentActivities[]=$x;

    $s=$conn->prepare("SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=? ORDER BY createdDate DESC LIMIT 5");
    $s->bind_param('s',$managerID);$s->execute();$r=$s->get_result();
    while($x=$r->fetch_assoc())$notifications[]=$x;
    $s->close();
}catch(Throwable $z){error_log($z->getMessage());$databaseError='Unable to load dashboard data. Please check the database connection.';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Resource Manager Dashboard - CirculAPU</title>
<link rel="stylesheet" href="../style.css"><link rel="stylesheet" href="manager_style.css?v=<?=filemtime(__DIR__.'/manager_style.css')?>">
<link rel="stylesheet" href="manager_mobile.css">
<style>
.quick-action-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
@media(max-width:1100px){.quick-action-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php';?>
<div class="main-content">
<?php include '../header.php';?>
<main class="page-body dashboard-page">

<section class="dashboard-welcome">
<div><h1>Welcome back, <?=e($managerName)?>!</h1><p>Review community asset listings, manage transactions and oversee resource-sharing activities.</p></div>
</section>

<?php if($databaseError):?><div class="dashboard-error-message"><?=e($databaseError)?></div><?php endif;?>

<section class="stats-grid" aria-label="Resource Manager statistics">
<a href="manager_assets.php" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/totalassets.png" alt=""></div>
<div class="stat-content"><h3>Total Assets</h3><strong class="stat-value"><?=$totalAssets?></strong><p class="stat-description">Community asset listings</p></div>
</a>

<a href="manager_assets.php?listingStatus=Pending+Review" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/pendingVerification.png" alt=""></div>
<div class="stat-content"><h3>Pending Listings</h3><strong class="stat-value"><?=$pendingListings?></strong><p class="stat-description">Awaiting manager review</p></div>
</a>

<a href="manager_transactions.php?activeTransaction=1" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/active-transactions.png" alt=""></div>
<div class="stat-content"><h3>Active Transactions</h3><strong class="stat-value"><?=$activeTransactions?></strong><p class="stat-description">Approved, in progress or overdue</p></div>
</a>

<a href="manager_borrowed_assets.php" class="stat-card">
<div class="stat-icon"><img src="../assets/icons/borrowed-assets.png" alt=""></div>
<div class="stat-content"><h3>Current Borrowings</h3><strong class="stat-value"><?=$currentBorrowed?></strong><p class="stat-description">Assets currently with borrowers</p></div>
</a>
</section>

<section class="dashboard-lower-grid">
<article class="content-card dashboard-panel">
<div class="card-header"><h2>Recent Activities</h2><a href="manager_recent_activities.php">View All</a></div>
<div class="table-wrapper desktop-activity-table"><table class="data-table manager-activity-table">
<thead><tr><th>Activity</th><th>Details</th><th>Time</th></tr></thead><tbody>
<?php if(!$recentActivities):?><tr><td colspan="3" class="dashboard-empty-state">No recent activities available.</td></tr>
<?php else:foreach($recentActivities as $x):?>
<tr>
<td><div class="activity-name activity-flex"><span class="activity-icon"><img src="../assets/icons/<?=e(activityIcon($x['actionType']))?>" alt=""></span><strong><?=e(activityLabel($x['actionType'],$x['refType']))?></strong></div></td>
<td><?=e($x['description'])?></td><td><?=e(managerDate($x['timeLogged']))?></td>
</tr>
<?php endforeach;endif;?>
</tbody></table></div>

<div class="mobile-activity-list">
<?php if(!$recentActivities):?>
<div class="dashboard-empty-state">No recent activities available.</div>
<?php else:foreach($recentActivities as $x):?>
<div class="mobile-activity-item">
    <span class="mobile-activity-icon"><img src="../assets/icons/<?=e(activityIcon($x['actionType']))?>" alt=""></span>
    <div class="mobile-activity-content">
        <strong><?=e(activityLabel($x['actionType'],$x['refType']))?></strong>
        <p><?=e($x['description'])?></p>
        <time><?=e(managerDate($x['timeLogged']))?></time>
    </div>
</div>
<?php endforeach;endif;?>
</div>
</article>

<article class="content-card dashboard-panel">
<div class="card-header"><h2>Notifications</h2><a href="manager_notifications.php">View All</a></div>
<div class="notification-list">
<?php if(!$notifications):?>
<div class="notification-empty-state"><img src="../assets/icons/notification.png" alt=""><p>No notifications available.</p></div>
<?php else:foreach($notifications as $x):?>
<a href="manager_notifications.php" class="notification-item <?=$x['status']==='Unread'?'notification-unread':''?>">
<div class="notification-icon"><img src="../assets/icons/notification.png" alt=""></div>
<div class="notification-content"><div class="notification-title-row"><strong><?=e($x['title'])?></strong><?php if($x['status']==='Unread'):?><span class="unread-indicator" title="Unread notification"></span><?php endif;?></div><p><?=e($x['content'])?></p><time><?=e(managerDate($x['createdDate']))?></time></div>
</a>
<?php endforeach;endif;?>
</div>
</article>
</section>

<section class="content-card quick-actions">
<div class="card-header"><h2>Quick Actions</h2></div>
<div class="quick-action-grid">
<a href="manager_assets.php?listingStatus=Pending+Review" class="quick-action"><img src="../assets/icons/view.png" alt=""><span>Review Asset Submissions</span></a>
<a href="manager_transactions.php?requestStatus=Pending" class="quick-action"><img src="../assets/icons/view.png" alt=""><span>Review Pending Requests</span></a>
<a href="manager_borrowed_assets.php" class="quick-action"><img src="../assets/icons/borrowed-assets.png" alt=""><span>Manage Returns</span></a>
<a href="manager_categories.php" class="quick-action"><img src="../assets/icons/edit.png" alt=""><span>Manage Categories</span></a>
<a href="manager_reports.php" class="quick-action"><img src="../assets/icons/report.png" alt=""><span>Generate Report</span></a>
</div>
</section>

</main>

<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php" class="active" aria-current="page">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
<span>Home</span>
</a>
<a href="manager_assets.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
<span>Assets</span>
</a>
<a href="manager_categories.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg>
<span>Categories</span>
</a>
<a href="manager_transactions.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg>
<span>Transactions</span>
</a>
<a href="manager_reports.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>
<span>Reports</span>
</a>
</nav>

</div></div>
</body></html>
