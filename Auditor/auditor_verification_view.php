<?php
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Auditor') exit('Access denied');

    require '../db.php';

    $id = $_GET['verificationID'] ?? '';
    if (!$id) exit('Invalid ID');

    function e($x) {
        return htmlspecialchars($x ?? '');
    }

    $q = $conn->prepare("
        SELECT v.*, u.name userName, a.name auditorName
        FROM verificationrecords v
        LEFT JOIN user u ON v.userID=u.userID
        LEFT JOIN user a ON v.verifiedBy=a.userID
        WHERE v.verificationID=?
    ");
    $q->bind_param("s",$id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();

    if (!$r) exit('Record not found');

    $type = $r['targetType'];
    $name = $image = $date = $category = $condition = '';

    if ($type == 'Asset') {
        $q = $conn->prepare("
            SELECT a.assetName,a.assetCondition,a.imageURL,a.createdDate,
                c.categoryName
            FROM assets a
            LEFT JOIN categories c ON a.categoryID=c.categoryID
            WHERE a.assetID=?
        ");
    } else {
        $q = $conn->prepare("
            SELECT m.missionName,ms.evidencePath,ms.submissionDate
            FROM missions m
            JOIN missionsubmission ms ON m.missionID=ms.missionID
            WHERE ms.submissionID=?
        ");
    }

    $q->bind_param("s",$r['targetID']);
    $q->execute();
    $t = $q->get_result()->fetch_assoc();

    if ($t) {
        $name = $t[$type == 'Asset' ? 'assetName' : 'missionName'];
        $image = $t[$type == 'Asset' ? 'imageURL' : 'evidencePath'];
        $date = $t[$type == 'Asset' ? 'createdDate' : 'submissionDate'];

        if ($type == 'Asset') {
            $category = $t['categoryName'];
            $condition = $t['assetCondition'];
        }
    }

    $fields = [
        'Verification ID' => $r['verificationID'],
        'User' => $r['userName'].' ('.$r['userID'].')',
        'Target Type' => $type,
        'Target ID' => $r['targetID'],
        $type == 'Asset' ? 'Asset Name' : 'Mission Name' => $name
    ];

    if ($type == 'Asset') {
        $fields += [
            'Category' => $category,
            'Condition' => $condition
        ];
    }

    $fields += [
        'Status' => $r['status'],
        'Submission Date' => $date ? date('d M Y',strtotime($date)) : '-',
        'Verified Date' => $r['verifiedDate']
            ? date('d M Y',strtotime($r['verifiedDate']))
            : 'Not verified yet',
        'Verified By' => $r['verifiedBy']
            ? $r['auditorName'].' ('.$r['verifiedBy'].')'
            : 'Not verified yet'
    ];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verification Details</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="auditor_style.css">
</head>

<body class="role-auditor">
<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">

<?php include '../header.php'; ?>

<main class="page-body auditor-management-detail-page">

<div class="breadcrumb">
    <a href="auditor_verification_management.php">Verification Management</a>
    &gt; Verification Details
</div>

<h1>Verification Record Details</h1>

<section class="auditor-management-detail-layout">

<div class="auditor-management-detail-information">

<?php foreach ($fields as $label=>$value): ?>
<div class="auditor-management-detail-row">
    <label><?= e($label) ?></label>
    <div class="auditor-management-detail-value"><?= e($value) ?></div>
</div>
<?php endforeach; ?>

<div class="auditor-management-detail-row">
    <label>Remarks</label>
    <textarea class="auditor-management-detail-remarks" readonly><?= e($r['remarks']) ?></textarea>
</div>

</div>


<aside class="auditor-management-detail-side">

<h2>Uploaded Image</h2>

<?php if ($image): ?>

<div class="auditor-management-detail-image">
    <img src="../<?= e($image) ?>" alt="Evidence">
</div>

<a href="../<?= e($image) ?>" target="_blank">View Image</a>

<?php else: ?>

<div class="auditor-management-no-image">No image uploaded</div>

<?php endif; ?>


<?php if ($r['status']=='Pending'): ?>

<div class="auditor-management-decision">

<button class="auditor-management-approve">
    <img src="../assets/icons/approve.png">Approve
</button>

<button class="auditor-management-reject">
    <img src="../assets/icons/reject.png">Reject
</button>

</div>

<?php endif; ?>


<div class="auditor-management-divider"></div>

<a class="auditor-management-detail-action"
   href="../print.php?type=verification&id=<?= e($id) ?>"
   target="_blank">
    <img src="../assets/icons/print.png">Print
</a>

<a class="auditor-management-detail-action"
   href="auditor_verification_edit.php?verificationID=<?= e($id) ?>">
    <img src="../assets/icons/edit.png">Edit
</a>

<a class="auditor-management-detail-action delete"
   href="../delete.php?type=verification&id=<?= e($id) ?>"
   onclick="return confirm('Delete this record?')">
    <img src="../assets/icons/delete.png">Delete
</a>

</aside>
</section>

<div class="auditor-management-detail-back">
    <a href="auditor_verification_management.php">Back</a>
</div>

</main>
</div>
</div>
</body>
</html>