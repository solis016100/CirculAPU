<?php
session_start();

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
$is_preview = isset($_GET['preview']);

$conn = new mysqli("localhost", "root", "", "circulapu_db");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Member' && !($is_admin && $is_preview))) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

/* Get User Name */
$nameQuery = "SELECT name FROM user WHERE userID = '$userID'";
$nameResult = $conn->query($nameQuery);

if ($nameResult && $nameResult->num_rows > 0) {
    $nameData = $nameResult->fetch_assoc();
    $memberName = $nameData['name'];
} else {
    $memberName = "Member";
}

/* Get Total My Request */
$requestQuery = "
SELECT
(
    SELECT COUNT(*)
    FROM transactionrequests
    WHERE requesterID = '$userID'
)
+
(
    SELECT COUNT(*)
    FROM verificationrecords
    WHERE userID = '$userID'
    AND targetType = 'Asset'
) AS totalRequest
";

$requestResult = $conn->query($requestQuery);
$requestData = $requestResult->fetch_assoc();
$totalRequest = $requestData['totalRequest'];

/* Get Recent Requests */
$requestListQuery = "
SELECT
    a.assetName AS requestName,
    'Borrow' AS requestType,
    tr.requestStatus AS requestStatus,
    tr.requestDate AS requestDate
FROM transactionrequests tr
INNER JOIN assets a ON tr.assetID = a.assetID
WHERE tr.requesterID = '$userID'

UNION ALL

SELECT
    a.assetName AS requestName,
    'Asset Update' AS requestType,
    vr.status AS requestStatus,
    vr.verifiedDate AS requestDate
FROM verificationrecords vr
INNER JOIN assets a ON vr.targetID = a.assetID
WHERE vr.userID = '$userID'
AND vr.targetType = 'Asset'

ORDER BY requestDate DESC
LIMIT 4
";

$requestListResult = $conn->query($requestListQuery);

if (!$requestListResult) {
    die("Request Query Failed: " . $conn->error);
}

/* Get Completed Missions */
$missionQuery = "
SELECT COUNT(*) AS totalMission
FROM missionsubmission
WHERE userID = '$userID'
AND submissionStatus = 'Verified'
";

$missionResult = $conn->query($missionQuery);
$missionData = $missionResult->fetch_assoc();
$totalMission = $missionData['totalMission'];

/* Get EcoPoints */
$pointQuery = "SELECT ecopoint FROM user WHERE userID = '$userID'";
$pointResult = $conn->query($pointQuery);
$pointData = $pointResult->fetch_assoc();
$totalPoint = $pointData['ecopoint'];

/* Get Badges Earned */
$badgeQuery = "
SELECT COUNT(*) AS totalBadge
FROM `user achievement badge`
WHERE userID = '$userID'
";

$badgeResult = $conn->query($badgeQuery);
$badgeData = $badgeResult->fetch_assoc();
$totalBadge = $badgeData['totalBadge'];

/* Get Available Missions */
$missionListQuery = "
SELECT missionID, missionName, missionDescription, ecoPointsReward
FROM missions
WHERE status = 'Active'
ORDER BY missionID ASC
LIMIT 3
";

$missionListResult = $conn->query($missionListQuery);

if (!$missionListResult) {
    die("Mission Query Failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

            <!-- Welcome -->
            <h1>Welcome back, <?php echo htmlspecialchars($memberName); ?></h1>
            <p>Here is an overview of the sustainability activities.</p>

            <!-- Statistic Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/myrequest.png" alt="My Request">
                    </div>
                    <h2><?php echo $totalRequest; ?></h2>
                    <p>My Request</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/mission.png" alt="Missions">
                    </div>
                    <h2><?php echo $totalMission; ?></h2>
                    <p>Missions</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/point.png" alt="Eco Points">
                    </div>
                    <h2><?php echo $totalPoint; ?></h2>
                    <p>Eco Points</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/badge.png" alt="Badges Earned">
                    </div>
                    <h2><?php echo $totalBadge; ?></h2>
                    <p>Badges Earned</p>
                </div>
            </div>

            <!-- My Recent Requests -->
            <div class="recent-request-card">
                <div class="recent-request-header">
                    <h2>My Recent Requests</h2>
                    <a href="member_requests.php" class="view-all">View All</a>
                </div>

                <div class="recent-request-table">
                    <div class="recent-request-row recent-request-title">
                        <span>Request</span>
                        <span>Type</span>
                        <span>Status</span>
                        <span>Date</span>
                    </div>

                    <?php if ($requestListResult && $requestListResult->num_rows > 0): ?>
                        <?php while ($request = $requestListResult->fetch_assoc()): ?>
                            <?php
                            $displayStatus = $request['requestStatus'];
                            if ($request['requestType'] === 'Asset Update' && strtolower($displayStatus) === 'pending') {
                                $displayStatus = 'Published';
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
                                    <span class="request-status <?php echo strtolower($displayStatus); ?>">
                                        <?php echo htmlspecialchars($displayStatus); ?>
                                    </span>
                                </span>
                                <span>
                                    <?php echo date("d M Y", strtotime($request['requestDate'])); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-request">No recent requests found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Missions -->
            <div class="available-mission-card">
                <div class="available-mission-header">
                    <h2>Available Missions</h2>
                    <a href="member_missions.php" class="explore-all">Explore All</a>
                </div>

                <div class="available-mission-list">
                    <?php if ($missionListResult && $missionListResult->num_rows > 0): ?>
                        <?php while ($mission = $missionListResult->fetch_assoc()): ?>
                            <div class="mission-preview-item">
                                <div class="mission-preview-icon">
                                    <img src="../assets/icons/mission.png" alt="Mission">
                                </div>

                                <div class="mission-preview-info">
                                    <h3><?php echo htmlspecialchars($mission['missionName']); ?></h3>
                                    <p><?php echo htmlspecialchars($mission['missionDescription']); ?></p>
                                </div>

                                <div class="mission-preview-point">
                                    <strong><?php echo htmlspecialchars($mission['ecoPointsReward']); ?></strong>
                                    <span>points</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-mission">No available missions.</div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>
</body>
</html>