<?php
session_start();

// Access Control Guard for Community Member Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

require_once '../db.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$message = "";
$messageType = "";

/* Get Categories */
$categoryResult = $conn->query("
SELECT categoryID, categoryName
FROM categories
ORDER BY categoryName ASC
");

/* Generate Asset ID */
function generateAssetID($conn) {
    $result = $conn->query("
    SELECT assetID
    FROM assets
    WHERE assetID LIKE 'A%'
    ORDER BY CAST(SUBSTRING(assetID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $lastID = $result->fetch_assoc()['assetID'];
        $number = (int)substr($lastID,1) + 1;
    } else {
        $number = 1;
    }

    return "A" . str_pad($number,3,"0",STR_PAD_LEFT);
}

/* Generate Verification ID */
function generateVerificationID($conn) {
    $result = $conn->query("
    SELECT verificationID
    FROM verificationrecords
    WHERE verificationID LIKE 'V%'
    ORDER BY CAST(SUBSTRING(verificationID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $lastID = $result->fetch_assoc()['verificationID'];
        $number = (int)substr($lastID,1) + 1;
    } else {
        $number = 1;
    }

    return "V" . str_pad($number,3,"0",STR_PAD_LEFT);
}


/* Generate Notification ID */
function generateNotificationID($conn) {
    $result = $conn->query("
    SELECT notificationID
    FROM notification
    WHERE notificationID LIKE 'N%'
    AND notificationID NOT LIKE 'NOT%'
    ORDER BY CAST(SUBSTRING(notificationID,2) AS UNSIGNED) DESC
    LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $number = (int)substr($row['notificationID'],1) + 1;
    } else {
        $number = 1;
    }

    return "N" . str_pad($number,3,"0",STR_PAD_LEFT);
}

/* Submit New Asset */
if (isset($_POST['submitAsset'])) {

    $assetName = trim($_POST['assetName'] ?? '');
    $categoryID = trim($_POST['categoryID'] ?? '');
    $assetCondition = trim($_POST['assetCondition'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (
        $assetName == '' ||
        $categoryID == '' ||
        $assetCondition == '' ||
        $description == '' ||
        empty($_FILES['assetImage']['name'])
    ) {
        $message = "Please complete all required fields.";
        $messageType = "error";
    } else {

        $imageURL = "";
        $uploadFolder = "../uploads/assets/";

        if (!is_dir($uploadFolder)) {
            mkdir($uploadFolder,0777,true);
        }

        $originalName = basename($_FILES['assetImage']['name']);
        $extension = strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
        $allowedTypes = ['jpg','jpeg','png'];

        if (!in_array($extension,$allowedTypes)) {
            $message = "Only JPG, JPEG and PNG images are allowed.";
            $messageType = "error";
        } elseif ($_FILES['assetImage']['size'] > 5000000) {
            $message = "Image size must be below 5MB.";
            $messageType = "error";
        } else {

            $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/','_',$originalName);
            $targetPath = $uploadFolder . $fileName;

            if (!move_uploaded_file($_FILES['assetImage']['tmp_name'],$targetPath)) {
                $message = "Unable to upload image.";
                $messageType = "error";
            } else {
                $imageURL = "uploads/assets/" . $fileName;
            }
        }

        if ($message == '') {

            $assetID = generateAssetID($conn);
            $verificationID = generateVerificationID($conn);

            $assetNameSafe = $conn->real_escape_string($assetName);
            $categorySafe = $conn->real_escape_string($categoryID);
            $conditionSafe = $conn->real_escape_string($assetCondition);
            $descriptionSafe = $conn->real_escape_string($description);
            $imageSafe = $conn->real_escape_string($imageURL);

            $conn->begin_transaction();

            try {

                /* Save Asset */
                $conn->query("
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
                    createdDate,
                    updatedDate,
                    verificationStatus
                )
                VALUES
                (
                    '$assetID',
                    '$userID',
                    '$categorySafe',
                    '$assetNameSafe',
                    '$descriptionSafe',
                    '$conditionSafe',
                    '$imageSafe',
                    'Unavailable',
                    'Pending Review',
                    NOW(),
                    NOW(),
                    'Pending'
                )
                ");

                /* Create Verification Record */
                $conn->query("
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
                ");

                /* Get Member Name */
                $memberResult = $conn->query("
                SELECT name
                FROM user
                WHERE userID='$userID'
                LIMIT 1
                ");

                $memberName = "Member";

                if ($memberResult && $memberResult->num_rows > 0) {
                    $memberData = $memberResult->fetch_assoc();
                    $memberName = $memberData['name'];
                }

                /* Send Notification To Manager */
                $managerResult = $conn->query("
                SELECT userID
                FROM user
                WHERE role='Manager'
                AND status='Active'
                ");

                if ($managerResult) {

                    $managerTitle = "New Asset for Review";

                    $managerContent =
                        $memberName .
                        " (" . $userID . ") added the asset " .
                        $assetName .
                        " (" . $assetID . ") for review.";

                    $safeManagerTitle =
                        $conn->real_escape_string($managerTitle);

                    $safeManagerContent =
                        $conn->real_escape_string($managerContent);

                    while ($manager = $managerResult->fetch_assoc()) {

                        $managerID =
                            $conn->real_escape_string($manager['userID']);

                        $notificationID =
                            generateNotificationID($conn);

                        if (!$conn->query("
                        INSERT INTO notification
                        (notificationID,userID,title,content,status,createdDate)
                        VALUES
                        ('$notificationID','$managerID','$safeManagerTitle',
                         '$safeManagerContent','Unread',NOW())
                        ")) {
                            throw new Exception("Unable to send manager notification.");
                        }
                    }
                }

                /* Send Notification To Member */
                $memberNotificationID =
                    generateNotificationID($conn);

                $memberTitle =
                    "Asset Submitted Successfully";

                $memberContent =
                    $assetName .
                    " (" . $assetID . ") has been submitted successfully and is ready for verification.";

                $safeMemberTitle =
                    $conn->real_escape_string($memberTitle);

                $safeMemberContent =
                    $conn->real_escape_string($memberContent);

                if (!$conn->query("
                INSERT INTO notification
                (notificationID,userID,title,content,status,createdDate)
                VALUES
                ('$memberNotificationID','$userID','$safeMemberTitle',
                 '$safeMemberContent','Unread',NOW())
                ")) {
                    throw new Exception("Unable to send member notification.");
                }

                $conn->commit();

                header("Location: member_requests.php");
                exit();

            } catch (Exception $e) {

                $conn->rollback();

                if ($imageURL != '' && file_exists("../" . $imageURL)) {
                    unlink("../" . $imageURL);
                }

                $message = "Unable to submit asset.";
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

    <title>List New Asset - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_new_style.css">

    <style>
        .required-star{color:#d93025}
        .required-note{margin:-10px 0 16px;font-size:10px;color:#777}
    </style>
</head>

<body class="role-member">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body">

            <div class="asset-form-header">

                <div>
                    <h1>List New Asset</h1>
                    <p>Lend your reusable asset with the APU community.</p>
                </div>

                <a href="member_marketplace.php" class="asset-back-btn">
                    ← Back to Marketplace
                </a>

            </div>

            <?php if ($message != ''): ?>

                <div class="asset-form-alert <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="asset-form-card">

                <h2>Asset Information</h2>

                <p class="required-note">
                    <span class="required-star">*</span> All fields are required.
                </p>

                <div class="asset-form-grid">

                    <div class="asset-form-group">

                        <label>
                            Asset Name <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            name="assetName"
                            placeholder="Enter asset name"
                            value="<?php echo htmlspecialchars($_POST['assetName'] ?? ''); ?>"
                            required
                        >

                    </div>

                    <div class="asset-form-group">

                        <label>
                            Category <span class="required-star">*</span>
                        </label>

                        <select name="categoryID" required>

                            <option value="">Select Category</option>

                            <?php if ($categoryResult && $categoryResult->num_rows > 0): ?>

                                <?php while ($category = $categoryResult->fetch_assoc()): ?>

                                    <option
                                        value="<?php echo htmlspecialchars($category['categoryID']); ?>"
                                        <?php echo (($_POST['categoryID'] ?? '') == $category['categoryID']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($category['categoryName']); ?>
                                    </option>

                                <?php endwhile; ?>

                            <?php endif; ?>

                        </select>

                    </div>

                    <div class="asset-form-group">

                        <label>
                            Condition <span class="required-star">*</span>
                        </label>

                        <select name="assetCondition" required>

                            <option value="">Select Condition</option>

                            <?php
                            $conditions = ['New','Like New','Good','Fair'];
                            foreach ($conditions as $condition):
                            ?>

                                <option
                                    value="<?php echo $condition; ?>"
                                    <?php echo (($_POST['assetCondition'] ?? '') == $condition) ? 'selected' : ''; ?>
                                >
                                    <?php echo $condition; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="asset-form-group asset-description-group">

                    <label>
                        Description <span class="required-star">*</span>
                    </label>

                    <textarea
                        name="description"
                        rows="5"
                        placeholder="Describe your asset"
                        required
                    ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                </div>

                <div class="asset-image-section">

                    <label>
                        Asset Image <span class="required-star">*</span>
                    </label>

                    <div id="newImageArea" style="display:none;">

                        <p class="current-image-text">Image Preview</p>

                        <div class="asset-current-image">
                            <img id="newImagePreview" src="" alt="Asset Image Preview">
                        </div>

                    </div>

                    <div class="asset-upload-box">

                        <input
                            type="file"
                            name="assetImage"
                            id="assetImage"
                            accept=".jpg,.jpeg,.png"
                            onchange="previewNewImage()"
                            required
                        >

                        <p>Upload one JPG, JPEG or PNG image. Maximum size: 5MB.</p>

                        <button
                            type="button"
                            id="cancelImageBtn"
                            class="cancel-image-btn"
                            onclick="cancelNewImage()"
                            style="display:none;"
                        >
                            Cancel Image
                        </button>

                    </div>

                </div>
                <div class="asset-form-actions">

                    <a href="member_marketplace.php" class="asset-cancel-btn">
                        Cancel
                    </a>

                    <button type="submit" name="submitAsset" class="asset-submit-btn">
                        Submit for Approval →
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