<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Get Report ID from GET parameter
$report_id = $_GET['id'] ?? '';

if (empty($report_id)) {
    header("Location: report_queue.php");
    exit();
}

$message = '';
$message_type = '';

// Handle Moderation Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_SESSION['user_id'] ?? 'U001';

    if (isset($_POST['action_approve'])) {
        // Mark Report as Resolved (Overruled & Approved)
        $update_report = $conn->prepare("UPDATE report SET status = 'Resolved' WHERE reportID = ?");
        $update_report->bind_param("s", $report_id);
        
        if ($update_report->execute()) {
            $message = "Report $report_id approved and marked as Resolved.";
            $message_type = "success";

            // Log Admin Action
            $log_desc = "Overruled decision and approved report ID: " . $report_id;
            $log_stmt = $conn->prepare("INSERT INTO systemlog (userID, actionType, refType, refID, description) VALUES (?, 'Approve', 'Verification', ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("sss", $admin_id, $report_id, $log_desc);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            $message = "Failed to update report status.";
            $message_type = "error";
        }
        $update_report->close();

    } elseif (isset($_POST['action_reject'])) {
        // Mark Report as Disputed / Rejected
        $update_report = $conn->prepare("UPDATE report SET status = 'Disputed' WHERE reportID = ?");
        $update_report->bind_param("s", $report_id);
        
        if ($update_report->execute()) {
            $message = "Report $report_id rejected and marked as Disputed.";
            $message_type = "success";

            // Log Admin Action
            $log_desc = "Rejected appeal for report ID: " . $report_id;
            $log_stmt = $conn->prepare("INSERT INTO systemlog (userID, actionType, refType, refID, description) VALUES (?, 'Reject', 'Verification', ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("sss", $admin_id, $report_id, $log_desc);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            $message = "Failed to update report status.";
            $message_type = "error";
        }
        $update_report->close();
    }
}

// Fetch Report Metadata and Details
$sql = "SELECT r.*, u.name AS reporterName, u.userID AS reporterUID, 
               v.verifiedBy, v.targetType, v.targetID, auditor.name AS auditorName
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
    header("Location: report_queue.php");
    exit();
}

$report = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Details - CirculAPU</title>
    <!-- Primary Global Stylesheet -->
    <link rel="stylesheet" href="../style.css">
    <!-- Secondary Admin Stylesheet -->
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
        <!-- Reusable Sidebar Component from parent directory -->
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <!-- Global Header Component from parent directory -->
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                <!-- Page Breadcrumb & Title Bar -->
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

                <!-- Report Details Container -->
                <div class="report-details-container">
                    
                    <!-- Report Metadata Section -->
                    <div class="report-card">
                        <h2 class="report-card-title">Report Metadata</h2>
                        <div class="metadata-grid">
                            <div class="metadata-column">
                                <p><strong>Report ID:</strong> <?php echo htmlspecialchars($report['reportID']); ?></p>
                                <p><strong>Reporter:</strong> <?php echo htmlspecialchars($report['reporterName'] ?? 'Odin Dun'); ?> (<?php echo htmlspecialchars($report['reporterID']); ?>)</p>
                                <p><strong>Date Reported:</strong> <?php echo date('j M Y', strtotime($report['timeCreated'])); ?></p>
                            </div>
                            <div class="metadata-column">
                                <p><strong>Target Type:</strong> <?php echo htmlspecialchars($report['targetType'] ?? 'Borrow'); ?></p>
                                <p><strong>Target ID:</strong> <?php echo htmlspecialchars($report['targetID'] ?? 'B076'); ?></p>
                                <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($report['auditorName'] ?? 'Exchange Manager'); ?> (<?php echo htmlspecialchars($report['verifiedBy'] ?? 'U002'); ?>)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Dispute Statement & Evidence Section -->
                    <div class="report-card evidence-card">
                        <h2 class="report-card-title">Dispute Statement &amp; Evidence</h2>
                        <div class="evidence-statement-box">
                            <p><?php echo nl2br(htmlspecialchars($report['reason'])); ?></p>
                        </div>
                        <?php if (!empty($report['evidencePath'])): ?>
                            <div class="evidence-image-preview">
                                <img src="../<?php echo htmlspecialchars($report['evidencePath']); ?>" alt="Evidence Upload">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Moderation Actions Footer -->
                    <form method="POST" action="admin_report_details.php?id=<?php echo urlencode($report_id); ?>" class="report-actions-footer">
                        <div class="action-btn-group">
                            <button type="submit" name="action_approve" class="btn-report-action btn-approve">
                                Approve
                            </button>
                            <button type="submit" name="action_reject" class="btn-report-action btn-reject">
                                Reject
                            </button>
                        </div>
                        <a href="report_queue.php" class="btn-back">
                            Back
                        </a>
                    </form>

                </div>

            </main>
        </div>
    </div>
</body>
</html>