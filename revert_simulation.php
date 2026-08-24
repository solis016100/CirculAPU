<?php
session_start();

if (isset($_SESSION['admin_true_id'])) {
    if (isset($_SESSION['simulation_admin_session'])) {
        // Restore the complete Admin identity captured before simulation.
        $admin_session = $_SESSION['simulation_admin_session'];
        $_SESSION['user_id'] = $admin_session['user_id'];
        $_SESSION['user_name'] = $admin_session['user_name'];
        $_SESSION['user_email'] = $admin_session['user_email'];
        $_SESSION['user_role'] = $admin_session['user_role'];
    } else {
        // Compatibility with simulation sessions created before full-session
        // identity snapshots were introduced.
        $_SESSION['user_id'] = $_SESSION['admin_true_id'];
        $_SESSION['user_role'] = 'Admin';
    }

    unset($_SESSION['admin_true_id']);
    unset($_SESSION['simulated_role']);
    unset($_SESSION['simulation_read_only']);
    unset($_SESSION['simulation_admin_session']);

    // Redirect back to Admin Role Simulation Page
    header("Location: Admin/role_simulation.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
