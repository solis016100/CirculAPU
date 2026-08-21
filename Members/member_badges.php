<?php
session_start();

$conn=new mysqli("localhost","root","","circulapu_db");

if($conn->connect_error){
    die("Database Connection Failed: ".$conn->connect_error);
}

if(!isset($_SESSION['user_id'])||$_SESSION['user_role']!=='Member'){
    header("Location: ../login.php");
    exit();
}

$userID=$_SESSION['user_id'];

/* Get Total EcoPoints = all Earned EcoPoints */
$pointQuery="
SELECT COALESCE(SUM(points),0) AS totalPoints
FROM ecopointstransaction
WHERE userID='$userID'
AND transactionType='Earned'
";
$pointResult=$conn->query($pointQuery);
$pointData=$pointResult->fetch_assoc();
$totalPoints=(int)($pointData['totalPoints']??0);

/* Get Current Tier Based On Total EcoPoints Only */
$tierQuery="
SELECT tierName,tierDescription,tierIcon,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints<=$totalPoints
ORDER BY minimumEcoPoints DESC
LIMIT 1
";
$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tierData=$tierResult->fetch_assoc();
    $tierName=$tierData['tierName'];
    $tierDescription=$tierData['tierDescription'];
    $tierIcon=$tierData['tierIcon'];
}else{
    $tierName="Eco Beginner";
    $tierDescription="Starting milestone for new sustainability participants";
    $tierIcon="icon_tier1.png";
}

/* Get Next Tier Based On Total EcoPoints Only */
$nextTierQuery="
SELECT tierName,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints>$totalPoints
ORDER BY minimumEcoPoints ASC
LIMIT 1
";
$nextTierResult=$conn->query($nextTierQuery);

if($nextTierResult&&$nextTierResult->num_rows>0){
    $nextTier=$nextTierResult->fetch_assoc();
    $pointsToNextTier=$nextTier['minimumEcoPoints']-$totalPoints;
}else{
    $nextTier=null;
    $pointsToNextTier=0;
}

/* Get All Badges */
$badgeQuery="
SELECT achieveBadgeID,achieveBadgeName,achieveBadgeDescription,
achieveBadgeRequirement,achieveBadgeIcon
FROM achievementbadges
ORDER BY achieveBadgeID ASC
";
$badgeResult=$conn->query($badgeQuery);

$badges=[];
while($badge=$badgeResult->fetch_assoc()){
    $badges[]=$badge;
}

/* Get Member Progress For Badge Requirements */

/* Completed Missions */
$completedMissionQuery="
SELECT COUNT(*) AS total
FROM missionsubmission
WHERE userID='$userID'
AND submissionStatus IN ('Approved','Verified')
";

$completedMissionResult=$conn->query($completedMissionQuery);
$totalCompletedMission=0;

if($completedMissionResult){
    $completedMissionData=$completedMissionResult->fetch_assoc();
    $totalCompletedMission=(int)($completedMissionData['total']??0);
}

/* Completed Exchanges */
$completedExchangeQuery="
SELECT COUNT(*) AS total
FROM transactionrequests
WHERE requesterID='$userID'
AND requestStatus='Completed'
";

$completedExchangeResult=$conn->query($completedExchangeQuery);
$totalCompletedExchange=0;

if($completedExchangeResult){
    $completedExchangeData=$completedExchangeResult->fetch_assoc();
    $totalCompletedExchange=(int)($completedExchangeData['total']??0);
}

/* Calculate Earned Badges */
$earnedBadges=[];

foreach($badges as $badge){

    $badgeID=$badge['achieveBadgeID'];

    if($badgeID==='AB001' && $totalCompletedMission>=1){
        $earnedBadges[]=$badgeID;
    }
    elseif($badgeID==='AB002' && $totalCompletedExchange>=5){
        $earnedBadges[]=$badgeID;
    }
    elseif($badgeID==='AB003' && $totalPoints>=500){
        $earnedBadges[]=$badgeID;
    }
}

/* Badge Count */
$totalBadge=count($badges);
$earnedCount=count($earnedBadges);
$lockedCount=$totalBadge-$earnedCount;

