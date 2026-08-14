<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';
    require_once '../delete.php';

    $id = $_GET['achieveBadgeID'] ?? '';

    // Get Achievement Badge
    $stmt = $conn->prepare("
        SELECT
            achieveBadgeID,
            achieveBadgeName,
            achieveBadgeDescription,
            achieveBadgeRequirement,
            achieveBadgeIcon
        FROM achievementbadges
        WHERE achieveBadgeID = ?
    ");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $badge = $stmt->get_result()->fetch_assoc();


    // Check Badge
    if (!$badge) {
        header("Location: auditor_achievement_badges_management.php");
        exit();
    }


    // Save Changes
    if (isset($_POST['save_changes'])) {

        if (
            !isset($_FILES['badgeIcon']) ||
            $_FILES['badgeIcon']['error'] === UPLOAD_ERR_NO_FILE
        ) {

            $errorMessage = "Please select an image.";

        } else {

            $file = $_FILES['badgeIcon'];

            $allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            // Validate Image

            if ($file['error'] !== UPLOAD_ERR_OK) {

                $errorMessage = "Unable to upload the badge icon.";

            } elseif (!in_array($file['type'], $allowedTypes)) {

                $errorMessage =
                    "Only JPG, PNG, and WEBP images are allowed.";

            } elseif ($file['size'] > 5 * 1024 * 1024) {

                $errorMessage =
                    "Badge icon must not exceed 5MB.";

            } else {

                $uploadDirectory = "../assets/images/";

                $extension = strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );


                // Generate New File Name

                $safeName = preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    strtolower($badge['achieveBadgeName'])
                );

                $fileName =
                    "badge_" .
                    $safeName .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $targetPath =
                    $uploadDirectory . $fileName;


                // Upload New Image

                if (!move_uploaded_file(
                    $file['tmp_name'],
                    $targetPath
                )) {

                    $errorMessage =
                        "Unable to save the badge icon.";

                } else {


                    // Update Badge Icon

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


                    if ($updateStmt->execute()) {

                        $updateStmt->close();


                        // Delete Old Image

                        $oldImage =
                            "../assets/images/" .
                            $badge['achieveBadgeIcon'];

                        if (
                            !empty($badge['achieveBadgeIcon']) &&
                            file_exists($oldImage)
                        ) {

                            unlink($oldImage);

                        }


                        // Create System Log

                        $logResult = mysqli_query($conn, "
                            SELECT MAX(
                                CAST(
                                    SUBSTRING(logID, 4)
                                    AS UNSIGNED
                                )
                            ) + 1 AS nextID
                            FROM systemlog
                        ");

                        $logRow =
                            mysqli_fetch_assoc($logResult);

                        $logID = "LOG" . str_pad(
                            $logRow['nextID'] ?? 1,
                            3,
                            "0",
                            STR_PAD_LEFT
                        );


                        $logDescription =
                            "Changed achievement badge icon: " .
                            $badge['achieveBadgeName'];


                        $userID =
                            $_SESSION['user_id'];


                        $logStmt = mysqli_prepare($conn, "
                            INSERT INTO systemlog
                            (
                                logID,
                                userID,
                                actionType,
                                refType,
                                refID,
                                description
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                'Update',
                                'Badge',
                                ?,
                                ?
                            )
                        ");


                        if ($logStmt) {

                            mysqli_stmt_bind_param(
                                $logStmt,
                                "ssss",
                                $logID,
                                $userID,
                                $id,
                                $logDescription
                            );

                            mysqli_stmt_execute(
                                $logStmt
                            );

                            mysqli_stmt_close(
                                $logStmt
                            );
                        }


                        // Successful Update

                        header(
                            "Location: auditor_achievement_badges_view.php?achieveBadgeID="
                            . urlencode($id)
                            . "&updated=1"
                        );

                        exit();


                    } else {

                        $updateStmt->close();

                        // Remove new image if database update fails
                        unlink($targetPath);

                        $errorMessage =
                            "Unable to update the badge icon.";
                    }
                }
            }
        }
    }


    // Successful Update Message

    $successMessage = "";

    if (isset($_GET['updated']) && $_GET['updated'] == '1') {

        $successMessage =
            "Achievement badge icon updated successfully.";

    }


    // Refresh Badge Data After Update

    $stmt = $conn->prepare("
        SELECT
            achieveBadgeID,
            achieveBadgeName,
            achieveBadgeDescription,
            achieveBadgeRequirement,
            achieveBadgeIcon
        FROM achievementbadges
        WHERE achieveBadgeID = ?
    ");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $badge = $stmt->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Auditor Achievement Badge Details - CirculAPU
    </title>

    <link
        rel="stylesheet"
        href="../style.css"
    >

    <link
        rel="stylesheet"
        href="auditor_style.css"
    >

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

                    &gt;

                    Achievement Badge Details

                </div>


                <h1>
                    Achievement Badge Details
                </h1>


                <!-- Success Message -->

                <?php if ($successMessage !== ""): ?>

                    <div class="management-success-message">

                        <?php echo htmlspecialchars($successMessage); ?>

                    </div>

                <?php endif; ?>


                <!-- Error Message -->

                <?php if (!empty($errorMessage)): ?>

                    <div class="management-error-message">

                        <?php echo htmlspecialchars($errorMessage); ?>

                    </div>

                <?php endif; ?>


                <!-- Badge Details -->

                <form
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <div class="management-form-card">


                        <!-- Badge Information -->

                        <div class="auditor-management-detail-information">


                            <!-- Badge ID -->

                            <div class="auditor-management-detail-row">

                                <label>
                                    Badge ID
                                </label>

                                <div class="auditor-management-detail-value">

                                    <?php
                                        echo htmlspecialchars(
                                            $badge['achieveBadgeID']
                                        );
                                    ?>

                                </div>

                            </div>


                            <!-- Badge Name -->

                            <div class="auditor-management-detail-row">

                                <label>
                                    Badge Name
                                </label>

                                <div class="auditor-management-detail-value">

                                    <?php
                                        echo htmlspecialchars(
                                            $badge['achieveBadgeName']
                                        );
                                    ?>

                                </div>

                            </div>


                            <!-- Description -->

                            <div class="auditor-management-detail-row">

                                <label>
                                    Description
                                </label>

                                <div class="auditor-management-detail-value remarks">

                                    <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $badge['achieveBadgeDescription']
                                            )
                                        );
                                    ?>

                                </div>

                            </div>


                            <!-- Requirement -->

                            <div class="auditor-management-detail-row">

                                <label>
                                    Requirement
                                </label>

                                <div class="auditor-management-detail-value remarks">

                                    <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $badge['achieveBadgeRequirement']
                                            )
                                        );
                                    ?>

                                </div>

                            </div>


                        </div>


                        <!-- Badge Icon & Actions -->

                        <div class="auditor-management-detail-side">


                            <!-- Badge Icon -->

                            <div class="auditor-management-detail-image">

                                <img
                                    id="badgeIconPreview"
                                    src="../assets/images/<?php echo htmlspecialchars($badge['achieveBadgeIcon']); ?>"
                                    alt="<?php echo htmlspecialchars($badge['achieveBadgeName']); ?>"
                                >

                            </div>


                            <!-- Add / Change Icon -->

                            <label
                                for="badgeIcon"
                                class="badge-icon-change"
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


                            <!-- Save Changes -->

                            <button
                                type="submit"
                                name="save_changes"
                                class="auditor-management-detail-action"
                            >

                                <img
                                    src="../assets/icons/save.png"
                                    alt="Save"
                                >

                                Save Changes

                            </button>


                            <!-- Print -->

                            <a
                                href="auditor_achievement_badges_view.php?achieveBadgeID=<?php echo urlencode($badge['achieveBadgeID']); ?>&print=1"
                                class="auditor-management-detail-action"
                            >

                                <img
                                    src="../assets/icons/print.png"
                                    alt="Print"
                                >

                                Print

                            </a>


                            <!-- Delete -->

                            <button
                                type="button"
                                class="auditor-management-detail-action delete"
                                onclick="openDeletePopout()"
                            >

                                <img
                                    src="../assets/icons/delete.png"
                                    alt="Delete"
                                >

                                Delete

                            </button>


                        </div>


                    </div>

                </form>


                <!-- Back Button -->

                <div class="auditor-management-detail-back">

                    <a
                        href="auditor_achievement_badges_management.php"
                        class="auditor-management-detail-action"
                    >
                        Back
                    </a>

                </div>


            </main>

        </div>

    </div>


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

    <script>

        function openDeletePopout() {

            document
                .getElementById('deletePopout')
                .classList.add('show');

        }


        function closeDeletePopout() {

            document
                .getElementById('deletePopout')
                .classList.remove('show');

        }

    </script>


    <!-- Delete Confirmation -->

    <div
        id="deletePopout"
        class="auditor-popout"
    >

        <div class="auditor-popout-box">


            <h2>
                Delete Achievement Badge Confirmation
            </h2>


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


    <!-- Badge Icon Preview -->

    <script>

        const badgeIcon =
            document.getElementById('badgeIcon');

        const badgeIconPreview =
            document.getElementById('badgeIconPreview');


        badgeIcon.addEventListener(
            'change',
            function () {

                if (this.files.length > 0) {

                    badgeIconPreview.src =
                        URL.createObjectURL(
                            this.files[0]
                        );

                }

            }
        );

    </script>


</body>

</html>