<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$managerID = $_SESSION['user_id'];
$managerName = $_SESSION['user_name'] ?? 'Resource Manager';

$categories = [];
$databaseError = '';
$formError = '';
$newAssetID = '';

// Allowed Values
$conditionOptions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];

$availabilityOptions = [
    'Available',
    'Reserved',
    'Borrowed',
    'Unavailable'
];

// Default Form Values
$assetName = '';
$categoryID = '';
$assetCondition = '';
$description = '';
$availabilityStatus = '';
$listingStatus = 'Pending Review';
$verificationStatus = 'Pending';

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

    // Generate Next Asset ID
    $idResult = $conn->query("
        SELECT MAX(
            CAST(SUBSTRING(assetID, 4) AS UNSIGNED)
        ) AS maxID
        FROM assets
    ");

    $idRow = $idResult->fetch_assoc();
    $nextNumber = ((int) ($idRow['maxID'] ?? 0)) + 1;
    $newAssetID = sprintf("AST%03d", $nextNumber);

    // Create Asset
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $assetName = trim($_POST['assetName'] ?? '');
        $categoryID = trim($_POST['categoryID'] ?? '');
        $assetCondition = trim($_POST['assetCondition'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $availabilityStatus = trim($_POST['availabilityStatus'] ?? '');

        // Validate Required Fields
        if ($assetName === '') {
            $formError = "Asset Name is required.";
        } elseif (strlen($assetName) > 150) {
            $formError = "Asset Name must not exceed 150 characters.";
        } elseif ($categoryID === '') {
            $formError = "Category is required.";
        } elseif (!in_array($assetCondition, $conditionOptions, true)) {
            $formError = "Please select a valid asset condition.";
        } elseif ($description === '') {
            $formError = "Description is required.";
        } elseif (strlen($description) > 255) {
            $formError = "Description must not exceed 255 characters.";
        } elseif (!in_array($availabilityStatus, $availabilityOptions, true)) {
            $formError = "Please select a valid availability status.";
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

            $categoryCheck->bind_param("s", $categoryID);
            $categoryCheck->execute();

            if (!$categoryCheck->get_result()->fetch_assoc()) {
                $formError = "Please select a valid active category.";
            }

            $categoryCheck->close();
        }

        // Validate Image
        $imageURL = '';
        $uploadedFile = '';

        if ($formError === '') {
            if (
                !isset($_FILES['assetImage']) ||
                $_FILES['assetImage']['error'] === UPLOAD_ERR_NO_FILE
            ) {
                $formError = "Please upload an asset image.";
            } else {
                $image = $_FILES['assetImage'];

                if ($image['error'] !== UPLOAD_ERR_OK) {
                    $formError = "Unable to upload the selected image.";
                } elseif ($image['size'] > 5 * 1024 * 1024) {
                    $formError = "Image size must not exceed 5MB.";
                } else {
                    $imageInfo = getimagesize($image['tmp_name']);

                    $allowedTypes = [
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_PNG => 'png'
                    ];

                    if (
                        $imageInfo === false ||
                        !isset($allowedTypes[$imageInfo[2]])
                    ) {
                        $formError = "Only JPG and PNG images are allowed.";
                    } else {
                        $extension = $allowedTypes[$imageInfo[2]];

                        $fileName =
                            strtolower($newAssetID)
                            . '_'
                            . time()
                            . '.'
                            . $extension;

                        $uploadDirectory =
                            __DIR__ . '/../assets/managerfile/';

                        if (!is_dir($uploadDirectory)) {
                            mkdir($uploadDirectory, 0775, true);
                        }

                        $uploadedFile = $uploadDirectory . $fileName;

                        if (!move_uploaded_file(
                            $image['tmp_name'],
                            $uploadedFile
                        )) {
                            $formError = "Unable to save the uploaded image.";
                        } else {
                            $imageURL =
                                'assets/managerfile/' . $fileName;
                        }
                    }
                }
            }
        }

        // Insert Asset
        if ($formError === '') {
            try {
                $conn->begin_transaction();

                $insertStatement = $conn->prepare("
                    INSERT INTO assets
                    (
                        assetID,
                        ownerID,
                        categoryID,
                        assetName,
                        description,
                        assetCondition,
                        imageURL,
                        availabilityStatus,
                        listingStatus,
                        verificationStatus,
                        createdDate
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        CURRENT_TIMESTAMP
                    )
                ");

                $insertStatement->bind_param(
                    "ssssssssss",
                    $newAssetID,
                    $managerID,
                    $categoryID,
                    $assetName,
                    $description,
                    $assetCondition,
                    $imageURL,
                    $availabilityStatus,
                    $listingStatus,
                    $verificationStatus
                );

                $insertStatement->execute();
                $insertStatement->close();

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

                $logID = sprintf("LOG%03d", $nextLogNumber);

                $logDescription =
                    "Created asset listing: " . $assetName;

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
                    VALUES (?, ?, 'Create', 'Asset', ?, ?)
                ");

                $logStatement->bind_param(
                    "ssss",
                    $logID,
                    $managerID,
                    $newAssetID,
                    $logDescription
                );

                $logStatement->execute();
                $logStatement->close();

                $conn->commit();

                header(
                    "Location: manager_asset_view.php"
                    . "?assetID="
                    . urlencode($newAssetID)
                    . "&created=1"
                );
                exit();

            } catch (Throwable $error) {
                $conn->rollback();

                if (
                    $uploadedFile !== '' &&
                    file_exists($uploadedFile)
                ) {
                    unlink($uploadedFile);
                }

                throw $error;
            }
        }
    }

} catch (Throwable $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to create the asset. Please try again.";
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

    <title>Add New Asset - CirculAPU</title>

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
                    <span class="breadcrumb-current">Add New Asset</span>
                </div>

                <h1>Add New Asset</h1>

                <p>
                    Create a new asset listing for the CirculAPU
                    resource-sharing community.
                </p>

            </section>

            <!-- 2. Database Error -->
            <?php if (!empty($databaseError)): ?>

                <div class="dashboard-error-message">
                    <?php echo htmlspecialchars($databaseError); ?>
                </div>

            <?php else: ?>

                <?php if (!empty($formError)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($formError); ?>
                    </div>
                <?php endif; ?>

                <!-- 3. Add Asset Form -->
                <section class="content-card asset-form-card">

                    <form
                        action="manager_asset_create.php"
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
                                        value="<?php echo htmlspecialchars($newAssetID); ?>"
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
                                        placeholder="Enter asset name"
                                        value="<?php echo htmlspecialchars($assetName); ?>"
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
                                                <?php echo $categoryID === $category['categoryID'] ? 'selected' : ''; ?>
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
                                        <option value="">
                                            Select condition
                                        </option>

                                        <?php foreach ($conditionOptions as $condition): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($condition); ?>"
                                                <?php echo $assetCondition === $condition ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($condition); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Description -->
                                <div class="asset-form-row asset-form-textarea">
                                    <label for="description">
                                        Description *
                                    </label>

                                    <textarea
                                        id="description"
                                        name="description"
                                        maxlength="255"
                                        rows="4"
                                        placeholder="Enter asset description"
                                        required
                                    ><?php echo htmlspecialchars($description); ?></textarea>
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
                                        <option value="">
                                            Select availability
                                        </option>

                                        <?php foreach ($availabilityOptions as $availability): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($availability); ?>"
                                                <?php echo $availabilityStatus === $availability ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($availability); ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <!-- Listing Status -->
                                <div class="asset-form-row">
                                    <label>
                                        Listing Status
                                    </label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars($listingStatus); ?>"
                                        readonly
                                    >
                                </div>

                                <!-- Uploaded By -->
                                <div class="asset-form-row">
                                    <label>
                                        Uploaded By
                                    </label>

                                    <input
                                        type="text"
                                        value="<?php echo htmlspecialchars(
                                            $managerName . ' (' . $managerID . ')'
                                        ); ?>"
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
                                        value="<?php echo date('d M Y'); ?>"
                                        readonly
                                    >
                                </div>

                            </div>

                            <!-- Upload and Actions -->
                            <aside class="asset-form-side">

                                <!-- Upload Image -->
                                <div class="asset-form-upload">

                                    <label for="assetImage">
                                        Upload Image *
                                    </label>

                                    <input
                                        type="file"
                                        id="assetImage"
                                        name="assetImage"
                                        accept=".jpg,.jpeg,.png"
                                        required
                                    >

                                    <small>
                                        JPG or PNG only. Maximum file size 5MB.
                                        One image per asset listing.
                                    </small>

                                </div>

                                <!-- Actions -->
                                <div class="asset-form-actions">

                                    <button
                                        type="submit"
                                        class="asset-form-submit"
                                    >
                                        <img
                                            src="../assets/icons/create.png"
                                            alt=""
                                        >
                                        Add Asset
                                    </button>

                                    <button
                                        type="reset"
                                        class="asset-form-reset"
                                    >
                                        <img
                                            src="../assets/icons/reset.png"
                                            alt=""
                                        >
                                        Clear Form
                                    </button>

                                    <a
                                        href="manager_assets.php"
                                        class="asset-form-back"
                                    >
                                        Back
                                    </a>

                                </div>

                                <!-- Form Note -->
                                <div class="asset-form-note">
                                    <strong>Note:</strong>

                                    <p>
                                        Fields marked with * are required.
                                        New listings will be submitted for review.
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