<?php
session_start();

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Portal - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="role-member">

    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>

            <main class="page-body">
                </main>

        </div>
    </div>

</body>
</html>