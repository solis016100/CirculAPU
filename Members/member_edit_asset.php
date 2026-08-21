<?php
session_start();

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

// Get Member's information from login
$memberID = $_SESSION['user_id'];
$memberName = $_SESSION['user_name'] ?? 'Member';
$userID = $memberID;

// Connect to Database
require_once '../db.php';

if ($conn->connect_error) {
    $databaseError = "Unable to connect to the database.";
} else {
    // UTF-8 allows the database to handle different languages
    $conn->set_charset("utf8mb4");
}

$message = "";
$messageType = "";

// Get Asset ID
$assetID = $_GET['id'] ?? '';

if ($assetID == '') {
    header("Location: member_requests.php");
    exit();
}

$assetID = $conn->real_escape_string($assetID);


/* Get Asset Information */
$assetQuery = "
SELECT
    assetID,
    ownerID,
    categoryID,
    assetName,
    description,
    assetCondition,
    imageURL,
    availabilityStatus,
    listingStatus,
    verificationStatus
FROM assets
WHERE assetID = '$assetID'
AND ownerID = '$userID'
LIMIT 1
";

$assetResult = $conn->query($assetQuery);

if (!$assetResult || $assetResult->num_rows == 0) {
    die("Asset not found or you are not allowed to edit this asset.");
}

$asset = $assetResult->fetch_assoc();


// Only Draft asset can be modified
if ($asset['listingStatus'] != 'Draft') {
    die("Only assets with Draft status can be modified.");
}


/* Get Categories */
$categoryQuery = "
SELECT categoryID, categoryName
FROM categories
ORDER BY categoryName ASC
";

$categoryResult = $conn->query($categoryQuery);


