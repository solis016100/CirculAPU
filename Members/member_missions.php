<?php
session_start();

/* Member access */
if (($_SESSION['user_role'] ?? '') !== 'Member' || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../db.php';
$conn->set_charset('utf8mb4');
$userID = $_SESSION['user_id'];
$memberName = $_SESSION['user_name'] ?? 'Member';
$message = '';
$error = '';

/* Short helper for prepared SQL queries. */
function runQuery($conn, $sql, $values = [])
{
    return $conn->execute_query($sql, $values);
}

/* Generate S001, V001, ET001 or N001. */
function nextID($conn, $table, $column, $prefix)
{
    $start = strlen($prefix) + 1;
    $row = $conn->query("SELECT $column FROM $table WHERE $column LIKE '{$prefix}%'
                         ORDER BY CAST(SUBSTRING($column,$start) AS UNSIGNED) DESC LIMIT 1")
                ->fetch_assoc();
    $number = $row ? (int)substr($row[$column], strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
}
function notify($conn, $userID, $title, $content)
{
    runQuery($conn, "INSERT INTO notification
                  (notificationID,userID,title,content,status,createdDate)
                  VALUES (?,?,?,?,'Unread',NOW())", [
        nextID($conn, 'notification', 'notificationID', 'N'),
        $userID, $title, $content
    ]);
}

/* Add missing EcoPoint records for approved missions. */
$approved = runQuery($conn, "SELECT ms.submissionID,m.ecoPointsReward
                          FROM missionsubmission ms JOIN missions m ON m.missionID=ms.missionID
                          WHERE ms.userID=? AND ms.submissionStatus IN ('Approved','Verified')
                          AND NOT EXISTS (
                              SELECT 1 FROM ecopointstransaction et
                              WHERE et.userID=ms.userID AND et.referenceID=ms.submissionID
                              AND et.transactionType='Earned' AND et.sourceType='Mission'
                          )", [$userID]);
while ($mission = $approved->fetch_assoc()) {
    runQuery($conn, "INSERT INTO ecopointstransaction
                  (transactionID,userID,points,transactionType,sourceType,referenceID,transactionDate)
                  VALUES (?,?,?,'Earned','Mission',?,NOW())", [
        nextID($conn, 'ecopointstransaction', 'transactionID', 'ET'),
        $userID, (int)$mission['ecoPointsReward'], $mission['submissionID']
    ]);
}

/* Join a mission. */
if (isset($_POST['joinMission'])) {
    $missionID = trim($_POST['missionID'] ?? '');
    $joined = runQuery($conn, 'SELECT 1 FROM missionsubmission WHERE userID=? AND missionID=? LIMIT 1',
                    [$userID, $missionID])->num_rows;
    if (!$joined) {
        runQuery($conn, "INSERT INTO missionsubmission
                      (submissionID,userID,missionID,evidencePath,submissionDate,submissionStatus)
                      VALUES (?,?,?,'',NOW(),'In Progress')", [
            nextID($conn, 'missionsubmission', 'submissionID', 'S'), $userID, $missionID
        ]);
    }
    header('Location: member_missions.php?tab=my' . (!$joined ? '&joined=1' : ''));
    exit;
}

/* Upload mission proof. */
if (isset($_POST['uploadProof'])) {
    $missionID = trim($_POST['missionID'] ?? '');
    $submission = runQuery($conn, "SELECT ms.submissionID,m.missionName
                                FROM missionsubmission ms JOIN missions m ON m.missionID=ms.missionID
                                WHERE ms.userID=? AND ms.missionID=? LIMIT 1",
                        [$userID, $missionID])->fetch_assoc();
    $file = $_FILES['evidenceImage'] ?? null;
    if (!$submission) {
        $error = 'You must join the mission before uploading proof.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a proof image.';
    } else {
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$mime])) {
            $error = 'Only JPG, PNG and WEBP images are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Image must be smaller than 5MB.';
        } else {
            $folder = '../uploads/submissions/';
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $fileName = "{$userID}_{$missionID}_" . time() . '.' . $types[$mime];
            $databasePath = 'uploads/submissions/' . $fileName;
            if (!move_uploaded_file($file['tmp_name'], $folder . $fileName)) {
                $error = 'Unable to upload the image.';
            } else {
                $conn->begin_transaction();
                try {
                    runQuery($conn, "UPDATE missionsubmission SET evidencePath=?,submissionDate=NOW(),
                                  submissionStatus='Pending' WHERE userID=? AND missionID=?",
                          [$databasePath, $userID, $missionID]);
                    $verification = runQuery($conn, "SELECT verificationID FROM verificationrecords
                                                  WHERE targetID=? AND targetType='MissionSubmission' LIMIT 1",
                                          [$submission['submissionID']])->fetch_assoc();
                    if ($verification) {
                        runQuery($conn, "UPDATE verificationrecords SET status='Pending',verifiedBy=NULL,
                                      remarks=NULL,verifiedDate=NOW()
                                      WHERE targetID=? AND targetType='MissionSubmission'",
                              [$submission['submissionID']]);
                    } else {
                        runQuery($conn, "INSERT INTO verificationrecords
                                      (verificationID,userID,targetID,targetType,status,verifiedDate)
                                      VALUES (?,?,?,'MissionSubmission','Pending',NOW())", [
                            nextID($conn, 'verificationrecords', 'verificationID', 'V'),
                            $userID, $submission['submissionID']
                        ]);
                    }
                    $member = runQuery($conn, 'SELECT name FROM user WHERE userID=? LIMIT 1', [$userID])
                              ->fetch_assoc();
                    $memberName = $member['name'] ?? $memberName;
                    $content = "$memberName completed {$submission['missionName']} ($missionID). " .
                               'Mission ready for approval.';
                    $auditors = $conn->query("SELECT userID FROM user WHERE role='Auditor' AND status='Active'");
                    while ($auditor = $auditors->fetch_assoc()) {
                        notify($conn, $auditor['userID'], 'Mission Completed', $content);
                    }
                    notify($conn, $userID, 'Mission Submitted Successfully',
                           "Your proof for {$submission['missionName']} ($missionID) is waiting for auditor approval.");
                    $conn->commit();
                    header('Location: member_missions.php?tab=my&uploaded=1');
                    exit;
                } catch (Exception $exception) {
                    $conn->rollback();
                    $error = 'Unable to save proof. Please try again.';
                }
            }
        }
    }
}
if (isset($_GET['joined'])) $message = 'Mission joined successfully.';
if (isset($_GET['uploaded'])) $message = 'Proof uploaded successfully. Waiting for verification.';

