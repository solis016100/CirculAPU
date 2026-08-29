<?php
session_start();

 
$isAdminPreview = ($_SESSION['user_role'] ?? '') === 'Admin'
    && ($_GET['preview'] ?? '') === 'Member';

if (empty($_SESSION['user_id'])
    || (($_SESSION['user_role'] ?? '') !== 'Member' && !$isAdminPreview)
) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

if ($conn->connect_error) {
    die('Unable to connect to the database.');
}

$conn->set_charset('utf8mb4');
$userID = $_SESSION['user_id'];

 
if ($isAdminPreview) {
    $previewUser = $conn->query(
        "SELECT userID FROM user
         WHERE role='Member' AND status='Active'
         ORDER BY dateCreated,userID LIMIT 1"
    )->fetch_assoc();

    if (!$previewUser) {
        die('No active Member account is available for preview.');
    }

    $userID = $previewUser['userID'];
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

 
function memberQuery(mysqli $conn, string $sql, string $userID): mysqli_result
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userID);
    $stmt->execute();
    return $stmt->get_result();
}

 
$stats = memberQuery($conn, "
    SELECT u.name,u.ecopoint,
        (SELECT COALESCE(SUM(points),0) FROM ecopointstransaction
         WHERE userID=u.userID AND transactionType='Earned') totalEarned,
        (SELECT COUNT(*) FROM transactionrequests WHERE requesterID=u.userID)
        + (SELECT COUNT(*) FROM assets WHERE ownerID=u.userID) totalRequest,
        (SELECT COUNT(*) FROM missionsubmission WHERE userID=u.userID) totalMission,
        (SELECT COUNT(*) FROM missionsubmission
         WHERE userID=u.userID AND submissionStatus IN ('Approved','Verified')) totalCompletedMission,
        (SELECT COUNT(*) FROM transactionrequests
         WHERE requesterID=u.userID AND requestStatus='Completed') totalExchange
    FROM user u WHERE u.userID=? LIMIT 1
", $userID)->fetch_assoc();
if (!$stats) die('Member not found.');
$memberName = $stats['name'] ?? 'Member';
$currentPoint = (int)$stats['ecopoint'];
$totalEarnedPoint = (int)$stats['totalEarned'];
$totalRequest = (int)$stats['totalRequest'];
$totalMission = (int)$stats['totalMission'];
$totalBadge = ($stats['totalCompletedMission'] >= 1)
            + ($stats['totalExchange'] >= 5)
            + ($totalEarnedPoint >= 500);

 
$stmt = $conn->prepare("SELECT a.assetName requestName,'Borrow Request' requestType,
                              tr.requestStatus,tr.requestDate
                       FROM transactionrequests tr JOIN assets a ON a.assetID=tr.assetID
                       WHERE tr.requesterID=?
                       UNION ALL
                       SELECT assetName,'Asset Update',listingStatus,updatedDate
                       FROM assets WHERE ownerID=?
                       ORDER BY requestDate DESC LIMIT 4");
$stmt->bind_param('ss', $userID, $userID);
$stmt->execute();
$requests = $stmt->get_result();
$missions = $conn->query("SELECT missionName,missionDescription,ecoPointsReward
                           FROM missions WHERE status='Active' ORDER BY missionID LIMIT 3");
$notifications = memberQuery($conn, "SELECT title,content,status,createdDate
                                     FROM notification WHERE userID=?
                                     ORDER BY createdDate DESC LIMIT 3", $userID);

 
$tierStmt = $conn->prepare("SELECT * FROM tiers WHERE minimumEcoPoints<=?
                            ORDER BY minimumEcoPoints DESC LIMIT 1");
$tierStmt->bind_param('i', $totalEarnedPoint);
$tierStmt->execute();
$currentTier = $tierStmt->get_result()->fetch_assoc()
            ?? $conn->query('SELECT * FROM tiers ORDER BY minimumEcoPoints LIMIT 1')->fetch_assoc();
$nextTier = null;
$progress = 100;
$pointsLeft = 0;
if ($currentTier) {
    $currentMin = (int)$currentTier['minimumEcoPoints'];
    $tierStmt = $conn->prepare("SELECT * FROM tiers WHERE minimumEcoPoints>?
                                ORDER BY minimumEcoPoints LIMIT 1");
    $tierStmt->bind_param('i', $currentMin);
    $tierStmt->execute();
    $nextTier = $tierStmt->get_result()->fetch_assoc();
    if ($nextTier) {
        $nextMin = (int)$nextTier['minimumEcoPoints'];
        $progress = max(0, min(100,
            (($totalEarnedPoint - $currentMin) / max(1, $nextMin - $currentMin)) * 100
        ));
        $pointsLeft = max(0, $nextMin - $totalEarnedPoint);
    }
}
$cards = [
    ['myrequest.png', $totalRequest, 'My Request'],
    ['mission.png', $totalMission, 'Missions'],
    ['point.png', $currentPoint, 'EcoPoints'],
    ['badge.png', $totalBadge, 'Badges Earned']
];
function statusClass(string $status): string
{
    $class = strtolower(trim($status));
    return match ($class) {
        'pending review' => 'pending',
        'ready for verification' => 'published',
        'removed' => 'rejected',
        default => str_replace(' ', '-', $class)
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Member Portal - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_styles.css">
</head>
<body class="role-member">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <?php include '../header.php'; ?>
        <main class="page-body">
             
            <div class="member-dashboard-heading">
                <div>
                    <h1>Welcome back, <?= e($memberName) ?></h1>
                    <p>Here is an overview of the sustainability activities.</p>
                </div>
                <a href="member_reports.php" class="member-report-btn member-btn secondary">
                    <img src="../assets/icons/member-report.png" alt=""> Submit Report
                </a>
            </div>

             
            <div class="stats-grid">
                <?php foreach ($cards as [$icon, $number, $label]): ?>
                    <div class="stat-card member-card">
                        <div class="stat-icon"><img src="../assets/icons/<?= $icon ?>" alt=""></div>
                        <h2><?= $number ?></h2><p><?= $label ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="main-quick-grid">
                 
                <section class="dashboard-card member-card main-request-quick">
                    <div class="dashboard-card-header member-card-header">
                        <h2>My Recent Requests</h2><a href="member_requests.php">View All</a>
                    </div>
                    <div class="recent-request-table">
                        <div class="recent-request-row recent-request-title">
                            <span>Request</span><span>Type</span><span>Status</span><span>Date</span>
                        </div>
                        <?php if ($requests->num_rows): ?>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <div class="recent-request-row">
                                    <span class="request-name"><?= e($request['requestName']) ?></span>
                                    <span><?= e($request['requestType']) ?></span>
                                    <span><span class="request-status <?= e(statusClass($request['requestStatus'])) ?>">
                                        <?= e($request['requestStatus']) ?>
                                    </span></span>
                                    <span><?= date('d M Y', strtotime($request['requestDate'])) ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="dashboard-empty">No recent requests found.</div>
                        <?php endif; ?>
                    </div>
                </section>

                 
                <section class="dashboard-card member-card main-reward-quick">
                    <div class="dashboard-card-header member-card-header"><h2>EcoPoints Progress</h2></div>
                    <div class="ecopoint-progress">
                        <div class="point-top">
                            <div><strong><?= $totalEarnedPoint ?></strong><span>Total EcoPoints</span></div>
                            <p><?= $nextTier
                                ? 'Next: ' . e($nextTier['tierName']) . "<br>$pointsLeft points to go"
                                : 'Highest tier reached' ?></p>
                        </div>
                        <div class="dashboard-progress">
                            <div class="dashboard-progress-bar" style="width:<?= $progress ?>%"></div>
                        </div>
                        <span class="progress-number"><?= round($progress) ?>%</span>
                        <a href="member_rewards.php" class="dashboard-button member-btn secondary">
                            View Rewards & Catalog →
                        </a>
                    </div>
                </section>

                 
                <section class="dashboard-card member-card main-mission-quick">
                    <div class="dashboard-card-header member-card-header">
                        <h2>Available Missions</h2><a href="member_missions.php">Explore All</a>
                    </div>
                    <div class="available-mission-list">
                        <?php if ($missions->num_rows): ?>
                            <?php while ($mission = $missions->fetch_assoc()): ?>
                                <div class="mission-preview-item">
                                    <div class="mission-preview-info">
                                        <h3><?= e($mission['missionName']) ?></h3>
                                        <p><?= e($mission['missionDescription']) ?></p>
                                    </div>
                                    <div class="mission-preview-point">
                                        <strong><?= (int)$mission['ecoPointsReward'] ?></strong><span>points</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="dashboard-empty">No available missions.</div>
                        <?php endif; ?>
                    </div>
                </section>

                 
                <section class="dashboard-card member-card main-notification-quick">
                    <div class="dashboard-card-header member-card-header">
                        <h2>Notifications</h2><a href="member_notifications.php">View All</a>
                    </div>
                    <div class="notification-preview">
                        <?php if ($notifications->num_rows): ?>
                            <?php while ($note = $notifications->fetch_assoc()): ?>
                                <div class="notification-preview-item <?= e(strtolower($note['status'])) ?>">
                                    <div class="notification-preview-info">
                                        <h3><?= e($note['title']) ?></h3><p><?= e($note['content']) ?></p>
                                    </div>
                                    <span class="notification-date">
                                        <?= date('d M Y', strtotime($note['createdDate'])) ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="dashboard-empty">No notifications.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>
</body>
</html>
