<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';

    $id = $_GET['verificationID'] ?? '';

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

        $status = $_POST['status'];
        $remarks = trim($_POST['remarks']);

        $q = $conn->prepare("
            UPDATE verificationrecords
            SET status = ?, remarks = ?
            WHERE verificationID = ?
        ");

        $q->bind_param("sss", $status, $remarks, $id);
        $q->execute();

        header("Location: auditor_verification_view.php?verificationID=" . urlencode($id));
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Verification</title>

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
                    <label>Status</label>

                    <select name="status" required>
                        <option value="Pending"
                            <?php echo $r['status'] == 'Pending' ? 'selected' : ''; ?>>
                            Pending
                        </option>

                        <option value="Approved"
                            <?php echo $r['status'] == 'Approved' ? 'selected' : ''; ?>>
                            Approved
                        </option>

                        <option value="Rejected"
                            <?php echo $r['status'] == 'Rejected' ? 'selected' : ''; ?>>
                            Rejected
                        </option>
                    </select>
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

                    <a href="auditor_verification_view.php?verificationID=<?php echo urlencode($id); ?>">
                        Cancel
                    </a>

                </div>

            </form>

        </main>

    </div>

</div>

</body>
</html>