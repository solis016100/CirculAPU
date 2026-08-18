<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['achieveBadgeID'] ?? $_GET['badgeID'] ?? '';
    $from = $_GET['from'] ?? 'management';

    if (!$id) {
        header("Location: auditor_achievement_badges_management.php");
        exit();
    }

    // Change Badge Icon
    if (isset($_POST['save_changes'], $_FILES['badgeIcon']) && $_FILES['badgeIcon']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['badgeIcon'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = "badge_" . $id . "_" . time() . "." . $ext;
            $target = "../assets/images/" . $fileName;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $update = $conn->prepare("UPDATE achievementbadges SET achieveBadgeIcon = ? WHERE achieveBadgeID = ?");

                $update->bind_param("ss", $fileName, $id);
                $update->execute();
                $update->close();

                header("Location: auditor_achievement_badges_view.php?achieveBadgeID=" . urlencode($id) . "&updated=1");

                exit();
            }
        }
    }

    /* Get achievement badge record */
    $stmt = $conn->prepare("SELECT achieveBadgeID, achieveBadgeName, achieveBadgeDescription, achieveBadgeRequirement, achieveBadgeIcon FROM achievementbadges WHERE achieveBadgeID = ?");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $badge = $stmt->get_result()->fetch_assoc();

    if (!$badge) {
        header("Location: auditor_achievement_badges_management.php");
        exit();
    }

    $requirementType = '';
    $requirementValue = '';
    if (preg_match('/Complete (\d+) missions?/i', $badge['achieveBadgeRequirement'], $m)) {
        $requirementType = 'mission';
        $requirementValue = $m[1];
    }elseif (preg_match('/Reach (\d+) EcoPoints/i', $badge['achieveBadgeRequirement'], $m)) {
        $requirementType = 'ecopoints';
        $requirementValue = $m[1];
    }elseif (preg_match('/Get (\d+) approved assets?/i', $badge['achieveBadgeRequirement'], $m)) {
        $requirementType = 'asset';
        $requirementValue = $m[1];
    }elseif (preg_match('/Borrow (\d+) assets?/i', $badge['achieveBadgeRequirement'], $m)) {
        $requirementType = 'borrowing';
        $requirementValue = $m[1];
    }elseif (preg_match('/Redeem (\d+) rewards?/i', $badge['achieveBadgeRequirement'], $m)) {
        $requirementType = 'reward';
        $requirementValue = $m[1];
    }

    $errorMessage = "";
    $successMessage = "";

    /* Update Achievement Badge */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $badgeName = trim($_POST['badgeName'] ?? '');
        $badgeDescription = trim($_POST['badgeDescription'] ?? '');
        $requirementType = $_POST['requirementType'] ?? '';
        $requirementValue = (int)($_POST['requirementValue'] ?? 0);

        if ($requirementType === 'mission') {
            $badgeRequirement = $requirementValue == 1 ? "Complete 1 mission" : "Complete {$requirementValue} missions";
        }elseif ($requirementType === 'ecopoints') {
            $badgeRequirement = "Reach {$requirementValue} EcoPoints";
        }elseif ($requirementType === 'asset') {
            $badgeRequirement = $requirementValue == 1 ? "Get 1 approved asset" : "Get {$requirementValue} approved assets";
        }elseif ($requirementType === 'borrowing') {
            $badgeRequirement = $requirementValue == 1 ? "Borrow 1 asset" : "Borrow {$requirementValue} assets";
        }elseif ($requirementType === 'reward') {
            $badgeRequirement = $requirementValue == 1 ? "Redeem 1 reward" : "Redeem {$requirementValue} rewards";
        }else {
            $badgeRequirement = '';
        }

        if ($badgeName === '') {
            $errorMessage = "Please enter the badge name.";
        }elseif ($badgeDescription === '') {
            $errorMessage = "Please enter the badge description.";
        }elseif ($requirementType === '' || $requirementValue < 1 || $requirementValue > 10000){
            $errorMessage = "Please enter a valid requirement.";
        }else {
            /* Check Duplicate Badge Name */
            $check = $conn->prepare("SELECT achieveBadgeID FROM achievementbadges WHERE LOWER(TRIM(achieveBadgeName)) = LOWER(TRIM(?)) AND achieveBadgeID != ? LIMIT 1");

            $check->bind_param("ss", $badgeName, $id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $errorMessage = "This badge name already exists. Please enter a different name.";
            }else {
                $icon = $badge['achieveBadgeIcon'];
                $oldIcon = $icon;

                /* Change Badge Icon */
                if (isset($_FILES['badgeIcon']) && $_FILES['badgeIcon']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['badgeIcon'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $errorMessage = "Unable to upload the badge icon.";
                    }elseif (!in_array($file['type'], $allowedTypes)) {
                        $errorMessage = "Only JPG, PNG, and WEBP images are allowed.";
                    }elseif ($file['size'] > 5 * 1024 * 1024) {
                        $errorMessage = "Badge icon must not exceed 5MB.";
                    }else {
                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($badgeName));
                        $icon = "badge_" . $safeName . "_" . time() . "." . $extension;
                        $targetPath = "../assets/images/" . $icon;
                        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                            $errorMessage = "Unable to save the badge icon.";
                        }
                    }
                }

                /* Update Database */
                if ($errorMessage === "") {
                    $update = $conn->prepare("UPDATE achievementbadges SET achieveBadgeName = ?, achieveBadgeDescription = ?, achieveBadgeRequirement = ?, achieveBadgeIcon = ? WHERE achieveBadgeID = ?");

                    $update->bind_param("sssss", $badgeName, $badgeDescription, $badgeRequirement, $icon, $id);

                    if ($update->execute()) {
                        /* Delete Old Icon */
                        if ($icon != $oldIcon && !empty($oldIcon) && file_exists("../assets/images/" . $oldIcon)) {
                            unlink("../assets/images/" . $oldIcon);
                        }

                        /* Create System Log */
                        $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)),  0) + 1 AS nextID FROM systemlog");
                        $row = $result->fetch_assoc();
                        $logID = "LOG" . str_pad($row['nextID'], 3, "0", STR_PAD_LEFT);
                        $logDescription = "Updated achievement badge: " . $badgeName;
                        $log = $conn->prepare("INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Update', 'Badge', ?, ?)");
                        $log->bind_param("ssss", $logID, $_SESSION['user_id'], $id, $logDescription);
                        $log->execute();

                        /* Successful Update */
                        if ($from === 'management') {
                            header("Location: auditor_achievement_badges_management.php?updated=1");
                        }else {
                            header("Location: auditor_achievement_badges_view.php?achieveBadgeID=" . urlencode($id) . "&updated=1");
                        }

                        exit();
                    }else {
                        if ($icon != $oldIcon && file_exists("../assets/images/" . $icon)) {
                            unlink("../assets/images/" . $icon);
                        }
                        $errorMessage = "Unable to update the achievement badge.";
                    }
                }
            }
            $check->close();
        }
    }

    /* Keep Entered Values */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $badge['achieveBadgeName'] = $badgeName;
        $badge['achieveBadgeDescription'] = $badgeDescription;
        $badge['achieveBadgeRequirement'] = $badgeRequirement;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Edit Achievement Badge Details - CirculAPU</title>
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
                    <a href="auditor_missions_management.php">
                        Achievement Badges Management
                    </a>
                    &gt;
                    Edit Achievement Badge Details
                </div>

                <h1>Edit Achievement Badge Details</h1>

                <!-- Error Message -->
                <?php if ($errorMessage): ?>
                    <div class="management-error-message">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <section class="auditor-management-edit-card">
                    <form method="POST" enctype="multipart/form-data" class="auditor-management-edit-form">
                        <div class="auditor-management-edit-row">
                            <label>Badge ID</label>
                            <input value="<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>" readonly>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Badge Name</label>
                            <input type="text" name="badgeName" value="<?php echo htmlspecialchars($badge['achieveBadgeName']); ?>" required>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Badge Icon</label>
                            <div class="badge-edit-icon">
                                <img id="badgeIconPreview" src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>" alt="Badge Icon">
                                <label for="badgeIcon" class="auditor-management-view-image">
                                    Add/Change Icon
                                </label>
                                <input type="file" id="badgeIcon" name="badgeIcon" accept=".jpg,.jpeg,.png,.webp" hidden>
                            </div>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label>Description</label>
                            <textarea name="badgeDescription" required><?php echo htmlspecialchars($badge['achieveBadgeDescription']); ?></textarea>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label for="requirementType">
                                Requirement Type
                            </label>

                            <select id="requirementType" name="requirementType" required>
                                <option value="">Select requirement type</option>
                                <option value="mission" <?= $requirementType === 'mission' ? 'selected' : '' ?>>Missions Completed</option>
                                <option value="ecopoints" <?= $requirementType === 'ecopoints' ? 'selected' : '' ?>>EcoPoints Reached</option>
                                <option value="asset" <?= $requirementType === 'asset' ? 'selected' : '' ?>>Approved Assets</option>
                                <option value="borrowing" <?= $requirementType === 'borrowing' ? 'selected' : '' ?>>Assets Borrowed</option>
                                <option value="reward" <?= $requirementType === 'reward' ? 'selected' : '' ?>>Rewards Redeemed</option>
                            </select>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label for="requirementValue">
                                Requirement Value
                            </label>
                            <input type="number" id="requirementValue" name="requirementValue" min="1" max="10000" value="<?= htmlspecialchars($requirementValue) ?>" placeholder="Enter value" required>
                        </div>

                        <div class="auditor-management-edit-row">
                            <label for="badgeRequirement">
                                Requirement
                            </label>

                            <input type="text" id="badgeRequirement" name="badgeRequirement" value="<?= htmlspecialchars($badge['achieveBadgeRequirement']) ?>" readonly required>
                        </div>

                        <div class="auditor-management-edit-actions">
                            <a href="<?php echo isset($_GET['from']) && $_GET['from'] === 'management' ? 'auditor_achievement_badges_management.php' : 'auditor_achievement_badges_view.php?achieveBadgeID=' . urlencode($id); ?>" class="auditor-management-edit-cancel">
                                Cancel
                            </a>

                            <button type="submit">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>

    <script>
        const requirementType = document.getElementById('requirementType');
        const requirementValue = document.getElementById('requirementValue');
        const badgeRequirement = document.getElementById('badgeRequirement');

        function updateRequirement() {
            const type = requirementType.value;
            const value = requirementValue.value;

            if (!type || !value) {
                badgeRequirement.value = '';
                return;
            }

            if (type === 'mission') {
                badgeRequirement.value = value == 1
                    ? 'Complete 1 mission'
                    : `Complete ${value} missions`;
            }else if (type === 'ecopoints') {
                badgeRequirement.value = `Reach ${value} EcoPoints`;
            }else if (type === 'asset') {
                badgeRequirement.value = value == 1
                    ? 'Get 1 approved asset'
                    : `Get ${value} approved assets`;
            }else if (type === 'borrowing') {
                badgeRequirement.value = value == 1
                    ? 'Borrow 1 asset'
                    : `Borrow ${value} assets`;
            }else if (type === 'reward') {
                badgeRequirement.value = value == 1
                    ? 'Redeem 1 reward'
                    : `Redeem ${value} rewards`;
            }
        }

        requirementType.addEventListener('change', updateRequirement);
        requirementValue.addEventListener('input', updateRequirement);

        document.getElementById('badgeIcon').addEventListener('change', function () {
            if (this.files.length) {
                document.getElementById('badgeIconPreview').src =
                    URL.createObjectURL(this.files[0]);
            }
        });
        </script>

</body>
</html>
