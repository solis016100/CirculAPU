<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: login.php");
        exit;
    }

    require_once '../db.php';
    $conn->set_charset("utf8mb4");

    $errorMessage = "";
    $successMessage = "";
    $duplicateMessage = "";
    $badgeName = "";
    $badgeDescription = "";
    $badgeRequirement = "";
    $requirementType = "";
    $requirementValue = "";

    // Check Duplicate Badge Name
    if (isset($_POST['checkBadgeName'])) {
        $badgeName = trim($_POST['badgeName'] ?? '');
        $nameStmt = mysqli_prepare($conn, "SELECT achieveBadgeID FROM achievementbadges WHERE LOWER(TRIM(achieveBadgeName)) = LOWER(TRIM(?)) LIMIT 1");

        mysqli_stmt_bind_param($nameStmt, "s", $badgeName);

        mysqli_stmt_execute($nameStmt);

        $nameResult = mysqli_stmt_get_result($nameStmt);

        echo mysqli_num_rows($nameResult) > 0 ? "exists" : "available";

        mysqli_stmt_close($nameStmt);
        exit;
    }

    // Successful Creation Message
    if (isset($_GET['created'])) {
        $successMessage = "Achievement badge created successfully.";
    }

    // Generate Next Badge ID
    $nextBadgeID = "AB001";
    $idQuery = "SELECT achieveBadgeID FROM achievementbadges ORDER BY CAST(SUBSTRING(achieveBadgeID, 3) AS UNSIGNED) DESC LIMIT 1";

    $idResult = mysqli_query($conn, $idQuery);

    if ($idResult && mysqli_num_rows($idResult) > 0) {
        $lastBadge = mysqli_fetch_assoc($idResult);
        if (preg_match('/AB(\d+)/', $lastBadge['achieveBadgeID'], $matches)) {
            $nextBadgeID = "AB" . str_pad((int)$matches[1] + 1, 3, "0", STR_PAD_LEFT);
        }
    }

    // Create Achievement Badge
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Keep entered values
        $badgeName = trim($_POST['badgeName'] ?? '');
        $badgeDescription = trim($_POST['badgeDescription'] ?? '');
        $requirementType = $_POST['requirementType'] ?? '';
        $requirementValue = (int)($_POST['requirementValue'] ?? 0);
        if ($requirementType === 'mission') {
            $badgeRequirement = "Complete {$requirementValue} missions";
        }elseif ($requirementType === 'ecopoints') {
            $badgeRequirement = "Reach {$requirementValue} EcoPoints";
        }elseif ($requirementType === 'asset') {
            $badgeRequirement = "Get {$requirementValue} approved assets";
        }elseif ($requirementType === 'borrowing') {
            $badgeRequirement = "Borrow {$requirementValue} assets";
        }elseif ($requirementType === 'reward') {
            $badgeRequirement = "Redeem {$requirementValue} rewards";
        }else {
            $badgeRequirement = '';
        }

        // Check if badge name exist in database
        $nameStmt = mysqli_prepare($conn, "SELECT achieveBadgeID FROM achievementbadges WHERE LOWER(TRIM(achieveBadgeName)) = LOWER(TRIM(?)) LIMIT 1");

        mysqli_stmt_bind_param($nameStmt, "s", $badgeName);

        mysqli_stmt_execute($nameStmt);

        $nameResult = mysqli_stmt_get_result($nameStmt);

        if (mysqli_num_rows($nameResult) > 0) {
            $duplicateMessage ="This badge name already exists. Please enter a different name.";
        }

        mysqli_stmt_close($nameStmt);

        // Validate Badge Name, description, requirement & image
        if ($badgeName === '') {
            $errorMessage = "Please enter the badge name.";
        }elseif ($duplicateMessage !== '') {
            // Duplicate badge name - stop badge creation
        }elseif ($badgeDescription === '') {
            $errorMessage = "Please enter the badge description.";
        }elseif ($requirementType === '' || $requirementValue < 1 || $requirementValue > 10000) {
            $errorMessage = "Please select a valid requirement.";
        }elseif (!isset($_FILES['badgeIcon']) || $_FILES['badgeIcon']['error'] === UPLOAD_ERR_NO_FILE) {
            $errorMessage = "Please upload a badge icon.";
        }else {
            $file = $_FILES['badgeIcon'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            // Check upload error, file type & file size
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = "Unable to upload the badge icon.";
            }elseif (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
            }elseif ($file['size'] > 5 * 1024 * 1024) {
                $errorMessage = "Badge icon must not exceed 5MB.";
            }else {
                $uploadDirectory = "../assets/images/";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                // Generate Safe File Name
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($badgeName));
                $fileName = "badge_" . $safeName . "_" . time() . "." . $extension;
                $targetPath = $uploadDirectory . $fileName;

                // Move Uploaded File
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errorMessage =  "Unable to save the badge icon.";
                }else {
                    // Insert Badge Into Database
                    $stmt = mysqli_prepare($conn, "INSERT INTO achievementbadges (achieveBadgeID, achieveBadgeName, achieveBadgeDescription, achieveBadgeRequirement, achieveBadgeIcon) VALUES (?, ?, ?, ?, ?)");

                    mysqli_stmt_bind_param($stmt, "sssss", $nextBadgeID, $badgeName, $badgeDescription, $badgeRequirement, $fileName);

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        // Create System Log
                        $logResult = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)) + 1 AS nextID FROM systemlog");

                        $logRow = mysqli_fetch_assoc($logResult);

                        $logID = "LOG" . str_pad($logRow['nextID'] ?? 1, 3, "0", STR_PAD_LEFT);

                        $logDescription = "Created achievement badge: " . $badgeName;

                        $userID =$_SESSION['user_id'];

                        $logStmt = mysqli_prepare($conn, "INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Create', 'Badge', ?, ?)");

                        if ($logStmt) {
                            mysqli_stmt_bind_param($logStmt, "ssss", $logID, $userID, $nextBadgeID, $logDescription);
                            mysqli_stmt_execute($logStmt);
                            mysqli_stmt_close($logStmt);
                        }

                        // Successful Creation
                        header("Location: auditor_achievement_badges_create.php?created=1");
                        exit();
                    }else {
                        mysqli_stmt_close($stmt);

                        // Remove uploaded image if database insert fails
                        unlink($targetPath);

                        $errorMessage = "Unable to create the achievement badge.";
                    }
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
    <title>Auditor Create Achievement Badge - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>
