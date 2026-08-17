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
$message="";
$messageType="";

/* Get User EcoPoints */
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
SELECT *
FROM tiers
WHERE minimumEcoPoints<=$totalPoints
AND minimumExchanges<=$totalExchange
ORDER BY minimumEcoPoints DESC,minimumExchanges DESC
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
        'minimumEcoPoints'=>0,
        'minimumExchanges'=>0
    ];
}

/* Redemption Limit Based On Tier */
$maxRedeem=$tier['minimumExchanges'];

if($maxRedeem==0){
    $maxRedeem=1;
}

/* Count Redeemed Rewards */
$redeemCountQuery="
SELECT COUNT(*) AS totalRedeemed
FROM rewardredemption
WHERE userID='$userID'
";
$redeemCountResult=$conn->query($redeemCountQuery);
$redeemCountData=$redeemCountResult->fetch_assoc();
$totalRedeemed=$redeemCountData['totalRedeemed']??0;

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
        }elseif($totalPoints<$reward['ecoPointsCost']){
            $message="You do not have enough EcoPoints.";
            $messageType="error";
        }elseif($totalRedeemed>=$maxRedeem){
            $message="You have reached the redemption limit for your current tier.";
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

                /* Set Unavailable When Stock Is Empty */
                $conn->query("
                UPDATE rewards
                SET availability='Unavailable'
                WHERE rewardID='$rewardID'
                AND quantity<=0
                ");

                $conn->commit();

                $totalPoints-=$cost;
                $totalRedeemed++;

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
WHERE minimumEcoPoints<=$totalPoints
AND minimumExchanges<=$totalExchange
ORDER BY minimumEcoPoints DESC,minimumExchanges DESC
LIMIT 1
";
$tierResult=$conn->query($tierQuery);

if($tierResult&&$tierResult->num_rows>0){
    $tier=$tierResult->fetch_assoc();
}

$maxRedeem=$tier['minimumExchanges'];

if($maxRedeem==0){
    $maxRedeem=1;
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

/* Redemption Progress */
$redeemProgress=($totalRedeemed/$maxRedeem)*100;

if($redeemProgress>100){
    $redeemProgress=100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Rewards & Redemption - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="member_new_style.css?v=32">
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
            $enoughPoints=$totalPoints>=$reward['ecoPointsCost'];
            $withinLimit=$totalRedeemed<$maxRedeem;
            $canRedeem=$inStock&&$enoughPoints&&$withinLimit;
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

                        <?php elseif(!$withinLimit): ?>
                            <span class="reward-status limit">
                                Tier Limit Reached
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
            <strong><?php echo $totalPoints; ?></strong>
            <span>Available EcoPoints</span>
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
                    <span>EcoPoints</span>
                    <strong><?php echo $totalPoints; ?></strong>
                </div>

                <div>
                    <span>Exchanges</span>
                    <strong><?php echo $totalExchange; ?></strong>
                </div>
            </div>
        </div>

        <!-- Redemption Progress -->
        <div class="reward-limit-box">
            <h3>Redemption Progress</h3>

            <div class="reward-limit-number">
                <strong><?php echo $totalRedeemed; ?></strong>
                <span>/ <?php echo $maxRedeem; ?></span>
            </div>

            <p>Rewards Redeemed</p>

            <div class="reward-progress">
                <div class="reward-progress-bar"
                style="width:<?php echo $redeemProgress; ?>%">
                </div>
            </div>

            <?php if($totalRedeemed>=$maxRedeem): ?>

                <span class="reward-limit-message">
                    Current tier limit reached
                </span>

            <?php else: ?>

                <span class="reward-limit-message">
                    <?php echo $maxRedeem-$totalRedeemed; ?>
                    redemptions remaining
                </span>

            <?php endif; ?>

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