<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
    header("Location: ../login.php");
    exit();
}

require '../db.php';
require_once '../print.php';
require_once '../delete.php';

// Change Badge Icon

if (isset($_POST['save_icon']) && isset($_FILES['badgeIcon'])) {

    $file = $_FILES['badgeIcon'];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (
        $file['error'] === UPLOAD_ERR_OK &&
        in_array($file['type'], $allowedTypes) &&
        $file['size'] <= 5 * 1024 * 1024
    ) {

        $uploadDirectory = "../assets/images/";

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $fileName = "badge_" . $id . "_" . time() . "." . $extension;

        $targetPath = $uploadDirectory . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {

            $updateStmt = $conn->prepare("
                UPDATE achievementbadges
                SET achieveBadgeIcon = ?
                WHERE achieveBadgeID = ?
            ");

            $updateStmt->bind_param(
                "ss",
                $fileName,
                $id
            );

            $updateStmt->execute();
            $updateStmt->close();

            header(
                "Location: auditor_achievement_badges_view.php?achieveBadgeID="
                . urlencode($id)
                . "&updated=1"
            );

            exit();
        }
    }
}

$id = $_GET['achieveBadgeID'] ?? '';

$stmt = $conn->prepare("
    SELECT achieveBadgeID, achieveBadgeName, achieveBadgeDescription,
           achieveBadgeRequirement, achieveBadgeIcon
    FROM achievementbadges
    WHERE achieveBadgeID = ?
");

$stmt->bind_param("s", $id);
$stmt->execute();
$badge = $stmt->get_result()->fetch_assoc();

if (!$badge) {
    header("Location: auditor_achievement_badges_management.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Auditor Achievement Badge Details - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">

</head>


<body class="role-auditor">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body auditor-management-detail-page">


            <!-- Breadcrumb -->

            <div class="breadcrumb">

                <a href="auditor_achievement_badges_management.php">
                    Achievement Badge Management
                </a>

                &gt; Achievement Badge Details

            </div>


            <h1>Achievement Badge Details</h1>


            <!-- Badge Details -->
<form method="POST" enctype="multipart/form-data">
            <div class="management-view-card">


                <!-- Badge Information -->

                <div class="management-view-body">


                    <!-- Badge ID -->

                    <div class="auditor-management-detail-row">

                        <label>Badge ID</label>

                        <div class="auditor-management-detail-value">
                            <?php echo htmlspecialchars($badge['achieveBadgeID']); ?>
                        </div>

                    </div>


                    <!-- Badge Name -->

                    <div class="auditor-management-detail-row">

                        <label>Badge Name</label>

                        <div class="auditor-management-detail-value">
                            <?php echo htmlspecialchars($badge['achieveBadgeName']); ?>
                        </div>

                    </div>


                    <!-- Badge Icon -->

                    <div class="auditor-management-detail-row">

                        <label>Badge Icon</label>

                        <div>

                            <div class="badge-view-icon-box">

                                <img
                                    id="badgeIconPreview"
                                    src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>"
                                    alt="Badge Icon"
                                >

                            </div>

                            <label
                                for="badgeIcon"
                                class="auditor-management-view-image"
                            >
                                Add/Change Icon
                            </label>

                            <input
                                type="file"
                                id="badgeIcon"
                                name="badgeIcon"
                                accept=".jpg,.jpeg,.png,.webp"
                                hidden
                            >

                        </div>

                    </div>


                    <!-- Description -->

                    <div class="auditor-management-detail-row">

                        <label>Description</label>

                        <div class="auditor-management-detail-value remarks">
                            <?php echo nl2br(htmlspecialchars($badge['achieveBadgeDescription'])); ?>
                        </div>

                    </div>


                    <!-- Requirement -->

                    <div class="auditor-management-detail-row">

                        <label>Requirement</label>

                        <div class="auditor-management-detail-value remarks">
                            <?php echo nl2br(htmlspecialchars($badge['achieveBadgeRequirement'])); ?>
                        </div>

                    </div>


                </div>


                <!-- Action Buttons -->

                <div class="auditor-management-detail-side">


                    <!-- Edit -->

                    <a
                        href="auditor_achievement_badges_edit.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>"
                        class="auditor-management-detail-action"
                    >

                        <img src="../assets/icons/edit.png" alt="Edit">

                        Edit

                    </a>


                    <!-- Print -->

                    <a
                        href="auditor_achievement_badges_view.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>&print=1"
                        class="auditor-management-detail-action"
                    >

                        <img src="../assets/icons/print.png" alt="Print">

                        Print

                    </a>


                    <!-- Delete -->

                    <button
                        type="button"
                        class="auditor-management-detail-action delete"
                        onclick="openDeletePopout()"
                    >

                        <img src="../assets/icons/delete.png" alt="Delete">

                        Delete

                    </button>


                </div>


                <!-- Back Button -->

                <div class="auditor-management-detail-back">

                    <a href="auditor_achievement_badges_management.php">
                        Back
                    </a>

                </div>


            </div>


        </main>

    </div>

</div>
</form>


<!-- Print -->

<?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>

<script>

window.onload = function() {
    window.print();
};

window.onafterprint = function() {
    window.location.href =
        'auditor_achievement_badges_management.php';
};

</script>

<?php endif; ?>


<!-- Delete Popout -->

<div id="deletePopout" class="auditor-popout">

    <div class="auditor-popout-box">

        <h2>Delete Achievement Badge Confirmation</h2>

        <p>
            Are you sure you want to delete this achievement badge?
        </p>

        <div class="auditor-popout-actions">

            <button
                type="button"
                onclick="closeDeletePopout()"
            >
                No
            </button>


            <form method="POST">

                <input
                    type="hidden"
                    name="badgeID"
                    value="<?php echo htmlspecialchars($badge['achieveBadgeID']); ?>"
                >

                <button
                    type="submit"
                    name="delete_badge"
                    class="auditor-popout-confirm"
                >
                    Yes, Delete
                </button>

            </form>

        </div>

    </div>

</div>


<script>

function openDeletePopout() {
    document.getElementById('deletePopout').classList.add('show');
}

function closeDeletePopout() {
    document.getElementById('deletePopout').classList.remove('show');
}

const badgeIcon = document.getElementById('badgeIcon');
const badgeIconPreview = document.getElementById('badgeIconPreview');
const saveIconBtn = document.getElementById('saveIconBtn');

badgeIcon.addEventListener('change', function () {

    if (this.files.length > 0) {

        badgeIconPreview.src =
            URL.createObjectURL(this.files[0]);

        saveIconBtn.style.display = 'flex';
    }

});
</script>


</body>

</html>