/* Selected Badge */
$selectedBadge=null;

if(isset($_GET['badge'])){
    $selectedID=$_GET['badge'];

    foreach($badges as $badge){
        if($badge['achieveBadgeID']==$selectedID){
            $selectedBadge=$badge;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Achievement Badges - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="member_new_style.css">

<style>
.badge-tier-info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7px;
    margin-top:12px;
}
.badge-tier-info div{
    padding:7px;
    background:#f4fbf6;
    border:1px solid #c5e5d0;
    border-radius:6px;
}
.badge-tier-info span{
    display:block;
    margin:0;
    font-size:8px;
    color:#777;
}
.badge-tier-info strong{
    display:block;
    margin-top:2px;
    font-size:11px;
    color:#159447;
}
</style>
</head>

<body class="role-member">
<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<?php if($selectedBadge): ?>

<?php
$badgeID=$selectedBadge['achieveBadgeID'];
$isEarned=in_array($badgeID,$earnedBadges);
?>

<!-- Badge Details -->
<div class="badge-detail-top">
    <div>
        <h1>Badge Details</h1>
        <p>Learn more about this badge and how you can earn it.</p>
    </div>
    <a href="member_badges.php" class="badge-back-btn">← Back to Badges</a>
</div>

<div class="badge-page-layout">
<div class="badge-main-area">

    <div class="badge-detail-card">
        <div class="badge-detail-icon">
            <img src="../assets/images/<?php echo htmlspecialchars($selectedBadge['achieveBadgeIcon']); ?>"
            alt="<?php echo htmlspecialchars($selectedBadge['achieveBadgeName']); ?>"
            class="clickable-badge"
            onclick="openBadgeImage(this.src)">
        </div>

        <div class="badge-detail-title">
            <h2><?php echo htmlspecialchars($selectedBadge['achieveBadgeName']); ?></h2>
            <p><?php echo htmlspecialchars($selectedBadge['achieveBadgeRequirement']); ?></p>

            <?php if($isEarned): ?>
                <span class="badge-status earned">Earned</span>

                

            <?php else: ?>
                <span class="badge-status locked">Locked</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="badge-info-box">
        <h3>Badge Information</h3>
        <p><?php echo htmlspecialchars($selectedBadge['achieveBadgeDescription']); ?></p>
    </div>

    <div class="badge-info-box">
        <h3>Requirement</h3>
        <p><?php echo htmlspecialchars($selectedBadge['achieveBadgeRequirement']); ?></p>
    </div>

</div>

<!-- Right Side -->
<div class="badge-side-area">
    <div class="badge-progress-box">
        <h3>Your Progress</h3>

        <div class="tier-icon">
            <img src="../assets/images/<?php echo htmlspecialchars($tierIcon); ?>"
            alt="<?php echo htmlspecialchars($tierName); ?>">
        </div>

        <strong><?php echo htmlspecialchars($tierName); ?></strong>
        <span><?php echo htmlspecialchars($tierDescription); ?></span>

        <div class="badge-tier-info">
            <div>
                <span>Total EcoPoints</span>
                <strong><?php echo $totalPoints; ?></strong>
            </div>

            <div>
                <span>Points to Next Tier</span>
                <strong>
                    <?php echo $nextTier ? $pointsToNextTier : 'Max Tier'; ?>
                </strong>
            </div>
        </div>
    </div>

    <div class="badge-guide-box">
        <h3>How to Earn Badges</h3>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Complete 1 sustainability mission</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Complete 5 successful exchanges</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Reach 500 total EcoPoints</p>
        </div>
    </div>
</div>
</div>

<!-- Badge Zoom -->
<div id="badgeImageModal" class="badge-image-modal" onclick="closeBadgeImage()">
    <span class="badge-image-close">&times;</span>
    <img id="badgeLargeImage" src="" alt="Badge" onclick="event.stopPropagation()">
</div>

<?php else: ?>

<!-- Badge Dashboard -->
<div class="badge-page-header">
    <h1>Achievement Badges</h1>
    <p>Earn badges by completing missions and making contributions.</p>
</div>

<div class="badge-page-layout">
<div class="badge-main-area">

    <!-- Badge Progress -->
    <div class="badge-summary-card">
        <h3>Your Badge Progress</h3>

        <div class="badge-summary-grid">
            <div class="badge-summary-item">
                <strong><?php echo $earnedCount; ?>/<?php echo $totalBadge; ?></strong>
                <span>Total Progress</span>
            </div>

            <div class="badge-summary-item">
                <strong><?php echo $earnedCount; ?></strong>
                <span>Earned</span>
            </div>

            <div class="badge-summary-item">
                <strong><?php echo $lockedCount; ?></strong>
                <span>Locked</span>
            </div>

            <div class="badge-summary-item">
                <strong><?php echo $totalPoints; ?></strong>
                <span>Total EcoPoints</span>
            </div>
        </div>
    </div>

    <!-- Badge Tabs -->
    <div class="badge-tabs">
        <button class="badge-tab active" onclick="filterBadges('all',this)">All Badges</button>
        <button class="badge-tab" onclick="filterBadges('earned',this)">Earned</button>
        <button class="badge-tab" onclick="filterBadges('locked',this)">Locked</button>
    </div>

    <!-- Badge Cards -->
    <div class="badge-grid">

    <?php foreach($badges as $badge): ?>

    <?php
    $badgeID=$badge['achieveBadgeID'];
    $isEarned=in_array($badgeID,$earnedBadges);

    if($isEarned){
        $status="earned";
    }else{
        $status="locked";
    }
    ?>

        <a href="member_badges.php?badge=<?php echo urlencode($badgeID); ?>"
        class="badge-card"
        data-status="<?php echo $status; ?>">

            <div class="badge-card-icon">
                <img src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>"
                alt="<?php echo htmlspecialchars($badge['achieveBadgeName']); ?>">
            </div>

            <h3><?php echo htmlspecialchars($badge['achieveBadgeName']); ?></h3>
            <p><?php echo htmlspecialchars($badge['achieveBadgeRequirement']); ?></p>

            <span class="badge-status <?php echo $status; ?>">
                <?php
                if($isEarned){
                    echo "Earned";
                }else{
                    echo "Locked";
                }
                ?>
            </span>

            
        </a>

    <?php endforeach; ?>

    </div>
</div>

<!-- Right Side -->
<div class="badge-side-area">
    <div class="badge-progress-box">
        <h3>Your Progress</h3>

        <div class="tier-icon">
            <img src="../assets/images/<?php echo htmlspecialchars($tierIcon); ?>"
            alt="<?php echo htmlspecialchars($tierName); ?>">
        </div>

        <strong><?php echo htmlspecialchars($tierName); ?></strong>
        <span><?php echo htmlspecialchars($tierDescription); ?></span>

        <div class="badge-tier-info">
            <div>
                <span>Total EcoPoints</span>
                <strong><?php echo $totalPoints; ?></strong>
            </div>

            <div>
                <span>Points to Next Tier</span>
                <strong>
                    <?php echo $nextTier ? $pointsToNextTier : 'Max Tier'; ?>
                </strong>
            </div>
        </div>
    </div>

    <div class="badge-guide-box">
        <h3>How to Earn Badges</h3>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Complete 1 sustainability mission</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Complete 5 successful exchanges</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Reach 500 total EcoPoints</p>
        </div>
    </div>
</div>
</div>

<?php endif; ?>

</main>
</div>
</div>

<script>
function filterBadges(status,button){
    var tabs=document.querySelectorAll(".badge-tab");
    var cards=document.querySelectorAll(".badge-card");

    for(var i=0;i<tabs.length;i++){
        tabs[i].classList.remove("active");
    }

    button.classList.add("active");

    for(var i=0;i<cards.length;i++){
        if(status=="all"||cards[i].dataset.status==status){
            cards[i].style.display="flex";
        }else{
            cards[i].style.display="none";
        }
    }
}

function openBadgeImage(imageSrc){
    document.getElementById("badgeLargeImage").src=imageSrc;
    document.getElementById("badgeImageModal").style.display="flex";
}

function closeBadgeImage(){
    document.getElementById("badgeImageModal").style.display="none";
}
</script>

</body>
</html>