<body class="role-auditor">
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        <div class="main-content">
            <?php include '../header.php'; ?>

            <main class="page-body">
                <!-- Page Heading -->
                <section class="management-heading">
                    <div class="breadcrumb">
                        <a href="auditor_achievement_badges_management.php">
                            Achievement Badges Management
                        </a>
                        &gt;
                        <span class="breadcrumb-current">
                            Create New Achievement Badge
                        </span>
                    </div>
                    <h1>Create New Achievement Badge</h1>
                    <p>Add a new achievement badge to CirculAPU.</p>
                </section>

                <!-- Error Message -->
                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Success Message -->
                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- Create Badge Form -->
                <form method="POST" enctype="multipart/form-data" id="badgeForm" class="content-card mission-create-card">
                    <div class="mission-create-body">
                        <!-- Badge ID -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeID">
                                Badge ID
                            </label>
                            <input type="text" id="badgeID" value="<?php echo htmlspecialchars($nextBadgeID); ?>" readonly>
                        </div>

                        <!-- Badge Name -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeName">
                                Badge Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="badgeName" name="badgeName" maxlength="100" placeholder="Enter badge name" value="<?php echo htmlspecialchars($badgeName); ?>" required>
                        </div>

                        <!-- Badge Description -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeDescription">
                                Description
                                <span class="required">*</span>
                            </label>
                            <textarea id="badgeDescription" name="badgeDescription" rows="5" maxlength="500"placeholder="Enter badge description"required><?php echo htmlspecialchars($badgeDescription); ?></textarea>
                        </div>

                        <!-- Requirement Type -->
                        <div class="auditor-management-edit-row">
                            <label for="requirementType">
                                Requirement Type
                                <span class="required">*</span>
                            </label>

                            <select id="requirementType" name="requirementType" required>
                                <option value="">Select requirement type</option>
                                <option value="mission">Missions Completed</option>
                                <option value="ecopoints">EcoPoints Reached</option>
                                <option value="asset">Approved Assets</option>
                                <option value="borrowing">Assets Borrowed</option>
                                <option value="reward">Rewards Redeemed</option>
                            </select>
                        </div>

                        <!-- Requirement Value -->
                        <div class="auditor-management-edit-row">
                            <label for="requirementValue">
                                Requirement Value
                                <span class="required">*</span>
                            </label>

                            <input type="number" id="requirementValue" name="requirementValue" min="1" max="10000" placeholder="Enter value" required>
                        </div>

                        <!-- Generated Requirement -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeRequirement">
                                Requirement
                            </label>

                            <input type="text" id="badgeRequirement" name="badgeRequirement" readonly required>
                        </div>

                        <!-- Badge Icon -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeIcon">
                                Badge Icon
                                <span class="required">*</span>
                            </label>
                            <div>
                                <input type="file" id="badgeIcon" name="badgeIcon" accept=".jpg,.jpeg,.png,.webp" required>
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
                        <a href="auditor_achievement_badges_management.php" class="auditor-back-btn">
                            Back
                        </a>

                        <!-- Clear -->
                        <button type="reset" class="auditor-management-detail-action">
                            <img src="../assets/icons/reset.png" alt="">
                            Clear
                        </button>

                        <!-- Create -->
                        <button type="button" id="createBadgeButton" class="auditor-management-detail-action auditor-create-btn">
                            <img src="../assets/icons/create.png" alt="">
                            Create Badge
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Achievement Badge Creation Confirmation -->
    <div id="createBadgePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Achievement Badge Creation Confirmation</h2>
            <p>Are you sure you want to create this badge with these details?</p>

            <!-- Badge Summary -->
            <div class="auditor-summary">
                <div>
                    <strong>Badge ID</strong>
                    <span id="confirmBadgeID">-</span>
                </div>

                <div>
                    <strong>Badge Name</strong>
                    <span id="confirmBadgeName">-</span>
                </div>

                <div>
                    <strong>Description</strong>
                    <span id="confirmBadgeDescription">-</span>
                </div>

                <div>
                    <strong>Requirement</strong>
                    <span id="confirmBadgeRequirement">-</span>
                </div>

                <div>
                    <strong>Badge Icon</strong>
                    <span>
                        <img id="confirmBadgeIcon" src="" alt="Badge Icon" class="confirm-item-image">
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="auditor-popout-actions">
                <button type="button" id="cancelCreateBadge">
                    No
                </button>

                <button type="button" id="confirmCreateBadge" class="auditor-popout-confirm">
                    Yes, Create
                </button>
            </div>
        </div>
    </div>

    <!-- Duplicate Badge Name Pop Out -->
    <div id="duplicateBadgePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Duplicate Badge Name</h2>
            <p>This badge name already exists. Please enter a different name.</p>
            <div class="auditor-popout-actions">
                <button type="button" id="duplicateBadgeOK" class="auditor-popout-confirm">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Create Another Badge Pop Out -->
    <?php if (isset($_GET['created'])): ?>
        <div id="createAnotherPopout" class="auditor-popout">
            <div class="auditor-popout-box">
                <h2>Achievement Badge Created Successfully</h2>
                <p>Do you want to create another achievement badge?</p>
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

    <script>
        const requirementType = document.getElementById('requirementType');
        const requirementValue = document.getElementById('requirementValue');
        const badgeRequirement = document.getElementById('badgeRequirement');
        const badgeForm = document.getElementById('badgeForm');
        const createBadgeButton = document.getElementById('createBadgeButton');
        const createBadgePopout = document.getElementById('createBadgePopout');
        const cancelCreateBadge = document.getElementById('cancelCreateBadge');
        const confirmCreateBadge = document.getElementById('confirmCreateBadge');
        const duplicateBadgePopout = document.getElementById('duplicateBadgePopout');
        const duplicateBadgeOK = document.getElementById('duplicateBadgeOK');

        // Badge Requirement
        function updateRequirement(){
            const type = requirementType.value;
            const value = requirementValue.value;

            if (!type || !value) {
                badgeRequirement.value = '';
                return;
            }

            if (type === 'mission'){
                badgeRequirement.value = `Complete ${value} missions`;
            }else if (type === 'ecopoints'){
                badgeRequirement.value = `Reach ${value} EcoPoints`;
            }else if (type === 'asset'){
                badgeRequirement.value = `Get ${value} approved assets`;
            }else if (type === 'borrowing'){
                badgeRequirement.value = `Borrow ${value} assets`;
            }else if (type === 'reward'){
                badgeRequirement.value = `Redeem ${value} rewards`;
            }
        }
        requirementType.addEventListener('change', updateRequirement);
        requirementValue.addEventListener('input', updateRequirement);

        // Open confirmation popup
        createBadgeButton.addEventListener('click', function () {
            if (!badgeForm.checkValidity()) {
                badgeForm.reportValidity();
                return;
            }

            // Check Badge Name Before Showing Summary
            const formData = new FormData();
            formData.append('checkBadgeName', '1');
            formData.append('badgeName', document.getElementById('badgeName').value);
            fetch('auditor_achievement_badges_create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                // Duplicate Name
                if (result.trim() === 'exists') {
                    duplicateBadgePopout.classList.add('show');
                    return;
                }

                // Display Badge Information
                document.getElementById('confirmBadgeID').textContent = document.getElementById('badgeID').value;
                document.getElementById('confirmBadgeName').textContent = document.getElementById('badgeName').value;
                document.getElementById('confirmBadgeDescription').textContent = document.getElementById('badgeDescription').value;
                document.getElementById('confirmBadgeRequirement').textContent = document.getElementById('badgeRequirement').value;

                // Display Badge Icon
                const badgeIcon = document.getElementById('badgeIcon');
                const confirmBadgeIcon = document.getElementById('confirmBadgeIcon');
                if (badgeIcon.files.length > 0) {
                    confirmBadgeIcon.src = URL.createObjectURL(badgeIcon.files[0]);
                }else {
                    confirmBadgeIcon.removeAttribute('src');
                }

                // Show Confirmation Popup pnly if Name is Available
                createBadgePopout.classList.add('show');
            })
            .catch(error => {
                console.error(error);
            });
        });

        // Action Buttons
        cancelCreateBadge.addEventListener('click', function () {
            createBadgePopout.classList.remove('show');
        });

        confirmCreateBadge.addEventListener('click', function () {
            badgeForm.submit();
        });

        // Duplicate Badge Name Popup
        duplicateBadgeOK.addEventListener('click', function () {
            duplicateBadgePopout.classList.remove('show');
            document.getElementById('badgeName').focus();
        });

        function createAnother() {
            document.getElementById('createAnotherPopout').style.display = 'none';
        }

        function goToManagement() {
            window.location.href =
                'auditor_achievement_badges_management.php?created=1';
        }

        <?php if (isset($_GET['created'])): ?>
            document.getElementById('createAnotherPopout').classList.add('show');
        <?php endif; ?>
    </script>

</body>
</html>
