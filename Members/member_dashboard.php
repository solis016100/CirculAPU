<?php
session_start();
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
$is_preview = isset($_GET['preview']);

// <<<<<<< HEAD
// Database Connection
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "circulapu_db"
);


if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: login.php");
// =======
// Access Control Guard for Community Member Role (Allows Admin Preview)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Member' && !($is_admin && $is_preview))) {
    header("Location: ../login.php");
// >>>>>>> 52a68bfa4e38e413cf43656322556d94ba2effee
    exit();
}

$userID = $_SESSION['user_id'];

// Get User Name

$nameQuery = "
SELECT name 
FROM user 
WHERE userID = '$userID'
";

$nameResult = $conn->query($nameQuery);

if ($nameResult && $nameResult->num_rows > 0) {
    $nameData = $nameResult->fetch_assoc();
    $memberName = $nameData['name'];
} else {
    $memberName = "Member";
}

// Get Total My Request

$requestQuery = "
SELECT COUNT(*) AS totalRequest
FROM transactionrequests
WHERE requesterID = '$userID'
";

$requestResult = $conn->query($requestQuery);
$requestData = $requestResult->fetch_assoc();
$totalRequest = $requestData['totalRequest'];

// Get Completed Missions

$missionQuery = "
SELECT COUNT(*) AS totalMission
FROM missionsubmission
WHERE userID = '$userID'
AND submissionStatus = 'Verified'
";

$missionResult = $conn->query($missionQuery);
$missionData = $missionResult->fetch_assoc();
$totalMission = $missionData['totalMission'];


// Get EcoPoints

$pointQuery = "
SELECT ecopoint
FROM user
WHERE userID = '$userID'
";

$pointResult = $conn->query($pointQuery);
$pointData = $pointResult->fetch_assoc();
$totalPoint = $pointData['ecopoint'];


// Get Badges Earned

$badgeQuery = "
SELECT COUNT(*) AS totalBadge
FROM `user achievement badge`
WHERE userID = '$userID'
";

$badgeResult = $conn->query($badgeQuery);
$badgeData = $badgeResult->fetch_assoc();
$totalBadge = $badgeData['totalBadge'];
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

            <!-- Welcome -->
             <h1> Welcome back, <?php echo htmlspecialchars($memberName);?> </h1>
             <p> Here is an overview of the sustainability activities. </p>

             <!-- Statistic card -->
            <div class="stats-grid">

                 <div class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/myrequest.png" alt="My Request"></div>
                    <h2><?php echo $totalRequest; ?></h2>
                    <p>My Request</p>
                </div>


                <div class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/mission.png" alt="Missions"></div>
                    <h2><?php echo $totalMission; ?></h2>
                    <p>Missions</p>
                </div>


                <div class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/point.png" alt="Eco Points"></div>
                    <h2><?php echo $totalPoint; ?></h2>
                    <p>Eco Points</p>
                </div>


                <div class="stat-card">
                    <div class="stat-icon"><img src="../assets/icons/badge.png" alt="Badges Earned"></div>
                    <h2><?php echo $totalBadge; ?></h2>
                    <p>Badges Earned</p>
                </div>

            </div>


            </main>

        </div>
    </div>



</body>
</html>