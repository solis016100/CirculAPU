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
$newUploadedFile = '';

try {
    $conn = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "circulapu_db",
        3306
    );

    $conn->set_charset("utf8mb4");

    // Retrieve Asset
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
        $databaseError =
            "The selected asset record could not be found.";
    }

    // Retrieve Active Categories
    if ($asset) {
        $categoryStatement = $conn->prepare("
            SELECT categoryID, categoryName
            FROM categories
            WHERE status = 'Active'
               OR categoryID = ?
            ORDER BY categoryName ASC
        ");

        $categoryStatement->bind_param(
            "s",
            $asset['categoryID']
        );

        $categoryStatement->execute();

        $categoryResult =
            $categoryStatement->get_result();

        while ($category = $categoryResult->fetch_assoc()) {
            $categories[] = $category;
        }

        $categoryStatement->close();
    }

    // Update Asset
    if (
        $asset &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        $assetName = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $assetCondition = trim(
            $_POST['assetCondition'] ?? ''
        );
        $description = trim($_POST['description'] ?? '');
        $availabilityStatus = trim(
            $_POST['availabilityStatus'] ?? ''
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
            $formError =
                "Please select a valid asset condition.";
        } elseif (!in_array(
            $availabilityStatus,
            $availabilityOptions,
            true
        )) {
            $formError =
                "Please select a valid availability status.";
        }

        // Validate Category
        if ($formError === '') {
            $categoryCheck = $conn->prepare("
                SELECT categoryID
                FROM categories
                WHERE categoryID = ?
                AND (
                    status = 'Active'
                    OR categoryID = ?
                )
                LIMIT 1
            ");

            $categoryCheck->bind_param(
                "ss",
                $categoryID,
                $asset['categoryID']
            );

            $categoryCheck->execute();

            if (
                !$categoryCheck
                    ->get_result()
                    ->fetch_assoc()
            ) {
                $formError =
                    "Please select a valid category.";
            }

            $categoryCheck->close();
        }

        // Current Image
        $imageURL = $asset['imageURL'];

        // Optional Replacement Image
        if (
            $formError === '' &&
            isset($_FILES['assetImage']) &&
            $_FILES['assetImage']['error']
                !== UPLOAD_ERR_NO_FILE
        ) {
            $uploadedImage = $_FILES['assetImage'];

            if ($uploadedImage['error'] !== UPLOAD_ERR_OK) {
                $formError =
                    "Unable to upload the selected image.";
            } elseif ($uploadedImage['size'] > 5 * 1024 * 1024) {
                $formError =
                    "Image size must not exceed 5MB.";
            } else {
                $imageInfo = getimagesize(
                    $uploadedImage['tmp_name']
                );

                if ($imageInfo === false) {
                    $formError =
                        "The selected file is not a valid image.";
                } else {
                    $mimeType = $imageInfo['mime'];

                    $allowedImageTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png'
                    ];

                    if (!isset(
                        $allowedImageTypes[$mimeType]
                    )) {
                        $formError =
                            "Only JPG and PNG images are allowed.";
                    } else {
                        $extension =
                            $allowedImageTypes[$mimeType];

                        $fileName =
                            strtolower($assetID)
                            . '_'
                            . time()
                            . '.'
                            . $extension;

                        $uploadDirectory =
                            dirname(__DIR__)
                            . '/assets/managerfile/';

                        if (!is_dir($uploadDirectory)) {
                            if (
                                !mkdir(
                                    $uploadDirectory,
                                    0775,
                                    true
                                )
                            ) {
                                $formError =
                                    "Unable to create the image upload folder.";
                            }
                        }

                        if ($formError === '') {
                            $newUploadedFile =
                                $uploadDirectory
                                . $fileName;

                            if (
                                !move_uploaded_file(
                                    $uploadedImage['tmp_name'],
                                    $newUploadedFile
                                )
                            ) {
                                $formError =
                                    "Unable to save the uploaded image.";
                            } else {
                                $imageURL =
                                    'assets/managerfile/'
                                    . $fileName;
                            }
                        }
                    }
                }
            }
        }

        // Save Changes
        if ($formError === '') {
            try {
                $conn->begin_transaction();

                $oldAvailabilityStatus =
                    $asset['availabilityStatus'];

                // Listing Status and Verification Status
                // are intentionally NOT updated here.
                $updateStatement = $conn->prepare("
                    UPDATE assets
                    SET
                        categoryID = ?,
                        assetName = ?,
                        description = ?,
                        assetCondition = ?,
                        imageURL = ?,
                        availabilityStatus = ?,
                        updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");

                $updateStatement->bind_param(
                    "sssssss",
                    $categoryID,
                    $assetName,
                    $description,
                    $assetCondition,
                    $imageURL,
                    $availabilityStatus,
                    $assetID
                );

                $updateStatement->execute();
                $updateStatement->close();

                // Record Availability Status Change
                if (
                    $oldAvailabilityStatus
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

                    $statusChangeReason =
                        "Availability updated by Resource Manager.";

                    $historyStatement = $conn->prepare("
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
                        $oldAvailabilityStatus,
                        $availabilityStatus,
                        $statusChangeReason
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
                    ((int) ($logRow['maxID'] ?? 0)) + 1;

                $logID = sprintf(
                    "LOG%03d",
                    $nextLogNumber
                );

                $logDescription =
                    "Updated asset information: "
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

                // Remove newly uploaded image if DB update fails
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
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to process the asset information. Please try again.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}

// Image Preview
$imagePath = '';

if ($asset && !empty($asset['imageURL'])) {
    $url = str_replace('\\', '/', trim($asset['imageURL']));

    $imagePath = preg_match('/^https?:\/\//i', $url)
        ? $url
        : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $url), '/');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

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

            <!-- 1. Page Heading -->
            <section class="management-heading">

                <div class="breadcrumb">
                    <a href="manager_assets.php">
                        Asset Management
                    </a>

                    <span>›</span>

                    <a
                        href="manager_asset_view.php?assetID=<?php
                        echo urlencode($assetID);
                        ?>"
                    >
                        Asset Details
                    </a>

                    <span>›</span>

                    <span class="breadcrumb-current">
                        Edit Asset
                    </span>
                </div>

                <h1>Edit Asset Information</h1>

                <p>
                    Update the asset listing information while
                    preserving its review and verification status.
                </p>

            </section>

            <!-- 2. Database Error -->
            <?php if (!empty($databaseError)): ?>

                <div class="dashboard-error-message">
                    <?php echo htmlspecialchars($databaseError); ?>
                </div>

                <a
                    href="manager_assets.php"
                    class="management-create-btn"
                >
                    Back to Asset Management
                </a>

            <?php elseif ($asset): ?>

                <!-- 3. Form Error -->
                <?php if (!empty($formError)): ?>

                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($formError); ?>
                    </div>

                <?php endif; ?>

                <!-- 4. Edit Form -->
                <section class="content-card asset-form-card">

                    <form
                        action="manager_asset_edit.php?assetID=<?php
                        echo urlencode($asset['assetID']);
                        ?>"
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <div class="asset-form-layout">

                            <!-- Asset Fields -->
                            <div class="asset-form-fields">

                                <!-- Asset ID -->
                                <div class="asset-form-row">
                                    <label>Asset ID</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $asset['assetID']
                                        ); ?>"
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
                                        value="<?php echo htmlspecialchars(
                                            $asset['assetName']
                                        ); ?>"
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

                                        <?php foreach ($categories as $category): ?>

                                            <option
                                                value="<?php echo htmlspecialchars(
                                                    $category['categoryID']
                                                ); ?>"
                                                <?php
                                                echo $asset['categoryID']
                                                    === $category['categoryID']
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars(
                                                    $category['categoryName']
                                                ); ?>
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
                                                value="<?php echo htmlspecialchars(
                                                    $condition
                                                ); ?>"
                                                <?php
                                                echo $asset['assetCondition']
                                                    === $condition
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars(
                                                    $condition
                                                ); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Description -->
                                <div
                                    class="asset-form-row asset-form-textarea"
                                >
                                    <label for="description">
                                        Description
                                    </label>

                                    <textarea
                                        id="description"
                                        name="description"
                                        maxlength="255"
                                        rows="4"
                                    ><?php echo htmlspecialchars(
                                        $asset['description'] ?? ''
                                    ); ?></textarea>
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
                                                value="<?php echo htmlspecialchars(
                                                    $availability
                                                ); ?>"
                                                <?php
                                                echo $asset['availabilityStatus']
                                                    === $availability
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >
                                                <?php echo htmlspecialchars(
                                                    $availability
                                                ); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Owner -->
                                <div class="asset-form-row">
                                    <label>Submitted By</label>

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

                                <!-- Listing Status -->
                                <div class="asset-form-row">
                                    <label>Listing Status</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $asset['listingStatus']
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Verification Status -->
                                <div class="asset-form-row">
                                    <label>Verification Status</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $asset['verificationStatus']
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Date Listed -->
                                <div class="asset-form-row">
                                    <label>Date Listed</label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime(
                                                    $asset['createdDate']
                                                )
                                            )
                                        ); ?>"
                                        readonly
                                    >
                                </div>

                            </div>

                            <!-- Side Panel -->
                            <aside class="asset-form-side">

                                <!-- Current Image -->
                                <div class="asset-form-image-section">

                                    <h2>Current Image</h2>

                                    <?php if ($imagePath !== ''): ?>

                                        <div class="asset-form-image-preview">
                                            <img
                                                src="<?php echo htmlspecialchars(
                                                    $imagePath
                                                ); ?>"
                                                alt="<?php echo htmlspecialchars(
                                                    $asset['assetName']
                                                ); ?>"
                                            >
                                        </div>

                                        <p>
                                            <?php echo htmlspecialchars(
                                                basename(
                                                    $asset['imageURL']
                                                )
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
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    >

                                    <small>
                                        Optional. JPG or PNG only,
                                        maximum 5MB.
                                    </small>

                                </div>

                                <!-- Note -->
                                <div class="asset-form-note">

                                    <strong>Important</strong>

                                    <p>
                                        Listing Status and Verification
                                        Status cannot be changed from this
                                        page. Use the correct review or
                                        verification workflow instead.
                                    </p>

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
                                        Reset
                                    </button>

                                    <a
                                        href="manager_asset_view.php?assetID=<?php
                                        echo urlencode(
                                            $asset['assetID']
                                        );
                                        ?>"
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