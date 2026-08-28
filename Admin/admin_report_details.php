<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
require_once '../db.php';
require_once 'admin_helpers.php';

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

 
$report_id = trim($_GET['id'] ?? '');

if (empty($report_id)) {
    header("Location: reports_queue.php");
    exit();
}

$message = '';
$message_type = '';

 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resolution = $_POST['resolution'] ?? '';

    if (!adminVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh the page and try again.';
        $message_type = 'error';
    } elseif (!in_array($resolution, ['approve', 'reject'], true)) {
        $message = 'Select a valid report resolution.';
        $message_type = 'error';
    } else {
        try {
            $conn->begin_transaction();

            $lock_sql = "SELECT r.status AS reportStatus, r.verificationID,
                                v.targetID, v.targetType, v.status AS verificationStatus, v.remarks
                         FROM report r
                         LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID
                         WHERE r.reportID = ? FOR UPDATE";
            $lock_stmt = $conn->prepare($lock_sql);
            $lock_stmt->bind_param('s', $report_id);
            $lock_stmt->execute();
            $resolution_data = $lock_stmt->get_result()->fetch_assoc();
            $lock_stmt->close();

            if (!$resolution_data) {
                throw new DomainException('Report not found.');
            }
            if ($resolution_data['reportStatus'] !== 'Pending') {
                throw new DomainException('Only pending reports can be resolved.');
            }

            $verification_status = $resolution === 'approve' ? 'Approved' : 'Rejected';
            $target_type_key = strtolower(str_replace([' ', '_', '-'], '', (string)$resolution_data['targetType']));
            $target_id = $resolution_data['targetID'];

            if ($resolution_data['verificationID']) {
                $decision_note = ($resolution === 'approve' ? 'Admin overrule approved' : 'Admin escalation rejected')
                    . " through report $report_id";
                $remarks = trim(implode(' | ', array_filter([$resolution_data['remarks'], $decision_note])));
                $remarks = mb_substr($remarks, 0, 255);

                $verification_stmt = $conn->prepare(
                    'UPDATE verificationrecords
                     SET status = ?, remarks = ?, verifiedDate = NOW()
                     WHERE verificationID = ?'
                );
                $verification_stmt->bind_param(
                    'sss',
                    $verification_status,
                    $remarks,
                    $resolution_data['verificationID']
                );
                $verification_stmt->execute();
                $verification_stmt->close();
            }

            if ($target_type_key === 'asset' && $target_id) {
                if ($resolution === 'approve') {
                    $target_stmt = $conn->prepare(
                        "UPDATE assets
                         SET verificationStatus = 'Approved',
                             listingStatus = CASE
                                 WHEN listingStatus IN ('Ready for Verification', 'Rejected') THEN 'Published'
                                 ELSE listingStatus
                             END,
                             updatedDate = NOW()
                         WHERE assetID = ? AND listingStatus != 'Removed'"
                    );
                } else {
                     
                    $target_stmt = $conn->prepare(
                        "UPDATE assets
                         SET verificationStatus = 'Rejected', listingStatus = 'Removed',
                             availabilityStatus = 'Unavailable', updatedDate = NOW()
                         WHERE assetID = ?"
                    );
                }
                $target_stmt->bind_param('s', $target_id);
                $target_stmt->execute();
                $target_stmt->close();
            } elseif ($target_type_key === 'missionsubmission' && $target_id) {
                $target_stmt = $conn->prepare('UPDATE missionsubmission SET submissionStatus = ? WHERE submissionID = ?');
                $target_stmt->bind_param('ss', $verification_status, $target_id);
                $target_stmt->execute();
                $target_stmt->close();
            }

            $report_stmt = $conn->prepare("UPDATE report SET status = 'Resolved' WHERE reportID = ? AND status = 'Pending'");
            $report_stmt->bind_param('s', $report_id);
            $report_stmt->execute();
            if ($report_stmt->affected_rows !== 1) {
                throw new RuntimeException('Report status changed before it could be resolved.');
            }
            $report_stmt->close();

            $action_type = $resolution === 'approve' ? 'Approve' : 'Reject';
            $description = $resolution === 'approve'
                ? "Overruled escalation $report_id and approved verification {$resolution_data['verificationID']}"
                : "Rejected escalation $report_id and removed target {$resolution_data['targetType']} $target_id from active content";
            adminWriteSystemLog(
                $conn,
                $_SESSION['user_id'],
                $action_type,
                'Verification',
                $resolution_data['verificationID'] ?: $report_id,
                mb_substr($description, 0, 255)
            );

            $conn->commit();
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: admin_report_details.php?id=' . urlencode($report_id) . '&resolved=' . $resolution);
            exit();
        } catch (DomainException $exception) {
            $conn->rollback();
            $message = $exception->getMessage();
            $message_type = 'error';
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log($exception->getMessage());
            $message = 'The report could not be resolved. Please try again.';
            $message_type = 'error';
        }
    }
}

 
$sql = "SELECT r.*, u.name AS reporterName, u.userID AS reporterUID,
               v.verifiedBy, v.targetType, v.targetID, v.status AS verificationStatus,
               v.remarks AS verificationRemarks, auditor.name AS auditorName
        FROM report r
        LEFT JOIN user u ON r.reporterID = u.userID
        LEFT JOIN verificationrecords v ON r.verificationID = v.verificationID
        LEFT JOIN user auditor ON v.verifiedBy = auditor.userID
        WHERE r.reportID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: reports_queue.php");
    exit();
}

