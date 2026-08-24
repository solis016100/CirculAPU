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

$message = "";
$messageType = "";

/* Get Current EcoPoints */
$pointQuery="
SELECT ecopoint
FROM user
WHERE userID='$userID'
";
$pointResult=$conn->query($pointQuery);
$pointData=$pointResult->fetch_assoc();
$currentPoints=$pointData['ecopoint']??0;



/* Get Total EcoPoints */
$earnedPointQuery = "
SELECT COALESCE(SUM(points),0) AS totalEarned
FROM ecopointstransaction
WHERE userID='$userID'
AND transactionType='Earned'
";

$earnedPointResult = $conn->query($earnedPointQuery);
$earnedPointData = $earnedPointResult->fetch_assoc();
$totalEarnedPoints = $earnedPointData['totalEarned'] ?? 0;

/* Get Current Tier Based On Total Earned EcoPoints */
$tierQuery="
SELECT tierID,tierName,tierDescription,tierIcon,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints<=$totalEarnedPoints
ORDER BY minimumEcoPoints DESC
LIMIT 1
";

$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tier=$tierResult->fetch_assoc();
}else{
    /* Fallback to the lowest tier from database */
    $lowestTierResult=$conn->query("
    SELECT tierID,tierName,tierDescription,tierIcon,minimumEcoPoints
    FROM tiers
    ORDER BY minimumEcoPoints ASC
    LIMIT 1
    ");

    if($lowestTierResult&&$lowestTierResult->num_rows>0){
        $tier=$lowestTierResult->fetch_assoc();
    }else{
        $tier=[
            'tierID'=>'',
            'tierName'=>'No Tier',
            'tierDescription'=>'No tier information available',
            'tierIcon'=>'',
            'minimumEcoPoints'=>0
        ];
    }
}

/* Generate EcoPoint Transaction ID */
function generateEcoPointTransactionID($conn){
    $result=$conn->query("
    SELECT transactionID
    FROM ecopointstransaction
    WHERE transactionID LIKE 'ET%'
    ORDER BY CAST(SUBSTRING(transactionID,3) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if($result&&$result->num_rows>0){
        $row=$result->fetch_assoc();
        $number=(int)substr($row['transactionID'],2)+1;
    }else{
        $number=1;
    }

    return "ET".str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Generate Notification ID */
function generateNotificationID($conn){
    $result=$conn->query("
    SELECT MAX(
        CAST(SUBSTRING(notificationID,2) AS UNSIGNED)
    ) AS maxNumber
    FROM notification
    WHERE notificationID REGEXP '^N[0-9]+$'
    ");

    if($result){
        $row=$result->fetch_assoc();
        $number=((int)($row['maxNumber']??0))+1;
    }else{
        $number=1;
    }

    return "N".str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Generate User Badge ID */
function generateUserBadgeID($conn){

    $result=$conn->query("
    SELECT MAX(
        CAST(SUBSTRING(userBadgeID,4) AS UNSIGNED)
    ) AS maxNumber
    FROM `user achievement badge`
    WHERE userBadgeID REGEXP '^UAB[0-9]+$'
    ");

    if($result){
        $row=$result->fetch_assoc();
        $number=((int)($row['maxNumber']??0))+1;
    }else{
        $number=1;
    }

    return "UAB".str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Check Reward Redemption Badge */
function checkRewardRedeemBadge($conn,$userID){

    /* Get Rewards Redeemed Badge */
    $badgeResult=$conn->query("
    SELECT
        achieveBadgeID,
        achieveBadgeName,
        achieveBadgeRequirement
    FROM achievementbadges
    WHERE achieveBadgeName='Rewards Redeemed'
    LIMIT 1
    ");

    if(!$badgeResult||$badgeResult->num_rows==0){
        return;
    }

    $badge=$badgeResult->fetch_assoc();

    $badgeID=$conn->real_escape_string($badge['achieveBadgeID']);
    $badgeName=$badge['achieveBadgeName'];
    $requirement=$badge['achieveBadgeRequirement'];


    /* Get Required Redeem Number From Requirement */
    preg_match('/(\d+)/',$requirement,$matches);

    $requiredRedeem=isset($matches[1])
        ? (int)$matches[1]
        : 1;


    /* Count Member Reward Redemptions */
    $redeemCountResult=$conn->query("
    SELECT COUNT(*) AS totalRedeemed
    FROM rewardredemption
    WHERE userID='$userID'
    ");

    if(!$redeemCountResult){
        throw new Exception(
            "Unable to check reward badge progress: ".$conn->error
        );
    }

    $redeemCountData=$redeemCountResult->fetch_assoc();
    $totalRedeemed=(int)($redeemCountData['totalRedeemed']??0);


    /* Check Member Already Has Badge */
    $earnedResult=$conn->query("
    SELECT userBadgeID
    FROM `user achievement badge`
    WHERE userID='$userID'
    AND achieveBadgeID='$badgeID'
    LIMIT 1
    ");

    if($earnedResult&&$earnedResult->num_rows>0){
        return;
    }


    /* Award Badge When Requirement Is Met */
    if($totalRedeemed>=$requiredRedeem){

        $userBadgeID=generateUserBadgeID($conn);

        $awardBadgeQuery="
        INSERT INTO `user achievement badge`
        (userBadgeID,userID,achieveBadgeID,awardedDate)
        VALUES
        ('$userBadgeID','$userID','$badgeID',NOW())
        ";

        if(!$conn->query($awardBadgeQuery)){
            throw new Exception(
                "Unable to award badge: ".$conn->error
            );
        }


        /* Notify Member About New Badge */
        $notificationID=generateNotificationID($conn);

        $notificationTitle="New Badge Earned";

        $notificationContent=
            "Congratulations! You earned the ".
            $badgeName.
            " badge.";

        $safeTitle=$conn->real_escape_string($notificationTitle);
        $safeContent=$conn->real_escape_string($notificationContent);

        $badgeNotificationQuery="
        INSERT INTO notification
        (notificationID,userID,title,content,status,createdDate)
        VALUES
        ('$notificationID','$userID','$safeTitle',
         '$safeContent','Unread',NOW())
        ";

        if(!$conn->query($badgeNotificationQuery)){
            throw new Exception(
                "Badge notification failed: ".$conn->error
            );
        }
    }
}


/* Redeem Reward */
if(isset($_POST['redeem'])){
    $rewardID=$conn->real_escape_string($_POST['rewardID']);

    $rewardQuery="
    SELECT *
    FROM rewards
    WHERE rewardID='$rewardID'
    ";
    $rewardResult=$conn->query($rewardQuery);

    if($rewardResult&&$rewardResult->num_rows>0){
        $reward=$rewardResult->fetch_assoc();

        if($reward['quantity']<=0||$reward['availability']!='Available'){
            $message="This reward is currently not available.";
            $messageType="error";
        }elseif($currentPoints<$reward['ecoPointsCost']){
            $message="You do not have enough EcoPoints.";
            $messageType="error";
        }else{
            $cost=$reward['ecoPointsCost'];

            /* Generate Redemption ID */
            $idQuery="
            SELECT redemptionID
            FROM rewardredemption
            ORDER BY redemptionID DESC
            LIMIT 1
            ";
            $idResult=$conn->query($idQuery);

            if($idResult&&$idResult->num_rows>0){
                $lastID=$idResult->fetch_assoc()['redemptionID'];
                $number=intval(substr($lastID,2))+1;
            }else{
                $number=1;
            }

            $redemptionID="RR".str_pad($number,3,"0",STR_PAD_LEFT);

            $conn->begin_transaction();

            try{
                /* Deduct EcoPoints */
                $conn->query("
                UPDATE user
                SET ecopoint=ecopoint-$cost
                WHERE userID='$userID'
                ");

                /* Reduce Reward Stock */
                $conn->query("
                UPDATE rewards
                SET quantity=quantity-1
                WHERE rewardID='$rewardID'
                ");

                /* Save Redemption History */
                $redemptionQuery="
                INSERT INTO rewardredemption
                (redemptionID,userID,rewardID,redemptionDate)
                VALUES
                ('$redemptionID','$userID','$rewardID',NOW())
                ";

                if(!$conn->query($redemptionQuery)){
                    throw new Exception(
                        "Unable to save redemption history: ".$conn->error
                    );
                }


                /* Check Reward Redemption Badge */
                checkRewardRedeemBadge($conn,$userID);


                /* Save EcoPoint Transaction */
                $transactionID=generateEcoPointTransactionID($conn);

                $conn->query("
                INSERT INTO ecopointstransaction
                (transactionID,userID,points,transactionType,sourceType,referenceID,transactionDate)
                VALUES
                ('$transactionID','$userID',-$cost,'Spent','RewardRedemption','$redemptionID',NOW())
                ");

                /* Set Unavailable When Stock Is Empty */
                $conn->query("
                UPDATE rewards
                SET availability='Unavailable'
                WHERE rewardID='$rewardID'
                AND quantity<=0
                ");

                /* Get Member Name */
                $memberResult=$conn->query("
                SELECT name
                FROM user
                WHERE userID='$userID'
                LIMIT 1
                ");

                $redeemMemberName=$memberName;

                if($memberResult&&$memberResult->num_rows>0){
                    $memberData=$memberResult->fetch_assoc();
                    $redeemMemberName=$memberData['name'];
                }

                /* Notify Member */
                $memberNotificationID=generateNotificationID($conn);

                $memberNotificationTitle="Reward Redeemed Successfully";

                $memberNotificationContent=
                    "You redeemed ".
                    $reward['rewardName'].
                    " (".$rewardID.") for ".
                    $cost.
                    " EcoPoints.";

                $safeMemberTitle=$conn->real_escape_string($memberNotificationTitle);
                $safeMemberContent=$conn->real_escape_string($memberNotificationContent);

                $memberNotificationQuery="
                INSERT INTO notification
                (notificationID,userID,title,content,status,createdDate)
                VALUES
                ('$memberNotificationID','$userID','$safeMemberTitle',
                 '$safeMemberContent','Unread',NOW())
                ";

                if(!$conn->query($memberNotificationQuery)){
                    throw new Exception(
                        "Member notification failed: ".$conn->error
                    );
                }

                /* Notify Active Auditors */
                $auditorResult=$conn->query("
                SELECT userID
                FROM user
                WHERE role='Auditor'
                AND status='Active'
                ");

                if($auditorResult){

                    $auditorNotificationTitle="Reward Redeemed";

                    $auditorNotificationContent=
                        $redeemMemberName.
                        " (".$userID.") redeemed ".
                        $reward['rewardName'].
                        " (".$rewardID.") for ".
                        $cost.
                        " EcoPoints.";

                    $safeAuditorTitle=$conn->real_escape_string($auditorNotificationTitle);
                    $safeAuditorContent=$conn->real_escape_string($auditorNotificationContent);

                    while($auditor=$auditorResult->fetch_assoc()){

                        $auditorID=$conn->real_escape_string($auditor['userID']);
                        $auditorNotificationID=generateNotificationID($conn);

                        $auditorNotificationQuery="
                        INSERT INTO notification
                        (notificationID,userID,title,content,status,createdDate)
                        VALUES
                        ('$auditorNotificationID','$auditorID','$safeAuditorTitle',
                         '$safeAuditorContent','Unread',NOW())
                        ";

                        if(!$conn->query($auditorNotificationQuery)){
                            throw new Exception(
                                "Auditor notification failed: ".$conn->error
                            );
                        }
                    }
                }

                $conn->commit();

                $currentPoints-=$cost;

                $message="Reward redeemed successfully!";
                $messageType="success";
            }catch(Exception $e){
                $conn->rollback();

                $message="Unable to redeem reward: ".$e->getMessage();
                $messageType="error";
            }
        }
    }
}

/* Refresh Current Points */
$currentPointResult=$conn->query("
SELECT ecopoint
FROM user
WHERE userID='$userID'
");

if($currentPointResult&&$currentPointResult->num_rows>0){
    $currentPointData=$currentPointResult->fetch_assoc();
    $currentPoints=(int)($currentPointData['ecopoint']??0);
}

/* Refresh Current Tier */
$tierQuery="
SELECT tierID,tierName,tierDescription,tierIcon,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints<=$totalEarnedPoints
ORDER BY minimumEcoPoints DESC
LIMIT 1
";

$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tier=$tierResult->fetch_assoc();
}

/* Get Next Tier Based On Total Earned EcoPoints */
$nextTierQuery="
SELECT tierID,tierName,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints>$totalEarnedPoints
ORDER BY minimumEcoPoints ASC
LIMIT 1
";

$nextTierResult=$conn->query($nextTierQuery);

if($nextTierResult&&$nextTierResult->num_rows>0){
    $nextTier=$nextTierResult->fetch_assoc();
    $pointsToNextTier=max(0,$nextTier['minimumEcoPoints']-$totalEarnedPoints);
}else{
    $nextTier=null;
    $pointsToNextTier=0;
}

/* Get Rewards */
$rewardQuery="
SELECT *
FROM rewards
ORDER BY rewardID ASC
";
$rewardResult=$conn->query($rewardQuery);

/* Redemption History */
$historyQuery="
SELECT r.rewardName,r.ecoPointsCost,rr.redemptionDate
FROM rewardredemption rr
INNER JOIN rewards r
ON rr.rewardID=r.rewardID
WHERE rr.userID='$userID'
ORDER BY rr.redemptionDate DESC
";
$historyResult=$conn->query($historyQuery);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Rewards & Redemption - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="member_style.css">

</head>

<body class="role-member">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<div class="reward-page-header">
    <h1>Rewards & Redemption</h1>
    <p>Use your EcoPoints to redeem rewards and enjoy member benefits.</p>
</div>

<?php if($message!=""): ?>
<div class="reward-alert <?php echo $messageType; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="reward-page-layout">
    <div class="reward-main-area">

        <!-- Reward Tabs -->
        <div class="reward-tabs">
            <button class="reward-tab active" onclick="openRewardTab('rewardList',this)">
                Redeem Rewards
            </button>

            <button class="reward-tab" onclick="openRewardTab('rewardHistory',this)">
                Redemption History
            </button>
        </div>

        <!-- Redeem Rewards -->
        <div id="rewardList" class="reward-tab-content active">
            <div class="reward-grid">

            <?php if($rewardResult&&$rewardResult->num_rows>0): ?>
            <?php while($reward=$rewardResult->fetch_assoc()): ?>

            <?php
            $inStock=$reward['quantity']>0&&$reward['availability']=='Available';
            $enoughPoints=$currentPoints>=$reward['ecoPointsCost'];
            $canRedeem=$inStock&&$enoughPoints;
            ?>

                <div class="reward-card">
                    <div class="reward-image-box">
                        <?php if(!empty($reward['rewardImage'])): ?>
                            <img
                                src="../assets/images/<?php echo htmlspecialchars($reward['rewardImage']); ?>"
                                alt="<?php echo htmlspecialchars($reward['rewardName']); ?>"
                                class="reward-image"
                                onclick="openRewardImage(this.src, this.alt)"
                            >
                        <?php else: ?>
                            <div class="reward-image-placeholder">No Image</div>
                        <?php endif; ?>
                    </div>

                    <div class="reward-card-info">
                        <h3>
                            <?php echo htmlspecialchars($reward['rewardName']); ?>
                        </h3>

                        <p class="reward-description">
                            <?php echo htmlspecialchars($reward['rewardDescription']); ?>
                        </p>

                        <!-- Only Show EcoPoints -->
                        <div class="reward-card-details">
                            <div>
                                <span>EcoPoints Required</span>
                                <strong><?php echo $reward['ecoPointsCost']; ?></strong>
                            </div>
                        </div>

                        <!-- Reward Status -->
                        <?php if(!$inStock): ?>
                            <span class="reward-status unavailable">
                                Not Available
                            </span>

                        <?php elseif(!$enoughPoints): ?>
                            <span class="reward-status points">
                                Not Enough EcoPoints
                            </span>

                        <?php else: ?>
                            <span class="reward-status available">
                                Available
                            </span>
                        <?php endif; ?>

                        <form method="POST" class="reward-redeem-form">
                            <input type="hidden" name="rewardID"
                            value="<?php echo htmlspecialchars($reward['rewardID']); ?>">

                            <button type="button"
                            class="redeem-btn"
                            <?php echo !$canRedeem?'disabled':''; ?>
                            onclick="openRedeemConfirm(this)">
                                Redeem Now
                            </button>
                        </form>
                    </div>
                </div>

            <?php endwhile; ?>
            <?php else: ?>

                <div class="reward-empty">
                    No rewards available.
                </div>

            <?php endif; ?>

            </div>
        </div>

        <!-- Redemption History -->
        <div id="rewardHistory" class="reward-tab-content">
            <div class="reward-history-box">

                <table class="reward-history-table">
                    <thead>
                        <tr>
                            <th>Reward</th>
                            <th>EcoPoints Used</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($historyResult&&$historyResult->num_rows>0): ?>
                    <?php while($history=$historyResult->fetch_assoc()): ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars($history['rewardName']); ?>
                            </td>

                            <td>
                                <?php echo $history['ecoPointsCost']; ?>
                            </td>

                            <td>
                                <?php echo date("d M Y",strtotime($history['redemptionDate'])); ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="reward-empty">
                                No redemption history found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </div>

    </div>

    <!-- Right Side -->
    <div class="reward-side-area">

        <!-- EcoPoints -->
        <div class="reward-point-box">
            <h3>My EcoPoints</h3>

            <strong><?php echo $currentPoints; ?></strong>
            <span>Current EcoPoints</span>

            <p>
                Total EcoPoints:
                <strong><?php echo $totalEarnedPoints; ?></strong>
            </p>
        </div>

        <!-- User Tier -->
        <div class="reward-tier-box">
            <h3>My Tier</h3>

            <div class="reward-tier-icon">
                <?php if(!empty($tier['tierIcon'])): ?>
                <img src="../assets/images/<?php echo htmlspecialchars($tier['tierIcon']); ?>"
                alt="<?php echo htmlspecialchars($tier['tierName']); ?>">
                <?php endif; ?>
            </div>

            <strong class="reward-tier-name">
                <?php echo htmlspecialchars($tier['tierName']); ?>
            </strong>

            <span class="reward-tier-description">
                <?php echo htmlspecialchars($tier['tierDescription']); ?>
            </span>

            <div class="reward-tier-info">
                <div>
                    <span>Total EcoPoints</span>
                    <strong><?php echo $totalEarnedPoints; ?></strong>
                </div>

                <div>
                    <span>Points to Next Tier</span>
                    <strong>
                        <?php echo $nextTier ? $pointsToNextTier : 'Max Tier'; ?>
                    </strong>
                </div>
            </div>
        </div>

    </div>
</div>

</main>
</div>
</div>

<!-- Redeem Confirmation Modal -->
<div id="redeemConfirmModal" class="redeem-confirm-modal">
    <div class="redeem-confirm-box">

        <div class="redeem-confirm-icon">?</div>

        <h2>Confirm Redemption</h2>

        <p>
            Are you sure you want to redeem this reward?
        </p>

        <div class="redeem-confirm-actions">
            <button type="button"
                    class="redeem-cancel-btn"
                    onclick="closeRedeemConfirm()">
                Cancel
            </button>

            <button type="button"
                    class="redeem-confirm-btn"
                    onclick="confirmRedeem()">
                Yes, Redeem
            </button>
        </div>

    </div>
</div>

<!-- Reward Image Preview Modal -->
<div id="rewardImageModal" class="reward-image-modal" onclick="closeRewardImage(event)">
    <button type="button" class="reward-image-close" onclick="closeRewardImage()">×</button>
    <div class="reward-image-modal-content">
        <img id="rewardImageLarge" src="" alt="">
        <p id="rewardImageCaption"></p>
    </div>
</div>

<script>
let selectedRedeemForm=null;

function openRedeemConfirm(button){
    selectedRedeemForm=button.closest(".reward-redeem-form");
    document.getElementById("redeemConfirmModal").classList.add("show");
}

function closeRedeemConfirm(){
    document.getElementById("redeemConfirmModal").classList.remove("show");
    selectedRedeemForm=null;
}

function confirmRedeem(){
    if(selectedRedeemForm){
        var redeemInput=document.createElement("input");

        redeemInput.type="hidden";
        redeemInput.name="redeem";
        redeemInput.value="1";

        selectedRedeemForm.appendChild(redeemInput);
        selectedRedeemForm.submit();
    }
}

document.getElementById("redeemConfirmModal").addEventListener("click",function(event){
    if(event.target===this){
        closeRedeemConfirm();
    }
});

function openRewardImage(imageSrc,imageAlt){
    var modal=document.getElementById("rewardImageModal");
    var largeImage=document.getElementById("rewardImageLarge");
    var caption=document.getElementById("rewardImageCaption");

    largeImage.src=imageSrc;
    largeImage.alt=imageAlt;
    caption.textContent=imageAlt;
    modal.classList.add("show");
}

function closeRewardImage(event){
    var modal=document.getElementById("rewardImageModal");

    if(!event || event.target===modal || event.target.classList.contains("reward-image-close")){
        modal.classList.remove("show");
    }
}

document.addEventListener("keydown",function(event){
    if(event.key==="Escape"){
        document.getElementById("rewardImageModal").classList.remove("show");
        closeRedeemConfirm();
    }
});

function openRewardTab(tabName,button){
    var contents=document.querySelectorAll(".reward-tab-content");
    var tabs=document.querySelectorAll(".reward-tab");

    for(var i=0;i<contents.length;i++){
        contents[i].classList.remove("active");
    }

    for(var i=0;i<tabs.length;i++){
        tabs[i].classList.remove("active");
    }

    document.getElementById(tabName).classList.add("active");
    button.classList.add("active");
}
</script>

</body>
</html>