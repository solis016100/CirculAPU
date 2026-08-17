<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once '../db.php';
require_once 'admin_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

function toggleRedirect(string $notice): never
{
    $return_data = [];
    parse_str($_POST['return_query'] ?? '', $candidate_data);
    foreach (['page', 'search', 'status', 'role', 'date_joined'] as $key) {
        if (isset($candidate_data[$key]) && is_scalar($candidate_data[$key])) {
            $return_data[$key] = (string)$candidate_data[$key];
        }
    }
    $return_data['notice'] = $notice;
    header('Location: user_management.php?' . http_build_query($return_data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !adminVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
    toggleRedirect('toggle-failed');
}

$target_user_id = trim($_POST['user_id'] ?? '');
if ($target_user_id === '' || $target_user_id === $_SESSION['user_id']) {
    toggleRedirect('self-status-blocked');
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT name, role, status FROM user WHERE userID = ? AND status != 'Deleted' FOR UPDATE");
    $stmt->bind_param('s', $target_user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !in_array($user['status'], ['Active', 'Suspended'], true)) {
        throw new RuntimeException('User is unavailable for status changes.');
    }

    if ($user['role'] === 'Admin' && $user['status'] === 'Active') {
        $active_admins = (int)$conn->query(
            "SELECT COUNT(*) AS total FROM user WHERE role = 'Admin' AND status = 'Active'"
        )->fetch_assoc()['total'];
        if ($active_admins <= 1) {
            throw new RuntimeException('The final active administrator cannot be suspended.');
        }
    }

    $new_status = $user['status'] === 'Active' ? 'Suspended' : 'Active';
    $update = $conn->prepare('UPDATE user SET status = ? WHERE userID = ?');
    $update->bind_param('ss', $new_status, $target_user_id);
    $update->execute();
    $update->close();

    adminWriteSystemLog(
        $conn,
        $_SESSION['user_id'],
        'Update',
        'User',
        $target_user_id,
        "Changed account status for {$user['name']} ($target_user_id) from {$user['status']} to $new_status"
    );

    $conn->commit();
    toggleRedirect('status-updated');
} catch (Throwable $exception) {
    $conn->rollback();
    error_log($exception->getMessage());
    toggleRedirect('toggle-failed');
}
