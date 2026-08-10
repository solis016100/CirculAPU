<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$managerID = $_SESSION['user_id'];

$asset = null;
$databaseError = '';
$removeError = '';

$assetID = trim($_GET['assetID'] ?? $_POST['assetID'] ?? '');

// Asset ID Required
if ($assetID === '') {
    header("Location: manager_assets.php");
    exit();
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

    // Retrieve Selected Asset
    $statement = $conn->prepare("
        SELECT
            a.assetID,
            a.assetName,
            a.listingStatus,
            a.availabilityStatus,
            c.categoryName
        FROM assets a
        INNER JOIN categories c
            ON a.categoryID = c.categoryID
        WHERE a.assetID = ?
        LIMIT 1
    ");

    $statement->bind_param("s", $assetID);
    $statement->execute();

    $asset = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$asset) {
        $databaseError =
            "The selected asset record could not be found.";
    }

    // Remove Asset
    if (
        $asset &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        if ($asset['listingStatus'] === 'Removed') {
            $removeError =
                "This asset listing has already been removed.";
        }

        if ($removeError === '') {
            try {
                $conn->begin_transaction();

                // Soft Remove Asset
                $removeStatement = $conn->prepare("
                    UPDATE assets
                    SET
                        listingStatus = 'Removed',
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");

                $removeStatement->bind_param(
                    "s",
                    $assetID
                );

                $removeStatement->execute();
                $removeStatement->close();

                // Generate System Log ID
                $logResult = $conn->query("
                    SELECT MAX(
                        CAST(SUBSTRING(logID, 4) AS UNSIGNED)
                    ) AS maxID
                    FROM systemlog
                ");

                $logRow = $logResult->fetch_assoc();

                $nextLogNumber =
                    ((int) ($logRow['maxID'] ?? 0)) + 1;

                $logID = sprintf(
                    "LOG%03d",
                    $nextLogNumber
                );

                $logDescription =
                    "Removed asset listing: "
                    . $asset['assetName'];

                // Record Removal
                $logStatement = $conn->prepare("
                    INSERT INTO systemlog
                    (
                        logID,
                        userID,
                        actionType,
                        refType,
                        refID,
                        description
                    )
                    VALUES (
                        ?,
                        ?,
                        'Delete',
                        'Asset',
                        ?,
                        ?
                    )
                ");

                $logStatement->bind_param(
                    "ssss",
                    $logID,
                    $managerID,
                    $assetID,
                    $logDescription
                );

                $logStatement->execute();
                $logStatement->close();

                $conn->commit();

                header(
                    "Location: manager_assets.php?removed=1"
                );
                exit();

            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to remove the asset. Please try again.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Remove Asset - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager.css">
    <link rel="stylesheet" href="../darktheme.css">
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

                    <?php if ($asset): ?>
                        <a href="manager_asset_view.php?assetID=<?php echo urlencode($assetID); ?>">
                            Asset Details
                        </a>
                        <span>›</span>
                    <?php endif; ?>

                    <span class="breadcrumb-current">
                        Remove Asset
                    </span>
                </div>

                <h1>Remove Asset</h1>

                <p>
                    Confirm the removal of this asset listing.
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

                <!-- 3. Remove Error -->
                <?php if (!empty($removeError)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($removeError); ?>
                    </div>
                <?php endif; ?>

                <!-- 4. Confirmation -->
                <section class="content-card asset-detail-card">

                    <div class="asset-detail-information">

                        <div class="asset-detail-row">
                            <span class="asset-detail-label">
                                Asset ID
                            </span>

                            <div class="asset-detail-value">
                                <?php echo htmlspecialchars($asset['assetID']); ?>
                            </div>
                        </div>

                        <div class="asset-detail-row">
                            <span class="asset-detail-label">
                                Asset Name
                            </span>

                            <div class="asset-detail-value">
                                <?php echo htmlspecialchars($asset['assetName']); ?>
                            </div>
                        </div>

                        <div class="asset-detail-row">
                            <span class="asset-detail-label">
                                Category
                            </span>

                            <div class="asset-detail-value">
                                <?php echo htmlspecialchars($asset['categoryName']); ?>
                            </div>
                        </div>

                        <div class="asset-detail-row">
                            <span class="asset-detail-label">
                                Availability
                            </span>

                            <div class="asset-detail-value">
                                <?php echo htmlspecialchars($asset['availabilityStatus']); ?>
                            </div>
                        </div>

                        <div class="asset-detail-row">
                            <span class="asset-detail-label">
                                Listing Status
                            </span>

                            <div class="asset-detail-value">
                                <?php echo htmlspecialchars($asset['listingStatus']); ?>
                            </div>
                        </div>

                    </div>

                    <div class="asset-form-note" style="margin-top: 24px;">
                        <strong>Important:</strong>

                        <p>
                            This action will remove the asset from the normal
                            Asset Management listing. The database record will
                            be retained for transaction history and auditing.
                        </p>
                    </div>

                    <form method="POST" action="manager_asset_delete.php">
                        <input
                            type="hidden"
                            name="assetID"
                            value="<?php echo htmlspecialchars($asset['assetID']); ?>"
                        >

                        <div
                            class="asset-detail-actions"
                            style="margin-top: 24px;"
                        >

                        <button
                            type="submit"
                            class="asset-detail-action-btn delete"
                            onclick="return confirm('Are you sure you want to remove this asset listing? This action will hide the asset from the normal listing but keep its record for auditing.');"
                        >
                            <img src="../assets/icons/delete.png" alt="">
                            <span>Confirm Remove</span>
                        </button>

                            <a
                                href="manager_asset_view.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                class="asset-detail-back-btn"
                            >
                                Cancel
                            </a>

                        </div>
                    </form>

                </section>

            <?php endif; ?>

        </main>

    </div>

</div>

</body>
</html>