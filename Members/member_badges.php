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

/* Get EcoPoints */
$pointQuery="
SELECT ecopoint
FROM user
WHERE userID='$userID'
";
$pointResult=$conn->query($pointQuery);
$pointData=$pointResult->fetch_assoc();
$totalPoints=$pointData['ecopoint']??0;

/* Count Completed Exchanges */
$exchangeQuery="
SELECT COUNT(*) AS totalExchange
FROM transactionrequests
WHERE requesterID='$userID'
AND requestStatus='Completed'
";
$exchangeResult=$conn->query($exchangeQuery);
$exchangeData=$exchangeResult->fetch_assoc();
$totalExchange=$exchangeData['totalExchange']??0;

/* Get User Tier */
$tierQuery="
SELECT tierName,tierDescription,tierIcon,minimumEcoPoints,minimumExchanges
FROM tiers
WHERE minimumEcoPoints<='$totalPoints'
AND minimumExchanges<='$totalExchange'
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

/* Get Earned Badges */
$earnedQuery="
SELECT achieveBadgeID,awardedDate
FROM `user achievement badge`
WHERE userID='$userID'
";
$earnedResult=$conn->query($earnedQuery);

$earnedBadges=[];
$awardedDates=[];

while($row=$earnedResult->fetch_assoc()){
    $earnedBadges[]=$row['achieveBadgeID'];
    $awardedDates[$row['achieveBadgeID']]=$row['awardedDate'];
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

                <?php if(isset($awardedDates[$badgeID])): ?>
                    <small class="badge-earned-date">
                        Earned on <?php echo date("d M Y",strtotime($awardedDates[$badgeID])); ?>
                    </small>
                <?php endif; ?>

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

        <p>
            <?php echo $totalPoints; ?> EcoPoints ·
            <?php echo $totalExchange; ?> Exchanges
        </p>
    </div>

    <div class="badge-guide-box">
        <h3>How to Earn Badges</h3>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Join and complete sustainability missions</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Lend or borrow assets</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Reduce waste and save energy</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Earn EcoPoints to upgrade your tier</p>
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

            <?php if($isEarned&&isset($awardedDates[$badgeID])): ?>
                <small class="badge-earned-date">
                    <?php echo date("d M Y",strtotime($awardedDates[$badgeID])); ?>
                </small>
            <?php endif; ?>
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

        <p>
            <?php echo $totalPoints; ?> EcoPoints ·
            <?php echo $totalExchange; ?> Exchanges
        </p>
    </div>

    <div class="badge-guide-box">
        <h3>How to Earn Badges</h3>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Join and complete sustainability missions</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Lend or borrow assets</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Reduce waste and save energy</p>
        </div>

        <div class="badge-guide-item">
            <span>✓</span>
            <p>Earn EcoPoints to upgrade your tier</p>
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