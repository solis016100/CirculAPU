<?php
session_start();

// Security Check
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'Admin' && !isset($_SESSION['admin_true_id']))) {
    header("Location: ../login.php");
    exit();
}

$target_role = $_GET['role'] ?? '';
$valid_roles = ['Manager', 'Auditor', 'Member'];

if (in_array($target_role, $valid_roles)) {
    // Save true Admin ID if not already saved
    if (!isset($_SESSION['admin_true_id'])) {
        $_SESSION['admin_true_id'] = $_SESSION['user_id'] ?? 'U001';
    }
    
    // Swap session role
    $_SESSION['simulated_role'] = $target_role;
    $_SESSION['user_role'] = $target_role;

    // Route to corresponding role dashboard
    switch ($target_role) {
        case 'Manager':
            header("Location: ../Manager/manager_dashboard.php");
            break;
        case 'Auditor':
            header("Location: ../Auditor/auditor_dashboard.php");
            break;
        case 'Member':
            header("Location: ../Members/member_dashboard.php");
            break;
    }
    exit();
} else {
    header("Location: role_simulation.php");
    exit();
}