<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    require '../db.php';
    require_once '../print.php';

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

    if (!$id) {
        header("Location: auditor_verification_management.php");
        exit();
    }

    // Approve & Reject Actions
    $remarks = trim($_POST['remarks'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $action = $_POST['action'] ?? '';
        $id = $_POST['verificationID'] ?? '';

        if (in_array($action, ['approve', 'reject']) && $remarks !== '') {

            $status = $action === 'approve' ? 'Approved' : 'Rejected';

            $q = $conn->prepare("
                UPDATE verificationrecords
                SET status = ?, remarks = ?, verifiedBy = ?, verifiedDate = NOW()
                WHERE verificationID = ? AND status = 'Pending'
            ");

            $q->bind_param(
                "ssss",
                $status,
                $remarks,
                $_SESSION['user_id'],
                $id
            );

            $q->execute();

            createUpdateLog(
                $conn,
                $_SESSION['user_id'],
                'Verification',
                $id,
                "Verification $id was $status"
            );

            header("Location: auditor_verification_view.php?verificationID=" . urlencode($id));
            exit();
        }
    }


    /* GET VERIFICATION */
    $q = $conn->prepare("
        SELECT v.*, 
            u.name AS userName,
            a.name AS auditorName
        FROM verificationrecords v
        LEFT JOIN user u ON v.userID = u.userID
        LEFT JOIN user a ON v.verifiedBy = a.userID
        WHERE v.verificationID = ?
    ");

    $q->bind_param("s", $id);
    $q->execute();

    $r = $q->get_result()->fetch_assoc();

    if (!$r) {
        exit("Record not found");
    }


    /* GET TARGET */
    $type = $r['targetType'];
    $name = '';
    $image = '';
    $date = '';
    $category = '';
    $condition = '';

    if ($type == 'Asset') {
        $q = $conn->prepare("
            SELECT a.assetName,
                a.assetCondition,
                a.imageURL,
                a.createdDate,
                c.categoryName
            FROM assets a
            LEFT JOIN categories c
            ON a.categoryID = c.categoryID
            WHERE a.assetID = ?
        ");

        $q->bind_param("s", $r['targetID']);
        $q->execute();

        $t = $q->get_result()->fetch_assoc();

        if ($t) {
            $name = $t['assetName'];
            $category = $t['categoryName'];
            $condition = $t['assetCondition'];
            $image = $t['imageURL'];
            $date = $t['createdDate'];
        }

    } else {

        $q = $conn->prepare("
            SELECT m.missionName,
                ms.evidencePath,
                ms.submissionDate
            FROM missions m
            JOIN missionsubmission ms
            ON m.missionID = ms.missionID
            WHERE ms.submissionID = ?
        ");

        $q->bind_param("s", $r['targetID']);
        $q->execute();

        $t = $q->get_result()->fetch_assoc();

        if ($t) {
            $name = $t['missionName'];
            $image = $t['evidencePath'];
            $date = $t['submissionDate'];
        }
    }


    /* DISPLAY FIELDS */
    $fields = [
        'Verification ID' => $r['verificationID'],
        'User' => $r['userName'] . ' (' . $r['userID'] . ')',
        'Target Type' => $type,
        'Target ID' => $r['targetID'],
        ($type == 'Asset' ? 'Asset Name' : 'Mission Name') => $name
    ];

    if ($type == 'Asset') {
        $fields['Category'] = $category;
        $fields['Condition'] = $condition;
    }

    $fields['Status'] = $r['status'];

    $fields['Remarks'] = $r['remarks'] ?? '';

    $fields['Submission Date'] = $date ? date('d M Y', strtotime($date)) : '-';

    $fields['Verified Date'] = $r['verifiedDate'] ? date('d M Y, h:i A', strtotime($r['verifiedDate'])) : 'Not verified yet';

    $fields['Verified By'] = $r['verifiedBy'] ? $r['auditorName'] . ' (' . $r['verifiedBy'] . ')' : 'Not verified yet';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Auditor Verification Record Details - CirculAPU</title>

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

                <h1>
                    Verification Record Details
                </h1>

                <section class="auditor-management-detail-layout">

                    <div class="auditor-management-detail-information">
                        <?php foreach ($fields as $label => $value): ?>
                            <div class="auditor-management-detail-row">
                                <label>
                                    <?php echo htmlspecialchars($label); ?>
                                </label>

                                <div class="auditor-management-detail-value <?php echo $label == 'Remarks' ? 'remarks' : ''; ?>">
                                    <?php echo htmlspecialchars($value); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="auditor-management-detail-side">
                        <h2>Uploaded Image</h2>

                        <?php if ($image): ?>
                            <div class="auditor-management-detail-image">
                                <img src="../<?php echo htmlspecialchars($image); ?>" alt="Evidence">
                            </div>

                            <a href="../<?php echo htmlspecialchars($image); ?>" target="_blank" class="auditor-management-view-image">
                                View Image
                            </a>

                        <?php else: ?>
                            <div class="auditor-management-no-image">
                                No image uploaded
                            </div>
                        <?php endif; ?>

                        <!-- Approve / Reject Button -->
                        <?php if ($r['status'] === 'Pending'): ?>
                            <div class="auditor-management-decision">
                                <form method="POST" onsubmit="return decision('approve')">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="verificationID"
                                        value="<?= htmlspecialchars($id) ?>">

                                    <button type="submit" class="auditor-management-approve">
                                        <img src="../assets/icons/approve.png" alt="">
                                        Approve
                                    </button>
                                </form>

                                <form method="POST" onsubmit="return decision('reject')">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="verificationID"
                                        value="<?= htmlspecialchars($id) ?>">

                                    <button type="submit" class="auditor-management-reject">
                                        <img src="../assets/icons/reject.png" alt="">
                                        Reject
                                    </button>
                                </form>
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
                        <?php printButton('Print', '../assets/icons/print.png', 'auditor-management-detail-action'); ?>

                        <!-- Edit -->
                        <a href="auditor_verification_edit.php?verificationID=<?= urlencode($id) ?>&from=view" class="auditor-management-detail-action" title="Edit">
                            <img src="../assets/icons/edit.png" alt="Edit">
                            Edit
                        </a>

                        <!-- Delete -->
                        <a href="../delete.php?type=verification&id=<?php echo htmlspecialchars($id); ?>" class="auditor-management-detail-action delete" onclick="return confirm('Delete this record?');">
                            <img src="../assets/icons/delete.png" alt="">
                            Delete
                        </a>
                    </div>
                </section>

                <!-- Back Button -->
                <div class="auditor-management-detail-back">
                    <a href="auditor_verification_management.php?<?= http_build_query(['search' => $_GET['search'] ?? '', 'user' => $_GET['user'] ?? '', 'type' => $_GET['type'] ?? '', 'status' => $_GET['status'] ?? '']) ?>">
                        Back
                    </a>
                </div>
            </main>
        </div>
    </div>
    <script>
        function decision(form, action) {
            if (!confirm( 'Are you sure you want to ' + (action === 'approve' ? 'approve' :  'reject') + ' this verification?' )){
                return false;
            } 

            let remarks = prompt('Enter remarks:');

            if (remarks === null){
                return false;
            } 

            if (!remarks.trim()) {
                alert('Remarks are required.');
                return false;
            }

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remarks';
            input.value = remarks;

            form.appendChild(input);

            return true;
        }
    </script>
</body>
</html>