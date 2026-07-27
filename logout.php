<?php
session_start();
session_unset();
session_destroy();

// Delete Remember Me cookies
setcookie('remember_user_id', '', time() - 3600, "/");
setcookie('remember_user_name', '', time() - 3600, "/");
setcookie('remember_user_role', '', time() - 3600, "/");

header("Location: login.php");
exit();
?>