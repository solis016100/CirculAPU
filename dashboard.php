<?php
session_start();

 
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['user_role'];

 
switch ($role) {
    case 'Admin':
        header("Location: Admin/admin_dashboard.php");
        exit();

    case 'Manager':
        header("Location: Manager/manager_dashboard.php");
        exit();

    case 'Auditor':
        header("Location: Auditor/auditor_dashboard.php");
        exit();

    case 'Member':
        header("Location: Members/member_dashboard.php");
        exit();

    default:
        session_destroy();
        header("Location: login.php");
        exit();
}
?>