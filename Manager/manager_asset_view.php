<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$asset = null;
$databaseError = '';
$assetID = trim($_GET['assetID'] ?? '');

// Asset ID Required
if ($assetID === '') {
    header("Location: manager_assets.php");
    exit();
}

// Format Date
function formatAssetDetailDate(?string $date): string
{
    if (empty($date)) {
        return 'Not updated yet';
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date('d M Y, h:i A', $timestamp)
        : '-';
}

$conn = null;

try {
    $conn = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "circulapu_db",
        3306
    );

    $conn->set_charset("utf8mb4");

    // Retrieve Asset Details
    $statement = $conn->prepare("
        SELECT
            a.assetID,
            a.assetName,
            a.description,
            a.assetCondition,
            a.imageURL,
            a.availabilityStatus,
            a.listingStatus,
            a.verificationStatus,
            a.createdDate,
            a.updatedDate,
            c.categoryName,
            u.userID AS ownerID,
            u.name AS ownerName
        FROM assets a
        INNER JOIN categories c
            ON a.categoryID = c.categoryID
        INNER JOIN user u
            ON a.ownerID = u.userID
        WHERE a.assetID = ?
        LIMIT 1
    ");

    $statement->bind_param("s", $assetID);
    $statement->execute();

    $asset = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$asset) {
        $databaseError = "The selected asset record could not be found.";
    }

} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to load the asset details. Please make sure MySQL is running and the database is available.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}

// Asset Image
$imagePath = '';
$imageName = '';

if ($asset && !empty($asset['imageURL'])) {
    $imageURL = str_replace('\\', '/', trim($asset['imageURL']));
    $imageName = basename($imageURL);

    $imagePath = preg_match('/^https?:\/\//i', $imageURL)
        ? $imageURL
        : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $imageURL), '/');
}
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

            <!-- 1. Page Heading -->
            <section class="management-heading">

                <div class="breadcrumb">
                    <a href="manager_assets.php">Asset Management</a>
                    <span>›</span>
                    <span class="breadcrumb-current">Asset Details</span>
                </div>

                <h1>Asset Details</h1>

                <p>
                    View complete information about the selected asset listing.
                </p>

            </section>

            <!-- 2. Database Error -->
            <?php if (!empty($databaseError)): ?>

                <div class="dashboard-error-message">
                    <?php echo htmlspecialchars($databaseError); ?>
                </div>

                <a href="manager_assets.php" class="management-create-btn">
                    Back to Asset Management
                </a>

            <?php elseif ($asset): ?>

                <!-- 3. Asset Details -->
                <section class="content-card asset-detail-card">

                    <div class="asset-detail-layout">

                        <!-- Asset Information -->
                        <div class="asset-detail-information">

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">Asset ID</span>
                                <div class="asset-detail-value">
                                    <?php echo htmlspecialchars($asset['assetID']); ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">Asset Name</span>
                                <div class="asset-detail-value">
                                    <?php echo htmlspecialchars($asset['assetName']); ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">Category</span>
                                <div class="asset-detail-value">
                                    <?php echo htmlspecialchars($asset['categoryName']); ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">Condition</span>
                                <div class="asset-detail-value">
                                    <?php echo htmlspecialchars($asset['assetCondition']); ?>
                                </div>
                            </div>

                            <div class="asset-detail-row description-row">
                                <span class="asset-detail-label">Description</span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['description']
                                        ?: 'No description provided.'
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">Uploaded By</span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['ownerName']
                                        . ' ('
                                        . $asset['ownerID']
                                        . ')'
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">
                                    Availability Status
                                </span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['availabilityStatus']
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">
                                    Listing Status
                                </span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['listingStatus']
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">
                                    Verification Status
                                </span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['verificationStatus']
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">
                                    Date Listed
                                </span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        formatAssetDetailDate(
                                            $asset['createdDate']
                                        )
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="asset-detail-row">
                                <span class="asset-detail-label">
                                    Last Updated
                                </span>

                                <div class="asset-detail-value">
                                    <?php
                                    echo htmlspecialchars(
                                        formatAssetDetailDate(
                                            $asset['updatedDate']
                                        )
                                    );
                                    ?>
                                </div>
                            </div>

                        </div>

                        <!-- Asset Image and Actions -->
                        <aside class="asset-detail-side">

                            <!-- Uploaded Image -->
                            <div class="asset-image-section">

                                <h2>Uploaded Image</h2>

                                <?php if ($imagePath !== ''): ?>

                                    <div class="asset-image-preview">
                                        <img
                                            src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($asset['assetName']); ?>"
                                        >
                                    </div>

                                    <p class="asset-image-name">
                                        <?php echo htmlspecialchars($imageName); ?>
                                    </p>

                                <?php else: ?>

                                    <div class="asset-image-empty">
                                        No image uploaded
                                    </div>

                                <?php endif; ?>

                            </div>

                            <!-- Asset Actions -->
                            <div class="asset-detail-actions">

                                <button
                                    type="button"
                                    class="asset-detail-action-btn"
                                    onclick="window.print()"
                                >
                                    <img
                                        src="../assets/icons/print.png"
                                        alt=""
                                    >
                                    <span>Print</span>
                                </button>

                                <a
                                    href="manager_asset_edit.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                    class="asset-detail-action-btn"
                                >
                                    <img
                                        src="../assets/icons/edit.png"
                                        alt=""
                                    >
                                    <span>Edit Asset</span>
                                </a>

                                <a
                                    href="manager_asset_delete.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                    class="asset-detail-action-btn delete"
                                >
                                    <img
                                        src="../assets/icons/delete.png"
                                        alt=""
                                    >
                                    <span>Remove Asset</span>
                                </a>

                                <a
                                    href="manager_assets.php"
                                    class="asset-detail-back-btn"
                                >
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