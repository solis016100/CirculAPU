<?php
    session_start();

        // Access Control Guard for Sustainability Auditor Role
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
            header("Location: login.php");
            exit();
        }

        // Get Auditor's information from login
        $auditorID= $_SESSION['user_id'];
        $auditorName = $_SESSION['user_name'] ?? 'Auditor';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Notification Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="role-auditor">

    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            
            <?php include 'header.php'; ?>
    
</body>
</html>