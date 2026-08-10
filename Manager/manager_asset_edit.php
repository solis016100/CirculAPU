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

$listingStatusOptions = [
    'Draft',
    'Pending Review',
    'Ready for Verification',
    'Approved',
    'Published',
    'Rejected',
    'Removed'
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

    // Retrieve Selected Asset
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
            u.name AS ownerName
        FROM assets a
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
            "The selected asset record could not be found.";
    }

    // Update Asset
    if (
        $asset &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        $assetName = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $assetCondition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $availabilityStatus = trim(
            $_POST['availabilityStatus'] ?? ''
        );
        $listingStatus = trim(
            $_POST['listingStatus'] ?? ''
        );

        // Validation
        if ($assetName === '') {
            $formError = "Asset Name is required.";
        } elseif (strlen($assetName) > 150) {
            $formError =
                "Asset Name must not exceed 150 characters.";
        } elseif (strlen($description) > 255) {
            $formError =
                "Description must not exceed 255 characters.";
        } elseif (!in_array(
            $assetCondition,
            $conditionOptions,
            true
        )) {
            $formError = "Invalid asset condition.";
        } elseif (!in_array(
            $availabilityStatus,
            $availabilityOptions,
            true
        )) {
            $formError = "Invalid availability status.";
        } elseif (!in_array(
            $listingStatus,
            $listingStatusOptions,
            true
        )) {
            $formError = "Invalid listing status.";
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

        $newImageURL = $asset['imageURL'];
        $newUploadedFile = '';

        // Validate Replacement Image
        if (
            $formError === '' &&
            isset($_FILES['assetImage']) &&
            $_FILES['assetImage']['error']
                !== UPLOAD_ERR_NO_FILE
        ) {
            $image = $_FILES['assetImage'];

            if ($image['error'] !== UPLOAD_ERR_OK) {
                $formError =
                    "Unable to upload the selected image.";
            } elseif ($image['size'] > 5 * 1024 * 1024) {
                $formError =
                    "Image size must not exceed 5MB.";
            } else {
                $imageInfo = getimagesize(
                    $image['tmp_name']
                );

                $allowedTypes = [
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG => 'png'
                ];

                if (
                    $imageInfo === false ||
                    !isset(
                        $allowedTypes[$imageInfo[2]]
                    )
                ) {
                    $formError =
                        "Only JPG and PNG images are allowed.";
                } else {
                    $extension =
                        $allowedTypes[$imageInfo[2]];

                    $fileName =
                        strtolower($assetID)
                        . '_'
                        . time()
                        . '.'
                        . $extension;

                    $uploadDirectory =
                        __DIR__
                        . '/../assets/managerfile/';

                    if (!is_dir($uploadDirectory)) {
                        mkdir(
                            $uploadDirectory,
                            0775,
                            true
                        );
                    }

                    $newUploadedFile =
                        $uploadDirectory . $fileName;

                    if (
                        !move_uploaded_file(
                            $image['tmp_name'],
                            $newUploadedFile
                        )
                    ) {
                        $formError =
                            "Unable to save the uploaded image.";
                    } else {
                        $newImageURL =
                            'assets/managerfile/'
                            . $fileName;
                    }
                }
            }
        }

        // Save Changes
        if ($formError === '') {
            $oldAvailability =
                $asset['availabilityStatus'];

            try {
                $conn->begin_transaction();

                $updateStatement = $conn->prepare("
                    UPDATE assets
                    SET
                        categoryID = ?,
                        assetName = ?,
                        description = ?,
                        assetCondition = ?,
                        imageURL = ?,
                        availabilityStatus = ?,
                        listingStatus = ?,
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");

                $updateStatement->bind_param(
                    "ssssssss",
                    $categoryID,
                    $assetName,
                    $description,
                    $assetCondition,
                    $newImageURL,
                    $availabilityStatus,
                    $listingStatus,
                    $assetID
                );

                $updateStatement->execute();
                $updateStatement->close();

                // Record Availability Change
                if (
                    $oldAvailability
                    !== $availabilityStatus
                ) {
                    $historyResult = $conn->query("
                        SELECT MAX(
                            CAST(
                                SUBSTRING(historyID, 4)
                                AS UNSIGNED
                            )
                        ) AS maxID
                        FROM assetstatushistory
                    ");

                    $historyRow =
                        $historyResult->fetch_assoc();

                    $nextHistoryNumber =
                        ((int) (
                            $historyRow['maxID'] ?? 0
                        )) + 1;

                    $historyID = sprintf(
                        "ASH%03d",
                        $nextHistoryNumber
                    );

                    $historyReason =
                        "Availability updated by Resource Manager.";

                    $historyStatement =
                        $conn->prepare("
                            INSERT INTO assetstatushistory
                            (
                                historyID,
                                assetID,
                                updatedBy,
                                oldAvailabilityStatus,
                                newAvailabilityStatus,
                                statusChangeReason
                            )
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                    $historyStatement->bind_param(
                        "ssssss",
                        $historyID,
                        $assetID,
                        $managerID,
                        $oldAvailability,
                        $availabilityStatus,
                        $historyReason
                    );

                    $historyStatement->execute();
                    $historyStatement->close();
                }

                // Generate System Log ID
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
                    ((int) ($logRow['maxID'] ?? 0))
                    + 1;

                $logID = sprintf(
                    "LOG%03d",
                    $nextLogNumber
                );

                $logDescription =
                    "Updated asset listing: "
                    . $assetName;

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

                $conn->commit();

                header(
                    "Location: manager_asset_view.php"
                    . "?assetID="
                    . urlencode($assetID)
                    . "&updated=1"
                );

                exit();

            } catch (Throwable $error) {
                $conn->rollback();

                if (
                    $newUploadedFile !== '' &&
                    file_exists($newUploadedFile)
                ) {
                    unlink($newUploadedFile);
                }

                throw $error;
            }
        }

        // Keep Entered Values After Validation Error
        if ($formError !== '') {
            $asset['assetName'] = $assetName;
            $asset['categoryID'] = $categoryID;
            $asset['assetCondition'] =
                $assetCondition;
            $asset['description'] = $description;
            $asset['availabilityStatus'] =
                $availabilityStatus;
            $asset['listingStatus'] =
                $listingStatus;
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to update the asset. Please try again.";
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

    <title>Edit Asset - CirculAPU</title>

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

                    <a href="manager_asset_view.php?assetID=<?php echo urlencode($assetID); ?>">
                        Asset Details
                    </a>

                    <span>›</span>
                    <span class="breadcrumb-current">Edit Asset</span>
                </div>

                <h1>Edit Asset</h1>

                <p>
                    Update the selected asset listing information.
                </p>

            </section>

            <!-- 2. Database Error -->
            <?php if (!empty($databaseError)): ?>

                <div class="dashboard-error-message">
                    <?php echo htmlspecialchars($databaseError); ?>
                </div>

            <?php elseif ($asset): ?>

                <?php if (!empty($formError)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($formError); ?>
                    </div>
                <?php endif; ?>

                <!-- 3. Edit Asset Form -->
                <section class="content-card asset-form-card">

                    <form
                        action="manager_asset_edit.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                        method="POST"
                        enctype="multipart/form-data"
                        class="asset-form"
                    >

                        <div class="asset-form-layout">

                            <!-- Asset Fields -->
                            <div class="asset-form-fields">

                                <!-- Asset ID -->
                                <div class="asset-form-row">
                                    <label for="assetID">
                                        Asset ID
                                    </label>

                                    <input
                                        type="text"
                                        id="assetID"
                                        value="<?php echo htmlspecialchars($asset['assetID']); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Asset Name -->
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

                                <!-- Category -->
                                <div class="asset-form-row">
                                    <label for="categoryID">
                                        Category *
                                    </label>

                                    <select
                                        id="categoryID"
                                        name="categoryID"
                                        required
                                    >
                                        <option value="">
                                            Select category
                                        </option>

                                        <?php foreach ($categories as $category): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($category['categoryID']); ?>"
                                                <?php
                                                echo $asset['categoryID'] === $category['categoryID']
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($category['categoryName']); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Condition -->
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
                                                <?php
                                                echo $asset['assetCondition'] === $condition
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($condition); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Description -->
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

                                <!-- Availability -->
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
                                                <?php
                                                echo $asset['availabilityStatus'] === $availability
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($availability); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Listing Status -->
                                <div class="asset-form-row">
                                    <label for="listingStatus">
                                        Listing Status *
                                    </label>

                                    <select
                                        id="listingStatus"
                                        name="listingStatus"
                                        required
                                    >
                                        <?php foreach ($listingStatusOptions as $status): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($status); ?>"
                                                <?php
                                                echo $asset['listingStatus'] === $status
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Uploaded By -->
                                <div class="asset-form-row">
                                    <label>
                                        Uploaded By
                                    </label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $asset['ownerName']
                                            . ' ('
                                            . $asset['ownerID']
                                            . ')'
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Verification Status -->
                                <div class="asset-form-row">
                                    <label>
                                        Verification Status
                                    </label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($asset['verificationStatus']); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Date Listed -->
                                <div class="asset-form-row">
                                    <label>
                                        Date Listed
                                    </label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($asset['createdDate'])
                                            )
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                            </div>

                            <!-- Image and Actions -->
                            <aside class="asset-form-side">

                                <!-- Current Image -->
                                <div class="asset-form-image-section">

                                    <h2>Current Image</h2>

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
                                            No image uploaded
                                        </div>

                                    <?php endif; ?>

                                </div>

                                <!-- Replace Image -->
                                <div class="asset-form-upload">

                                    <label for="assetImage">
                                        Replace Image
                                    </label>

                                    <input
                                        type="file"
                                        id="assetImage"
                                        name="assetImage"
                                        accept=".jpg,.jpeg,.png"
                                    >

                                    <small>
                                        JPG or PNG, maximum 5MB.
                                        Leave empty to keep the current image.
                                    </small>

                                </div>

                                <!-- Actions -->
                                <div class="asset-form-actions">

                                    <button
                                        type="submit"
                                        class="asset-form-submit"
                                    >
                                        <img
                                            src="../assets/icons/edit.png"
                                            alt=""
                                        >
                                        Save Changes
                                    </button>

                                    <button
                                        type="reset"
                                        class="asset-form-reset"
                                    >
                                        <img
                                            src="../assets/icons/reset.png"
                                            alt=""
                                        >
                                        Reset Changes
                                    </button>

                                    <a
                                        href="manager_asset_view.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                        class="asset-form-back"
                                    >
                                        Back
                                    </a>

                                </div>

                                <div class="asset-form-note">
                                    <strong>Note:</strong>
                                    <p>
                                        Fields marked with * are required.
                                    </p>
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