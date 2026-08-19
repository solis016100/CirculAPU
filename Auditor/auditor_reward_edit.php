<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['rewardID'] ?? $_GET['rewardID'] ?? '';
    $from = $_GET['from'] ?? 'management';

    $returnParams = $_GET;
    unset($returnParams['rewardID'], $returnParams['from']);
    $returnQuery = http_build_query($returnParams);

    if (!$id) {
        header("Location: auditor_rewards_management.php");
        exit();
    }

    $errorMessage = "";
    $successMessage = "";

    // Get reward record
    $stmt = $conn->prepare("SELECT rewardID, rewardName, rewardDescription, rewardImage, ecoPointsCost, quantity, availability FROM rewards WHERE rewardID = ?");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $reward = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$reward) {
        header("Location: auditor_rewards_management.php");
        exit();
    }

    // Update Reward
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rewardName = trim($_POST['rewardName'] ?? '');
        $rewardDescription = trim($_POST['rewardDescription'] ?? '');
        $ecoPointsCost = trim($_POST['ecoPointsCost'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $availability = trim($_POST['availability'] ?? '');

        if ($rewardName === '') {
            $errorMessage = "Please enter the reward name.";
        }elseif ($rewardDescription === '') {
            $errorMessage = "Please enter the reward description.";
        }elseif ($ecoPointsCost === '') {
            $errorMessage = "Please enter the EcoPoints cost.";
        }elseif (!ctype_digit($ecoPointsCost) || (int)$ecoPointsCost < 0) {
            $errorMessage = "EcoPoints cost must be a valid whole number.";
        }elseif ($quantity === '') {
            $errorMessage = "Please enter the quantity.";
        }elseif (!ctype_digit($quantity) || (int)$quantity < 0) {
            $errorMessage = "Quantity must be a valid whole number.";
        }elseif (!in_array($availability, ['Available', 'Unavailable'])) {
            $errorMessage = "Please select a valid availability.";
        }else {
            // Check duplicate reward name
            $check = $conn->prepare("SELECT rewardID FROM rewards WHERE LOWER(TRIM(rewardName)) = LOWER(TRIM(?)) AND rewardID != ? LIMIT 1");

            $check->bind_param("ss", $rewardName, $id);

            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $errorMessage = "This reward name already exists. Please enter a different name.";
            }else {
                $oldImage = $reward['rewardImage'];
                $image = $oldImage;
                $newImageUploaded = false;

                // Change reward image
                if (isset($_FILES['rewardImage']) && $_FILES['rewardImage']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['rewardImage'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $errorMessage ="Unable to upload the reward image.";
                    }elseif (!in_array($file['type'], $allowedTypes)) {
                        $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
                    }elseif ($file['size'] > 5 * 1024 * 1024) {
                        $errorMessage = "Reward image must not exceed 5MB.";
                    }else {
                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($rewardName));
                        $image = "reward_" . $safeName . "_" . time() . "." . $extension;
                        $targetPath = "../assets/images/" . $image;

                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $newImageUploaded = true;
                        }else {
                            $errorMessage = "Unable to save the reward image.";
                        }
                    }
                }

                // Update database
                if ($errorMessage === '') {
                    $update = $conn->prepare("UPDATE rewards SET rewardName = ?, rewardDescription = ?, rewardImage = ?, ecoPointsCost = ?, quantity = ?, availability = ? WHERE rewardID = ?");

                    $ecoPointsCostInt = (int)$ecoPointsCost;
                    $quantityInt = (int)$quantity;

                    $update->bind_param("sssiiss", $rewardName, $rewardDescription, $image, $ecoPointsCostInt, $quantityInt, $availability, $id);

                    if ($update->execute()) {
                        // Delete ole image
                        if ($newImageUploaded && !empty($oldImage) && $oldImage !== $image && file_exists("../assets/images/" . $oldImage)) {
                            unlink("../assets/images/" . $oldImage);
                        }

                        // Create System Log
                        $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)), 0) + 1 AS nextID FROM systemlog");

                        $row = $result->fetch_assoc();

                        $logID = "LOG" . str_pad($row['nextID'], 3, "0", STR_PAD_LEFT);

                        $logDescription = "Updated reward: " . $rewardName;

                        $log = $conn->prepare("INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES(?, ?, 'Update', 'Reward', ?, ?)");

                        $log->bind_param("ssss", $logID, $_SESSION['user_id'], $id, $logDescription);

                        $log->execute();
                        $log->close();

                        $update->close();

                        // Success Updation
                        header("Location: auditor_reward_view.php?rewardID=" . urlencode($id) . "&updated=1");

                        exit();

                    }else {
                        // Remove newly uploaded image
                        if ($newImageUploaded && file_exists("../assets/images/" . $image)) {
                            unlink("../assets/images/" . $image);}

                        $errorMessage = "Unable to update the reward.";
                    }
                    $update->close();
                }
            }
            $check->close();
        }

        // Keep Entered Values
        $reward['rewardName'] = $rewardName;
        $reward['rewardDescription'] = $rewardDescription;
        $reward['ecoPointsCost'] = $ecoPointsCost;
        $reward['quantity'] = $quantity;
        $reward['availability'] = $availability;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Edit Reward Details - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>
            <main class="page-body auditor-management-edit-page">
                <div class="breadcrumb">
                    <a href="auditor_rewards_management.php">
                        Reward Management
                    </a>
                    &gt;
                    Edit Reward Details
                </div>

                <h1>Edit Reward Details</h1>

                <!-- Error Message -->
                <?php if ($errorMessage): ?>
                    <div class="management-error-message">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <section class="auditor-management-edit-card">
                    <form method="POST" enctype="multipart/form-data" class="auditor-management-edit-form">
                        <!-- Reward ID -->
                        <div class="auditor-management-edit-row">
                            <label>Reward ID</label>
                            <input value="<?php echo htmlspecialchars($reward['rewardID']); ?>" readonly>
                        </div>

                        <!-- Reward Name -->
                        <div class="auditor-management-edit-row">
                            <label>Reward Name</label>
                            <input type="text" name="rewardName" value="<?php echo htmlspecialchars($reward['rewardName']); ?>" required>
                        </div>

                        <!-- Reward Image -->
                        <div class="auditor-management-edit-row">
                            <label>Reward Image</label>
                            <div class="badge-edit-icon">
                                <img id="rewardImagePreview" src="../assets/images/<?php echo htmlspecialchars($reward['rewardImage']); ?>" alt="Reward Image">

                                <label for="rewardImage" class="auditor-management-view-image">
                                    Add/Change Image
                                </label>
                                <input type="file" id="rewardImage" name="rewardImage" accept=".jpg,.jpeg,.png,.webp" hidden>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="auditor-management-edit-row">
                            <label>Description</label>
                            <textarea name="rewardDescription" required><?php echo htmlspecialchars($reward['rewardDescription']); ?></textarea>
                        </div>

                        <!-- EcoPoints Cost -->
                        <div class="auditor-management-edit-row">
                            <label>EcoPoints Cost</label>
                            <input type="number" name="ecoPointsCost" min="0" value="<?php echo htmlspecialchars($reward['ecoPointsCost']); ?>" required>
                        </div>

                        <!-- Quantity -->
                        <div class="auditor-management-edit-row">
                            <label>Quantity</label>
                            <input type="number" name="quantity" min="0" value="<?php echo htmlspecialchars($reward['quantity']); ?>" required>
                        </div>

                        <!-- Availability -->
                        <div class="auditor-management-edit-row">
                            <label>Availability</label>
                            <select name="availability" required>
                                <option value="Available" <?php echo $reward['availability'] === 'Available' ? 'selected' : '';?>>
                                    Available
                                </option>

                                <option value="Unavailable" <?php echo $reward['availability'] === 'Unavailable' ? 'selected' : '';?>>
                                    Unavailable
                                </option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="auditor-management-edit-actions">
                            <a href="<?php if ($from === 'view') {echo 'auditor_reward_view.php?rewardID=' . urlencode($id); if ($returnQuery !== '') {echo '&' . htmlspecialchars($returnQuery);}}else {echo 'auditor_rewards_management.php'; if ($returnQuery !== '') {echo '?' . htmlspecialchars($returnQuery);}}?>">
                                Cancel
                            </a>

                            <button type="submit" name="save_changes">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('rewardImage').addEventListener('change', function () {
            if (this.files.length) {
                document.getElementById('rewardImagePreview').src =
                    URL.createObjectURL(this.files[0]);
            }
        });
    </script>

</body>
</html>
