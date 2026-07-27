<?php
session_start();

// 1. Protection: Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['user_role'];

// 2. Route user to their role-specific dashboard file
switch ($role) {
    case 'Admin':
        header("Location: admin_dashboard.php");
        exit();

    case 'Manager':
        header("Location: manager_dashboard.php");
        exit();

    case 'Auditor':
        header("Location: auditor_dashboard.php");
        exit();

    case 'Member':
        header("Location: member_dashboard.php");
        exit();

    default:
        // Fallback or error handling
        session_destroy();
        header("Location: login.php?error=invalid_role");
        exit();
}
?>