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

    if (isset($_GET['created'])) {
        $successMessage = "Achievement badge created successfully.";
    }

    $nextBadgeID = "AB001";
    $idQuery = "SELECT achieveBadgeID FROM achievementbadges ORDER BY CAST(SUBSTRING(achieveBadgeID, 3) AS UNSIGNED) DESC LIMIT 1";

    $idResult = mysqli_query($conn, $idQuery);

    if ($idResult && mysqli_num_rows($idResult) > 0) {
        $lastBadge = mysqli_fetch_assoc($idResult);
        if (preg_match('/AB(\d+)/', $lastBadge['achieveBadgeID'], $matches)) {
            $nextBadgeID = "AB" . str_pad((int)$matches[1] + 1, 3, "0", STR_PAD_LEFT);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        }elseif ($requirementType === 'reward') {
            $badgeRequirement = "Redeem {$requirementValue} rewards";
        }else {
            $badgeRequirement = '';
        }

        $nameStmt = mysqli_prepare($conn, "SELECT achieveBadgeID FROM achievementbadges WHERE LOWER(TRIM(achieveBadgeName)) = LOWER(TRIM(?)) LIMIT 1");

        mysqli_stmt_bind_param($nameStmt, "s", $badgeName);

        mysqli_stmt_execute($nameStmt);

        $nameResult = mysqli_stmt_get_result($nameStmt);

        if (mysqli_num_rows($nameResult) > 0) {
            $duplicateMessage ="This badge name already exists. Please enter a different name.";
        }

        mysqli_stmt_close($nameStmt);

        if ($badgeName === '') {
            $errorMessage = "Please enter the badge name.";
        }elseif ($duplicateMessage !== '') {
        }elseif ($badgeDescription === '') {
            $errorMessage = "Please enter the badge description.";
        }elseif ($requirementType === '' || $requirementValue < 1 || $requirementValue > 10000) {
            $errorMessage = "Please select a valid requirement.";
        }elseif (!isset($_FILES['badgeIcon']) || $_FILES['badgeIcon']['error'] === UPLOAD_ERR_NO_FILE) {
            $errorMessage = "Please upload a badge icon.";
        }else {
            $file = $_FILES['badgeIcon'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

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

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($badgeName));
                $fileName = "badge_" . $safeName . "_" . time() . "." . $extension;
                $targetPath = $uploadDirectory . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errorMessage =  "Unable to save the badge icon.";
                }else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO achievementbadges (achieveBadgeID, achieveBadgeName, achieveBadgeDescription, achieveBadgeRequirement, achieveBadgeIcon) VALUES (?, ?, ?, ?, ?)");

                    mysqli_stmt_bind_param($stmt, "sssss", $nextBadgeID, $badgeName, $badgeDescription, $badgeRequirement, $fileName);

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        $result = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(notificationID, 2) AS UNSIGNED)), 0) + 1 AS nextID FROM notification");

                        $row = mysqli_fetch_assoc($result);
                        $nextNotificationID = (int)$row['nextID'];

                        $members = mysqli_query($conn, "SELECT userID FROM user WHERE role = 'Member'");

                        while ($member = mysqli_fetch_assoc($members)) {
                            $notificationID = "N" . str_pad($nextNotificationID++, 3, "0", STR_PAD_LEFT);

                            $title = "New Achievement Badge!";
                            $content = "A new badge \"{$badgeName}\" is now available. Requirement: {$badgeRequirement}";
                            $status = "Unread";

                            $notification = mysqli_prepare($conn, "INSERT INTO notification(notificationID, userID, title, content, status) VALUES (?, ?, ?, ?, ?)");

                            mysqli_stmt_bind_param($notification, "sssss", $notificationID, $member['userID'], $title, $content, $status);

                            mysqli_stmt_execute($notification);
                            mysqli_stmt_close($notification);
                        }

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

                        header("Location: auditor_achievement_badges_create.php?created=1");
                        exit();
                    }else {
                        mysqli_stmt_close($stmt);

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

                 
                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                 
                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                 
                <form method="POST" enctype="multipart/form-data" id="badgeForm" class="content-card mission-create-card">
                    <div class="mission-create-body">
                         
                        <div class="auditor-management-edit-row">
                            <label for="badgeID">
                                Badge ID
                            </label>
                            <input type="text" id="badgeID" value="<?php echo htmlspecialchars($nextBadgeID); ?>" readonly>
                        </div>

                         
                        <div class="auditor-management-edit-row">
                            <label for="badgeName">
                                Badge Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="badgeName" name="badgeName" maxlength="100" placeholder="Enter badge name" value="<?php echo htmlspecialchars($badgeName); ?>" required>
                        </div>

                         
                        <div class="auditor-management-edit-row">
                            <label for="badgeDescription">
                                Description
                                <span class="required">*</span>
                            </label>
                            <textarea id="badgeDescription" name="badgeDescription" rows="5" maxlength="500"placeholder="Enter badge description"required><?php echo htmlspecialchars($badgeDescription); ?></textarea>
                        </div>

                         
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
                                <option value="reward">Rewards Redeemed</option>
                            </select>
                        </div>

                         
                        <div class="auditor-management-edit-row">
                            <label for="requirementValue">
                                Requirement Value
                                <span class="required">*</span>
                            </label>

                            <input type="number" id="requirementValue" name="requirementValue" min="1" max="10000" placeholder="Enter value" required>
                        </div>

                         
                        <div class="auditor-management-edit-row">
                            <label for="badgeRequirement">
                                Requirement
                            </label>

                            <input type="text" id="badgeRequirement" name="badgeRequirement" readonly required>
                        </div>

                         
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

                     
                    <div class="auditor-create-actions">
                         
                        <a href="auditor_achievement_badges_management.php" class="auditor-back-btn">
                            Back
                        </a>

                         
                        <button type="reset" class="auditor-management-detail-action">
                            <img src="../assets/icons/reset.png" alt="">
                            Clear
                        </button>

                         
                        <button type="button" id="createBadgeButton" class="auditor-management-detail-action auditor-create-btn">
                            <img src="../assets/icons/create.png" alt="">
                            Create Badge
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

     
    <div id="createBadgePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Achievement Badge Creation Confirmation</h2>
            <p>Are you sure you want to create this badge with these details?</p>

             
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
            }else if (type === 'reward'){
                badgeRequirement.value = `Redeem ${value} rewards`;
            }
        }
        requirementType.addEventListener('change', updateRequirement);
        requirementValue.addEventListener('input', updateRequirement);


        createBadgeButton.addEventListener('click', function () {
            if (!badgeForm.checkValidity()) {
                badgeForm.reportValidity();
                return;
            }


            const formData = new FormData();
            formData.append('checkBadgeName', '1');
            formData.append('badgeName', document.getElementById('badgeName').value);
            fetch('auditor_achievement_badges_create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {

                if (result.trim() === 'exists') {
                    duplicateBadgePopout.classList.add('show');
                    return;
                }


                document.getElementById('confirmBadgeID').textContent = document.getElementById('badgeID').value;
                document.getElementById('confirmBadgeName').textContent = document.getElementById('badgeName').value;
                document.getElementById('confirmBadgeDescription').textContent = document.getElementById('badgeDescription').value;
                document.getElementById('confirmBadgeRequirement').textContent = document.getElementById('badgeRequirement').value;


                const badgeIcon = document.getElementById('badgeIcon');
                const confirmBadgeIcon = document.getElementById('confirmBadgeIcon');
                if (badgeIcon.files.length > 0) {
                    confirmBadgeIcon.src = URL.createObjectURL(badgeIcon.files[0]);
                }else {
                    confirmBadgeIcon.removeAttribute('src');
                }


                createBadgePopout.classList.add('show');
            })
            .catch(error => {
                console.error(error);
            });
        });


        cancelCreateBadge.addEventListener('click', function () {
            createBadgePopout.classList.remove('show');
        });

        confirmCreateBadge.addEventListener('click', function () {
            badgeForm.submit();
        });


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
