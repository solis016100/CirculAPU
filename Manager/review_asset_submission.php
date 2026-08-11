<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING($column,4) AS UNSIGNED)),0)+1 nextID FROM $table")->fetch_assoc();
    return sprintf($prefix . '%03d', (int)$row['nextID']);
}
function imagePath($v): string {
    if (!$v) return '';
    $v = str_replace('\\', '/', trim($v));
    return preg_match('/^https?:\/\//i', $v) ? $v : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $v), '/');
}

$managerID = $_SESSION['user_id'];
$assetID = trim($_GET['assetID'] ?? '');
if (!$assetID) { header('Location: manager_assets.php'); exit(); }

$asset = null;
$categories = [];
$error = '';
$conditions = ['New','Like New','Good','Fair','Poor'];

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    /* 1. Load Asset */
    $stmt = $conn->prepare("
        SELECT a.*,u.name ownerName,u.email ownerEmail
        FROM assets a
        JOIN user u ON a.ownerID=u.userID
        WHERE a.assetID=? LIMIT 1
    ");
    $stmt->bind_param('s', $assetID);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) {
        $error = 'The selected asset submission could not be found.';
    } elseif ($asset['listingStatus'] !== 'Pending Review') {
        header("Location: manager_asset_view.php?assetID=$assetID"); exit();
    }

    /* 2. Active Categories + Current Category */
    if ($asset) {
        $stmt = $conn->prepare("
            SELECT categoryID,categoryName
            FROM categories
            WHERE status='Active' OR categoryID=?
            ORDER BY categoryName
        ");
        $stmt->bind_param('s', $asset['categoryID']);
        $stmt->execute();
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    /* 3. Review */
    if ($asset && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $condition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $reason = trim($_POST['correctionReason'] ?? '');
        $action = trim($_POST['reviewAction'] ?? '');
        $availability = 'Unavailable';

        if (!$name) $error = 'Asset Name is required.';
        elseif (strlen($name) > 150) $error = 'Asset Name must not exceed 150 characters.';
        elseif (strlen($description) > 255) $error = 'Description must not exceed 255 characters.';
        elseif (!in_array($condition, $conditions, true)) $error = 'Please select a valid condition.';
        elseif (!in_array($action, ['save','return','verify'], true)) $error = 'Invalid review action.';
        elseif ($action === 'return' && !$reason) $error = 'Please enter a correction reason.';
        elseif ($action === 'return' && strlen($reason) > 150) $error = 'Correction Reason must not exceed 150 characters.';

        if (!$error) {
            $stmt = $conn->prepare("
                SELECT 1 FROM categories
                WHERE categoryID=? AND (status='Active' OR categoryID=?)
                LIMIT 1
            ");
            $stmt->bind_param('ss', $categoryID, $asset['categoryID']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_row()) $error = 'Please select a valid category.';
            $stmt->close();
        }

        if (!$error) {
            $newStatus = $action === 'return' ? 'Draft' : ($action === 'verify' ? 'Ready for Verification' : 'Pending Review');
            $conn->begin_transaction();

            try {
                $oldAvailability = $asset['availabilityStatus'];

                $stmt = $conn->prepare("
                    UPDATE assets SET categoryID=?,assetName=?,description=?,assetCondition=?,
                    availabilityStatus=?,listingStatus=?,updatedDate=CURRENT_TIMESTAMP
                    WHERE assetID=?
                ");
                $stmt->bind_param('sssssss', $categoryID, $name, $description, $condition, $availability, $newStatus, $assetID);
                $stmt->execute(); $stmt->close();

                if ($oldAvailability !== $availability) {
                    $historyID = nextID($conn, 'assetstatushistory', 'historyID', 'ASH');
                    $historyReason = 'Availability updated during asset review.';
                    $stmt = $conn->prepare("
                        INSERT INTO assetstatushistory
                        (historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason)
                        VALUES (?,?,?,?,?,?)
                    ");
                    $stmt->bind_param('ssssss', $historyID, $assetID, $managerID, $oldAvailability, $availability, $historyReason);
                    $stmt->execute(); $stmt->close();
                }

                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');
                $logAction = $action === 'verify' ? 'Submit' : 'Update';
                $logText = $action === 'return'
                    ? "Returned $assetID for correction. Reason: $reason"
                    : ($action === 'verify'
                        ? "Sent asset listing for verification: $name"
                        : "Updated asset submission during review: $name");

                $stmt = $conn->prepare("
                    INSERT INTO systemlog(logID,userID,actionType,refType,refID,description)
                    VALUES (?,?,?,'Asset',?,?)
                ");
                $stmt->bind_param('sssss', $logID, $managerID, $logAction, $assetID, $logText);
                $stmt->execute(); $stmt->close();

                if ($action !== 'save') {
                    $notificationID = nextID($conn, 'notification', 'notificationID', 'NOT');
                    $title = $action === 'return' ? 'Asset Listing Returned' : 'Asset Sent for Verification';
                    $content = $action === 'return'
                        ? "Asset $assetID was returned for correction. Reason: $reason"
                        : "Asset $assetID has been sent for Sustainability Auditor verification.";

                    $stmt = $conn->prepare("
                        INSERT INTO notification(notificationID,userID,title,content,status)
                        VALUES (?,?,?,?,'Unread')
                    ");
                    $stmt->bind_param('ssss', $notificationID, $asset['ownerID'], $title, $content);
                    $stmt->execute(); $stmt->close();
                }

                $conn->commit();
                $url = $action === 'save'
                    ? "review_asset_submission.php?assetID=$assetID&saved=1"
                    : 'manager_assets.php?reviewed=1';
                header("Location: $url"); exit();

            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }

        $asset['assetName'] = $name;
        $asset['categoryID'] = $categoryID;
        $asset['assetCondition'] = $condition;
        $asset['description'] = $description;
        $asset['availabilityStatus'] = $availability;
    }

    $conn->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to process the asset review. Please try again.';
}

$image = imagePath($asset['imageURL'] ?? '');
$showCorrection = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['reviewAction'] ?? '') === 'return';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Asset Submission - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb">
        <a href="manager_assets.php">Asset Management</a><span>›</span>
        <span class="breadcrumb-current">Review Asset Submission</span>
    </div>
    <h1>Review Asset Submission</h1>
    <p>Review the Community Member's listing before sending it for Sustainability Auditor verification.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['saved'] ?? '') === '1'): ?><div class="alert alert-success">Asset listing changes saved successfully.</div><?php endif; ?>

<?php if ($asset): ?>
<section class="content-card asset-form-card">
<form method="POST">
<div class="asset-form-layout">

<div class="asset-form-fields">
    <div class="asset-form-row"><label>Asset ID</label><input value="<?= e($assetID) ?>" readonly></div>

    <div class="asset-form-row">
        <label for="assetName">Asset Name *</label>
        <input id="assetName" name="assetName" maxlength="150" value="<?= e($asset['assetName']) ?>" required>
    </div>

    <div class="asset-form-row">
        <label for="categoryID">Category *</label>
        <select id="categoryID" name="categoryID" required>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['categoryID']) ?>" <?= $asset['categoryID'] === $c['categoryID'] ? 'selected' : '' ?>>
                <?= e($c['categoryName']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="asset-form-row">
        <label for="assetCondition">Condition *</label>
        <select id="assetCondition" name="assetCondition" required>
            <?php foreach ($conditions as $v): ?>
            <option value="<?= e($v) ?>" <?= $asset['assetCondition'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="asset-form-row asset-form-textarea">
        <label for="description">Description</label>
        <textarea id="description" name="description" maxlength="255" rows="4"><?= e($asset['description'] ?? '') ?></textarea>
    </div>

    <div class="asset-form-row"><label>Availability</label><input value="Unavailable" readonly></div>

    <?php
    $readonly = [
        'Submitted By' => $asset['ownerName'] . ' (' . $asset['ownerID'] . ')',
        'Member Email' => $asset['ownerEmail'],
        'Date Submitted' => date('d M Y, h:i A', strtotime($asset['createdDate'])),
        'Listing Status' => $asset['listingStatus'],
        'Verification Status' => $asset['verificationStatus']
    ];
    foreach ($readonly as $label => $value):
    ?>
    <div class="asset-form-row"><label><?= e($label) ?></label><input value="<?= e($value) ?>" readonly></div>
    <?php endforeach; ?>

    <div class="asset-form-row asset-form-textarea" id="correctionBox" style="<?= $showCorrection ? '' : 'display:none;' ?>">
        <label for="correctionReason">Correction Reason *</label>
        <textarea id="correctionReason" name="correctionReason" maxlength="150" rows="3"
                  placeholder="Explain clearly what the Community Member needs to correct"><?= e($_POST['correctionReason'] ?? '') ?></textarea>
    </div>
</div>

<aside class="asset-form-side">
    <div class="asset-form-image-section">
        <h2>Submitted Image</h2>
        <?php if ($image): ?>
            <div class="asset-form-image-preview"><img src="<?= e($image) ?>" alt="<?= e($asset['assetName']) ?>"></div>
            <p><?= e(basename($asset['imageURL'])) ?></p>
        <?php else: ?>
            <div class="asset-image-empty">No image submitted</div>
        <?php endif; ?>
    </div>

    <div class="asset-form-note">
        <strong>Review Checklist</strong>
        <p>Check completeness, category, duplicate information and appropriate listing details.</p>
    </div>

    <div class="asset-form-actions">
        <button type="submit" name="reviewAction" value="save" class="asset-form-submit">
            <img src="../assets/icons/edit.png" alt=""> Save Changes
        </button>

        <button type="submit" name="reviewAction" value="return" class="asset-form-reset" onclick="return prepareReturn(event)">
            <img src="../assets/icons/reset.png" alt="">
            <span id="returnButtonText"><?= $showCorrection ? 'Confirm Return' : 'Return for Correction' ?></span>
        </button>

        <button type="submit" name="reviewAction" value="verify" class="asset-form-submit"
                onclick="return confirm('Send this listing for Sustainability Auditor verification?')">
            <img src="../assets/icons/pendingVerification.png" alt=""> Send for Verification
        </button>

        <a href="manager_assets.php" class="asset-form-back">Back</a>
    </div>
</aside>

</div>
</form>
</section>
<?php endif; ?>

</main>
</div>
</div>

<script>
function prepareReturn(e){
    const box=document.getElementById('correctionBox'),
          reason=document.getElementById('correctionReason'),
          text=document.getElementById('returnButtonText');

    if(box.style.display==='none'){
        e.preventDefault(); box.style.display='grid'; text.textContent='Confirm Return'; reason.focus(); return false;
    }
    if(!reason.value.trim()){
        e.preventDefault(); alert('Please enter a correction reason.'); reason.focus(); return false;
    }
    return confirm('Return this listing to the Community Member for correction?');
}
</script>

</body>
</html>
