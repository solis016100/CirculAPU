<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING($column,4) AS UNSIGNED)),0)+1 nextID
        FROM $table
    ")->fetch_assoc();

    return sprintf($prefix . '%03d', (int)$row['nextID']);
}

function assetImagePath(?string $url): string {
    if (!$url) return '';

    $url = str_replace('\\', '/', trim($url));

    return preg_match('/^https?:\/\//i', $url)
        ? $url
        : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $url), '/');
}

function uploadAssetImage(array $file, string $assetID, string $currentURL): array {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($err === UPLOAD_ERR_NO_FILE) return [$currentURL, '', ''];
    if ($err !== UPLOAD_ERR_OK) return [$currentURL, '', 'Unable to upload the selected image.'];
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return [$currentURL, '', 'Image size must not exceed 5MB.'];

    $info = getimagesize($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    if (!$info || !isset($types[$info['mime']])) {
        return [$currentURL, '', 'Only valid JPG and PNG images are allowed.'];
    }

    $dir = dirname(__DIR__) . '/assets/managerfile/';

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return [$currentURL, '', 'Unable to create the image upload folder.'];
    }

    $name = strtolower($assetID) . '_' . time() . '.' . $types[$info['mime']];
    $path = $dir . $name;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return [$currentURL, '', 'Unable to save the uploaded image.'];
    }

    return ['assets/managerfile/' . $name, $path, ''];
}

$managerID = $_SESSION['user_id'];
$assetID = trim($_GET['assetID'] ?? '');
$asset = null;
$categories = [];
$error = $newFile = '';

$conditions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];
$availabilities = ['Available', 'Reserved', 'Borrowed', 'Unavailable'];

if ($assetID === '') {
    header('Location: manager_assets.php'); exit();
}

