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
$error = "";

/* Generate Submission ID */
function generateSubmissionID($conn) {
    $result = $conn->query("
    SELECT submissionID
    FROM missionsubmission
    WHERE submissionID LIKE 'S%'
    ORDER BY CAST(SUBSTRING(submissionID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $number = (int)substr($row['submissionID'],1) + 1;
    } else {
        $number = 1;
    }

    return "S".str_pad($number,3,"0",STR_PAD_LEFT);
}

/* Generate Verification ID */
function generateVerificationID($conn) {
    $result = $conn->query("
    SELECT verificationID
    FROM verificationrecords
    WHERE verificationID LIKE 'V%'
    ORDER BY CAST(SUBSTRING(verificationID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $number = (int)substr($row['verificationID'],1) + 1;
    } else {
        $number = 1;
    }

    return "V".str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Generate EcoPoint Transaction ID */
function generateEcoPointTransactionID($conn) {
    $result = $conn->query("
    SELECT transactionID
    FROM ecopointstransaction
    WHERE transactionID LIKE 'ET%'
    ORDER BY CAST(SUBSTRING(transactionID,3) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $number = (int)substr($row['transactionID'],2) + 1;
    } else {
        $number = 1;
    }

    return "ET".str_pad($number,3,"0",STR_PAD_LEFT);
}



/* Generate Notification ID */
function generateNotificationID($conn) {
    $result = $conn->query("
    SELECT MAX(
        CAST(SUBSTRING(notificationID,2) AS UNSIGNED)
    ) AS maxNumber
    FROM notification
    WHERE notificationID REGEXP '^N[0-9]+$'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $number = ((int)($row['maxNumber'] ?? 0)) + 1;
    } else {
        $number = 1;
    }

    return "N".str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Add EcoPoints Transaction When Mission Is Approved */
$approvedMissionQuery = "
SELECT
    ms.submissionID,
    m.ecoPointsReward
FROM missionsubmission ms
INNER JOIN missions m
ON ms.missionID = m.missionID
WHERE ms.userID = '$userID'
AND ms.submissionStatus IN ('Approved','Verified')
AND NOT EXISTS (
    SELECT 1
    FROM ecopointstransaction et
    WHERE et.userID = ms.userID
    AND et.referenceID = ms.submissionID
    AND et.transactionType = 'Earned'
    AND et.sourceType = 'Mission'
)
";

$approvedMissionResult = $conn->query($approvedMissionQuery);

if ($approvedMissionResult) {
    while ($approvedMission = $approvedMissionResult->fetch_assoc()) {

        $transactionID = generateEcoPointTransactionID($conn);
        $submissionID = $conn->real_escape_string($approvedMission['submissionID']);
        $rewardPoints = (int)$approvedMission['ecoPointsReward'];

        $conn->query("
        INSERT INTO ecopointstransaction
        (transactionID, userID, points, transactionType,
         sourceType, referenceID, transactionDate)
        VALUES
        ('$transactionID', '$userID', $rewardPoints, 'Earned',
         'Mission', '$submissionID', NOW())
        ");
    }
}


/* Join Mission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['joinMission'])) {
    $missionID = $conn->real_escape_string($_POST['missionID']);

    $checkQuery="
    SELECT submissionID
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$missionID'
    LIMIT 1
    ";

    $checkResult = $conn->query($checkQuery);

    if ($checkResult && $checkResult->num_rows === 0) {
        $submissionID = generateSubmissionID($conn);

        $joinQuery="
        INSERT INTO missionsubmission
        (submissionID, userID, missionID, evidencePath, submissionDate, submissionStatus)
        VALUES
        ('$submissionID', '$userID', '$missionID', '', NOW(), 'In Progress')
        ";

        if ($conn->query($joinQuery)) {
            header("Location: member_missions.php?tab=my&joined=1");
            exit();
        } else {
            $error = "Unable to join mission: ".$conn->error;
        }
    } else {
        header("Location: member_missions.php?tab=my");
        exit();
    }
}

/* Upload Proof */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uploadProof'])) {
    $missionID = $conn->real_escape_string($_POST['missionID']);

    $checkQuery = "
    SELECT submissionID
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$missionID'
    LIMIT 1
    ";

    $checkResult = $conn->query($checkQuery);

    if (!$checkResult || $checkResult->num_rows === 0) {
        $error="You must join the mission before uploading proof.";
    } elseif (!isset($_FILES['evidenceImage']) || $_FILES['evidenceImage']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a proof image.";
    } else {
        $submissionData = $checkResult->fetch_assoc();
        $submissionID = $submissionData['submissionID'];

        $file = $_FILES['evidenceImage'];
        $allowedExtensions = ['jpg','jpeg','png','webp'];
        $extension = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));

        if (!in_array($extension,$allowedExtensions)) {
            $error = "Only JPG, PNG and WEBP images are allowed.";
        } elseif ($file['size'] > 5*1024*1024) {
            $error = "Image must be smaller than 5MB.";
        } else {
            $uploadFolder = "../uploads/submissions/";

            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder,0777,true);
            }

            $fileName = $userID."_".$missionID."_".time().".".$extension;
            $filePath = $uploadFolder.$fileName;
            $databasePath = "uploads/submissions/".$fileName;

            if (move_uploaded_file($file['tmp_name'],$filePath)) {
                $safePath = $conn->real_escape_string($databasePath);

                $updateQuery="
                UPDATE missionsubmission
                SET evidencePath='$safePath',
                submissionDate=NOW(),
                submissionStatus='Pending'
                WHERE userID='$userID'
                AND missionID='$missionID'
                ";

                if ($conn->query($updateQuery)) {

                    /* Add Mission Submission to Verification Record */
                    $verificationCheck = $conn->query("
                    SELECT verificationID
                    FROM verificationrecords
                    WHERE targetID='$submissionID'
                    AND targetType='MissionSubmission'
                    LIMIT 1
                    ");

                    if ($verificationCheck && $verificationCheck->num_rows > 0) {

                        // Reset existing record when member uploads proof again
                        $verificationQuery = "
                        UPDATE verificationrecords
                        SET status='Pending',
                            verifiedBy=NULL,
                            remarks=NULL,
                            verifiedDate=NOW()
                        WHERE targetID='$submissionID'
                        AND targetType='MissionSubmission'
                        ";

                    } else {

                        $verificationID = generateVerificationID($conn);

                        $verificationQuery = "
                        INSERT INTO verificationrecords
                        (verificationID, userID, verifiedBy, targetID,
                         targetType, status, remarks, verifiedDate)
                        VALUES
                        ('$verificationID', '$userID', NULL, '$submissionID',
                         'MissionSubmission', 'Pending', NULL, NOW())
                        ";
                    }

                    if ($conn->query($verificationQuery)) {

                        /* Send Notification To Auditor(s) */
                        $missionInfoResult = $conn->query("
                        SELECT missionName
                        FROM missions
                        WHERE missionID='$missionID'
                        LIMIT 1
                        ");

                        $missionName = "Mission";

                        if ($missionInfoResult && $missionInfoResult->num_rows > 0) {
                            $missionInfo = $missionInfoResult->fetch_assoc();
                            $missionName = $missionInfo['missionName'];
                        }

                        /* Get latest member name */
                        $memberInfoResult = $conn->query("
                        SELECT name
                        FROM user
                        WHERE userID='$userID'
                        LIMIT 1
                        ");

                        if ($memberInfoResult && $memberInfoResult->num_rows > 0) {
                            $memberInfo = $memberInfoResult->fetch_assoc();
                            $memberName = $memberInfo['name'];
                        }

                        /* Send to all active Auditors */
                        $auditorResult = $conn->query("
                        SELECT userID
                        FROM user
                        WHERE role='Auditor'
                        AND status='Active'
                        ");

                        if ($auditorResult) {

                            $notificationTitle = "Mission Completed";

                            $notificationContent =
                                $memberName .
                                " completed the mission " .
                                $missionName .
                                " (" . $missionID . "). Mission ready for approval.";

                            $safeTitle = $conn->real_escape_string($notificationTitle);
                            $safeContent = $conn->real_escape_string($notificationContent);

                            while ($auditor = $auditorResult->fetch_assoc()) {

                                $auditorID = $conn->real_escape_string($auditor['userID']);
                                $notificationID = generateNotificationID($conn);

                                $auditorNotificationQuery = "
                                INSERT INTO notification
                                (notificationID, userID, title, content, status, createdDate)
                                VALUES
                                ('$notificationID', '$auditorID', '$safeTitle',
                                 '$safeContent', 'Unread', NOW())
                                ";

                                if (!$conn->query($auditorNotificationQuery)) {
                                    $error = "Auditor notification failed: ".$conn->error;
                                    break;
                                }
                            }
                        }

                        /* Send Notification To Member */
                        $memberNotificationID = generateNotificationID($conn);

                        $memberNotificationTitle = "Mission Submitted Successfully";

                        $memberNotificationContent =
                            "Your proof for " .
                            $missionName .
                            " (" . $missionID . ") has been submitted successfully and is waiting for auditor approval.";

                        $safeMemberNotificationTitle =
                            $conn->real_escape_string($memberNotificationTitle);

                        $safeMemberNotificationContent =
                            $conn->real_escape_string($memberNotificationContent);

                        $memberNotificationQuery = "
                        INSERT INTO notification
                        (notificationID, userID, title, content, status, createdDate)
                        VALUES
                        ('$memberNotificationID', '$userID', '$safeMemberNotificationTitle',
                         '$safeMemberNotificationContent', 'Unread', NOW())
                        ";

                        if (!$conn->query($memberNotificationQuery)) {
                            $error = "Member notification failed: ".$conn->error;
                        }

                        if ($error == "") {
                            header("Location: member_missions.php?tab=my&uploaded=1");
                            exit();
                        }

                    } else {
                        $error = "Unable to create verification record: ".$conn->error;
                    }

                } else {
                    $error = "Unable to save proof: ".$conn->error;
                }
            } else {
                $error="Unable to upload the image.";
            }
        }
    }
}

/* Success Messages */
if (isset($_GET['joined'])) {
    $message = "Mission joined successfully.";
}

if (isset($_GET['uploaded'])) {
    $message = "Proof uploaded successfully. Waiting for verification.";
}

/* Mission Details */
$selectedMission = null;
$missionJoined = false;
$missionUserStatus = "";
$missionEvidence = "";
$missionSubmissionDate = "";

if (isset($_GET['mission']) && $_GET['mission'] !== "") {
    $selectedMissionID = $conn->real_escape_string($_GET['mission']);

    $detailQuery="
    SELECT missionID,missionName,missionDescription,
    missionRequirement,ecoPointsReward,status
    FROM missions
    WHERE missionID='$selectedMissionID'
    LIMIT 1
    ";

    $detailResult = $conn->query($detailQuery);

    if ($detailResult && $detailResult->num_rows > 0) {
        $selectedMission = $detailResult->fetch_assoc();
    }

    $joinedQuery="
    SELECT submissionStatus,evidencePath,submissionDate
    FROM missionsubmission
    WHERE userID='$userID'
    AND missionID='$selectedMissionID'
    LIMIT 1
    ";

    $joinedResult = $conn->query($joinedQuery);

    if ($joinedResult && $joinedResult->num_rows > 0) {
        $missionJoined = true;
        $joinedData = $joinedResult->fetch_assoc();
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

$availableResult = $conn->query($availableQuery);

if (!$availableResult) {
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

$myMissionResult = $conn->query($myMissionQuery);

if (!$myMissionResult) {
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
<link rel="stylesheet" href="member_new_style.css">
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

            <?php
            $statusLower = strtolower(trim($missionUserStatus));
            ?>

            <?php if(
                $statusLower === 'in progress' ||
                $statusLower === 'rejected'
            ): ?>

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
                    <?php
                    echo $statusLower === 'rejected'
                        ? 'Resubmit Proof'
                        : 'Upload Proof';
                    ?>
                </button>
            </form>

            <?php elseif($statusLower === 'pending'): ?>

            <div class="mission-proof-locked pending">
                Proof submitted. Waiting for auditor approval.
            </div>

            <?php elseif(
                $statusLower === 'approved' ||
                $statusLower === 'verified'
            ): ?>

            <div class="mission-proof-locked approved">
                ✓ Mission approved. Proof submission is completed.
            </div>

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