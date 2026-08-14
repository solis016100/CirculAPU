<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$managerID = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$notifications = [];
$total = $unread = 0;
$error = '';

try {
    $conn->set_charset('utf8mb4');

    /* 1. Actions */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationID = trim($_POST['notificationID'] ?? '');
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            $stmt->bind_param('ss', $notificationID, $managerID);
            $stmt->execute(); $stmt->close();

            header('Location: manager_notifications.php?updated=1'); exit();
        }

        if ($action === 'mark_all') {
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $stmt->bind_param('s', $managerID);
            $stmt->execute(); $stmt->close();

            header('Location: manager_notifications.php?allread=1'); exit();
        }
    }

    /* 2. Counts */
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

    /* 3. List */
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
        <span class="breadcrumb-current">Notifications</span>
    </div>
    <h1>Notifications</h1>
    <p>View updates and important notifications related to Resource Manager activities.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Notification marked as read.</div><?php endif; ?>
<?php if (($_GET['allread'] ?? '') === '1'): ?><div class="alert alert-success">All notifications marked as read.</div><?php endif; ?>

<!-- Summary -->
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

<!-- Filters -->

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
<span class="mobile-status-pill"><?=e($n['status'])?></span>
</div>

<p class="mobile-notification-message"><?=e($n['content'])?></p>

<?php if($n['status']==='Unread'):?>
<form method="POST" class="mobile-notification-action">
<input type="hidden" name="action" value="mark_read">
<input type="hidden" name="notificationID" value="<?=e($n['notificationID'])?>">
<button type="submit">Mark as Read</button>
</form>
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

<!-- Notification List -->
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
            <tr class="<?= $n['status'] === 'Unread' ? 'notification-unread' : '' ?>">
                <td><strong><?= e($n['title']) ?></strong></td>
                <td><?= e($n['content']) ?></td>
                <td><?= e($n['status']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($n['createdDate']))) ?></td>
                <td>
                    <?php if ($n['status'] === 'Unread'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="notificationID" value="<?= e($n['notificationID']) ?>">
                        <button type="submit" class="management-action-btn" title="Mark as Read">
                            <img src="../assets/icons/mark_as_read.png" alt="Mark as Read">
                        </button>
                    </form>
                    <?php else: ?>-<?php endif; ?>
                </td>
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
