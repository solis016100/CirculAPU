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
    $badgeName = "";
    $badgeDescription = "";
    $badgeRequirement = "";
    $badgeIcon = "";

    $nextBadgeID = "AB001";

    $idQuery = "
        SELECT achieveBadgeID
        FROM achievementbadges
        ORDER BY CAST(SUBSTRING(achieveBadgeID, 3) AS UNSIGNED) DESC
        LIMIT 1
    ";

    $idResult = mysqli_query($conn, $idQuery);

    if ($idResult && mysqli_num_rows($idResult) > 0) {
        $lastBadge = mysqli_fetch_assoc($idResult);
        $lastID = $lastBadge['achieveBadgeID'];

        // Extract number after BADGE
        if (preg_match('/AB(\d+)/', $lastID, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
            $nextBadgeID = "AB" . str_pad(
                $nextNumber,
                3,
                "0",
                STR_PAD_LEFT
            );
        }
    }


    // Create Achievement Badge
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Keep entered values
        $badgeName = trim($_POST['badgeName'] ?? '');
        $badgeDescription = trim($_POST['badgeDescription'] ?? '');
        $badgeRequirement = trim($_POST['badgeRequirement'] ?? '');

        // Validate Badge Name, description, requirement & image
        if ($badgeName === '') {
            $errorMessage = "Please enter the badge name.";
        }elseif ($badgeDescription === '') {
            $errorMessage = "Please enter the badge description.";
        }elseif ($badgeRequirement === '') {
            $errorMessage = "Please enter the badge requirement.";
        }elseif (!isset($_FILES['badgeIcon']) || $_FILES['badgeIcon']['error'] === UPLOAD_ERR_NO_FILE) {
            $errorMessage = "Please upload a badge icon.";
        }else {
            $file = $_FILES['badgeIcon'];
            $allowedTypes = [ 'image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            // Check upload error, file type & flie size
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = "Unable to upload the badge icon.";
            }elseif (!in_array($file['type'], $allowedTypes, true)) {
                $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
            }elseif ($file['size'] > $maxFileSize) {
                $errorMessage = "Badge icon must not exceed 5MB.";
            }
            else {
                $uploadDirectory = "../assets/images/";
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }
                
                // Generate Safe File Name
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $safeName = preg_replace('/[^A-Za-z0-9_-]/','_',strtolower($badgeName));
                $fileName = "badge_" . $safeName . "_" . time() . "." . $extension;
                $targetPath = $uploadDirectory . $fileName;

                // Move Uploaded File
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errorMessage = "Unable to save the badge icon.";
                }else {
                    // Insert Badge Into Database
                    $insertQuery = "
                        INSERT INTO achievementbadges
                        (
                            achieveBadgeID,
                            achieveBadgeName,
                            achieveBadgeDescription,
                            achieveBadgeRequirement,
                            achieveBadgeIcon
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $stmt = mysqli_prepare($conn, $insertQuery);


                    if($stmt) {
                        mysqli_stmt_bind_param(
                            $stmt,
                            "sssss",
                            $nextBadgeID,
                            $badgeName,
                            $badgeDescription,
                            $badgeRequirement,
                            $fileName
                        );

                        if (mysqli_stmt_execute($stmt)) {
                            $successMessage = "Achievement badge created successfully.";

                            // Clear form
                            $badgeName = "";
                            $badgeDescription = "";
                            $badgeRequirement = "";

                            // Generate next ID
                            $nextNumber = (int)substr($nextBadgeID, 2) + 1;

                            $nextBadgeID = "AB" . str_pad(
                                $nextNumber,
                                3,
                                "0",
                                STR_PAD_LEFT
                            );
                        }else {
                            // Remove uploaded image if database insert fails
                            if (file_exists($targetPath)) {
                                unlink($targetPath);
                            }

                            $errorMessage = "Unable to create the achievement badge.";
                        }

                        mysqli_stmt_close($stmt);
                    }else {
                        // Remove uploaded image if statement cannot be prepared
                        if (file_exists($targetPath)) {
                            unlink($targetPath);
                        }

                        $errorMessage = "Unable to prepare the database query.";
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

                        <!-- Badge Requirement -->
                        <div class="auditor-management-edit-row">
                            <label for="badgeRequirement">
                                Requirement
                                <span class="required">*</span>
                            </label>
                            <textarea id="badgeRequirement" name="badgeRequirement" rows="4" maxlength="300" placeholder="Enter requirement to earn this badge" required><?php echo htmlspecialchars($badgeRequirement); ?></textarea>
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
                    <div class="mission-create-actions">
                        <!-- Back -->
                        <a href="auditor_achievement_badges_management.php" class="mission-back-btn">
                            Back
                        </a>

                        <!-- Clear -->
                        <button type="reset" class="auditor-management-detail-action">
                            Clear
                        </button>

                        <!-- Create -->
                        <button type="submit" class="auditor-management-detail-action mission-create-btn">
                            Create Badge
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>