/* Mission detail. */
$selectedMission = null;
$missionJoined = false;
$missionUserStatus = '';
$missionEvidence = '';
$missionSubmissionDate = '';
if ($selectedMissionID = trim($_GET['mission'] ?? '')) {
    $selectedMission = runQuery($conn, "SELECT missionID,missionName,missionDescription,
                                     missionRequirement,ecoPointsReward,status
                                     FROM missions WHERE missionID=? LIMIT 1",
                             [$selectedMissionID])->fetch_assoc();
    $joinedData = runQuery($conn, "SELECT submissionStatus,evidencePath,submissionDate
                                FROM missionsubmission WHERE userID=? AND missionID=? LIMIT 1",
                        [$userID, $selectedMissionID])->fetch_assoc();
    if ($joinedData) {
        $missionJoined = true;
        $missionUserStatus = $joinedData['submissionStatus'] ?: 'In Progress';
        $missionEvidence = $joinedData['evidencePath'] ?? '';
        $missionSubmissionDate = $joinedData['submissionDate'] ?? '';
    }
}

/* Mission lists. */
$available = runQuery($conn, "SELECT m.* FROM missions m WHERE m.status='Active'
                           AND NOT EXISTS (SELECT 1 FROM missionsubmission ms
                                           WHERE ms.missionID=m.missionID AND ms.userID=?)
                           ORDER BY m.missionID", [$userID]);
$myMissions = runQuery($conn, "SELECT m.*,ms.submissionStatus,ms.evidencePath,ms.submissionDate
                            FROM missionsubmission ms JOIN missions m ON m.missionID=ms.missionID
                            WHERE ms.userID=? ORDER BY ms.submissionDate DESC", [$userID]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Missions - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_styles.css">
</head>
<body class="role-member">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <?php include '../header.php'; ?>
        <main class="page-body">
            <?php if ($error): ?><div class="mission-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="mission-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($selectedMission): ?>
                <!-- Mission details -->
                <div class="mission-detail-top">
                    <div><h1>Mission Details</h1><p>Learn more about this mission and make an impact.</p></div>
                    <a href="member_missions.php" class="mission-back-btn member-btn secondary">← Back to Missions</a>
                </div>
                <div class="mission-detail-card member-card">
                    <div class="mission-detail-header member-card-header">
                        <div class="mission-detail-title">
                            <h2><?= htmlspecialchars($selectedMission['missionName']) ?></h2>
                            <span class="mission-detail-status <?= $missionJoined
                                ? htmlspecialchars(strtolower(str_replace(' ', '-', $missionUserStatus))) : 'available' ?>">
                                <?= $missionJoined ? htmlspecialchars($missionUserStatus) : 'Available' ?>
                            </span>
                        </div>
                        <div class="mission-detail-points">
                            <strong>+<?= (int)$selectedMission['ecoPointsReward'] ?></strong><span>EcoPoints</span>
                        </div>
                    </div>
                    <div class="mission-detail-body">
                        <div class="mission-detail-section"><h3>Description</h3><p><?= htmlspecialchars($selectedMission['missionDescription']) ?></p></div>
                        <div class="mission-detail-section"><h3>Mission Requirement</h3><p><?= htmlspecialchars($selectedMission['missionRequirement']) ?></p></div>
                        <div class="mission-detail-info-grid">
                            <?php foreach ([
                                'Mission ID' => $selectedMission['missionID'],
                                'Reward' => $selectedMission['ecoPointsReward'] . ' EcoPoints',
                                'Mission Status' => $selectedMission['status'],
                                'Your Status' => $missionJoined ? $missionUserStatus : 'Not Joined'
                            ] as $label => $value): ?>
                                <div class="mission-detail-info"><span><?= $label ?></span><strong><?= htmlspecialchars($value) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($missionJoined): $status = strtolower(trim($missionUserStatus)); ?>
                            <div class="mission-proof-section">
                                <div class="proof-heading">
                                    <h3>Proof Submission</h3>
                                    <span class="proof-label <?= $missionEvidence ? 'uploaded' : 'not-uploaded' ?>">
                                        <?= $missionEvidence ? '✓ Proof uploaded' : 'Proof not uploaded' ?>
                                    </span>
                                </div>
                                <p class="proof-requirement"><?= htmlspecialchars($selectedMission['missionRequirement']) ?></p>
                                <?php if ($missionEvidence): ?>
                                    <div class="mission-proof-preview">
                                        <img src="../<?= htmlspecialchars($missionEvidence) ?>" alt="Mission Evidence">
                                        <div class="proof-preview-info"><strong>Submitted Evidence</strong>
                                            <?php if ($missionSubmissionDate): ?>
                                                <span><?= date('d M Y, h:i A', strtotime($missionSubmissionDate)) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($status, ['in progress', 'rejected'])): ?>
                                    <form method="post" enctype="multipart/form-data" class="proof-upload-form">
                                        <input type="hidden" name="missionID" value="<?= htmlspecialchars($selectedMission['missionID']) ?>">
                                        <div class="proof-file-area">
                                            <label for="evidenceImage">Proof Photo</label>
                                            <input type="file" id="evidenceImage" name="evidenceImage"
                                                   accept=".jpg,.jpeg,.png,.webp" required>
                                            <small>JPG, PNG or WEBP. Maximum 5MB.</small>
                                        </div>
                                        <button name="uploadProof" class="upload-proof-btn member-btn primary">
                                            <?= $status === 'rejected' ? 'Resubmit Proof' : 'Upload Proof' ?>
                                        </button>
                                    </form>
                                <?php elseif ($status === 'pending'): ?>
                                    <div class="mission-proof-locked pending">Proof submitted. Waiting for auditor approval.</div>
                                <?php elseif (in_array($status, ['approved', 'verified'])): ?>
                                    <div class="mission-proof-locked approved">✓ Mission approved. Proof submission is completed.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mission-detail-footer">
                        <?php if (!$missionJoined && $selectedMission['status'] === 'Active'): ?>
                            <form method="post"><input type="hidden" name="missionID" value="<?= htmlspecialchars($selectedMission['missionID']) ?>">
                                <button name="joinMission" class="mission-detail-join-btn member-btn primary">Join Mission</button>
                            </form>
                        <?php elseif ($missionJoined): ?>
                            <a href="member_missions.php?tab=my" class="mission-my-btn member-btn secondary">View My Missions</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Mission lists -->
                <div class="mission-page-header">
                    <h1>Sustainability Missions</h1>
                    <p>Join missions, take action and earn EcoPoints while helping our campus.</p>
                </div>
                <div class="mission-dashboard">
                    <div class="mission-tabs">
                        <button id="availableTab" class="mission-tab active" onclick="showMissionTab('available',this)">Available Missions</button>
                        <button id="myMissionTab" class="mission-tab" onclick="showMissionTab('myMissions',this)">My Missions</button>
                    </div>
                    <div id="available" class="mission-tab-content active">
                        <?php if ($available->num_rows): while ($mission = $available->fetch_assoc()): ?>
                            <div class="mission-card member-card">
                                <div class="mission-card-info"><h2><?= htmlspecialchars($mission['missionName']) ?></h2>
                                    <strong>Description:</strong><p><?= htmlspecialchars($mission['missionDescription']) ?></p></div>
                                <div class="mission-card-right">
                                    <div class="mission-points"><strong>+<?= (int)$mission['ecoPointsReward'] ?></strong><span>EcoPoints</span></div>
                                    <form method="post"><input type="hidden" name="missionID" value="<?= htmlspecialchars($mission['missionID']) ?>">
                                        <button name="joinMission" class="join-mission-btn member-btn primary">Join Mission</button></form>
                                </div>
                                <div class="mission-card-action"><a href="?mission=<?= urlencode($mission['missionID']) ?>">→</a></div>
                            </div>
                        <?php endwhile; else: ?><div class="empty-mission">No available missions.</div><?php endif; ?>
                    </div>
                    <div id="myMissions" class="mission-tab-content">
                        <?php if ($myMissions->num_rows): while ($mission = $myMissions->fetch_assoc()):
                            $status = $mission['submissionStatus'] ?: 'In Progress'; ?>
                            <div class="mission-card member-card">
                                <div class="mission-card-info"><h2><?= htmlspecialchars($mission['missionName']) ?></h2>
                                    <strong>Description:</strong><p><?= htmlspecialchars($mission['missionDescription']) ?></p>
                                    <span class="proof-small <?= $mission['evidencePath'] ? 'uploaded' : 'not-uploaded' ?>">
                                        <?= $mission['evidencePath'] ? '✓ Proof Uploaded' : 'Proof not uploaded' ?>
                                    </span>
                                </div>
                                <div class="mission-card-right">
                                    <div class="my-mission-status <?= htmlspecialchars(strtolower(str_replace(' ', '-', $status))) ?>"><?= htmlspecialchars($status) ?></div>
                                    <div class="mission-small-points">+<?= (int)$mission['ecoPointsReward'] ?> EcoPoints</div>
                                </div>
                                <div class="mission-card-action"><a href="?mission=<?= urlencode($mission['missionID']) ?>">→</a></div>
                            </div>
                        <?php endwhile; else: ?><div class="empty-mission">You have not joined any missions yet.</div><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
function showMissionTab(id, button) {
    document.querySelectorAll('.mission-tab-content,.mission-tab').forEach(function (item) {
        item.classList.remove('active');
    });
    document.getElementById(id).classList.add('active');
    button.classList.add('active');
}
if (new URLSearchParams(location.search).get('tab') === 'my') {
    showMissionTab('myMissions', document.getElementById('myMissionTab'));
}
</script>
</body>
</html>