/* Generate Verification ID */
function generateVerificationID($conn) {

    $idQuery = "
    SELECT verificationID
    FROM verificationrecords
    WHERE verificationID LIKE 'V%'
    ORDER BY CAST(SUBSTRING(verificationID,2) AS UNSIGNED) DESC
    LIMIT 1
    ";

    $idResult = $conn->query($idQuery);

    if ($idResult && $idResult->num_rows > 0) {
        $lastID = $idResult->fetch_assoc()['verificationID'];
        $number = (int)substr($lastID,1) + 1;
    } else {
        $number = 1;
    }

    return "V" . str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Resubmit Asset */
if (isset($_POST['resubmit'])) {

    $assetName = trim($_POST['assetName'] ?? '');
    $categoryID = $_POST['categoryID'] ?? '';
    $assetCondition = $_POST['assetCondition'] ?? '';
    $description = trim($_POST['description'] ?? '');

    $hasCurrentImage = !empty($asset['imageURL']);
    $hasNewImage = !empty($_FILES['assetImage']['name']);

    if ($assetName == '' || $categoryID == '' || $assetCondition == '' || $description == '') {

        $message = "Please complete all required fields.";
        $messageType = "error";

    } elseif (!$hasCurrentImage && !$hasNewImage) {

        $message = "Please upload an asset image.";
        $messageType = "error";

    } else {

        $assetName = $conn->real_escape_string($assetName);
        $categoryID = $conn->real_escape_string($categoryID);
        $assetCondition = $conn->real_escape_string($assetCondition);
        $description = $conn->real_escape_string($description);

        $imageURL = $asset['imageURL'];

        // Upload New Image If User Selected One
        if (!empty($_FILES['assetImage']['name'])) {

            $uploadFolder = "../uploads/assets/";

            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder,0777,true);
            }

            $fileName = time() . "_" . basename($_FILES['assetImage']['name']);
            $targetPath = $uploadFolder . $fileName;

            $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg','jpeg','png'];

            if (!in_array($fileType,$allowedTypes)) {

                $message = "Only JPG, JPEG and PNG images are allowed.";
                $messageType = "error";

            } elseif ($_FILES['assetImage']['size'] > 5000000) {

                $message = "Image size must be below 5MB.";
                $messageType = "error";

            } else {

                if (move_uploaded_file($_FILES['assetImage']['tmp_name'],$targetPath)) {
                    $imageURL = "uploads/assets/" . $fileName;
                } else {
                    $message = "Unable to upload image.";
                    $messageType = "error";
                }
            }
        }

        if ($message == '') {

            $safeImageURL = $conn->real_escape_string($imageURL ?? '');

            $conn->begin_transaction();

            try {

                /* Update Asset */
                $updateQuery = "
                UPDATE assets
                SET
                    assetName = '$assetName',
                    categoryID = '$categoryID',
                    description = '$description',
                    assetCondition = '$assetCondition',
                    imageURL = '$safeImageURL',
                    availabilityStatus = 'Unavailable',
                    listingStatus = 'Pending Review',
                    verificationStatus = 'Pending',
                    updatedDate = NOW()
                WHERE assetID = '$assetID'
                AND ownerID = '$userID'
                ";

                $conn->query($updateQuery);


                /* Add New Verification Record */
                $verificationID = generateVerificationID($conn);

                $verificationQuery = "
                INSERT INTO verificationrecords
                (
                    verificationID,
                    userID,
                    verifiedBy,
                    targetID,
                    targetType,
                    status,
                    remarks,
                    verifiedDate
                )
                VALUES
                (
                    '$verificationID',
                    '$userID',
                    NULL,
                    '$assetID',
                    'Asset',
                    'Pending',
                    NULL,
                    NOW()
                )
                ";

                $conn->query($verificationQuery);

                $conn->commit();

                header("Location: member_requests.php");
                exit();

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Unable to resubmit asset.";
                $messageType = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Modify Asset - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_new_style.css">

</head>

<body class="role-member">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body">

            <div class="asset-form-header">

                <div>
                    <h1>Modify Asset</h1>
                    <p>Update your asset information and resubmit it for verification.</p>
                </div>

                <a href="member_requests.php" class="asset-back-btn">
                    ← Back to My Request
                </a>

            </div>


            <?php if ($message != ''): ?>
                <div class="asset-form-alert <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>


            <form method="POST" enctype="multipart/form-data" class="asset-form-card">

                <h2>Asset Information</h2>
                <p class="required-note"><span class="required-star">*</span> All fields are required.</p>

                <div class="asset-form-grid">

                    <!-- Asset Name -->
                    <div class="asset-form-group">
                        <label>Asset Name <span class="required-star">*</span></label>

                        <input
                            type="text"
                            name="assetName"
                            value="<?php echo htmlspecialchars($asset['assetName']); ?>"
                            required
                        >
                    </div>


                    <!-- Category -->
                    <div class="asset-form-group">
                        <label>Category <span class="required-star">*</span></label>

                        <select name="categoryID" required>

                            <option value="">Select Category</option>

                            <?php if ($categoryResult && $categoryResult->num_rows > 0): ?>

                                <?php while ($category = $categoryResult->fetch_assoc()): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($category['categoryID']); ?>"
                                        <?php echo $asset['categoryID'] == $category['categoryID'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($category['categoryName']); ?>
                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </select>
                    </div>


                    <!-- Condition -->
                    <div class="asset-form-group">
                        <label>Condition <span class="required-star">*</span></label>

                        <select name="assetCondition" required>

                            <option value="">Select Condition</option>

                            <option value="New"
                                <?php echo $asset['assetCondition'] == 'New' ? 'selected' : ''; ?>>
                                New
                            </option>

                            <option value="Like New"
                                <?php echo $asset['assetCondition'] == 'Like New' ? 'selected' : ''; ?>>
                                Like New
                            </option>

                            <option value="Good"
                                <?php echo $asset['assetCondition'] == 'Good' ? 'selected' : ''; ?>>
                                Good
                            </option>

                            <option value="Fair"
                                <?php echo $asset['assetCondition'] == 'Fair' ? 'selected' : ''; ?>>
                                Fair
                            </option>

                        </select>
                    </div>

                </div>


                <!-- Description -->
                <div class="asset-form-group asset-description-group">

                    <label>Description <span class="required-star">*</span></label>

                    <textarea
                        name="description"
                        rows="5"
                        required
                    ><?php echo htmlspecialchars($asset['description']); ?></textarea>

                </div>


                <!-- Asset Image -->
                <div class="asset-image-section">

                    <label>Asset Image <span class="required-star">*</span></label>

                    <!-- Current Image -->
                    <?php if (!empty($asset['imageURL'])): ?>

                        <p class="current-image-text">Current Image</p>

                        <div class="asset-current-image" id="currentImageBox">
                            <img
                                src="../<?php echo htmlspecialchars($asset['imageURL']); ?>"
                                alt="<?php echo htmlspecialchars($asset['assetName']); ?>"
                            >
                        </div>

                    <?php endif; ?>


                    <!-- New Image Preview -->
                    <div id="newImageArea" style="display:none;">

                        <p class="current-image-text">New Image Preview</p>

                        <div class="asset-current-image">
                            <img id="newImagePreview" src="" alt="New Asset Image">
                        </div>

                    </div>


                    <!-- Choose New Image -->
                    <div class="asset-upload-box">

                        <input
                            type="file"
                            name="assetImage"
                            id="assetImage"
                            accept=".jpg,.jpeg,.png"
                            onchange="previewNewImage()"
                        >

                        <p>
                            Choose a new image only if you want to replace the current image.
                            If you cancel it, the current image will remain.
                        </p>

                        <button
                            type="button"
                            id="cancelImageBtn"
                            class="cancel-image-btn"
                            onclick="cancelNewImage()"
                            style="display:none;">
                            Cancel New Image
                        </button>

                    </div>

                </div>


                <!-- Current Status -->
                <div class="asset-resubmit-status">

                    <div>
                        <span>Listing Status</span>
                        <strong><?php echo htmlspecialchars($asset['listingStatus']); ?></strong>
                    </div>

                    <div>
                        <span>Verification Status</span>
                        <strong><?php echo htmlspecialchars($asset['verificationStatus']); ?></strong>
                    </div>

                </div>


                <!-- Buttons -->
                <div class="asset-form-actions">

                    <a href="member_requests.php" class="asset-cancel-btn">
                        Cancel
                    </a>

                    <button type="submit" name="resubmit" class="asset-submit-btn">
                        Resubmit for Review →
                    </button>

                </div>

            </form>

        </main>

    </div>

</div>

<script>
function previewNewImage() {
    const imageInput = document.getElementById('assetImage');
    const newImageArea = document.getElementById('newImageArea');
    const newImagePreview = document.getElementById('newImagePreview');
    const cancelImageBtn = document.getElementById('cancelImageBtn');

    if (imageInput.files && imageInput.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            newImagePreview.src = e.target.result;
            newImageArea.style.display = 'block';
            cancelImageBtn.style.display = 'inline-block';
        };

        reader.readAsDataURL(imageInput.files[0]);
    }
}

function cancelNewImage() {
    const imageInput = document.getElementById('assetImage');
    const newImageArea = document.getElementById('newImageArea');
    const newImagePreview = document.getElementById('newImagePreview');
    const cancelImageBtn = document.getElementById('cancelImageBtn');

    imageInput.value = '';
    newImagePreview.src = '';
    newImageArea.style.display = 'none';
    cancelImageBtn.style.display = 'none';
}
</script>

</body>
</html>