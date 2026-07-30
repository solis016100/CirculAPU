<?php
    session_start();

    // Access Control Guard for Sustainability Auditor Role
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit();
    }

    // Get Auditor's Name from login
    $auditorName = $_SESSION['user_name'] ?? 'Auditor';

    // Default Dashboard Values
    $pendingAssets = 0;
    $pendingMissions = 0;
    $rewardRedemptions = 0;
    $userTierUpgrades = 0;

    $recentActivities = [];

    // Connect to Database
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if (!$conn->connect_error){
        // (UTF-8) allows the database to handle different languages
        $conn->set_charset("utf8mb4");
    }

    // Count Function
    function getCount(mysqli $conn, string $sql): int{
        $result = $conn->query($sql);
        if ($result && ($row = $result->fetch_assoc())){
            return (int) $row['total'];
        }
        return 0;
    }

    // Count pending asset verification
    $pendingAssets = getCount($conn, "SELECT COUNT(*) AS total FROM assets WHERE verificationStatus = 'Pending'");

    // Count pending mission submission
    $pendingMissions = getCount($conn, "SELECT COUNT(*) AS total FROM missionsubmission WHERE submissionStatus = 'Pending'");

    // Count reward redemption
    $rewardRedemptions = getCount($conn, "SELECT COUNT(*) AS total FROM rewardredemption");

    // Count user tier records
    $userTierUpgrades = getCount($conn, "SELECT COUNT(*) AS total FROM usertier");

    // Recent Activities table data


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Dashboard - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="role-auditor">

    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            
            <?php include 'header.php'; ?>

            <main class="page-body">
                </main>

        </div>
    </div>

</body>
</html>

