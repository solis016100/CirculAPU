<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

require_once '../print.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function assetDate(?string $date): string {
    if (!$date) return 'Not updated yet';
    $time = strtotime($date);
    return $time ? date('d M Y, h:i A', $time) : '-';
}

$assetID = trim($_GET['assetID'] ?? '');
$asset = null;
$error = '';

if ($assetID === '') {
    header("Location: manager_assets.php");
    exit();
}

$conn = null;

try {
    $conn = new mysqli("127.0.0.1", "root", "", "circulapu_db", 3306);
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("
        SELECT a.*, c.categoryName,
               u.userID AS ownerID, u.name AS ownerName
        FROM assets a
        JOIN categories c ON a.categoryID = c.categoryID
        JOIN user u ON a.ownerID = u.userID
        WHERE a.assetID = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $assetID);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$asset) $error = "The selected asset record could not be found.";

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = "Unable to load the asset details. Please try again.";
}

if ($conn instanceof mysqli) $conn->close();

/* Asset Image */
$imagePath = $imageName = '';

if ($asset && !empty($asset['imageURL'])) {
    $url = str_replace('\\', '/', trim($asset['imageURL']));
    $imageName = basename($url);
    $imagePath = preg_match('/^https?:\/\//i', $url)
        ? $url
        : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $url), '/');
}

/* Detail Rows */
$details = $asset ? [
    'Asset ID' => $asset['assetID'],
    'Asset Name' => $asset['assetName'],
    'Category' => $asset['categoryName'],
    'Condition' => $asset['assetCondition'],
    'Description' => $asset['description'] ?: 'No description provided.',
    'Uploaded By' => $asset['ownerName'] . ' (' . $asset['ownerID'] . ')',
    'Availability Status' => $asset['availabilityStatus'],
    'Listing Status' => $asset['listingStatus'],
    'Verification Status' => $asset['verificationStatus'],
    'Date Listed' => assetDate($asset['createdDate']),
    'Last Updated' => assetDate($asset['updatedDate'])
] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Details - CirculAPU</title>
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
            <span class="breadcrumb-current">Asset Details</span>
        </div>

        <h1>Asset Details</h1>
        <p>View complete information about the selected asset listing.</p>
    </section>

    <!-- 2. Messages -->
    <?php if ($error): ?>

        <div class="dashboard-error-message"><?= e($error) ?></div>

        <a href="manager_assets.php" class="management-create-btn">
            Back to Asset Management
        </a>

    <?php elseif ($asset): ?>

        <?php if (($_GET['updated'] ?? '') === '1'): ?>
            <div class="alert alert-success">
                Asset information updated successfully.
            </div>
        <?php endif; ?>

        <!-- 3. Asset Details -->
        <section class="content-card asset-detail-card">

            <div class="asset-detail-layout">

                <div class="asset-detail-information">

                    <?php foreach ($details as $label => $value): ?>

                        <div class="asset-detail-row <?= $label === 'Description' ? 'description-row' : '' ?>">
                            <span class="asset-detail-label"><?= e($label) ?></span>
                            <div class="asset-detail-value"><?= e($value) ?></div>
                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- 4. Image and Actions -->
                <aside class="asset-detail-side">

                    <div class="asset-image-section">
                        <h2>Uploaded Image</h2>

                        <?php if ($imagePath): ?>

                            <div class="asset-image-preview">
                                <img src="<?= e($imagePath) ?>" alt="<?= e($asset['assetName']) ?>">
                            </div>

                            <p class="asset-image-name"><?= e($imageName) ?></p>

                        <?php else: ?>

                            <div class="asset-image-empty">No image uploaded</div>

                        <?php endif; ?>
                    </div>

                    <div class="asset-detail-actions">

                        <?php printButton('Print', '../assets/icons/print.png', 'asset-detail-action-btn'); ?>

                        <!-- Pending Review follows review workflow -->
                        <?php if ($asset['listingStatus'] === 'Pending Review'): ?>

                            <a
                                href="review_asset_submission.php?assetID=<?= e($asset['assetID']) ?>"
                                class="asset-detail-action-btn"
                            >
                                <img src="../assets/icons/edit.png" alt="">
                                <span>Review Submission</span>
                            </a>

                        <?php elseif ($asset['listingStatus'] !== 'Removed'): ?>

                            <a
                                href="manager_asset_edit.php?assetID=<?= e($asset['assetID']) ?>"
                                class="asset-detail-action-btn"
                            >
                                <img src="../assets/icons/edit.png" alt="">
                                <span>Edit Asset</span>
                            </a>

                        <?php endif; ?>

                        <!-- Soft Remove -->
                        <?php if (
                            $asset['listingStatus'] !== 'Removed' &&
                            !in_array($asset['availabilityStatus'], ['Reserved', 'Borrowed'], true)
                        ): ?>

                            <form
                                method="POST"
                                action="manager_assets.php"
                                onsubmit="return confirm('Remove this asset listing? The record will be retained for auditing.');"
                            >
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="assetID" value="<?= e($asset['assetID']) ?>">

                                <button
                                    type="submit"
                                    class="asset-detail-action-btn delete"
                                >
                                    <img src="../assets/icons/delete.png" alt="">
                                    <span>Remove Asset</span>
                                </button>
                            </form>

                        <?php endif; ?>

                        <a href="manager_assets.php" class="asset-detail-back-btn">
                            Back
                        </a>

                    </div>

                </aside>

            </div>

        </section>

    <?php endif; ?>

</main>
</div>
</div>

</body>
</html>