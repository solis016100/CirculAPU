<?php
session_start();
$_SESSION = array();
session_destroy();

// Delete persistent cookies
if (isset($_COOKIE['remember_user_id'])) {
    setcookie('remember_user_id', '', time() - 3600, '/');
    setcookie('remember_user_name', '', time() - 3600, '/');
    setcookie('remember_user_role', '', time() - 3600, '/');
    setcookie('remember_user_email', '', time() - 3600, '/');
}

header("Location: login.php");
exit();
?>