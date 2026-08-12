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
$error="";

/* Generate Submission ID */
function generateSubmissionID($conn){
    $result=$conn->query("
    SELECT submissionID
    FROM missionsubmission
    WHERE submissionID IS NOT NULL
    AND submissionID!=''
    ORDER BY CAST(SUBSTRING(submissionID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if($result&&$result->num_rows>0){
        $row=$result->fetch_assoc();
        $number=(int)substr($row['submissionID'],1)+1;
    }else{
        $number=1;
    }

    return "S".str_pad($number,3,"0",STR_PAD_LEFT);
}

/* Join Mission */
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['joinMission'])){
    $missionID=$conn->real_escape_string($_POST['missionID']);

    $checkQuery="
    SELECT submissionID
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$missionID'
    LIMIT 1
    ";

    $checkResult=$conn->query($checkQuery);

    if($checkResult&&$checkResult->num_rows===0){
        $submissionID=generateSubmissionID($conn);

        $joinQuery="
        INSERT INTO missionsubmission
        (submissionID,userID,missionID,evidencePath,submissionDate,submissionStatus)
        VALUES
        ('$submissionID','$userID','$missionID','',NOW(),'In Progress')
        ";

        if($conn->query($joinQuery)){
            header("Location: member_missions.php?tab=my&joined=1");
            exit();
        }else{
            $error="Unable to join mission: ".$conn->error;
        }
    }else{
        header("Location: member_missions.php?tab=my");
        exit();
    }
}

/* Upload Proof */
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['uploadProof'])){
    $missionID=$conn->real_escape_string($_POST['missionID']);

    $checkQuery="
    SELECT submissionID
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$missionID'
    LIMIT 1
    ";

    $checkResult=$conn->query($checkQuery);

    if(!$checkResult||$checkResult->num_rows===0){
        $error="You must join the mission before uploading proof.";
    }elseif(!isset($_FILES['evidenceImage'])||$_FILES['evidenceImage']['error']!==UPLOAD_ERR_OK){
        $error="Please select a proof image.";
    }else{
        $file=$_FILES['evidenceImage'];
        $allowedExtensions=['jpg','jpeg','png','webp'];
        $extension=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));

        if(!in_array($extension,$allowedExtensions)){
            $error="Only JPG, PNG and WEBP images are allowed.";
        }elseif($file['size']>5*1024*1024){
            $error="Image must be smaller than 5MB.";
        }else{
            $uploadFolder="../uploads/submissions/";

            if(!is_dir($uploadFolder)){
                mkdir($uploadFolder,0777,true);
            }

            $fileName=$userID."_".$missionID."_".time().".".$extension;
            $filePath=$uploadFolder.$fileName;
            $databasePath="uploads/submissions/".$fileName;

            if(move_uploaded_file($file['tmp_name'],$filePath)){
                $safePath=$conn->real_escape_string($databasePath);

                $updateQuery="
                UPDATE missionsubmission
                SET evidencePath='$safePath',
                submissionDate=NOW(),
                submissionStatus='Pending'
                WHERE userID='$userID'
                AND missionID='$missionID'
                ";

                if($conn->query($updateQuery)){
                    header("Location: member_missions.php?tab=my&uploaded=1");
                    exit();
                }else{
                    $error="Unable to save proof: ".$conn->error;
                }
            }else{
                $error="Unable to upload the image.";
            }
        }
    }
}

/* Success Messages */
if(isset($_GET['joined'])){
    $message="Mission joined successfully.";
}

if(isset($_GET['uploaded'])){
    $message="Proof uploaded successfully. Waiting for verification.";
}

/* Mission Details */
$selectedMission=null;
$missionJoined=false;
$missionUserStatus="";
$missionEvidence="";
$missionSubmissionDate="";

if(isset($_GET['mission'])&&$_GET['mission']!==""){
    $selectedMissionID=$conn->real_escape_string($_GET['mission']);

    $detailQuery="
    SELECT missionID,missionName,missionDescription,
    missionRequirement,ecoPointsReward,status
    FROM missions
    WHERE missionID='$selectedMissionID'
    LIMIT 1
    ";

    $detailResult=$conn->query($detailQuery);

    if($detailResult&&$detailResult->num_rows>0){
        $selectedMission=$detailResult->fetch_assoc();
    }

    $joinedQuery="
    SELECT submissionStatus,evidencePath,submissionDate
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$selectedMissionID'
    LIMIT 1
    ";

    $joinedResult=$conn->query($joinedQuery);

    if($joinedResult&&$joinedResult->num_rows>0){
        $missionJoined=true;
        $joinedData=$joinedResult->fetch_assoc();
        $missionUserStatus=$joinedData['submissionStatus']?:'In Progress';
        $missionEvidence=$joinedData['evidencePath']??'';
        $missionSubmissionDate=$joinedData['submissionDate']??'';
    }
}

/* Available Missions */
$availableQuery="
SELECT m.missionID,m.missionName,m.missionDescription,
m.missionRequirement,m.ecoPointsReward,m.status
FROM missions m
WHERE m.status='Active'
AND NOT EXISTS(
    SELECT 1
    FROM missionsubmission ms
    WHERE ms.missionID=m.missionID
    AND ms.userID='$userID'
)
ORDER BY m.missionID ASC
";

