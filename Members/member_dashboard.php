<?php
session_start();

$is_admin=isset($_SESSION['user_role'])&&$_SESSION['user_role']==='Admin';
$is_preview=isset($_GET['preview']);

$conn=new mysqli("localhost","root","","circulapu_db");

if($conn->connect_error){
    die("Database Connection Failed: ".$conn->connect_error);
}

if(!isset($_SESSION['user_id'])||($_SESSION['user_role']!=='Member'&&!($is_admin&&$is_preview))){
    header("Location: ../login.php");
    exit();
}

$userID=$_SESSION['user_id'];

/* User */
$userResult=$conn->query("
SELECT name,ecopoint
FROM user
WHERE userID='$userID'
");

$user=$userResult->fetch_assoc();
$memberName=$user['name']??'Member';
$totalPoint=$user['ecopoint']??0;

/* Request Count */
$requestResult=$conn->query("
SELECT
(SELECT COUNT(*) FROM transactionrequests WHERE requesterID='$userID')
+
(SELECT COUNT(*) FROM verificationrecords WHERE userID='$userID' AND targetType='Asset') AS total
");

$totalRequest=$requestResult->fetch_assoc()['total']??0;

/* Recent Requests */
$requestListResult=$conn->query("
SELECT a.assetName AS requestName,'Borrow' AS requestType,
tr.requestStatus,tr.requestDate
FROM transactionrequests tr
JOIN assets a ON tr.assetID=a.assetID
WHERE tr.requesterID='$userID'

UNION ALL

SELECT a.assetName,'Asset Update',vr.status,vr.verifiedDate
FROM verificationrecords vr
JOIN assets a ON vr.targetID=a.assetID
WHERE vr.userID='$userID'
AND vr.targetType='Asset'

ORDER BY requestDate DESC
LIMIT 4
");

/* Completed Missions */
$missionResult=$conn->query("
SELECT COUNT(*) AS total
FROM missionsubmission
WHERE userID='$userID'
AND submissionStatus='Verified'
");

$totalMission=$missionResult->fetch_assoc()['total']??0;

/* Badges */
$badgeResult=$conn->query("
SELECT COUNT(*) AS total
FROM `user achievement badge`
WHERE userID='$userID'
");

$totalBadge=$badgeResult->fetch_assoc()['total']??0;

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

/* Current Tier */
$tierResult=$conn->query("
SELECT *
FROM tiers
WHERE minimumEcoPoints<=$totalPoint
AND minimumExchanges<=$totalExchange
ORDER BY minimumEcoPoints DESC,minimumExchanges DESC
LIMIT 1
");

$currentTier=$tierResult->fetch_assoc();

/* Next Tier */
$nextTier=null;
$progress=100;
$pointsLeft=0;

if($currentTier){
    $nextResult=$conn->query("
    SELECT *
    FROM tiers
    WHERE minimumEcoPoints>".$currentTier['minimumEcoPoints']."
    ORDER BY minimumEcoPoints ASC
    LIMIT 1
    ");

    if($nextResult&&$nextResult->num_rows>0){
        $nextTier=$nextResult->fetch_assoc();
        $currentMin=$currentTier['minimumEcoPoints'];
        $nextMin=$nextTier['minimumEcoPoints'];
        $progress=(($totalPoint-$currentMin)/($nextMin-$currentMin))*100;
        $progress=max(0,min(100,$progress));
        $pointsLeft=max(0,$nextMin-$totalPoint);
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

<h1>Welcome back, <?php echo htmlspecialchars($memberName); ?></h1>
<p>Here is an overview of the sustainability activities.</p>

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
        <h2><?php echo $totalPoint; ?></h2>
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
                    if($request['requestType']==='Asset Update'&&strtolower($status)==='pending'){
                        $status='Published';
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
                            <span class="request-status <?php echo strtolower($status); ?>">
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
                    <strong><?php echo $totalPoint; ?></strong>
                    <span>Current EcoPoints</span>
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
                        <div class="mission-preview-icon">
                            <img src="../assets/icons/mission.png" alt="Mission">
                        </div>

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
                        <div class="notification-small-icon">🔔</div>

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