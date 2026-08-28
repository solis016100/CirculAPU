<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Member') {
    header('Location: ../login.php');
    exit();
}

require_once '../db.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function nextID(mysqli $conn, string $table, string $column, string $prefix): string
{
    $allowed = [
        'report.reportID' => true,
        'notification.notificationID' => true,
    ];

    if (!isset($allowed["{$table}.{$column}"])) {
        throw new InvalidArgumentException('Invalid ID source.');
    }

    $prefixLength = strlen($prefix) + 1;
    $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING({$column}, {$prefixLength}) AS UNSIGNED)), 0) AS maxNumber
            FROM {$table}
            WHERE {$column} REGEXP ?";
    $pattern = '^' . preg_quote($prefix, '/') . '[0-9]+$';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $number = (int) ($stmt->get_result()->fetch_assoc()['maxNumber'] ?? 0) + 1;
    $stmt->close();

    return $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

function removeUploadedFile(?string $absolutePath): void
{
    if ($absolutePath && is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

$memberID = (string) $_SESSION['user_id'];
$message = '';
$messageType = '';
$reports = [];
$verifications = [];

if (!isset($_SESSION['member_report_csrf'])) {
    $_SESSION['member_report_csrf'] = bin2hex(random_bytes(32));
}

try {
    $conn->set_charset('utf8mb4');

    $verificationStmt = $conn->prepare(
        'SELECT verificationID, targetType, targetID, status
         FROM verificationrecords
         WHERE userID=?
         ORDER BY verifiedDate DESC, verificationID DESC'
    );
    $verificationStmt->bind_param('s', $memberID);
    $verificationStmt->execute();
    $verifications = $verificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $verificationStmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $verificationID = trim((string) ($_POST['verificationID'] ?? ''));

        if (!hash_equals($_SESSION['member_report_csrf'], $csrfToken)) {
            throw new DomainException('Your session expired. Refresh the page and try again.');
        }

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw new DomainException('Complaint reason must contain between 10 and 255 characters.');
        }

        if ($verificationID === '') {
            throw new DomainException('Select the verification record being disputed.');
        }

        if ($verificationID !== '') {
            $checkStmt = $conn->prepare(
                'SELECT verificationID FROM verificationrecords WHERE verificationID=? AND userID=?'
            );
            $checkStmt->bind_param('ss', $verificationID, $memberID);
            $checkStmt->execute();
            $isOwnedVerification = (bool) $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$isOwnedVerification) {
                throw new DomainException('Select a valid verification record belonging to your account.');
            }
        }

        $relativeEvidencePath = null;
        $absoluteEvidencePath = null;

        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
                throw new DomainException('The evidence image could not be uploaded.');
            }

            if ((int) $_FILES['evidence']['size'] > 5 * 1024 * 1024) {
                throw new DomainException('Evidence image must not exceed 5 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['evidence']['tmp_name']);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($extensions[$mimeType])) {
                throw new DomainException('Evidence must be a JPG, PNG or WEBP image.');
            }

            $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('Unable to create the report upload directory.');
            }

            $fileName = $memberID . '_' . bin2hex(random_bytes(10)) . '.' . $extensions[$mimeType];
            $absoluteEvidencePath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $absoluteEvidencePath)) {
                throw new RuntimeException('Unable to save the evidence image.');
            }

            $relativeEvidencePath = 'uploads/reports/' . $fileName;
        }

        try {
            $conn->begin_transaction();
            $reportID = nextID($conn, 'report', 'reportID', 'R');
            $verificationValue = $verificationID !== '' ? $verificationID : null;

            $insertStmt = $conn->prepare(
                "INSERT INTO report
                    (reportID, reporterID, verificationID, reason, evidenceImage, status, timeCreated)
                 VALUES (?, ?, ?, ?, ?, 'Pending', NOW())"
            );
            $insertStmt->bind_param(
                'sssss',
                $reportID,
                $memberID,
                $verificationValue,
                $reason,
                $relativeEvidencePath
            );
            $insertStmt->execute();
            $insertStmt->close();

            $adminResult = $conn->query("SELECT userID FROM user WHERE role='Admin' AND status<>'Deleted'");
            $notificationTitle = 'New Member Complaint';
            $notificationContent = "Report {$reportID} was submitted by member {$memberID}.";

            while ($admin = $adminResult->fetch_assoc()) {
                $notificationID = nextID($conn, 'notification', 'notificationID', 'N');
                $adminID = (string) $admin['userID'];
                $notificationStmt = $conn->prepare(
                    "INSERT INTO notification
                        (notificationID, userID, title, content, status, createdDate)
                     VALUES (?, ?, ?, ?, 'Unread', NOW())"
                );
                $notificationStmt->bind_param(
                    'ssss',
                    $notificationID,
                    $adminID,
                    $notificationTitle,
                    $notificationContent
                );
                $notificationStmt->execute();
                $notificationStmt->close();
            }

             
            $memberNotificationID = nextID($conn, 'notification', 'notificationID', 'N');
            $memberNotificationTitle = 'Complaint Submitted';
            $memberNotificationContent = "Your complaint {$reportID} was submitted successfully and is waiting for administrator review.";
            $memberNotificationStmt = $conn->prepare(
                "INSERT INTO notification
                    (notificationID, userID, title, content, status, createdDate)
                 VALUES (?, ?, ?, ?, 'Unread', NOW())"
            );
            $memberNotificationStmt->bind_param(
                'ssss',
                $memberNotificationID,
                $memberID,
                $memberNotificationTitle,
                $memberNotificationContent
            );
            $memberNotificationStmt->execute();
            $memberNotificationStmt->close();

            $conn->commit();
            $_SESSION['member_report_csrf'] = bin2hex(random_bytes(32));
            header('Location: member_reports.php?submitted=1&id=' . urlencode($reportID));
            exit();
        } catch (Throwable $exception) {
            $conn->rollback();
            removeUploadedFile($absoluteEvidencePath);
            throw $exception;
        }
    }

    $reportsStmt = $conn->prepare(
        'SELECT r.reportID, r.verificationID, r.reason, r.evidenceImage,
                r.status, r.timeCreated, v.targetType, v.targetID
         FROM report r
         LEFT JOIN verificationrecords v ON r.verificationID=v.verificationID
         WHERE r.reporterID=?
         ORDER BY r.timeCreated DESC'
    );
    $reportsStmt->bind_param('s', $memberID);
    $reportsStmt->execute();
    $reports = $reportsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reportsStmt->close();
} catch (DomainException $exception) {
    $message = $exception->getMessage();
    $messageType = 'error';
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $message = 'Unable to process your complaint. Please try again.';
    $messageType = 'error';
}