$availableResult=$conn->query($availableQuery);

if(!$availableResult){
    die("Available Mission Query Failed: ".$conn->error);
}

/* My Missions */
$myMissionQuery="
SELECT m.missionID,m.missionName,m.missionDescription,
m.missionRequirement,m.ecoPointsReward,
ms.submissionStatus,ms.evidencePath,ms.submissionDate
FROM missionsubmission ms
INNER JOIN missions m
ON ms.missionID=m.missionID
WHERE ms.userID='$userID'
ORDER BY ms.submissionDate DESC
";

$myMissionResult=$conn->query($myMissionQuery);

if(!$myMissionResult){
    die("My Mission Query Failed: ".$conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Missions - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="member_style.css">
</head>

<body class="role-member">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<?php if($error!=""): ?>
<div class="mission-alert error">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if($message!=""): ?>
<div class="mission-alert success">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if($selectedMission): ?>

<div class="mission-detail-top">
    <div>
        <h1>Mission Details</h1>
        <p>Learn more about this mission and how you can make an impact.</p>
    </div>

    <a href="member_missions.php" class="mission-back-btn">
        ← Back to Missions
    </a>
</div>

<div class="mission-detail-card">

    <div class="mission-detail-header">

        <div class="mission-detail-icon">
            <img src="../assets/icons/mission.png" alt="Mission">
        </div>

        <div class="mission-detail-title">
            <h2><?php echo htmlspecialchars($selectedMission['missionName']); ?></h2>

            <?php if($missionJoined): ?>
                <?php $detailStatusClass=strtolower(str_replace(' ','-',$missionUserStatus)); ?>

                <span class="mission-detail-status <?php echo $detailStatusClass; ?>">
                    <?php echo htmlspecialchars($missionUserStatus); ?>
                </span>
            <?php else: ?>
                <span class="mission-detail-status available">Available</span>
            <?php endif; ?>
        </div>

        <div class="mission-detail-points">
            <strong>+<?php echo htmlspecialchars($selectedMission['ecoPointsReward']); ?></strong>
            <span>EcoPoints</span>
        </div>

    </div>

    <div class="mission-detail-body">

        <div class="mission-detail-section">
            <h3>Description</h3>
            <p><?php echo htmlspecialchars($selectedMission['missionDescription']); ?></p>
        </div>

        <div class="mission-detail-section">
            <h3>Mission Requirement</h3>
            <p><?php echo htmlspecialchars($selectedMission['missionRequirement']); ?></p>
        </div>

        <div class="mission-detail-info-grid">

            <div class="mission-detail-info">
                <span>Mission ID</span>
                <strong><?php echo htmlspecialchars($selectedMission['missionID']); ?></strong>
            </div>

            <div class="mission-detail-info">
                <span>Reward</span>
                <strong><?php echo htmlspecialchars($selectedMission['ecoPointsReward']); ?> EcoPoints</strong>
            </div>

            <div class="mission-detail-info">
                <span>Mission Status</span>
                <strong><?php echo htmlspecialchars($selectedMission['status']); ?></strong>
            </div>

            <div class="mission-detail-info">
                <span>Your Status</span>
                <strong><?php echo $missionJoined?htmlspecialchars($missionUserStatus):'Not Joined'; ?></strong>
            </div>

        </div>

        <?php if($missionJoined): ?>

        <div class="mission-proof-section">

            <div class="proof-heading">
                <h3>Proof Submission</h3>

                <?php if(!empty($missionEvidence)): ?>
                    <span class="proof-label uploaded">✓ Proof uploaded</span>
                <?php else: ?>
                    <span class="proof-label not-uploaded">Proof not uploaded</span>
                <?php endif; ?>
            </div>

            <p class="proof-requirement">
                <?php echo htmlspecialchars($selectedMission['missionRequirement']); ?>
            </p>

            <?php if(!empty($missionEvidence)): ?>

            <div class="mission-proof-preview">
                <img src="../<?php echo htmlspecialchars($missionEvidence); ?>" alt="Mission Evidence">

                <div class="proof-preview-info">
                    <strong>Submitted Evidence</strong>

                    <?php if(!empty($missionSubmissionDate)): ?>
                        <span>
                            <?php echo date("d M Y, h:i A",strtotime($missionSubmissionDate)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>

            <?php if(strtolower($missionUserStatus)!=='verified'): ?>

            <form method="post" enctype="multipart/form-data" class="proof-upload-form">
                <input type="hidden" name="missionID"
                value="<?php echo htmlspecialchars($selectedMission['missionID']); ?>">

                <div class="proof-file-area">
                    <label for="evidenceImage">Proof Photo</label>
                    <input type="file" id="evidenceImage" name="evidenceImage"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                    <small>JPG, PNG or WEBP. Maximum 5MB.</small>
                </div>

                <button type="submit" name="uploadProof" class="upload-proof-btn">
                    <?php echo empty($missionEvidence)?'Upload Proof':'Replace Proof'; ?>
                </button>
            </form>

            <?php endif; ?>

        </div>

        <?php endif; ?>

    </div>

    <div class="mission-detail-footer">

        <?php if(!$missionJoined&&$selectedMission['status']==='Active'): ?>

        <form method="post" action="member_missions.php">
            <input type="hidden" name="missionID"
            value="<?php echo htmlspecialchars($selectedMission['missionID']); ?>">

            <button type="submit" name="joinMission" class="mission-detail-join-btn">
                Join Mission
            </button>
        </form>

        <?php elseif($missionJoined): ?>

        <a href="member_missions.php?tab=my" class="mission-my-btn">
            View My Missions
        </a>

        <?php endif; ?>

    </div>

</div>

<?php else: ?>

<div class="mission-page-header">
    <h1>Sustainability Missions</h1>
    <p>Join missions, take action and earn EcoPoints while making a positive impact on our campus.</p>
</div>

<div class="mission-dashboard">

    <div class="mission-tabs">
        <button type="button" id="availableTab" class="mission-tab active"
        onclick="showMissionTab('available',this)">
            Available Missions
        </button>

        <button type="button" id="myMissionTab" class="mission-tab"
        onclick="showMissionTab('myMissions',this)">
            My Missions
        </button>
    </div>

    <div id="available" class="mission-tab-content active">

        <?php if($availableResult->num_rows>0): ?>

        <?php while($mission=$availableResult->fetch_assoc()): ?>

        <div class="mission-card">

            <div class="mission-card-icon">
                <img src="../assets/icons/mission.png" alt="Mission">
            </div>

            <div class="mission-card-info">
                <h2><?php echo htmlspecialchars($mission['missionName']); ?></h2>
                <strong>Description:</strong>
                <p><?php echo htmlspecialchars($mission['missionDescription']); ?></p>
            </div>

            <div class="mission-card-right">
                <div class="mission-points">
                    <strong>+<?php echo htmlspecialchars($mission['ecoPointsReward']); ?></strong>
                    <span>EcoPoints</span>
                </div>

                <form method="post" action="member_missions.php">
                    <input type="hidden" name="missionID"
                    value="<?php echo htmlspecialchars($mission['missionID']); ?>">

                    <button type="submit" name="joinMission" class="join-mission-btn">
                        Join Mission
                    </button>
                </form>
            </div>

            <div class="mission-card-action">
                <a href="member_missions.php?mission=<?php echo urlencode($mission['missionID']); ?>">
                    →
                </a>
            </div>

        </div>

        <?php endwhile; ?>

        <?php else: ?>

        <div class="empty-mission">
            No available missions.
        </div>

        <?php endif; ?>

    </div>

    <div id="myMissions" class="mission-tab-content">

        <?php if($myMissionResult->num_rows>0): ?>

        <?php while($mission=$myMissionResult->fetch_assoc()): ?>

        <?php
        $submissionStatus=$mission['submissionStatus']?:'In Progress';
        $statusClass=strtolower(str_replace(' ','-',$submissionStatus));
        $evidencePath=$mission['evidencePath']??'';
        ?>

        <div class="mission-card">

            <div class="mission-card-icon">
                <img src="../assets/icons/mission.png" alt="Mission">
            </div>

            <div class="mission-card-info">
                <h2><?php echo htmlspecialchars($mission['missionName']); ?></h2>
                <strong>Description:</strong>
                <p><?php echo htmlspecialchars($mission['missionDescription']); ?></p>

                <?php if(!empty($evidencePath)): ?>
                    <span class="proof-small uploaded">✓ Proof Uploaded</span>
                <?php else: ?>
                    <span class="proof-small not-uploaded">Proof not uploaded</span>
                <?php endif; ?>
            </div>

            <div class="mission-card-right">
                <div class="my-mission-status <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($submissionStatus); ?>
                </div>

                <div class="mission-small-points">
                    +<?php echo htmlspecialchars($mission['ecoPointsReward']); ?> EcoPoints
                </div>
            </div>

            <div class="mission-card-action">
                <a href="member_missions.php?mission=<?php echo urlencode($mission['missionID']); ?>">
                    →
                </a>
            </div>

        </div>

        <?php endwhile; ?>

        <?php else: ?>

        <div class="empty-mission">
            You have not joined any missions yet.
        </div>

        <?php endif; ?>

    </div>

</div>

<?php endif; ?>

</main>
</div>
</div>

<script>
function showMissionTab(tabID,button){
    document.querySelectorAll('.mission-tab-content').forEach(function(content){
        content.classList.remove('active');
    });

    document.querySelectorAll('.mission-tab').forEach(function(tab){
        tab.classList.remove('active');
    });

    document.getElementById(tabID).classList.add('active');
    button.classList.add('active');
}

const params=new URLSearchParams(window.location.search);

if(params.get('tab')==='my'){
    const myTab=document.getElementById('myMissionTab');

    if(myTab){
        showMissionTab('myMissions',myTab);
    }
}
</script>

</body>
</html>