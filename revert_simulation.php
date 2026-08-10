<?php
session_start();

if (isset($_SESSION['admin_true_id'])) {
    // Restore Admin Role
    $_SESSION['user_role'] = 'Admin';
    unset($_SESSION['admin_true_id']);
    unset($_SESSION['simulated_role']);

    // Redirect back to Admin Role Simulation Page
    header("Location: Admin/role_simulation.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}