<?php
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit;
    }

    require_once '../db.php';
    $conn->set_charset("utf8mb4");

    $errorMessage = "";
    $duplicateMessage = "";
    $rewardName = "";
    $rewardDescription = "";
    $ecoPointsCost = "";
    $quantity = "";
    $availability = "Available";

    // Check if there is duplicate reward name
    if (isset($_POST['checkRewardName'])) {
        $rewardName = trim($_POST['rewardName'] ?? '');
        $nameStmt = mysqli_prepare($conn, "SELECT rewardID FROM rewards WHERE LOWER(TRIM(rewardName)) = LOWER(TRIM(?)) LIMIT 1");

        mysqli_stmt_bind_param($nameStmt, "s", $rewardName);
        mysqli_stmt_execute($nameStmt);

        $nameResult = mysqli_stmt_get_result($nameStmt);

        echo mysqli_num_rows($nameResult) > 0 ? "exists" : "available";

        mysqli_stmt_close($nameStmt);
        exit;
    }

    // Generate next reward id
    $nextRewardID = "R001";
    $idQuery = "SELECT rewardID FROM rewards ORDER BY CAST(SUBSTRING(rewardID, 2) AS UNSIGNED) DESC LIMIT 1";

    $idResult = mysqli_query($conn, $idQuery);

    if ($idResult && mysqli_num_rows($idResult) > 0) {
        $lastReward = mysqli_fetch_assoc($idResult);
        if (preg_match('/R(\d+)/', $lastReward['rewardID'], $matches)) {
            $nextRewardID = "R" . str_pad((int)$matches[1] + 1, 3, "0", STR_PAD_LEFT);
        }
    }

    // Create reward
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rewardName = trim($_POST['rewardName'] ?? '');
        $rewardDescription = trim($_POST['rewardDescription'] ?? '');
        $ecoPointsCost = trim($_POST['ecoPointsCost'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $availability = trim($_POST['availability'] ?? 'Available');

        // Check Duplicate Name
        $nameStmt = mysqli_prepare($conn, "SELECT rewardID FROM rewards WHERE LOWER(TRIM(rewardName)) = LOWER(TRIM(?)) LIMIT 1");

        mysqli_stmt_bind_param($nameStmt, "s", $rewardName);
        mysqli_stmt_execute($nameStmt);

        $nameResult = mysqli_stmt_get_result($nameStmt);

        if (mysqli_num_rows($nameResult) > 0) {
            $duplicateMessage = "This reward name already exists. Please enter a different name.";
        }

        mysqli_stmt_close($nameStmt);

        if ($rewardName === '') {
            $errorMessage = "Please enter the reward name.";
        }elseif ($duplicateMessage !== '') {
            // Stop creation
        }elseif ($rewardDescription === '') {
            $errorMessage = "Please enter the reward description.";
        }elseif ($ecoPointsCost === '') {
            $errorMessage = "Please enter the EcoPoints cost.";
        }elseif (!is_numeric($ecoPointsCost) || $ecoPointsCost < 0) {
            $errorMessage = "EcoPoints cost must be a valid number.";
        }elseif ($quantity === '') {
            $errorMessage = "Please enter the quantity.";
        }elseif (!ctype_digit($quantity)) {
            $errorMessage = "Quantity must be a whole number.";
        }elseif (!in_array($availability, ['Available', 'Unavailable'])) {
            $errorMessage = "Please select a valid availability.";
        }elseif (!isset($_FILES['rewardImage']) || $_FILES['rewardImage']['error'] === UPLOAD_ERR_NO_FILE){
            $errorMessage = "Please upload a reward image.";
        }else {
            $file = $_FILES['rewardImage'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = "Unable to upload the reward image.";
            }elseif (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
            }elseif ($file['size'] > 5 * 1024 * 1024) {
                $errorMessage = "Reward image must not exceed 5MB.";
            }else {
                $uploadDirectory = "../assets/images/";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                // Generate save file name
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($rewardName));

                $fileName = "reward_" . $safeName . "_" . time() . "." . $extension;

                $targetPath = $uploadDirectory . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errorMessage = "Unable to save the reward image.";
                }else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO rewards(rewardID, rewardName, rewardDescription, rewardImage, ecoPointsCost, quantity, availability) VALUES (?, ?, ?, ?, ?, ?, ?)");

                    mysqli_stmt_bind_param($stmt, "ssssdis", $nextRewardID, $rewardName, $rewardDescription, $fileName, $ecoPointsCost, $quantity, $availability);

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        // Create System Log
                        $logResult = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)) + 1 AS nextID FROM systemlog");
                        $logRow = mysqli_fetch_assoc($logResult);
                        $logID = "LOG" . str_pad($logRow['nextID'] ?? 1, 3, "0", STR_PAD_LEFT);
                        $logDescription = "Created reward: " . $rewardName;
                        $userID = $_SESSION['user_id'];
                        $logStmt = mysqli_prepare($conn, "INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Create',  'Reward', ?, ?)");

                        if ($logStmt) {
                            mysqli_stmt_bind_param($logStmt, "ssss", $logID, $userID, $nextRewardID, $logDescription);

                            mysqli_stmt_execute($logStmt);
                            mysqli_stmt_close($logStmt);
                        }

                        // Successfull Creation
                        header("Location: auditor_reward_create.php?created=1");
                        exit();
                    }else {
                        mysqli_stmt_close($stmt);
                        // Remove uploaded image if database insert fails
                        unlink($targetPath);
                        $errorMessage = "Unable to create the reward.";
                    }
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Auditor Create New Reward - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>
            <main class="page-body">
                <section class="management-heading">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="auditor_rewards_management.php">Rewards Management</a> &gt;
                        <span class="breadcrumb-current">Create New Reward</span>
                    </div>

                    <h1>Create New Reward</h1>
                    <p>Add a new reward to CirculAPU.</p>
                </section>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage);?>
                    </div>
                <?php endif; ?>

                <!-- Green bar showing Success Creation -->
                <?php if (isset($_GET['created'])): ?>
                    <div class="alert alert-success">
                        Reward created successfully.
                    </div>
                <?php endif; ?>

                <!-- Create reward -->
                <form method="POST" enctype="multipart/form-data" id="rewardForm" class="content-card mission-create-card">
                    <div class="mission-create-body">
                        <!-- Reward ID -->
                        <div class="auditor-management-edit-row">
                            <label for="rewardID">
                                Reward ID
                            </label>
                            <input type="text" id="rewardID" value="<?php echo htmlspecialchars($nextRewardID); ?>" readonly>
                        </div>

                        <!-- Reward Name -->
                        <div class="auditor-management-edit-row">
                            <label for="rewardName">
                                Reward Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="rewardName" name="rewardName" maxlength="100" placeholder="Enter reward name" value="<?php echo htmlspecialchars($rewardName); ?>" required>
                        </div>

                        <!-- Reward Description -->
                        <div class="auditor-management-edit-row">
                            <label for="rewardDescription">
                                Description
                                <span class="required">*</span>
                            </label>
                            <textarea id="rewardDescription" name="rewardDescription" rows="5" maxlength="500" placeholder="Enter reward description" required>
                                <?php echo htmlspecialchars($rewardDescription); ?>
                            </textarea>
                        </div>

                        <!-- EcoPoints Cost -->
                        <div class="auditor-management-edit-row">
                            <label for="ecoPointsCost">
                                EcoPoints Cost
                                <span class="required">*</span>
                            </label>
                            <input type="number" id="ecoPointsCost" name="ecoPointsCost" min="0" step="1" placeholder="Enter EcoPoints cost" value="<?php echo htmlspecialchars($ecoPointsCost); ?>" required>
                        </div>

                        <!-- Quantity -->
                        <div class="auditor-management-edit-row">
                            <label for="quantity">
                                Quantity
                                <span class="required">*</span>
                            </label>
                            <input type="number" id="quantity" name="quantity" min="0" step="1" placeholder="Enter reward quantity" value="<?php echo htmlspecialchars($quantity); ?>" required>
                        </div>

                        <!-- Availability -->
                        <div class="auditor-management-edit-row">
                            <label for="availability">
                                Availability
                                <span class="required">*</span>
                            </label>
                            <select id="availability" name="availability" required>
                                <option value="Available" <?php echo $availability === 'Available' ? 'selected' : '';?>>
                                    Available
                                </option>
                                <option value="Unavailable" <?php echo $availability === 'Unavailable' ? 'selected' : '';?>>
                                    Unavailable
                                </option>
                            </select>
                        </div>

                        <!-- Reward Image -->
                        <div class="auditor-management-edit-row">
                            <label for="rewardImage">
                                Reward Image
                                <span class="required">*</span>
                            </label>
                            <div>
                                <input type="file" id="rewardImage" name="rewardImage" accept=".jpg,.jpeg,.png,.webp" required>
                                <small>
                                    **Accepted formats: JPG, PNG, WEBP.
                                    Maximum size: 5MB.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="auditor-create-actions">
                        <!-- Back -->
                        <a href="auditor_rewards_management.php" class="auditor-back-btn">
                            Back
                        </a>

                        <!-- Clear -->
                        <button type="reset" class="auditor-management-detail-action">
                            <img src="../assets/icons/reset.png" alt="">
                            Clear
                        </button>

                        <!-- Create -->
                        <button type="button" id="createRewardButton" class="auditor-management-detail-action auditor-create-btn">
                            <img src="../assets/icons/create.png" alt="">
                            Create Reward
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Confirmation Pop Out Message -->
    <div id="createRewardPopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Reward Creation Confirmation</h2>
            <p>Are you sure you want to create this reward with these details?</p>

            <!-- Reward Summary Pop out -->
            <div class="auditor-summary">
                <div>
                    <strong>Reward ID</strong>
                    <span id="confirmRewardID">-</span>
                </div>

                <div>
                    <strong>Reward Name</strong>
                    <span id="confirmRewardName">-</span>
                </div>

                <div>
                    <strong>Description</strong>
                    <span id="confirmRewardDescription">-</span>
                </div>

                <div>
                    <strong>EcoPoints Cost</strong>
                    <span id="confirmEcoPointsCost">-</span>
                </div>

                <div>
                    <strong>Quantity</strong>
                    <span id="confirmQuantity">-</span>
                </div>

                <div>
                    <strong>Availability</strong>
                    <span id="confirmAvailability">-</span>
                </div>

                <div>
                    <strong>Reward Image</strong>
                    <span>
                        <img id="confirmRewardImage" src="" alt="Reward Image" class="confirm-item-image">
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="auditor-popout-actions">
                <button type="button" id="cancelCreateReward">
                    No
                </button>

                <button type="button" id="confirmCreateReward" class="auditor-popout-confirm">
                    Yes, Create
                </button>
            </div>
        </div>
    </div>

    <!-- Duplicate Reward Name -->
    <div id="duplicateRewardPopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Duplicate Reward Name</h2>
            <p>This reward name already exists. Please enter a different name.</p>

            <div class="auditor-popout-actions">
                <button type="button" id="duplicateRewardOK" class="auditor-popout-confirm">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Create another reward -->
    <?php if (isset($_GET['created'])): ?>
        <div id="createAnotherPopout" class="auditor-popout">
            <div class="auditor-popout-box">
                <h2>Reward Created Successfully</h2>
                <p>Do you want to create another reward?</p>

                <div class="auditor-popout-actions">
                    <button type="button" onclick="goToManagement()">
                        No
                    </button>

                    <button type="button" onclick="createAnother()">
                        Yes, Create Another
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Success Creation of Reward -->
    <div id="successPopout" class="auditor-popout">
        <div class="auditor-popout-box success-box">
            <h2>Reward Created Successfully!</h2>
            <p>Do you want to create another reward?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="location.href='auditor_reward_create.php?success=1'">
                    Yes
                </button>

                <button type="button" class="auditor-popout-confirm" onclick="location.href='auditor_rewards_management.php?created=1'">
                    No
                </button>
            </div>
        </div>
    </div>


    <script>
        const rewardForm = document.getElementById('rewardForm');
        const createRewardButton = document.getElementById('createRewardButton');
        const createRewardPopout = document.getElementById('createRewardPopout');
        const cancelCreateReward = document.getElementById('cancelCreateReward');
        const confirmCreateReward = document.getElementById('confirmCreateReward');
        const duplicateRewardPopout = document.getElementById('duplicateRewardPopout');
        const duplicateRewardOK = document.getElementById('duplicateRewardOK');

        // Confirmation Pop Out
        createRewardButton.addEventListener('click', function () {
            if (!rewardForm.checkValidity()) {
                rewardForm.reportValidity();
                return;
            }

            /* Check duplicate reward name */
            const formData = new FormData();
            formData.append('checkRewardName', '1');

            formData.append('rewardName', document.getElementById('rewardName').value);

            fetch('auditor_reward_create.php', {method: 'POST', body: formData}) . then(response => response.text()) . then(result => {
                /* Duplicate */
                if (result.trim() === 'exists') {
                    duplicateRewardPopout . classList . add('show');
                    return;
                }

                /* Display Reward Information */
                document.getElementById('confirmRewardID').textContent = document.getElementById('rewardID').value;
                document.getElementById('confirmRewardName').textContent = document.getElementById('rewardName').value;
                document.getElementById('confirmRewardDescription').textContent = document.getElementById('rewardDescription').value;
                document.getElementById('confirmEcoPointsCost').textContent = document.getElementById('ecoPointsCost').value;
                document.getElementById('confirmQuantity').textContent = document.getElementById('quantity').value;
                document.getElementById('confirmAvailability').textContent = document.getElementById('availability').value;

                /* Display Reward Image */
                const rewardImage = document.getElementById('rewardImage');
                const confirmRewardImage = document.getElementById('confirmRewardImage');
                if (rewardImage.files.length > 0) {
                    confirmRewardImage.src = URL.createObjectURL(rewardImage.files[0]);
                }else {
                    confirmRewardImage.removeAttribute('src');
                }

                /* Show Confirmation */
                createRewardPopout.classList.add('show');
            })

            .catch(error => {
                console.error(error);
            });
        }
    );

        // Action Buttons
        cancelCreateReward.addEventListener('click', function () {
            createRewardPopout . classList . remove('show');
        });

        confirmCreateReward.addEventListener('click', function () {
            rewardForm. submit();
        });

        // Duplicate Reward Name Popup
        duplicateRewardOK.addEventListener('click', function () {
            duplicateRewardPopout . classList . remove('show');
            document.getElementById('rewardName').focus();
        });

        function createAnother() {
            document.getElementById('createAnotherPopout').style.display = 'none';
        }

        function goToManagement() {
            window.location.href = 'auditor_rewards_management.php?created=1';
        }

        <?php if (isset($_GET['created'])): ?>
            document.getElementById(
                'createAnotherPopout'
            ).classList.add('show');
        <?php endif; ?>
    </script>
</body>
</html>