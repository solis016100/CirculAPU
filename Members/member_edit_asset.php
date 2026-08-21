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


/* Generate Notification ID */
function generateNotificationID($conn) {

    $result = $conn->query("
    SELECT MAX(
        CAST(SUBSTRING(notificationID,2) AS UNSIGNED)
    ) AS maxNumber
    FROM notification
    WHERE notificationID REGEXP '^N[0-9]+$'
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $number = ((int)($row['maxNumber'] ?? 0)) + 1;
    } else {
        $number = 1;
    }

    return "N" . str_pad($number,3,"0",STR_PAD_LEFT);
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

                if (!$conn->query($updateQuery)) {
                    throw new Exception("Unable to update asset: " . $conn->error);
                }


                /* Get Existing Verification Record */
                $verificationCheck = $conn->query("
                SELECT verificationID
                FROM verificationrecords
                WHERE targetID = '$assetID'
                AND targetType = 'Asset'
                ORDER BY verifiedDate DESC, verificationID DESC
                LIMIT 1
                ");

                /* Update Existing Verification Record If It Exists
                   Do not insert a new verification record during resubmit. */
                if ($verificationCheck && $verificationCheck->num_rows > 0) {

                    $verificationData = $verificationCheck->fetch_assoc();
                    $verificationID = $conn->real_escape_string(
                        $verificationData['verificationID']
                    );

                    $verificationQuery = "
                    UPDATE verificationrecords
                    SET
                        userID = '$userID',
                        verifiedBy = NULL,
                        status = 'Pending',
                        remarks = NULL,
                        verifiedDate = NOW()
                    WHERE verificationID = '$verificationID'
                    ";

                    if (!$conn->query($verificationQuery)) {
                        throw new Exception(
                            "Unable to update verification record: " . $conn->error
                        );
                    }
                }


                /* Get Latest Member Name */
                $latestMemberName = $memberName;

                $memberResult = $conn->query("
                SELECT name
                FROM user
                WHERE userID = '$userID'
                LIMIT 1
                ");

                if ($memberResult && $memberResult->num_rows > 0) {
                    $memberData = $memberResult->fetch_assoc();
                    $latestMemberName = $memberData['name'];
                }


                /* Send Notification To Member */
                $memberNotificationID = generateNotificationID($conn);

                $memberTitle = "Asset Resubmitted Successfully";
                $memberContent =
                    "Your asset " . $assetName .
                    " (" . $assetID .
                    ") has been resubmitted successfully and is pending manager review.";

                $safeMemberTitle = $conn->real_escape_string($memberTitle);
                $safeMemberContent = $conn->real_escape_string($memberContent);

                $memberNotificationQuery = "
                INSERT INTO notification
                (notificationID, userID, title, content, status, createdDate)
                VALUES
                ('$memberNotificationID', '$userID', '$safeMemberTitle',
                 '$safeMemberContent', 'Unread', NOW())
                ";

                if (!$conn->query($memberNotificationQuery)) {
                    throw new Exception(
                        "Member notification failed: " . $conn->error
                    );
                }


                /* Send Notification To Active Manager(s) */
                $managerResult = $conn->query("
                SELECT userID
                FROM user
                WHERE role = 'Manager'
                AND status = 'Active'
                ");

                if (!$managerResult) {
                    throw new Exception(
                        "Unable to find manager: " . $conn->error
                    );
                }

                while ($manager = $managerResult->fetch_assoc()) {

                    $managerID = $conn->real_escape_string($manager['userID']);
                    $managerNotificationID = generateNotificationID($conn);

                    $managerTitle = "Asset Resubmitted for Review";
                    $managerContent =
                        $latestMemberName .
                        " (" . $userID . ") resubmitted " .
                        $assetName .
                        " (" . $assetID .
                        "). The asset is pending review.";

                    $safeManagerTitle = $conn->real_escape_string($managerTitle);
                    $safeManagerContent = $conn->real_escape_string($managerContent);

                    $managerNotificationQuery = "
                    INSERT INTO notification
                    (notificationID, userID, title, content, status, createdDate)
                    VALUES
                    ('$managerNotificationID', '$managerID', '$safeManagerTitle',
                     '$safeManagerContent', 'Unread', NOW())
                    ";

                    if (!$conn->query($managerNotificationQuery)) {
                        throw new Exception(
                            "Manager notification failed: " . $conn->error
                        );
                    }
                }


                $conn->commit();

                header("Location: member_requests.php");
                exit();

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Unable to resubmit asset: " . $e->getMessage();
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


                <div class="asset-form-grid">

                    <!-- Asset Name -->
                    <div class="asset-form-group">
                        <label>Asset Name</label>

                        <input
                            type="text"
                            name="assetName"
                            value="<?php echo htmlspecialchars($asset['assetName']); ?>"
                            required
                        >
                    </div>


                    <!-- Category -->
                    <div class="asset-form-group">
                        <label>Category</label>

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
                        <label>Condition</label>

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

                    <label>Description</label>

                    <textarea
                        name="description"
                        rows="5"
                        required
                    ><?php echo htmlspecialchars($asset['description']); ?></textarea>

                </div>


                <!-- Asset Image -->
                <div class="asset-image-section">

                    <label>Asset Image</label>

                    <!-- Previous / Current Image -->
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
                            <img id="newImagePreview" src="" alt="New Asset Image Preview">
                        </div>

                    </div>


                    <!-- Replace Image -->
                    <div class="asset-upload-box">

                        <input
                            type="file"
                            name="assetImage"
                            id="assetImage"
                            accept=".jpg,.jpeg,.png"
                            onchange="previewNewImage()"
                        >

                        <p>
                            Leave this empty to keep the current image.
                            Choose a new image only if you want to replace it.
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

    const input = document.getElementById('assetImage');
    const area = document.getElementById('newImageArea');
    const preview = document.getElementById('newImagePreview');
    const cancelBtn = document.getElementById('cancelImageBtn');

    if (input.files && input.files[0]) {

        const reader = new FileReader();

        reader.onload = function(e) {
            preview.src = e.target.result;
            area.style.display = 'block';
            cancelBtn.style.display = 'inline-block';
        };

        reader.readAsDataURL(input.files[0]);
    }
}

function cancelNewImage() {

    const input = document.getElementById('assetImage');
    const area = document.getElementById('newImageArea');
    const preview = document.getElementById('newImagePreview');
    const cancelBtn = document.getElementById('cancelImageBtn');

    input.value = '';
    preview.src = '';
    area.style.display = 'none';
    cancelBtn.style.display = 'none';
}
</script>

</body>
</html>