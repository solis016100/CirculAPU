<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function activityLabel(string $action, string $ref): string {
    if ($action === 'Delete') return $ref === 'Asset' ? 'Removed Asset' : "Deleted $ref";
    $labels = ['Create'=>'Created','Submit'=>'Submitted','Approve'=>'Approved','Reject'=>'Rejected','Assign'=>'Assigned','Update'=>'Updated'];
    return ($labels[$action] ?? $action) . " $ref";
}
function activityIcon(string $action): string {
    return match ($action) {
        'Create' => 'create.png',
        'Update', 'Assign' => 'edit.png',
        'Delete', 'Reject' => 'delete.png',
        'Approve' => 'mark_as_read.png',
        'Submit' => 'pendingVerification.png',
        default => 'notification.png'
    };
}

$search = trim($_GET['search'] ?? '');
$refType = trim($_GET['refType'] ?? '');
$actionType = trim($_GET['actionType'] ?? '');
$refTypes = ['Asset','Category','Transaction','Borrowing'];
$actionTypes = ['Create','Submit','Approve','Reject','Assign','Update','Delete'];
$activities = [];
$error = '';

try {
    $conn->set_charset('utf8mb4');

    $sql = "
        SELECT sl.logID,sl.userID,u.name userName,u.role userRole,
               sl.actionType,sl.refType,sl.refID,sl.description,sl.timeLogged
        FROM systemlog sl
        LEFT JOIN user u ON sl.userID=u.userID
        WHERE sl.refType IN ('Asset','Category','Transaction','Borrowing')
    ";
    $types = '';
    $params = [];

    if ($search) {
        $sql .= " AND (sl.refID LIKE ? OR sl.description LIKE ? OR sl.userID LIKE ? OR u.name LIKE ?)";
        $like = "%$search%";
        array_push($params, $like, $like, $like, $like);
        $types .= 'ssss';
    }
    if (in_array($refType, $refTypes, true)) {
        $sql .= " AND sl.refType=?";
        $params[] = $refType; $types .= 's';
    }
    if (in_array($actionType, $actionTypes, true)) {
        $sql .= " AND sl.actionType=?";
        $params[] = $actionType; $types .= 's';
    }

    $sql .= " ORDER BY sl.timeLogged DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load recent activities. Please try again.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activities - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
    <link rel="stylesheet" href="manager_mobile.css">
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
        <span class="breadcrumb-current">Recent Activities</span>
    </div>
    <h1>Recent Activities</h1>
    <p>View activity records related to asset, category, transaction and borrowing management.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>


<section class="mobile-recent-activities-ui">
<details class="mobile-filter-card" <?=($search||$refType||$actionType)?'open':''?>>
<summary><span>Filter Activities</span><?php if($search||$refType||$actionType):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<label><span>Search</span><input name="search" value="<?=e($search)?>" placeholder="Reference ID, user or activity"></label>
<div class="mobile-filter-grid">
<label><span>Module</span><select name="refType">
<option value="">All Modules</option>
<?php foreach($refTypes as $v):?><option value="<?=e($v)?>" <?=$refType===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?>
</select></label>
<label><span>Action</span><select name="actionType">
<option value="">All Actions</option>
<?php foreach($actionTypes as $v):?><option value="<?=e($v)?>" <?=$actionType===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?>
</select></label>
</div>
<div class="mobile-filter-actions"><a href="manager_recent_activities.php">Reset</a><button>Apply Filters</button></div>
</form>
</details>

<div class="mobile-section-heading">
<div><h2>Activity History</h2><p><?=count($activities)?> record<?=count($activities)===1?'':'s'?></p></div>
</div>

<?php if(!$activities):?>
<div class="mobile-empty-card">No activities found.</div>
<?php else:foreach($activities as $a):?>
<article class="mobile-activity-history-card">
<div class="mobile-activity-history-top">
<div class="mobile-activity-history-title">
<span class="activity-icon"><img src="../assets/icons/<?=e(activityIcon($a['actionType']))?>" alt=""></span>
<div>
<h3><?=e(activityLabel($a['actionType'],$a['refType']))?></h3>
<p><?=e($a['refID']?:'-')?> · <?=e($a['refType'])?></p>
</div>
</div>
<span class="role-badge"><?=e($a['userRole']?:'Unknown')?></span>
</div>

<div class="mobile-activity-history-meta">
<div><span>Performed By</span><strong><?=e(($a['userName']?:'Unknown User').' ('.$a['userID'].')')?></strong></div>
<div><span>Date & Time</span><strong><?=e(date('d M Y, h:i A',strtotime($a['timeLogged'])))?></strong></div>
</div>

<div class="mobile-activity-history-details">
<span>Details</span>
<p><?=e($a['description']?:'-')?></p>
</div>
</article>
<?php endforeach;endif;?>
</section>

<section class="management-filter desktop-activity-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Activity</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" value="<?= e($search) ?>" placeholder="Reference ID, user or activity">
        </div>
    </div>

    <div class="management-filter-group">
        <label for="refType">Module</label>
        <select id="refType" name="refType">
            <option value="">All Modules</option>
            <?php foreach ($refTypes as $v): ?>
            <option value="<?= e($v) ?>" <?= $refType === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="management-filter-group">
        <label for="actionType">Action</label>
        <select id="actionType" name="actionType">
            <option value="">All Actions</option>
            <?php foreach ($actionTypes as $v): ?>
            <option value="<?= e($v) ?>" <?= $actionType === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
        <a href="manager_recent_activities.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
    </div>
</form>
</section>

<section class="content-card desktop-activity-history">
    <div class="card-header"><h2>Activity History</h2></div>
    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Activity</th><th>Reference</th><th>Performed By</th><th>Details</th><th>Date & Time</th></tr></thead>
        <tbody>
        <?php if (!$activities): ?>
            <tr><td colspan="5" class="dashboard-empty-state">No activities found.</td></tr>
        <?php else: foreach ($activities as $a): ?>
            <tr>
                <td>
                    <div class="activity-name activity-flex">
                        <span class="activity-icon"><img src="../assets/icons/<?= e(activityIcon($a['actionType'])) ?>" alt=""></span>
                        <strong><?= e(activityLabel($a['actionType'], $a['refType'])) ?></strong>
                    </div>
                </td>
                <td><?= e($a['refID'] ?: '-') ?></td>
                <td>
                    <?= e(($a['userName'] ?: 'Unknown User') . ' (' . $a['userID'] . ')') ?>
                    <span class="role-badge"><?= e($a['userRole'] ?: 'Unknown') ?></span>
                </td>
                <td><?= e($a['description'] ?: '-') ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($a['timeLogged']))) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>

</main>

<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php" class="active" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span></a>
<a href="manager_assets.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span></a>
<a href="manager_categories.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span></a>
<a href="manager_transactions.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span></a>
<a href="manager_reports.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span></a>
</nav>

</div>
</div>
</body>
</html>
