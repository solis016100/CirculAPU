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
<section class="management-filter">
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
<section class="content-card">
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
</div>
</div>
</body>
</html>
