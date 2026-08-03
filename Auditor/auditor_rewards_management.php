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

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error){
        $databaseError = "Unable to connect to the database.";
    }else {
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");
    }

    // Get all rewards
    $query = "SELECT * FROM rewards";
    $result = mysqli_query($connection, $query);


    // Count total rewards
    $countQuery = "SELECT COUNT(*) AS total FROM rewards";
    $countResult = mysqli_query($connection, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRewards = $countRow['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Rewards Management Dashboard - CirculAPU</title>
</head>
<body class="role-auditor">
    <div class="dashboard-container">

        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <?php include '../header.php'; ?>
    
</body>
</html>