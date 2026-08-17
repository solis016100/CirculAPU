<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    function createUpdateLog($conn, $userID, $refType, $refID, $description) {
        $logID = 'LOG' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("
            INSERT INTO systemlog
            (logID, userID, actionType, refType, refID, description)
            VALUES (?, ?, 'Update', ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssss",
            $logID,
            $userID,
            $refType,
            $refID,
            $description
        );

        $stmt->execute();
    }

    $id = $_GET['verificationID'] ?? '';
    $from = $_GET['from'] ?? 'management';

    if (!$id) {
        header("Location: auditor_verification_management.php");
        exit();
    }

    /* GET RECORD */
    $q = $conn->prepare("
        SELECT v.*, u.name AS userName
        FROM verificationrecords v
        LEFT JOIN user u ON v.userID = u.userID
        WHERE v.verificationID = ?
    ");

    $q->bind_param("s", $id);
    $q->execute();

    $r = $q->get_result()->fetch_assoc();

    if (!$r) {
        exit("Record not found.");
    }

    /* UPDATE */
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $remarks = trim($_POST['remarks']);

        $q = $conn->prepare("
            UPDATE verificationrecords
            SET remarks = ?
            WHERE verificationID = ?
        ");

        $q->bind_param("ss", $remarks, $id);
        $q->execute();

        createUpdateLog(
            $conn,
            $_SESSION['user_id'],
            'Verification',
            $id,
            "Updated verification remarks: $id"
        );

        header(
            "Location: auditor_verification_view.php?"
            . http_build_query([
                'verificationID' => $id,
                'success' => 1
            ])
        );
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Auditor Edit Verification Record - CirculAPU</title>

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
                <a href="auditor_verification_management.php">
                    Verification Management
                </a>
                &gt;
                Edit Verification
            </div>

            <h1>Edit Verification Record</h1>

            <section class="auditor-management-edit-card">
                <form method="POST" class="auditor-management-edit-form">

                    <div class="auditor-management-edit-row">
                        <label>Verification ID</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($r['verificationID']); ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>User</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($r['userName'] . ' (' . $r['userID'] . ')'); ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Target Type</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($r['targetType']); ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Target ID</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($r['targetID']); ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Verified By</label>
                        <input
                            type="text"
                            value="<?php echo htmlspecialchars($r['verifiedBy'] ?? '-'); ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Verified Date</label>
                        <input
                            type="text"
                            value="<?= $r['verifiedDate'] ? date('d M Y, h:i A', strtotime($r['verifiedDate'])) : '-' ?>"
                            readonly>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Status</label>
                        <span class="verification-status status-<?php echo strtolower($r['status']); ?>">
                            <?php echo htmlspecialchars($r['status']); ?>
                        </span>
                    </div>

                    <div class="auditor-management-edit-row">
                        <label>Remarks</label>

                        <textarea
                            name="remarks"
                            rows="5"><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></textarea>
                    </div>

                    <div class="auditor-management-edit-actions">
                        <button type="submit">
                            Save Changes
                        </button>
                        <a href="<?= $from === 'view' ? 'auditor_verification_view.php?verificationID=' . urlencode($id) : 'auditor_verification_management.php' ?>">
                            Cancel
                        </a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<?php include 'auditor_responsive_navigation.php'; ?>
</body>
</html>