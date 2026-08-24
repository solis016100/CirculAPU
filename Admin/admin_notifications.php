<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function notificationReturn(string $flag): never {
    $query = array_filter([
        'search' => trim($_GET['search'] ?? ''),
        'status' => trim($_GET['status'] ?? ''),
        $flag => '1',
    ], static fn ($value) => $value !== '');
    header('Location: admin_notifications.php?' . http_build_query($query));
    exit();
}

$adminID = (string) $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$notifications = [];
$total = $unread = 0;
$error = '';

if (!in_array($status, ['', 'Read', 'Unread'], true)) {
    $status = '';
}

try {
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationID = trim($_POST['notificationID'] ?? '');
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            $stmt->bind_param('ss', $notificationID, $adminID);
            $stmt->execute();
            $stmt->close();
            notificationReturn('updated');
        }

        if ($action === 'mark_all') {
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $stmt->bind_param('s', $adminID);
            $stmt->execute();
            $stmt->close();
            notificationReturn('allread');
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='Unread'), 0) unread FROM notification WHERE userID=?");
    $stmt->bind_param('s', $adminID);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total = (int) ($counts['total'] ?? 0);
    $unread = (int) ($counts['unread'] ?? 0);

    $sql = 'SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=?';
    $types = 's';
    $params = [$adminID];

    if ($search !== '') {
        $sql .= ' AND (title LIKE ? OR content LIKE ?)';
        $like = "%{$search}%";
        array_push($params, $like, $like);
        $types .= 'ss';
    }
    if ($status !== '') {
        $sql .= ' AND status=?';
        $params[] = $status;
        $types .= 's';
    }

    $sql .= ' ORDER BY createdDate DESC, notificationID DESC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $exception) {
    error_log('Unable to load admin notifications: ' . $exception->getMessage());
    $error = 'Unable to load notifications. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body admin-notification-page">
    <div class="breadcrumb">
        <a href="admin_dashboard.php">System Administrator Dashboard</a><span>&gt;</span><span>Notifications</span>
    </div>

    <section class="management-heading">
        <h1>Notification Center</h1>
        <p>View and manage system and administration updates for your account.</p>
    </section>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Notification marked as read.</div><?php endif; ?>
    <?php if (($_GET['allread'] ?? '') === '1'): ?><div class="alert alert-success">All notifications marked as read.</div><?php endif; ?>

    <section class="stats-grid admin-notification-stats">
        <a href="admin_notifications.php" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/notification.png" alt=""></div>
            <div class="stat-content"><h3>Total Notifications</h3><strong class="stat-value"><?= $total ?></strong><p class="stat-description">All administrator notifications</p></div>
        </a>
        <a href="admin_notifications.php?status=Unread" class="stat-card">
            <div class="stat-icon"><img src="../assets/icons/envelope.png" alt=""></div>
            <div class="stat-content"><h3>Unread</h3><strong class="stat-value"><?= $unread ?></strong><p class="stat-description">Require your attention</p></div>
        </a>
    </section>

    <section class="management-filter">
        <form method="GET">
            <div class="management-filter-group search">
                <label for="search">Search Notification</label>
                <div class="management-search-input"><img src="../assets/icons/search.png" alt=""><input id="search" type="search" name="search" value="<?= e($search) ?>" placeholder="Notification title or message"></div>
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
                <a href="admin_notifications.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
            </div>
        </form>
    </section>

    <section class="content-card admin-notification-list">
        <div class="card-header">
            <div><h2>Notifications</h2><p class="admin-notification-count"><?= count($notifications) ?> shown</p></div>
            <?php if ($unread > 0): ?>
            <form method="POST" onsubmit="return confirm('Mark all notifications as read?');">
                <input type="hidden" name="action" value="mark_all">
                <button type="submit" class="management-create-btn"><img src="../assets/icons/mark_as_read.png" alt=""> Mark All as Read</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="table-wrapper">
            <table class="data-table admin-notification-table">
                <thead><tr><th>Notification</th><th>Message</th><th>Status</th><th>Date &amp; Time</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$notifications): ?>
                    <tr><td colspan="5" class="dashboard-empty-state">No notifications found.</td></tr>
                <?php else: foreach ($notifications as $notification): ?>
                    <tr class="<?= $notification['status'] === 'Unread' ? 'notification-unread' : '' ?>">
                        <td data-label="Notification"><strong><?= e($notification['title']) ?></strong></td>
                        <td data-label="Message"><?= e($notification['content']) ?></td>
                        <td data-label="Status"><span class="admin-notification-status <?= strtolower(e($notification['status'])) ?>"><?= e($notification['status']) ?></span></td>
                        <td data-label="Date &amp; Time"><?= e(date('d M Y, h:i A', strtotime($notification['createdDate']))) ?></td>
                        <td data-label="Action">
                            <?php if ($notification['status'] === 'Unread'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notificationID" value="<?= e($notification['notificationID']) ?>">
                                <button type="submit" class="management-action-btn" title="Mark as read"><img src="../assets/icons/mark_as_read.png" alt="Mark as read"></button>
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
