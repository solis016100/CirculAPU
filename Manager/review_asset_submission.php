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
$categories = [];
$databaseError = '';
$formError = '';

$assetID = trim($_GET['assetID'] ?? '');

// Asset ID Required
if ($assetID === '') {
    header("Location: manager_assets.php");
    exit();
}

// Allowed Values
$conditionOptions = [
    'New',
    'Like New',
    'Good',
    'Fair',
    'Poor'
];

$availabilityOptions = [
    'Available',
    'Reserved',
    'Borrowed',
    'Unavailable'
];

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

    // Retrieve Active Categories
    $categoryResult = $conn->query("
        SELECT categoryID, categoryName
        FROM categories
        WHERE status = 'Active'
        ORDER BY categoryName ASC
    ");

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category;
    }

    // Retrieve Submitted Asset
    $statement = $conn->prepare("
        SELECT
            a.assetID,
            a.ownerID,
            a.categoryID,
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
            u.name AS ownerName,
            u.email AS ownerEmail
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
        $databaseError =
            "The selected asset submission could not be found.";
    }

    // Process Review Action
    if (
        $asset &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        if ($asset['listingStatus'] !== 'Pending Review') {
            $formError =
                "Only listings with Pending Review status can be reviewed.";
        }

        $assetName = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $assetCondition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $availabilityStatus = trim(
            $_POST['availabilityStatus'] ?? ''
        );

        $reviewAction = trim(
            $_POST['reviewAction'] ?? ''
        );

        // Validate Form
        if ($formError === '') {
            if ($assetName === '') {
                $formError = "Asset Name is required.";
            } elseif (strlen($assetName) > 150) {
                $formError =
                    "Asset Name must not exceed 150 characters.";
            } elseif (!in_array(
                $assetCondition,
                $conditionOptions,
                true
            )) {
                $formError =
                    "Please select a valid asset condition.";
            } elseif (strlen($description) > 255) {
                $formError =
                    "Description must not exceed 255 characters.";
            } elseif (!in_array(
                $availabilityStatus,
                $availabilityOptions,
                true
            )) {
                $formError =
                    "Please select a valid availability status.";
            } elseif (!in_array(
                $reviewAction,
                ['save', 'return', 'verify'],
                true
            )) {
                $formError =
                    "Invalid review action.";
            }
        }

        // Validate Category
        if ($formError === '') {
            $categoryCheck = $conn->prepare("
                SELECT categoryID
                FROM categories
                WHERE categoryID = ?
                AND status = 'Active'
                LIMIT 1
            ");

            $categoryCheck->bind_param(
                "s",
                $categoryID
            );

            $categoryCheck->execute();

            if (
                !$categoryCheck
                    ->get_result()
                    ->fetch_assoc()
            ) {
                $formError =
                    "Please select a valid active category.";
            }

            $categoryCheck->close();
        }

        // Save Review
        if ($formError === '') {
            try {
                $conn->begin_transaction();

                // Determine Listing Status
                $newListingStatus = 'Pending Review';

                if ($reviewAction === 'return') {
                    $newListingStatus = 'Draft';
                }

                if ($reviewAction === 'verify') {
                    $newListingStatus =
                        'Ready for Verification';
                }

                // Update Asset Listing
                $updateStatement = $conn->prepare("
                    UPDATE assets
                    SET
                        categoryID = ?,
                        assetName = ?,
                        description = ?,
                        assetCondition = ?,
                        availabilityStatus = ?,
                        listingStatus = ?,
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");

                $updateStatement->bind_param(
                    "sssssss",
                    $categoryID,
                    $assetName,
                    $description,
                    $assetCondition,
                    $availabilityStatus,
                    $newListingStatus,
                    $assetID
                );

                $updateStatement->execute();
                $updateStatement->close();

                // Create System Log ID
                $logResult = $conn->query("
                    SELECT MAX(
                        CAST(
                            SUBSTRING(logID, 4)
                            AS UNSIGNED
                        )
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

                // Log Description
                if ($reviewAction === 'save') {
                    $logDescription =
                        "Updated asset submission during review: "
                        . $assetName;
                } elseif ($reviewAction === 'return') {
                    $logDescription =
                        "Returned asset listing for correction: "
                        . $assetName;
                } else {
                    $logDescription =
                        "Sent asset listing for verification: "
                        . $assetName;
                }

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
                        'Update',
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

                // Notify Member When Workflow Status Changes
                if (
                    $reviewAction === 'return' ||
                    $reviewAction === 'verify'
                ) {
                    $notificationResult = $conn->query("
                        SELECT MAX(
                            CAST(
                                SUBSTRING(notificationID, 4)
                                AS UNSIGNED
                            )
                        ) AS maxID
                        FROM notification
                    ");

                    $notificationRow =
                        $notificationResult->fetch_assoc();

                    $nextNotificationNumber =
                        ((int) (
                            $notificationRow['maxID'] ?? 0
                        )) + 1;

                    $notificationID = sprintf(
                        "NOT%03d",
                        $nextNotificationNumber
                    );

                    if ($reviewAction === 'return') {
                        $notificationTitle =
                            "Asset Listing Returned";

                        $notificationContent =
                            "Your asset listing "
                            . $assetID
                            . " ("
                            . $assetName
                            . ") was returned for correction.";
                    } else {
                        $notificationTitle =
                            "Asset Sent for Verification";

                        $notificationContent =
                            "Your asset listing "
                            . $assetID
                            . " ("
                            . $assetName
                            . ") has been sent for Sustainability Auditor verification.";
                    }

                    $notificationStatement =
                        $conn->prepare("
                            INSERT INTO notification
                            (
                                notificationID,
                                userID,
                                title,
                                content,
                                status
                            )
                            VALUES (
                                ?,
                                ?,
                                ?,
                                ?,
                                'Unread'
                            )
                        ");

                    $notificationStatement->bind_param(
                        "ssss",
                        $notificationID,
                        $asset['ownerID'],
                        $notificationTitle,
                        $notificationContent
                    );

                    $notificationStatement->execute();
                    $notificationStatement->close();
                }

                $conn->commit();

                // Redirect According to Action
                if ($reviewAction === 'save') {
                    header(
                        "Location: review_asset_submission.php"
                        . "?assetID="
                        . urlencode($assetID)
                        . "&saved=1"
                    );
                } else {
                    header(
                        "Location: manager_assets.php"
                        . "?reviewed=1"
                    );
                }

                exit();

            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }

        // Keep Entered Values After Error
        if ($formError !== '') {
            $asset['assetName'] = $assetName;
            $asset['categoryID'] = $categoryID;
            $asset['assetCondition'] =
                $assetCondition;
            $asset['description'] = $description;
            $asset['availabilityStatus'] =
                $availabilityStatus;
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to process the asset review. Please try again.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}
?>
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Asset Submission - CirculAPU</title>

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
                    <span class="breadcrumb-current">Review Asset Submission</span>
                </div>

                <h1>Review Asset Submission</h1>

                <p>
                    Review the listing information submitted by the Community Member
                    before sending it for Sustainability Auditor verification.
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

                <?php if ($asset['listingStatus'] !== 'Pending Review'): ?>
                    <div class="alert alert-error">
                        This asset is not currently waiting for Resource Manager review.
                        Current listing status:
                        <?php echo htmlspecialchars($asset['listingStatus']); ?>.
                    </div>
                <?php endif; ?>

                <?php if (!empty($formError)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($formError); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                    <div class="alert alert-success">
                        Asset listing changes saved successfully.
                    </div>
                <?php endif; ?>

                <!-- 3. Review Form -->
                <section class="content-card asset-form-card">

                    <form
                        action="review_asset_submission.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                        method="POST"
                        class="asset-form"
                    >

                        <div class="asset-form-layout">

                            <!-- Listing Information -->
                            <div class="asset-form-fields">

                                <div class="asset-form-row">
                                    <label>Asset ID</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($asset['assetID']); ?>"
                                        readonly
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label for="assetName">
                                        Asset Name *
                                    </label>

                                    <input
                                        type="text"
                                        id="assetName"
                                        name="assetName"
                                        maxlength="150"
                                        value="<?php echo htmlspecialchars($asset['assetName']); ?>"
                                        required
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label for="categoryID">
                                        Category *
                                    </label>

                                    <select
                                        id="categoryID"
                                        name="categoryID"
                                        required
                                    >
                                        <?php foreach ($categories as $category): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($category['categoryID']); ?>"
                                                <?php echo $asset['categoryID'] === $category['categoryID'] ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($category['categoryName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="asset-form-row">
                                    <label for="assetCondition">
                                        Condition *
                                    </label>

                                    <select
                                        id="assetCondition"
                                        name="assetCondition"
                                        required
                                    >
                                        <?php foreach ($conditionOptions as $condition): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($condition); ?>"
                                                <?php echo $asset['assetCondition'] === $condition ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($condition); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="asset-form-row asset-form-textarea">
                                    <label for="description">
                                        Description
                                    </label>

                                    <textarea
                                        id="description"
                                        name="description"
                                        maxlength="255"
                                        rows="4"
                                    ><?php echo htmlspecialchars($asset['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="asset-form-row">
                                    <label for="availabilityStatus">
                                        Availability *
                                    </label>

                                    <select
                                        id="availabilityStatus"
                                        name="availabilityStatus"
                                        required
                                    >
                                        <?php foreach ($availabilityOptions as $availability): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($availability); ?>"
                                                <?php echo $asset['availabilityStatus'] === $availability ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($availability); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="asset-form-row">
                                    <label>Submitted By</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $asset['ownerName'] . ' (' . $asset['ownerID'] . ')'
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label>Member Email</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($asset['ownerEmail']); ?>"
                                        readonly
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label>Date Submitted</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            date('d M Y, h:i A', strtotime($asset['createdDate']))
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label>Listing Status</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($asset['listingStatus']); ?>"
                                        readonly
                                    >
                                </div>

                                <div class="asset-form-row">
                                    <label>Verification Status</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($asset['verificationStatus']); ?>"
                                        readonly
                                    >
                                </div>

                            </div>

                            <!-- Image and Review Actions -->
                            <aside class="asset-form-side">

                                <div class="asset-form-image-section">

                                    <h2>Submitted Image</h2>

                                    <?php if (!empty($asset['imageURL'])): ?>

                                        <div class="asset-form-image-preview">
                                            <img
                                                src="../assets/managerfile/<?php echo htmlspecialchars(
                                                    basename($asset['imageURL'])
                                                ); ?>"
                                                alt="<?php echo htmlspecialchars($asset['assetName']); ?>"
                                            >
                                        </div>

                                        <p>
                                            <?php echo htmlspecialchars(
                                                basename($asset['imageURL'])
                                            ); ?>
                                        </p>

                                    <?php else: ?>

                                        <div class="asset-image-empty">
                                            No image submitted
                                        </div>

                                    <?php endif; ?>

                                </div>

                                <!-- Review Checklist -->
                                <div class="asset-form-note">
                                    <strong>Review Checklist:</strong>

                                    <p>
                                        Check listing completeness, category,
                                        duplicate information and appropriate details.
                                    </p>
                                </div>

                                <!-- Review Actions -->
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
                                        onclick="return confirm('Return this listing to the Community Member for correction?');"
                                    >
                                        <img src="../assets/icons/reset.png" alt="">
                                        Return for Correction
                                    </button>

                                    <button
                                        type="submit"
                                        name="reviewAction"
                                        value="verify"
                                        class="asset-form-submit"
                                        onclick="return confirm('Send this asset listing for Sustainability Auditor verification?');"
                                    >
                                        <img src="../assets/icons/pendingVerification.png" alt="">
                                        Send for Verification
                                    </button>

                                    <a
                                        href="manager_assets.php"
                                        class="asset-form-back"
                                    >
                                        Back
                                    </a>

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