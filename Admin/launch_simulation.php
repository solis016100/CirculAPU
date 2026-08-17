<?php
session_start();

// Role simulation is a read-only Admin preview; never mutate the authenticated identity.
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$target_role = $_GET['role'] ?? '';
$role_dashboards = [
    'Manager' => '../Manager/manager_dashboard.php?preview=Manager',
    'Auditor' => '../Auditor/auditor_dashboard.php?preview=Auditor',
    'Member' => '../Members/member_dashboard.php?preview=Member',
];

if (isset($role_dashboards[$target_role])) {
    header('Location: ' . $role_dashboards[$target_role]);
    exit();
}

header("Location: role_simulation.php");
exit();
