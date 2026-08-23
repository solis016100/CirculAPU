<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Member') {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function go(string $query = ''): never {
    header('Location: member_notifications.php' . ($query ? '?' . $query : ''));
    exit();
}

$memberID = (string) $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$notifications = [];
$total = $unread = 0;
$error = '';

if (!in_array($status, ['', 'Read', 'Unread'], true)) $status = '';

try {
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'read') {
            $notificationID = trim($_POST['notificationID'] ?? '');
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE notificationID=? AND userID=?");
            $stmt->bind_param('ss', $notificationID, $memberID);
            $stmt->execute();
            $stmt->close();
            go('updated=1');
        }

        if ($action === 'all') {
            $stmt = $conn->prepare("UPDATE notification SET status='Read' WHERE userID=? AND status='Unread'");
            $stmt->bind_param('s', $memberID);
            $stmt->execute();
            $stmt->close();
            go('allread=1');
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='Unread'),0) unread FROM notification WHERE userID=?");
    $stmt->bind_param('s', $memberID);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int) ($counts['total'] ?? 0);
    $unread = (int) ($counts['unread'] ?? 0);

    $sql = 'SELECT notificationID,title,content,status,createdDate FROM notification WHERE userID=?';
    $types = 's';
    $params = [$memberID];

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

    $sql .= ' ORDER BY createdDate DESC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Unable to load notifications. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Member Notifications - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_new_style.css?v=<?= filemtime(__DIR__ . '/member_new_style.css') ?>">
</head>
<body class="role-member member-notification-page">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">
    <section class="management-heading">
        <h1>Notifications</h1>
        <p>Updates about your listings, requests and activities.</p>
    </section>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Notification marked as read.</div><?php endif; ?>
    <?php if (($_GET['allread'] ?? '') === '1'): ?><div class="alert alert-success">All notifications marked as read.</div><?php endif; ?>

    <section class="member-notification-stats">
        <a href="member_notifications.php" class="member-notification-stat-card">
            <h3>Total Notifications</h3><strong><?= $total ?></strong><p>For your account</p>
        </a>
        <a href="member_notifications.php?status=Unread" class="member-notification-stat-card">
            <h3>Unread</h3><strong><?= $unread ?></strong><p>Require your attention</p>
        </a>
    </section>

    <section class="member-notification-filter">
        <form method="GET">
            <div class="member-notification-filter-group search">
                <label for="search">Search Notification</label>
                <input id="search" type="search" name="search" value="<?= e($search) ?>" placeholder="Title or message">
            </div>
            <div class="member-notification-filter-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Unread" <?= $status === 'Unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="Read" <?= $status === 'Read' ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
            <div class="member-notification-filter-buttons">
                <button type="submit" class="member-notification-filter-btn">Filter</button>
                <a href="member_notifications.php" class="member-notification-reset-btn">Reset</a>
            </div>
        </form>
    </section>

    <section class="member-notification-card">
        <div class="member-notification-card-header">
            <h2>Notifications</h2>
            <?php if ($unread > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="all">
                    <button type="submit" class="member-notification-read-all-btn">Mark All as Read</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="member-notification-table-wrapper">
            <table class="member-notification-table">
                <thead><tr><th>Notification</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$notifications): ?>
                    <tr><td colspan="5" class="member-notification-empty">No notifications found.</td></tr>
                <?php else: foreach ($notifications as $notification): ?>
                    <tr class="<?= $notification['status'] === 'Unread' ? 'unread' : '' ?>">
                        <td data-label="Notification"><strong><?= e($notification['title']) ?></strong></td>
                        <td data-label="Message"><?= e($notification['content']) ?></td>
                        <td data-label="Status"><span class="member-notification-status <?= strtolower(e($notification['status'])) ?>"><?= e($notification['status']) ?></span></td>
                        <td data-label="Date"><?= e(date('d M Y, h:i A', strtotime($notification['createdDate']))) ?></td>
                        <td data-label="Action">
                            <?php if ($notification['status'] === 'Unread'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="read">
                                    <input type="hidden" name="notificationID" value="<?= e($notification['notificationID']) ?>">
                                    <button type="submit" class="member-notification-read-btn" title="Mark as Read">✓</button>
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