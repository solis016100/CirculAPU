<?php
session_start();

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

// Get Member's information from login
$memberID = $_SESSION['user_id'];
$memberName = $_SESSION['user_name'] ?? 'Member';
$userID = $memberID;

// Connect to Database
require_once '../db.php';

if ($conn->connect_error) {
    $databaseError = "Unable to connect to the database.";
} else {
    // UTF-8 allows the database to handle different languages
    $conn->set_charset("utf8mb4");
}

/* User */
$userResult=$conn->query("
SELECT name,ecopoint
FROM user
WHERE userID='$userID'
");

$user=$userResult->fetch_assoc();
$memberName=$user['name']??'Member';

/* Current EcoPoints - can be spent on rewards */
$currentPoint=$user['ecopoint']??0;

/* Total Earned EcoPoints - used for badge and tier progress */
$totalEarnedPointResult=$conn->query("
SELECT COALESCE(SUM(points),0) AS totalEarned
FROM ecopointstransaction
WHERE userID='$userID'
AND transactionType='Earned'
");

$totalEarnedPointData=$totalEarnedPointResult->fetch_assoc();
$totalEarnedPoint=$totalEarnedPointData['totalEarned']??0;

/* Request Count
   Same source as My Request page:
   Borrow Request = transactionrequests
   Asset Update   = assets owned by the member
*/
$requestResult=$conn->query("
SELECT
(SELECT COUNT(*) FROM transactionrequests WHERE requesterID='$userID')
+
(SELECT COUNT(*) FROM assets WHERE ownerID='$userID') AS total
");

$totalRequest=$requestResult->fetch_assoc()['total']??0;

/* Recent Requests
   Asset Update uses assets.listingStatus so the dashboard
   shows the same asset information/status as My Request.
*/
$requestListResult=$conn->query("
SELECT
    a.assetName AS requestName,
    'Borrow Request' AS requestType,
    tr.requestStatus AS requestStatus,
    tr.requestDate AS requestDate
FROM transactionrequests tr
INNER JOIN assets a ON tr.assetID=a.assetID
WHERE tr.requesterID='$userID'

UNION ALL

SELECT
    a.assetName AS requestName,
    'Asset Update' AS requestType,
    a.listingStatus AS requestStatus,
    a.updatedDate AS requestDate
FROM assets a
WHERE a.ownerID='$userID'

ORDER BY requestDate DESC
LIMIT 4
");

/* My Missions */
$missionResult=$conn->query("
SELECT COUNT(*) AS total
FROM missionsubmission
WHERE userID='$userID'
");

$totalMission=$missionResult->fetch_assoc()['total']??0;

/* Completed Missions For Badge */
$completedMissionResult=$conn->query("
SELECT COUNT(*) AS total
FROM missionsubmission
WHERE userID='$userID'
AND submissionStatus IN ('Approved','Verified')
");

$totalCompletedMission=$completedMissionResult->fetch_assoc()['total']??0;

/* Available Missions */
$missionListResult=$conn->query("
SELECT missionName,missionDescription,ecoPointsReward
FROM missions
WHERE status='Active'
ORDER BY missionID ASC
LIMIT 3
");

/* Latest Notifications */
$notificationResult=$conn->query("
SELECT title,content,status,createdDate
FROM notification
WHERE userID='$userID'
ORDER BY createdDate DESC
LIMIT 3
");

/* Completed Exchanges */
$exchangeResult=$conn->query("
SELECT COUNT(*) AS total
FROM transactionrequests
WHERE requesterID='$userID'
AND requestStatus='Completed'
");

$totalExchange=$exchangeResult->fetch_assoc()['total']??0;

/* Badges Earned */
$totalBadge=0;

/* AB001 - Complete 1 Mission */
if($totalCompletedMission>=1){
    $totalBadge++;
}

/* AB002 - Complete 5 Exchanges */
if($totalExchange>=5){
    $totalBadge++;
}

/* AB003 - Reach 500 Total EcoPoints */
if($totalEarnedPoint>=500){
    $totalBadge++;
}

/* Current Tier */
$tierResult=$conn->query("
SELECT *
FROM tiers
WHERE minimumEcoPoints<=$totalEarnedPoint
ORDER BY minimumEcoPoints DESC
LIMIT 1
");

$currentTier=null;

if($tierResult&&$tierResult->num_rows>0){
    $currentTier=$tierResult->fetch_assoc();
}else{
    /* Fallback to lowest tier in database */
    $lowestTierResult=$conn->query("
    SELECT *
    FROM tiers
    ORDER BY minimumEcoPoints ASC
    LIMIT 1
    ");

    if($lowestTierResult&&$lowestTierResult->num_rows>0){
        $currentTier=$lowestTierResult->fetch_assoc();
    }
}

/* Next Tier */
$nextTier=null;
$progress=100;
$pointsLeft=0;

if($currentTier){

    $currentMin=(int)$currentTier['minimumEcoPoints'];

    $nextResult=$conn->query("
    SELECT *
    FROM tiers
    WHERE minimumEcoPoints>$currentMin
    ORDER BY minimumEcoPoints ASC
    LIMIT 1
    ");

    if($nextResult&&$nextResult->num_rows>0){

        $nextTier=$nextResult->fetch_assoc();
        $nextMin=(int)$nextTier['minimumEcoPoints'];

        if($nextMin>$currentMin){
            $progress=(($totalEarnedPoint-$currentMin)/($nextMin-$currentMin))*100;
            $progress=max(0,min(100,$progress));
        }else{
            $progress=0;
        }

        $pointsLeft=max(0,$nextMin-$totalEarnedPoint);

    }else{
        $progress=100;
        $pointsLeft=0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Member Portal - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="member_style.css">
</head>

<body class="role-member">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<!-- Dashboard Heading -->
<div class="member-dashboard-heading">
    <div>
        <h1>
            Welcome back,
            <?php echo htmlspecialchars($memberName); ?>
        </h1>

        <p>Here is an overview of the sustainability activities.</p>
    </div>

    <a href="member_reports.php" class="member-report-btn">
        <img src="../assets/icons/member-report.png" alt="">
        Submit Report
    </a>
</div> 

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <img src="../assets/icons/myrequest.png" alt="Request">
        </div>
        <h2><?php echo $totalRequest; ?></h2>
        <p>My Request</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <img src="../assets/icons/mission.png" alt="Mission">
        </div>
        <h2><?php echo $totalMission; ?></h2>
        <p>Missions</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <img src="../assets/icons/point.png" alt="EcoPoints">
        </div>
        <h2><?php echo $currentPoint; ?></h2>
        <p>EcoPoints</p>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <img src="../assets/icons/badge.png" alt="Badge">
        </div>
        <h2><?php echo $totalBadge; ?></h2>
        <p>Badges Earned</p>
    </div>
</div>

<!-- Quick Access -->
<div class="main-quick-grid">

    <!-- Recent Requests -->
    <div class="dashboard-card main-request-quick">
        <div class="dashboard-card-header">
            <h2>My Recent Requests</h2>
            <a href="member_requests.php">View All</a>
        </div>

        <div class="recent-request-table">
            <div class="recent-request-row recent-request-title">
                <span>Request</span>
                <span>Type</span>
                <span>Status</span>
                <span>Date</span>
            </div>

            <?php if($requestListResult&&$requestListResult->num_rows>0): ?>
                <?php while($request=$requestListResult->fetch_assoc()): ?>
                    <?php
                    $status=$request['requestStatus'];

                    // Keep the real database status.
                    // Only normalize the CSS class name.
                    $statusClass=strtolower(trim($status));

                    if($statusClass==='pending review'){
                        $statusClass='pending';
                    }elseif($statusClass==='ready for verification'){
                        $statusClass='published';
                    }elseif($statusClass==='removed'){
                        $statusClass='rejected';
                    }else{
                        $statusClass=str_replace(' ','-',$statusClass);
                    }
                    ?>

                    <div class="recent-request-row">
                        <span class="request-name">
                            <?php echo htmlspecialchars($request['requestName']); ?>
                        </span>

                        <span>
                            <?php echo htmlspecialchars($request['requestType']); ?>
                        </span>

                        <span>
                            <span class="request-status <?php echo htmlspecialchars($statusClass); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </span>

                        <span>
                            <?php echo date("d M Y",strtotime($request['requestDate'])); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="dashboard-empty">No recent requests found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EcoPoints Progress -->
    <div class="dashboard-card main-reward-quick">
        <div class="dashboard-card-header">
            <h2>EcoPoints Progress</h2>
        </div>

        <div class="ecopoint-progress">
            <div class="point-top">
                <div>
                    <strong><?php echo $totalEarnedPoint; ?></strong>
                    <span>Total EcoPoints</span>
                </div>

                <?php if($nextTier): ?>
                    <p>
                        Next: <?php echo htmlspecialchars($nextTier['tierName']); ?><br>
                        <?php echo $pointsLeft; ?> points to go
                    </p>
                <?php else: ?>
                    <p>Highest tier reached</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-progress">
                <div class="dashboard-progress-bar"
                style="width:<?php echo $progress; ?>%">
                </div>
            </div>

            <span class="progress-number">
                <?php echo round($progress); ?>%
            </span>

            <a href="member_rewards.php" class="dashboard-button">
                View Rewards & Catalog →
            </a>
        </div>
    </div>

    <!-- Available Missions -->
    <div class="dashboard-card main-mission-quick">
        <div class="dashboard-card-header">
            <h2>Available Missions</h2>
            <a href="member_missions.php">Explore All</a>
        </div>

        <div class="available-mission-list">
            <?php if($missionListResult&&$missionListResult->num_rows>0): ?>
                <?php while($mission=$missionListResult->fetch_assoc()): ?>
                    <div class="mission-preview-item">
                        <div class="mission-preview-info">
                            <h3>
                                <?php echo htmlspecialchars($mission['missionName']); ?>
                            </h3>

                            <p>
                                <?php echo htmlspecialchars($mission['missionDescription']); ?>
                            </p>
                        </div>

                        <div class="mission-preview-point">
                            <strong>
                                <?php echo $mission['ecoPointsReward']; ?>
                            </strong>
                            <span>points</span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="dashboard-empty">No available missions.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications -->
    <div class="dashboard-card main-notification-quick">
        <div class="dashboard-card-header">
            <h2>Notifications</h2>
            <a href="member_notifications.php">View All</a>
        </div>

        <div class="notification-preview">
            <?php if($notificationResult&&$notificationResult->num_rows>0): ?>
                <?php while($notification=$notificationResult->fetch_assoc()): ?>
                    <div class="notification-preview-item <?php echo strtolower($notification['status']); ?>">
                        <div class="notification-preview-info">
                            <h3>
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </h3>

                            <p>
                                <?php echo htmlspecialchars($notification['content']); ?>
                            </p>
                        </div>

                        <span class="notification-date">
                            <?php echo date("d M Y",strtotime($notification['createdDate'])); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="dashboard-empty">No notifications.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

</main>
</div>
</div>
</body>
</html>