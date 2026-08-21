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

/* Get User Tier Based On Total EcoPoints */
$tierQuery="
SELECT *
FROM tiers
WHERE minimumEcoPoints<=$totalEarnedPoints
ORDER BY minimumEcoPoints DESC
LIMIT 1
";
$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tier=$tierResult->fetch_assoc();
}else{
    $tier=[
        'tierID'=>'T001',
        'tierName'=>'Eco Beginner',
        'tierDescription'=>'Starting milestone for new sustainability participants',
        'tierIcon'=>'icon_tier1.png',
        'minimumEcoPoints'=>0
    ];
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
                $conn->query("
                INSERT INTO rewardredemption
                (redemptionID,userID,rewardID,redemptionDate)
                VALUES
                ('$redemptionID','$userID','$rewardID',NOW())
                ");

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

                $conn->commit();

                $currentPoints-=$cost;

                $message="Reward redeemed successfully!";
                $messageType="success";
            }catch(Exception $e){
                $conn->rollback();

                $message="Unable to redeem reward.";
                $messageType="error";
            }
        }
    }
}

/* Refresh User Tier */
$tierQuery="
SELECT *
FROM tiers
WHERE minimumEcoPoints<=$totalEarnedPoints
ORDER BY minimumEcoPoints DESC
LIMIT 1
";
$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tier=$tierResult->fetch_assoc();
}

/* Get Next Tier */
$nextTierQuery="
SELECT tierName,minimumEcoPoints
FROM tiers
WHERE minimumEcoPoints>$totalEarnedPoints
ORDER BY minimumEcoPoints ASC
LIMIT 1
";

$nextTierResult=$conn->query($nextTierQuery);

if($nextTierResult&&$nextTierResult->num_rows>0){
    $nextTier=$nextTierResult->fetch_assoc();
    $pointsToNextTier=$nextTier['minimumEcoPoints']-$totalEarnedPoints;
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
<link rel="stylesheet" href="member_new_style.css">
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
                    <div class="reward-icon">
                        <span>🎁</span>
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

                        <form method="POST">
                            <input type="hidden" name="rewardID"
                            value="<?php echo htmlspecialchars($reward['rewardID']); ?>">

                            <button type="submit"
                            name="redeem"
                            class="redeem-btn"
                            <?php echo !$canRedeem?'disabled':''; ?>
                            onclick="return confirm('Are you sure you want to redeem this reward?')">
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
                <img src="../assets/images/<?php echo htmlspecialchars($tier['tierIcon']); ?>"
                alt="<?php echo htmlspecialchars($tier['tierName']); ?>">
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

<script>
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