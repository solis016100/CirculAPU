<?php
session_start();

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$target_role = $_GET['role'] ?? '';
$role_dashboards = [
    'Manager' => '../Manager/manager_dashboard.php',
    'Auditor' => '../Auditor/auditor_dashboard.php',
    'Member' => '../Members/member_dashboard.php',
];

if (!isset($role_dashboards[$target_role])) {
    header("Location: role_simulation.php");
    exit();
}

require_once '../db.php';

$preview_user_statement = $conn->prepare(
    "SELECT userID, name, email
     FROM user
     WHERE role = ? AND status = 'Active'
     ORDER BY dateCreated ASC, userID ASC
     LIMIT 1"
);
$preview_user_statement->bind_param('s', $target_role);
$preview_user_statement->execute();
$preview_user = $preview_user_statement->get_result()->fetch_assoc();
$preview_user_statement->close();

if (!$preview_user) {
    header('Location: role_simulation.php?' . http_build_query([
        'error' => 'no_preview_user',
        'role' => $target_role,
    ]));
    exit();
}

 
 
$_SESSION['simulation_admin_session'] = [
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'] ?? 'Admin',
    'user_email' => $_SESSION['user_email'] ?? '',
    'user_role' => $_SESSION['user_role'],
];
$_SESSION['admin_true_id'] = $_SESSION['user_id'];
$_SESSION['simulated_role'] = $target_role;
$_SESSION['simulation_read_only'] = true;

$_SESSION['user_id'] = $preview_user['userID'];
$_SESSION['user_name'] = $preview_user['name'];
$_SESSION['user_email'] = $preview_user['email'];
$_SESSION['user_role'] = $target_role;

header('Location: ' . $role_dashboards[$target_role]);
exit();