$report = $result->fetch_assoc();
$stmt->close();

if (($_GET['resolved'] ?? '') === 'approve') {
    $message = 'The escalation was overruled, its verification was approved, and the report was resolved.';
    $message_type = 'success';
} elseif (($_GET['resolved'] ?? '') === 'reject') {
    $message = 'The escalation was rejected, related content was removed from active listings, and the report was resolved.';
    $message_type = 'success';
}

$evidence_path = trim((string)($report['evidenceImage'] ?? ''));
$evidence_url = null;
if ($evidence_path !== '') {
    $normalised_evidence = ltrim(str_replace('\\', '/', $evidence_path), '/');
    if (!str_contains($normalised_evidence, '..')) {
        $evidence_url = '../' . $normalised_evidence;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Details - CirculAPU</title>
     
    <link rel="stylesheet" href="../style.css">
     
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
         
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
             
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                 
                <div class="admin-page-header">
                    <div>
                        <p class="header-breadcrumb">Reports Queue &gt; Report Details</p>
                        <h1 class="header-title">Report Details</h1>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                 
                <div class="report-details-container">
                    
                     
                    <div class="report-card">
                        <h2 class="report-card-title">Report Metadata</h2>
                        <div class="metadata-grid">
                            <div class="metadata-column">
                                <p><strong>Report ID:</strong> <?php echo htmlspecialchars($report['reportID']); ?></p>
                                <p><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporterName'] ?: 'Unknown User'); ?> (<?php echo htmlspecialchars($report['reporterID']); ?>)</p>
                                <p><strong>Date Reported:</strong> <?php echo date('j M Y', strtotime($report['timeCreated'])); ?></p>
                                <p><strong>Report Status:</strong> <?php echo htmlspecialchars($report['status']); ?></p>
                            </div>
                            <div class="metadata-column">
                                <p><strong>Target Type:</strong> <?php echo htmlspecialchars($report['targetType'] ?: '-'); ?></p>
                                <p><strong>Target ID:</strong> <?php echo htmlspecialchars($report['targetID'] ?: '-'); ?></p>
                                <p><strong>Assigned To:</strong>
                                    <?php if ($report['verifiedBy']): ?>
                                        <?php echo htmlspecialchars($report['auditorName'] ?: 'Unknown User'); ?> (<?php echo htmlspecialchars($report['verifiedBy']); ?>)
                                    <?php else: ?>
                                        Unassigned
                                    <?php endif; ?>
                                </p>
                                <p><strong>Verification Status:</strong> <?php echo htmlspecialchars($report['verificationStatus'] ?: '-'); ?></p>
                            </div>
                        </div>
                    </div>

                     
                    <div class="report-card evidence-card">
                        <h2 class="report-card-title">Dispute Statement &amp; Evidence</h2>
                        <div class="evidence-statement-box">
                            <p><?php echo nl2br(htmlspecialchars($report['reason'])); ?></p>
                        </div>
                        <?php if ($evidence_url): ?>
                            <div class="evidence-image-preview">
                                <img src="<?php echo htmlspecialchars($evidence_url); ?>" alt="Evidence submitted with report <?php echo htmlspecialchars($report_id); ?>">
                            </div>
                        <?php elseif ($evidence_path !== ''): ?>
                            <p class="table-empty-msg">The stored evidence path is invalid.</p>
                        <?php else: ?>
                            <p class="table-empty-msg">No evidence image was submitted.</p>
                        <?php endif; ?>
                    </div>

                     
                    <form method="POST" action="admin_report_details.php?id=<?php echo urlencode($report_id); ?>" class="report-actions-footer">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
                        <div class="action-btn-group">
                            <?php if ($report['status'] === 'Pending'): ?>
                                <button type="submit" name="resolution" value="approve" class="btn-report-action btn-approve" onclick="return confirm('Overrule this escalation and approve the related verification?');">
                                    Overrule &amp; Approve
                                </button>
                                <button type="submit" name="resolution" value="reject" class="btn-report-action btn-reject" onclick="return confirm('Reject this escalation and remove its related content from active listings?');">
                                    Reject<?php echo strtolower((string)$report['targetType']) === 'asset' ? ' & Remove Asset' : ''; ?>
                                </button>
                            <?php else: ?>
                                <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">This report is <?php echo htmlspecialchars($report['status']); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="reports_queue.php" class="btn-back">
                            Back
                        </a>
                    </form>

                </div>

            </main>
        </div>
    </div>
</body>
</html>
