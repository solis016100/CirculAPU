<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';
    require_once '../delete.php';

    $id = $_GET['verificationID'] ?? '';

    if (!$id) {
        header("Location: auditor_verification_management.php");
        exit();
    }

    /* Filter */
    $search = $_GET['search'] ?? '';
    $user = $_GET['user'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';

    $filterQuery = http_build_query(['search' => $search, 'user' => $user, 'type' => $type, 'status' => $status]);

    // Get verification record
    $stmt = $conn->prepare("SELECT v.*, u.name AS userName, a.name AS auditorName FROM verificationrecords v LEFT JOIN user u ON v.userID = u.userID LEFT JOIN user a ON v.verifiedBy = a.userID WHERE v.verificationID = ?");

    $stmt->bind_param("s", $id);
    $stmt->execute();

    $verification = $stmt->get_result()->fetch_assoc();

    if (!$verification) {
        header("Location: auditor_verification_management.php");
        exit();
    }

    // Get target details
    $targetType = $verification['targetType'];
    $targetName = '';
    $image = '';
    $submissionDate = '';
    $category = '';
    $condition = '';

    if ($targetType == 'Asset') {
        $stmt = $conn->prepare("SELECT a.assetName, a.assetCondition, a.imageURL, a.createdDate, c.categoryName FROM assets a LEFT JOIN categories c ON a.categoryID = c.categoryID WHERE a.assetID = ?");

        $stmt->bind_param("s", $verification['targetID']);
        $stmt->execute();

        $asset = $stmt->get_result()->fetch_assoc();

        if ($asset) {
            $targetName = $asset['assetName'];
            $category = $asset['categoryName'];
            $condition = $asset['assetCondition'];
            $image = $asset['imageURL'];
            $submissionDate = $asset['createdDate'];
        }

    }else {
        $stmt = $conn->prepare(" SELECT m.missionName, ms.evidencePath, ms.submissionDate FROM missions m JOIN missionsubmission ms ON m.missionID = ms.missionID WHERE ms.submissionID = ?");

        $stmt->bind_param("s", $verification['targetID']);
        $stmt->execute();

        $mission = $stmt->get_result()->fetch_assoc();

        if ($mission) {
            $targetName = $mission['missionName'];
            $image = $mission['evidencePath'];
            $submissionDate = $mission['submissionDate'];
        }
    }


    // Approve or Reject
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if (in_array($action, ['approve', 'reject']) && $remarks != '') {
            $newStatus = $action == 'approve' ? 'Approved' : 'Rejected';
            $selectedCondition = $_POST['condition'] ?? '';
            $stmt = $conn->prepare("UPDATE verificationrecords SET status = ?, remarks = ?, verifiedBy = ?, verifiedDate = NOW() WHERE verificationID = ? AND status = 'Pending'");

            $stmt->bind_param("ssss", $newStatus, $remarks, $_SESSION['user_id'], $id);

            $stmt->execute();

            // Update Asset COndition
            if ($targetType == 'Asset' && $selectedCondition != '') {
                $stmt = $conn->prepare("UPDATE assets SET assetCondition = ? WHERE assetID = ?");

                $stmt->bind_param("ss", $selectedCondition, $verification['targetID']);

                $stmt->execute();
            }

            header("Location: auditor_verification_view.php?" . http_build_query(['verificationID' => $id, 'search' => $search, 'user' => $user, 'type' => $type, 'status' => $status, 'verified' => 1]));

            exit();
        }
    }

    // Delete verification record
    if (isset($_POST['delete_verification'])) {
        $stmt = $conn->prepare("SELECT verificationID FROM verificationrecords WHERE verificationID = ?");

        $stmt->bind_param("s", $id);
        $stmt->execute();

        $record = $stmt->get_result()->fetch_assoc();

        if ($record) {
            $stmt = $conn->prepare("DELETE FROM verificationrecords WHERE verificationID = ?");

            $stmt->bind_param("s", $id);
            $stmt->execute();

            // Generate system log
            $result = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(logID,4) AS UNSIGNED)), 0) + 1 AS nextID FROM systemlog");
            $nextID = $result->fetch_assoc()['nextID'];
            $logID = 'LOG' . str_pad($nextID, 3, '0', STR_PAD_LEFT);
            $description = "Deleted verification record: " . $id;
            $stmt = $conn->prepare("INSERT INTO systemlog(logID, userID, actionType, refType, refID, description) VALUES (?, ?, 'Delete', 'Verification', ?, ?)");
            $stmt->bind_param("ssss", $logID, $_SESSION['user_id'], $id, $description);
            $stmt->execute();

            header("Location: auditor_verification_management.php?" . $filterQuery . '&deleted=1');
            exit();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Verification Details - CirculAPU</title>
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
                    <a href="auditor_verification_management.php">
                        Verification Management
                    </a>
                    &gt;
                    Verification Details
                </div>

                <h1>Verification Record Details</h1>

                <!-- Green Bar showing Success Updation -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Verification updated successfully.
                    </div>
                <?php endif; ?>

                <!-- Green Bar showing Success Verification -->
                <?php if (isset($_GET['verified'])): ?>
                    <div class="alert alert-success">
                        Verification verified successfully.
                    </div>
                <?php endif; ?>

                <div class="management-view-card">
                    <div class="management-view-body">
                        <!-- Verification ID -->
                        <div class="auditor-management-detail-row">
                            <label>Verification ID</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($verification['verificationID']) ?>
                            </div>
                        </div>

                        <!-- User -->
                        <div class="auditor-management-detail-row">
                            <label>User</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($verification['userName']) ?>
                                (<?= htmlspecialchars($verification['userID']) ?>)
                            </div>
                        </div>

                        <!-- Target Type -->
                        <div class="auditor-management-detail-row">
                            <label>Target Type</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($targetType) ?>
                            </div>
                        </div>

                        <!-- Target ID -->
                        <div class="auditor-management-detail-row">
                            <label>Target ID</label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($verification['targetID']) ?>
                            </div>
                        </div>

                        <!-- Target Name -->
                        <div class="auditor-management-detail-row">
                            <label>
                                <?= $targetType == 'Asset' ? 'Asset Name' : 'Mission Name' ?>
                            </label>
                            <div class="auditor-management-detail-value">
                                <?= htmlspecialchars($targetName) ?>
                            </div>
                        </div>

                        <!-- Asset Only -->
                        <?php if ($targetType == 'Asset'): ?>
                            <div class="auditor-management-detail-row">
                                <label>Category</label>
                                <div class="auditor-management-detail-value">
                                    <?= htmlspecialchars($category) ?>
                                </div>
                            </div>

                            <div class="auditor-management-detail-row">
                                <label>Condition</label>
                                <div class="auditor-management-detail-value">
                                    <span class="asset-condition">
                                        <?= htmlspecialchars($condition) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Status -->
                        <div class="auditor-management-detail-row">
                            <label>Status</label>
                            <div class="auditor-management-detail-value">
                                <span class="verification-status status-<?= strtolower($verification['status']) ?>">
                                    <?= htmlspecialchars($verification['status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="auditor-management-detail-row">
                            <label>Remarks</label>
                            <div class="auditor-management-detail-value remarks">
                                <?= nl2br(htmlspecialchars($verification['remarks'] ?? '')) ?>
                            </div>
                        </div>

                        <!-- Submission Date -->
                        <div class="auditor-management-detail-row">
                            <label>Submission Date</label>
                            <div class="auditor-management-detail-value">
                                <?= $submissionDate ? date('d M Y', strtotime($submissionDate)) : '-' ?>
                            </div>
                        </div>

                        <!-- Verified Date -->
                        <div class="auditor-management-detail-row">
                            <label>Verified Date</label>
                            <div class="auditor-management-detail-value">
                                <?= $verification['verifiedDate'] ? date('d M Y, h:i A', strtotime($verification['verifiedDate'])) : 'Not verified yet' ?>
                            </div>
                        </div>

                        <!-- Verified By -->
                        <div class="auditor-management-detail-row">
                            <label>Verified By</label>
                            <div class="auditor-management-detail-value">
                                <?php if ($verification['verifiedBy']): ?>
                                    <?= htmlspecialchars($verification['auditorName']) ?>
                                    (<?= htmlspecialchars($verification['verifiedBy']) ?>)
                                <?php else: ?>
                                    Not verified yet
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right side details -->
                    <div class="auditor-management-detail-side">
                        <!-- Uploaded Image -->
                        <h2>Uploaded Image</h2>
                        <?php if ($image): ?>
                            <div class="auditor-management-detail-image">
                                <img src="../<?= htmlspecialchars($image) ?>" alt="Evidence">
                            </div>

                            <a href="#" class="auditor-management-view-image" onclick="openImage(event)">
                                View Image
                            </a>
                        <?php else: ?>
                            <div class="auditor-management-no-image">
                                No image uploaded
                            </div>
                        <?php endif; ?>

                        <!-- Approve / Reject Buttons -->
                        <?php if ($verification['status'] == 'Pending'): ?>
                            <div class="auditor-management-decision">
                                <button type="button" class="auditor-management-approve" onclick="openDecisionPopout('approve')">
                                    <img src="../assets/icons/approve.png" alt="">
                                    Approve
                                </button>

                                <button type="button" class="auditor-management-reject" onclick="openDecisionPopout('reject')">
                                    <img src="../assets/icons/reject.png" alt="">
                                    Reject
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="auditor-management-divider"></div>

                        <!-- Print -->
                        <?php if (isset($_GET['print'])): ?>
                            <script>
                                window.addEventListener('afterprint', function () {
                                    window.location.href = 'auditor_verification_management.php';
                                });

                                window.onload = function () {
                                    window.print();
                                };
                            </script>
                        <?php endif; ?>

                        <?php printButton('Print', '../assets/icons/print.png', 'auditor-management-detail-action');?>

                        <!-- Edit -->
                        <a href="auditor_verification_edit.php?verificationID=<?= urlencode($id) ?>&from=view" class="auditor-management-detail-action" title="Edit">
                            <img src="../assets/icons/edit.png" alt="Edit">
                            Edit
                        </a>

                        <!-- Delete -->
                        <button type="button" class="auditor-management-detail-action delete" onclick="openDeletePopout()">
                            <img src="../assets/icons/delete.png" alt="">
                            Delete
                        </button>

                        <!-- Back -->
                        <div class="auditor-management-detail-back">
                            <a href="auditor_verification_management.php?<?= $filterQuery ?>">
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Print -->
    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        <script>
            window.onload = function () {
                window.print();
            };

            window.onafterprint = function () {
                window.location.href = 'auditor_verification_management.php';
            };
        </script>
    <?php endif; ?>

    <!-- Image Popout -->
    <div id="imagePopout" class="image-popout" onclick="closeImage()">
        <span class="image-popout-close" onclick="event.stopPropagation(); closeImage()">
            &times;
        </span>
        <img id="popoutImage" src="" alt="Evidence" onclick="event.stopPropagation();">
    </div>

    <!-- Delete Popout -->
    <div id="deletePopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2>Delete Verification Confirmation</h2>
            <p>Are you sure you want to delete this verification record?</p>
            <div class="auditor-popout-actions">
                <button type="button" onclick="closeDeletePopout()">
                    No
                </button>

                <form method="POST">
                    <button type="submit" name="delete_verification" class="auditor-popout-confirm">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve / Reject Confirmation Popout -->
    <div id="decisionPopout" class="auditor-popout">
        <div class="auditor-popout-box">
            <h2 id="decisionTitle">Approve Verification</h2>
            <p id="decisionMessage">Are you sure you want to approve this verification?</p>
            <form method="POST">
                <input type="hidden" name="action" id="decisionAction">
                <input type="hidden" name="verificationID" value="<?= htmlspecialchars($id) ?>">

                <?php if ($targetType == 'Asset'): ?>
                    <label for="decisionCondition">
                        Condition
                    </label>

                    <select
                        id="decisionCondition"
                        name="condition"
                        class="decision-condition"
                        required
                    >
                        <option value="Good" <?= $condition == 'Good' ? 'selected' : '' ?>>
                            Good
                        </option>

                        <option value="Like New" <?= $condition == 'Like New' ? 'selected' : '' ?>>
                            Like New
                        </option>

                        <option value="Fair" <?= $condition == 'Fair' ? 'selected' : '' ?>>
                            Fair
                        </option>

                        <option value="Poor" <?= $condition == 'Poor' ? 'selected' : '' ?>>
                            Poor
                        </option>
                    </select>

                <?php endif; ?>
                <label for="decisionRemarks">
                    Remarks
                </label>

                <textarea
                    id="decisionRemarks"
                    name="remarks"
                    class="decision-remarks"
                    rows="4"
                    required
                    placeholder="Enter remarks..."
                ></textarea>

                <div class="auditor-popout-actions">

                    <button
                        type="button"
                        onclick="closeDecisionPopout()"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="auditor-popout-confirm"
                    >
                        Confirm
                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        function openDecisionPopout(action) {

            document.getElementById('decisionAction').value = action;

            if (action === 'approve') {

                document.getElementById('decisionTitle').textContent =
                    'Approve Verification';

                document.getElementById('decisionMessage').textContent =
                    'Are you sure you want to approve this verification?';

            } else {

                document.getElementById('decisionTitle').textContent =
                    'Reject Verification';

                document.getElementById('decisionMessage').textContent =
                    'Are you sure you want to reject this verification?';
            }

            document.getElementById('decisionRemarks').value = '';

            document.getElementById('decisionPopout')
                .classList.add('show');
        }


        function closeDecisionPopout() {

            document.getElementById('decisionPopout')
                .classList.remove('show');

        }

        function openImage(e) {
            e.preventDefault();
            document.getElementById('popoutImage').src = "../<?= htmlspecialchars($image) ?>";
            document.getElementById('imagePopout').style.display = 'flex';
        }

        function closeImage() {
            document.getElementById('imagePopout').style.display = 'none';
        }

        function openDeletePopout() {
            document.getElementById('deletePopout').classList.add('show');
        }

        function closeDeletePopout() {
            document.getElementById('deletePopout').classList.remove('show');
        }
    </script>
</body>
</html>