if (($_GET['submitted'] ?? '') === '1') {
    $message = 'Complaint ' . e($_GET['id'] ?? '') . ' submitted successfully.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_styles.css">
</head>
<body class="role-member">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body member-report-page">
    <section class="member-report-heading">
        <div>
            <h1>My Complaints</h1>
            <p>Submit a complaint to the System Administrator and track its progress.</p>
        </div>
        <a href="member_dashboard.php" class="member-report-back-btn">Back to Dashboard</a>
    </section>

    <?php if ($message !== ''): ?>
        <div class="member-report-alert <?= e($messageType) ?>"><?= $message ?></div>
    <?php endif; ?>

    <section class="member-report-form-card">
        <div class="member-report-card-header">
            <h2>Submit Complaint</h2>
        </div>

        <form method="POST" enctype="multipart/form-data" class="member-report-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['member_report_csrf']) ?>">

            <label class="member-report-field">
                <span>Related Verification</span>
                <select name="verificationID" required>
                    <option value="">Select the verification being disputed</option>
                    <?php foreach ($verifications as $verification): ?>
                        <option
                            value="<?= e($verification['verificationID']) ?>"
                            <?= ($_POST['verificationID'] ?? '') === $verification['verificationID'] ? 'selected' : '' ?>
                        >
                            <?= e($verification['verificationID']) ?> —
                            <?= e($verification['targetType']) ?> <?= e($verification['targetID']) ?>
                            (<?= e($verification['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="member-report-field">
                <span>Complaint Reason</span>
                <textarea
                    name="reason"
                    maxlength="255"
                    required
                    placeholder="Describe the issue clearly (10–255 characters)."
                ><?= e($_POST['reason'] ?? '') ?></textarea>
            </label>

            <label class="member-report-field">
                <span>Evidence Image <small>(optional)</small></span>
                <input type="file" name="evidence" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG or WEBP. Maximum size: 5 MB.</small>
            </label>

            <div class="member-report-form-actions">
                <button type="submit" class="member-report-submit-btn">Submit Complaint</button>
            </div>
        </form>
    </section>

    <section class="member-report-list-card">
        <div class="member-report-card-header">
            <h2>Complaint History</h2>
            <span><?= count($reports) ?> report<?= count($reports) === 1 ? '' : 's' ?></span>
        </div>

        <div class="member-report-table-wrapper">
            <table class="member-report-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Related Record</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$reports): ?>
                    <tr><td colspan="5" class="member-report-empty">No complaints submitted.</td></tr>
                <?php else: foreach ($reports as $report): ?>
                    <tr>
                        <td data-label="Report ID"><strong><?= e($report['reportID']) ?></strong></td>
                        <td data-label="Related Record">
                            <?php if ($report['verificationID']): ?>
                                <?= e($report['verificationID']) ?><br>
                                <small><?= e($report['targetType']) ?> <?= e($report['targetID']) ?></small>
                            <?php else: ?>
                                General complaint
                            <?php endif; ?>
                        </td>
                        <td data-label="Reason"><?= e($report['reason']) ?></td>
                        <td data-label="Status">
                            <span class="member-report-status <?= e(strtolower(str_replace(' ', '-', $report['status']))) ?>">
                                <?= e($report['status']) ?>
                            </span>
                        </td>
                        <td data-label="Date"><?= e(date('d M Y, h:i A', strtotime($report['timeCreated']))) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
</body>
</html>
