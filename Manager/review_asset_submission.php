<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("
        SELECT MAX(CAST(SUBSTRING($column, 4) AS UNSIGNED)) AS maxID
        FROM $table
    ")->fetch_assoc();

    return sprintf($prefix . "%03d", ((int)($row['maxID'] ?? 0)) + 1);
}

$managerID = $_SESSION['user_id'];
$assetID = trim($_GET['assetID'] ?? '');
$asset = null;
$categories = [];
$databaseError = $formError = '';

$conditions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];
$availabilityOptions = ['Available', 'Reserved', 'Borrowed', 'Unavailable'];

if (!$assetID) {
    header("Location: manager_assets.php");
    exit();
}

$conn = null;

try {
    $conn = new mysqli("127.0.0.1", "root", "", "circulapu_db", 3306);
    $conn->set_charset("utf8mb4");

    // Categories
    $result = $conn->query("
        SELECT categoryID, categoryName
        FROM categories
        WHERE status='Active'
        ORDER BY categoryName
    ");
    while ($row = $result->fetch_assoc()) $categories[] = $row;

    // Asset
    $stmt = $conn->prepare("
        SELECT a.*, u.name AS ownerName, u.email AS ownerEmail
        FROM assets a
        JOIN user u ON a.ownerID=u.userID
        WHERE a.assetID=?
        LIMIT 1
    ");
    $stmt->bind_param("s", $assetID);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) {
        $databaseError = "The selected asset submission could not be found.";
    }

    // Review Action
    if ($asset && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $assetName = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $condition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $availability = trim($_POST['availabilityStatus'] ?? '');
        $reason = trim($_POST['correctionReason'] ?? '');
        $action = trim($_POST['reviewAction'] ?? '');

        // Validation
        if ($asset['listingStatus'] !== 'Pending Review')
            $formError = "Only Pending Review listings can be reviewed.";
        elseif (!$assetName)
            $formError = "Asset Name is required.";
        elseif (strlen($assetName) > 150)
            $formError = "Asset Name must not exceed 150 characters.";
        elseif (strlen($description) > 255)
            $formError = "Description must not exceed 255 characters.";
        elseif (!in_array($condition, $conditions, true))
            $formError = "Please select a valid condition.";
        elseif (!in_array($availability, $availabilityOptions, true))
            $formError = "Please select a valid availability status.";
        elseif (!in_array($action, ['save', 'return', 'verify'], true))
            $formError = "Invalid review action.";
        elseif ($action === 'return' && !$reason)
            $formError = "Please enter a correction reason.";
        elseif ($action === 'return' && strlen($reason) > 150)
            $formError = "Correction Reason must not exceed 150 characters.";

        // Category Validation
        if (!$formError) {
            $stmt = $conn->prepare("
                SELECT categoryID
                FROM categories
                WHERE categoryID=? AND status='Active'
                LIMIT 1
            ");
            $stmt->bind_param("s", $categoryID);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc())
                $formError = "Please select a valid active category.";

            $stmt->close();
        }

        if (!$formError) {
            try {
                $conn->begin_transaction();

                $newStatus = $action === 'return'
                    ? 'Draft'
                    : ($action === 'verify' ? 'Ready for Verification' : 'Pending Review');

                // Update Asset
                $stmt = $conn->prepare("
                    UPDATE assets SET
                        categoryID=?, assetName=?, description=?, assetCondition=?,
                        availabilityStatus=?, listingStatus=?, updatedDate=CURRENT_TIMESTAMP
                    WHERE assetID=?
                ");
                $stmt->bind_param(
                    "sssssss",
                    $categoryID, $assetName, $description, $condition,
                    $availability, $newStatus, $assetID
                );
                $stmt->execute();
                $stmt->close();

                // System Log
                if ($action === 'return')
                    $logText = "Returned $assetID for correction. Reason: $reason";
                elseif ($action === 'verify')
                    $logText = "Sent asset listing for verification: $assetName";
                else
                    $logText = "Updated asset submission during review: $assetName";

                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');

                $stmt = $conn->prepare("
                    INSERT INTO systemlog
                        (logID, userID, actionType, refType, refID, description)
                    VALUES (?, ?, 'Update', 'Asset', ?, ?)
                ");
                $stmt->bind_param("ssss", $logID, $managerID, $assetID, $logText);
                $stmt->execute();
                $stmt->close();

                // Member Notification
                if ($action !== 'save') {
                    $notificationID = nextID(
                        $conn,
                        'notification',
                        'notificationID',
                        'NOT'
                    );

                    if ($action === 'return') {
                        $title = "Asset Listing Returned";
                        $content = "Your asset $assetID ($assetName) was returned for correction. Reason: $reason";
                    } else {
                        $title = "Asset Sent for Verification";
                        $content = "Your asset $assetID ($assetName) has been sent for Sustainability Auditor verification.";
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO notification
                            (notificationID, userID, title, content, status)
                        VALUES (?, ?, ?, ?, 'Unread')
                    ");
                    $stmt->bind_param(
                        "ssss",
                        $notificationID,
                        $asset['ownerID'],
                        $title,
                        $content
                    );
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();

                $url = $action === 'save'
                    ? "review_asset_submission.php?assetID=" . urlencode($assetID) . "&saved=1"
                    : "manager_assets.php?reviewed=1";

                header("Location: $url");
                exit();

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }

        // Preserve Form Values After Error
        if ($formError) {
            $asset['assetName'] = $assetName;
            $asset['categoryID'] = $categoryID;
            $asset['assetCondition'] = $condition;
            $asset['description'] = $description;
            $asset['availabilityStatus'] = $availability;
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $databaseError = "Unable to process the asset review. Please try again.";
}

if ($conn instanceof mysqli) $conn->close();

$showCorrection = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['reviewAction'] ?? '') === 'return';
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

    <!-- 1. Heading -->
    <section class="management-heading">
        <div class="breadcrumb">
            <a href="manager_assets.php">Asset Management</a>
            <span>›</span>
            <span class="breadcrumb-current">Review Asset Submission</span>
        </div>

        <h1>Review Asset Submission</h1>
        <p>Review the Community Member's listing before sending it for Sustainability Auditor verification.</p>
    </section>

    <?php if ($databaseError): ?>

        <div class="dashboard-error-message"><?php echo e($databaseError); ?></div>

    <?php elseif ($asset): ?>

        <?php if ($asset['listingStatus'] !== 'Pending Review'): ?>
            <div class="alert alert-error">
                This asset is not waiting for Resource Manager review.
                Current status: <?php echo e($asset['listingStatus']); ?>.
            </div>
        <?php endif; ?>

        <?php if ($formError): ?>
            <div class="alert alert-error"><?php echo e($formError); ?></div>
        <?php endif; ?>

        <?php if (($_GET['saved'] ?? '') === '1'): ?>
            <div class="alert alert-success">
                Asset listing changes saved successfully.
            </div>
        <?php endif; ?>

        <!-- 2. Review Form -->
        <section class="content-card asset-form-card">

            <form
                method="POST"
                action="review_asset_submission.php?assetID=<?php echo urlencode($assetID); ?>"
            >

                <div class="asset-form-layout">

                    <!-- Asset Information -->
                    <div class="asset-form-fields">

                        <div class="asset-form-row">
                            <label>Asset ID</label>
                            <input type="text" value="<?php echo e($assetID); ?>" readonly>
                        </div>

                        <div class="asset-form-row">
                            <label for="assetName">Asset Name *</label>
                            <input
                                type="text"
                                id="assetName"
                                name="assetName"
                                maxlength="150"
                                value="<?php echo e($asset['assetName']); ?>"
                                required
                            >
                        </div>

                        <div class="asset-form-row">
                            <label for="categoryID">Category *</label>

                            <select id="categoryID" name="categoryID" required>
                                <?php foreach ($categories as $category): ?>
                                    <option
                                        value="<?php echo e($category['categoryID']); ?>"
                                        <?php echo $asset['categoryID'] === $category['categoryID'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($category['categoryName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="asset-form-row">
                            <label for="assetCondition">Condition *</label>

                            <select id="assetCondition" name="assetCondition" required>
                                <?php foreach ($conditions as $condition): ?>
                                    <option
                                        value="<?php echo e($condition); ?>"
                                        <?php echo $asset['assetCondition'] === $condition ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($condition); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="asset-form-row asset-form-textarea">
                            <label for="description">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                maxlength="255"
                                rows="4"
                            ><?php echo e($asset['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="asset-form-row">
                            <label for="availabilityStatus">Availability *</label>

                            <select id="availabilityStatus" name="availabilityStatus" required>
                                <?php foreach ($availabilityOptions as $availability): ?>
                                    <option
                                        value="<?php echo e($availability); ?>"
                                        <?php echo $asset['availabilityStatus'] === $availability ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($availability); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="asset-form-row">
                            <label>Submitted By</label>
                            <input
                                type="text"
                                value="<?php echo e($asset['ownerName'] . ' (' . $asset['ownerID'] . ')'); ?>"
                                readonly
                            >
                        </div>

                        <div class="asset-form-row">
                            <label>Member Email</label>
                            <input type="text" value="<?php echo e($asset['ownerEmail']); ?>" readonly>
                        </div>

                        <div class="asset-form-row">
                            <label>Date Submitted</label>
                            <input
                                type="text"
                                value="<?php echo e(date('d M Y, h:i A', strtotime($asset['createdDate']))); ?>"
                                readonly
                            >
                        </div>

                        <div class="asset-form-row">
                            <label>Listing Status</label>
                            <input type="text" value="<?php echo e($asset['listingStatus']); ?>" readonly>
                        </div>

                        <div class="asset-form-row">
                            <label>Verification Status</label>
                            <input type="text" value="<?php echo e($asset['verificationStatus']); ?>" readonly>
                        </div>

                        <!-- Hidden Until Return Is Chosen -->
                        <div
                            class="asset-form-row asset-form-textarea"
                            id="correctionBox"
                            style="<?php echo $showCorrection ? '' : 'display:none;'; ?>"
                        >
                            <label for="correctionReason">Correction Reason *</label>

                            <textarea
                                id="correctionReason"
                                name="correctionReason"
                                maxlength="150"
                                rows="3"
                                placeholder="Explain clearly what the Community Member needs to correct"
                            ><?php echo e($_POST['correctionReason'] ?? ''); ?></textarea>
                        </div>

                    </div>

                    <!-- Side Panel -->
                    <aside class="asset-form-side">

                        <div class="asset-form-image-section">
                            <h2>Submitted Image</h2>

                            <?php if (!empty($asset['imageURL'])): ?>

                                <div class="asset-form-image-preview">
                                    <img
                                        src="../assets/managerfile/<?php echo e(basename($asset['imageURL'])); ?>"
                                        alt="<?php echo e($asset['assetName']); ?>"
                                    >
                                </div>

                                <p><?php echo e(basename($asset['imageURL'])); ?></p>

                            <?php else: ?>

                                <div class="asset-image-empty">No image submitted</div>

                            <?php endif; ?>
                        </div>

                        <div class="asset-form-note">
                            <strong>Review Checklist:</strong>
                            <p>Check completeness, category, duplicate information and appropriate listing details.</p>
                        </div>

                        <div class="asset-form-actions">

                            <button
                                type="submit"
                                name="reviewAction"
                                value="save"
                                class="asset-form-submit"
                            >
                                <img src="../assets/icons/edit.png" alt="">
                                Save Changes
                            </button>

                            <button
                                type="submit"
                                name="reviewAction"
                                value="return"
                                class="asset-form-reset"
                                onclick="return prepareReturn(event)"
                            >
                                <img src="../assets/icons/reset.png" alt="">
                                <span id="returnButtonText">
                                    <?php echo $showCorrection ? 'Confirm Return' : 'Return for Correction'; ?>
                                </span>
                            </button>

                            <button
                                type="submit"
                                name="reviewAction"
                                value="verify"
                                class="asset-form-submit"
                                onclick="return confirm('Send this listing for Sustainability Auditor verification?');"
                            >
                                <img src="../assets/icons/pendingVerification.png" alt="">
                                Send for Verification
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
function prepareReturn(e) {
    const box = document.getElementById('correctionBox');
    const reason = document.getElementById('correctionReason');
    const text = document.getElementById('returnButtonText');

    if (box.style.display === 'none') {
        e.preventDefault();
        box.style.display = 'grid';
        text.textContent = 'Confirm Return';
        reason.focus();
        return false;
    }

    if (!reason.value.trim()) {
        e.preventDefault();
        alert('Please enter a correction reason.');
        reason.focus();
        return false;
    }

    return confirm('Return this listing to the Community Member for correction?');
}
</script>

</body>
</html>