$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    // 1. Load Asset
    $stmt = $conn->prepare("
        SELECT a.*, u.name ownerName
        FROM assets a
        JOIN user u ON a.ownerID = u.userID
        WHERE a.assetID = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $assetID);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) {
        $error = 'The selected asset record could not be found.';
    } elseif ($asset['listingStatus'] === 'Pending Review') {
        header("Location: review_asset_submission.php?assetID=$assetID"); exit();
    } elseif ($asset['listingStatus'] === 'Removed') {
        $error = 'Removed asset listings cannot be edited.';
    }

    // 2. Categories
    if ($asset && !$error) {
        $stmt = $conn->prepare("
            SELECT categoryID, categoryName
            FROM categories
            WHERE status = 'Active' OR categoryID = ?
            ORDER BY categoryName
        ");
        $stmt->bind_param('s', $asset['categoryID']);
        $stmt->execute();

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $categories[] = $row;

        $stmt->close();
    }

    // 3. Update Asset
    if ($asset && !$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $condition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $availability = trim($_POST['availabilityStatus'] ?? '');

        if ($name === '') $error = 'Asset Name is required.';
        elseif (strlen($name) > 150) $error = 'Asset Name must not exceed 150 characters.';
        elseif (strlen($description) > 255) $error = 'Description must not exceed 255 characters.';
        elseif (!in_array($condition, $conditions, true)) $error = 'Please select a valid asset condition.';
        elseif (!in_array($availability, $availabilities, true)) $error = 'Please select a valid availability status.';

        if (!$error) {
            $stmt = $conn->prepare("
                SELECT categoryID
                FROM categories
                WHERE categoryID = ?
                AND (status = 'Active' OR categoryID = ?)
                LIMIT 1
            ");
            $stmt->bind_param('ss', $categoryID, $asset['categoryID']);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                $error = 'Please select a valid category.';
            }

            $stmt->close();
        }

        $imageURL = $asset['imageURL'];

        if (!$error) {
            [$imageURL, $newFile, $uploadError] =
                uploadAssetImage($_FILES['assetImage'] ?? [], $assetID, $imageURL);

            if ($uploadError) $error = $uploadError;
        }

        if (!$error) {
            try {
                $conn->begin_transaction();
                $oldAvailability = $asset['availabilityStatus'];

                $stmt = $conn->prepare("
                    UPDATE assets SET
                        categoryID = ?, assetName = ?, description = ?,
                        assetCondition = ?, imageURL = ?, availabilityStatus = ?,
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");
                $stmt->bind_param(
                    'sssssss',
                    $categoryID, $name, $description,
                    $condition, $imageURL, $availability, $assetID
                );
                $stmt->execute();
                $stmt->close();

                // Availability History
                if ($oldAvailability !== $availability) {
                    $historyID = nextID($conn, 'assetstatushistory', 'historyID', 'ASH');
                    $reason = 'Availability updated by Resource Manager.';

                    $stmt = $conn->prepare("
                        INSERT INTO assetstatushistory
                            (historyID, assetID, updatedBy,
                             oldAvailabilityStatus, newAvailabilityStatus,
                             statusChangeReason)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        'ssssss',
                        $historyID, $assetID, $managerID,
                        $oldAvailability, $availability, $reason
                    );
                    $stmt->execute();
                    $stmt->close();
                }

                // System Log
                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');
                $logText = "Updated asset information: $name";

                $stmt = $conn->prepare("
                    INSERT INTO systemlog
                        (logID, userID, actionType, refType, refID, description)
                    VALUES (?, ?, 'Update', 'Asset', ?, ?)
                ");
                $stmt->bind_param('ssss', $logID, $managerID, $assetID, $logText);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                header("Location: manager_asset_view.php?assetID=$assetID&updated=1");
                exit();

            } catch (Throwable $e) {
                $conn->rollback();

                if ($newFile && file_exists($newFile)) unlink($newFile);

                throw $e;
            }
        }

        if ($error) {
            $asset['assetName'] = $name;
            $asset['categoryID'] = $categoryID;
            $asset['assetCondition'] = $condition;
            $asset['description'] = $description;
            $asset['availabilityStatus'] = $availability;
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to process the asset information. Please try again.';
}

if ($conn instanceof mysqli) $conn->close();

$imagePath = assetImagePath($asset['imageURL'] ?? null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - CirculAPU</title>
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
        <a href="manager_asset_view.php?assetID=<?= e($assetID) ?>">Asset Details</a><span>›</span>
        <span class="breadcrumb-current">Edit Asset</span>
    </div>

    <h1>Edit Asset Information</h1>
    <p>Update asset information while preserving its review and verification workflow.</p>
</section>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($asset && $asset['listingStatus'] !== 'Removed'): ?>

<section class="content-card asset-form-card">

<form method="POST" enctype="multipart/form-data">

<div class="asset-form-layout">

<div class="asset-form-fields">

    <div class="asset-form-row">
        <label>Asset ID</label>
        <input value="<?= e($asset['assetID']) ?>" readonly>
    </div>

    <div class="asset-form-row">
        <label for="assetName">Asset Name *</label>
        <input id="assetName" name="assetName" maxlength="150"
               value="<?= e($asset['assetName']) ?>" required>
    </div>

    <div class="asset-form-row">
        <label for="categoryID">Category *</label>

        <select id="categoryID" name="categoryID" required>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c['categoryID']) ?>"
                    <?= $asset['categoryID'] === $c['categoryID'] ? 'selected' : '' ?>>
                    <?= e($c['categoryName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="asset-form-row">
        <label for="assetCondition">Condition *</label>

        <select id="assetCondition" name="assetCondition" required>
            <?php foreach ($conditions as $v): ?>
                <option value="<?= e($v) ?>"
                    <?= $asset['assetCondition'] === $v ? 'selected' : '' ?>>
                    <?= e($v) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="asset-form-row asset-form-textarea">
        <label for="description">Description</label>
        <textarea id="description" name="description"
                  maxlength="255" rows="4"><?= e($asset['description'] ?? '') ?></textarea>
    </div>

    <div class="asset-form-row">
        <label for="availabilityStatus">Availability *</label>

        <select id="availabilityStatus" name="availabilityStatus" required>
            <?php foreach ($availabilities as $v): ?>
                <option value="<?= e($v) ?>"
                    <?= $asset['availabilityStatus'] === $v ? 'selected' : '' ?>>
                    <?= e($v) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="asset-form-row">
        <label>Submitted By</label>
        <input value="<?= e($asset['ownerName'] . ' (' . $asset['ownerID'] . ')') ?>" readonly>
    </div>

    <div class="asset-form-row">
        <label>Listing Status</label>
        <input value="<?= e($asset['listingStatus']) ?>" readonly>
    </div>

    <div class="asset-form-row">
        <label>Verification Status</label>
        <input value="<?= e($asset['verificationStatus']) ?>" readonly>
    </div>

    <div class="asset-form-row">
        <label>Date Listed</label>
        <input value="<?= e(date('d M Y, h:i A', strtotime($asset['createdDate']))) ?>" readonly>
    </div>

</div>

<aside class="asset-form-side">

    <div class="asset-form-image-section">
        <h2>Current Image</h2>

        <?php if ($imagePath): ?>
            <div class="asset-form-image-preview">
                <img src="<?= e($imagePath) ?>" alt="<?= e($asset['assetName']) ?>">
            </div>
            <p><?= e(basename($asset['imageURL'])) ?></p>
        <?php else: ?>
            <div class="asset-image-empty">No image uploaded</div>
        <?php endif; ?>
    </div>

    <div class="asset-form-upload">
        <label for="assetImage">Replace Image</label>
        <input type="file" id="assetImage" name="assetImage"
               accept=".jpg,.jpeg,.png,image/jpeg,image/png">
        <small>Optional. JPG or PNG only, maximum 5MB.</small>
    </div>

    <div class="asset-form-note">
        <strong>Important</strong>
        <p>Listing Status and Verification Status cannot be changed here. Use the correct workflow instead.</p>
    </div>

    <div class="asset-form-actions">
        <button type="submit" class="asset-form-submit">
            <img src="../assets/icons/edit.png" alt="">Save Changes
        </button>

        <button type="reset" class="asset-form-reset">
            <img src="../assets/icons/reset.png" alt="">Reset
        </button>

        <a href="manager_asset_view.php?assetID=<?= e($asset['assetID']) ?>"
           class="asset-form-back">Back</a>
    </div>

</aside>

</div>

</form>

</section>

<?php endif; ?>

</main>
</div>
</div>
</body>
</html>