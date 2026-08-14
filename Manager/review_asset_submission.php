<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $start = strlen($prefix) + 1;
    $like = $prefix . '%';
    $stmt = $conn->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($column,$start) AS UNSIGNED)),0)+1 nextID FROM $table WHERE $column LIKE ?");
    $stmt->bind_param('s', $like); $stmt->execute();
    $next = (int)$stmt->get_result()->fetch_assoc()['nextID']; $stmt->close();
    return sprintf($prefix . '%03d', $next);
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
                    $historyID = nextID($conn, 'assetstatushistory', 'historyID', 'H');
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
                    $notificationID = nextID($conn, 'notification', 'notificationID', 'N');
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
                $url=$action==='save'
                    ?"review_asset_submission.php?assetID=$assetID&saved=1"
                    :"manager_assets.php?reviewDone=$action";
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


} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to process the asset review. Please try again.';
}

$image = imagePath($asset['imageURL'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Asset Submission - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
    <link rel="stylesheet" href="manager_mobile.css">
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
<form method="POST" id="reviewForm">
<input type="hidden" name="reviewAction" id="reviewAction">
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

    <div class="asset-form-actions review-asset-actions">
        <button type="button" class="asset-form-submit" onclick="openReviewConfirm('save')">
            <img src="../assets/icons/edit.png" alt=""> Save Changes
        </button>

        <button type="button" class="asset-form-reset" onclick="openCorrectionDialog()">
            <img src="../assets/icons/reset.png" alt=""> Return for Correction
        </button>

        <button type="button" class="asset-form-submit" onclick="openReviewConfirm('verify')">
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

<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span></a>
<a href="manager_assets.php" class="active" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span></a>
<a href="manager_categories.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span></a>
<a href="manager_transactions.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span></a>
<a href="manager_reports.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span></a>
</nav>

</div>
</div>

<dialog id="reviewConfirm" class="flow-dialog">
<h2 id="confirmTitle"></h2>
<p id="confirmMessage"></p>

<div class="flow-dialog-summary">
    <div><strong>Asset ID</strong><span><?=e($assetID)?></span></div>
    <div><strong>Asset Name</strong><span id="confirmName"></span></div>
    <div><strong>Category</strong><span id="confirmCategory"></span></div>
    <div><strong>Condition</strong><span id="confirmCondition"></span></div>
    <div><strong>Availability</strong><span>Unavailable</span></div>
    <div><strong>Description</strong><span id="confirmDescription"></span></div>
</div>

<div class="flow-dialog-actions">
    <button type="button" class="category-form-back" onclick="reviewConfirm.close()">No</button>
    <button type="button" class="category-form-submit" id="confirmButton" onclick="submitReview()">Confirm</button>
</div>
</dialog>

<dialog id="correctionDialog" class="flow-dialog">
<h2>Return Listing for Correction</h2>
<p>Enter a clear reason explaining what the Community Member needs to correct.</p>

<div class="asset-form-row asset-form-textarea" style="grid-template-columns:1fr">
    <label for="correctionReason">Correction Reason *</label>
    <textarea id="correctionReason" name="correctionReason" form="reviewForm"
              maxlength="150" rows="4"
              placeholder="Example: Please provide a clearer description or correct the selected category."></textarea>
</div>

<div class="flow-dialog-actions">
    <button type="button" class="category-form-back" onclick="correctionDialog.close()">Cancel</button>
    <button type="button" class="category-form-submit" onclick="submitCorrection()">Yes, Return</button>
</div>
</dialog>

<script>
const form=document.getElementById('reviewForm'),
      actionInput=document.getElementById('reviewAction'),
      correctionReason=document.getElementById('correctionReason');

function fillSummary(){
    confirmName.textContent=form.assetName.value.trim();
    confirmCategory.textContent=form.categoryID.options[form.categoryID.selectedIndex]?.text||'-';
    confirmCondition.textContent=form.assetCondition.value;
    confirmDescription.textContent=form.description.value.trim()||'-';
}

function openReviewConfirm(action){
    if(!form.checkValidity()){form.reportValidity();return}

    actionInput.value=action;
    fillSummary();

    const saving=action==='save';
    confirmTitle.textContent=saving?'Confirm Save Changes':'Confirm Verification Submission';
    confirmMessage.textContent=saving
        ?'Save these changes to the asset listing?'
        :'Send this reviewed listing to the Sustainability Auditor?';
    confirmButton.textContent=saving?'Yes, Save Changes':'Yes, Send';
    reviewConfirm.showModal();
}

function openCorrectionDialog(){
    if(!form.checkValidity()){form.reportValidity();return}
    correctionDialog.showModal();
    correctionReason.focus();
}

function submitCorrection(){
    if(!correctionReason.value.trim()){
        correctionReason.focus();
        return;
    }
    actionInput.value='return';
    form.submit();
}

function submitReview(){form.submit()}
</script>

</body>
</html>
