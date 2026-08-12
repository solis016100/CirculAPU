<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING($column,4) AS UNSIGNED)),0)+1 nextID FROM $table")->fetch_assoc();
    return sprintf($prefix . '%03d', (int)$row['nextID']);
}
function showDate($v, bool $time = true): string {
    return $v && strtotime($v) ? date($time ? 'd M Y, h:i A' : 'd M Y', strtotime($v)) : '-';
}
function addLog(mysqli $conn, string $userID, string $action, string $refID, string $text): void {
    $id = nextID($conn, 'systemlog', 'logID', 'LOG');
    $stmt = $conn->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,?,'Transaction',?,?)");
    $stmt->bind_param('sssss', $id, $userID, $action, $refID, $text);
    $stmt->execute(); $stmt->close();
}
function notify(mysqli $conn, string $userID, string $title, string $content): void {
    $id = nextID($conn, 'notification', 'notificationID', 'NOT');
    $stmt = $conn->prepare("INSERT INTO notification(notificationID,userID,title,content,status) VALUES (?,?,?,?,'Unread')");
    $stmt->bind_param('ssss', $id, $userID, $title, $content);
    $stmt->execute(); $stmt->close();
}
function history(mysqli $conn, string $assetID, string $managerID, string $old, string $new, string $reason): void {
    if ($old === $new) return;
    $id = nextID($conn, 'assetstatushistory', 'historyID', 'ASH');
    $stmt = $conn->prepare("
        INSERT INTO assetstatushistory(historyID,assetID,updatedBy,oldAvailabilityStatus,newAvailabilityStatus,statusChangeReason)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->bind_param('ssssss', $id, $assetID, $managerID, $old, $new, $reason);
    $stmt->execute(); $stmt->close();
}

$managerID = $_SESSION['user_id'];
$transactionID = trim($_GET['transactionID'] ?? '');
if (!$transactionID) { header('Location: manager_transactions.php'); exit(); }

$transaction = $borrowing = null;
$logs = [];
$error = '';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    /* Transaction + Asset + People */
    $stmt = $conn->prepare("
        SELECT t.*,a.assetName,a.assetCondition,a.availabilityStatus,a.listingStatus,a.verificationStatus,
               c.categoryName,c.categoryIcon,
               r.name requesterName,r.email requesterEmail,
               o.userID ownerID,o.name ownerName,o.email ownerEmail
        FROM transactionrequests t
        JOIN assets a ON t.assetID=a.assetID
        JOIN categories c ON a.categoryID=c.categoryID
        JOIN user r ON t.requesterID=r.userID
        JOIN user o ON a.ownerID=o.userID
        WHERE t.transactionID=? LIMIT 1
    ");
    $stmt->bind_param('s', $transactionID);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        $error = 'The selected transaction could not be found.';
    } else {
        /* Existing borrowing */
        $stmt = $conn->prepare("SELECT * FROM borrowings WHERE transactionID=? ORDER BY borrowDate DESC LIMIT 1");
        $stmt->bind_param('s', $transactionID);
        $stmt->execute();
        $borrowing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /* Actions */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim($_POST['action'] ?? '');
            $remark = trim($_POST['managerRemark'] ?? '');
            $expected = trim($_POST['expectedReturnDate'] ?? '');

            if (strlen($remark) > 255) $error = 'Manager Remark must not exceed 255 characters.';

            if (!$error && $action === 'approve') {
                if ($transaction['requestStatus'] !== 'Pending') {
                    $error = 'Only Pending requests can be approved.';
                } elseif (
                    $transaction['listingStatus'] !== 'Published' ||
                    $transaction['verificationStatus'] !== 'Approved' ||
                    $transaction['availabilityStatus'] !== 'Available'
                ) {
                    $error = 'This asset is not currently eligible for borrowing.';
                } elseif (!$expected || !DateTime::createFromFormat('Y-m-d', $expected)) {
                    $error = 'Please select a valid expected return date.';
                } elseif ($expected < date('Y-m-d')) {
                    $error = 'Expected return date cannot be in the past.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $returnAt = $expected . ' 23:59:59';

                        $stmt = $conn->prepare("
                            UPDATE transactionrequests
                            SET requestStatus='Approved',managerRemark=?,decisionDate=CURRENT_TIMESTAMP
                            WHERE transactionID=?
                        ");
                        $stmt->bind_param('ss', $remark, $transactionID);
                        $stmt->execute(); $stmt->close();

                        $borrowID = nextID($conn, 'borrowings', 'borrowID', 'BOR');
                        $stmt = $conn->prepare("
                            INSERT INTO borrowings
                            (borrowID,assetID,borrowerID,transactionID,expectedReturnDate,borrowingStatus)
                            VALUES (?,?,?,?,?,'Approved')
                        ");
                        $stmt->bind_param('sssss', $borrowID, $transaction['assetID'], $transaction['requesterID'], $transactionID, $returnAt);
                        $stmt->execute(); $stmt->close();

                        $old = $transaction['availabilityStatus'];
                        $stmt = $conn->prepare("UPDATE assets SET availabilityStatus='Reserved',updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                        $stmt->bind_param('s', $transaction['assetID']);
                        $stmt->execute(); $stmt->close();

                        history($conn, $transaction['assetID'], $managerID, $old, 'Reserved', "Approved borrow request $transactionID");
                        addLog($conn, $managerID, 'Approve', $transactionID, "Approved borrowing request for {$transaction['assetName']}");
                        notify($conn, $transaction['requesterID'], 'Borrowing Request Approved',
                            "Your request $transactionID for {$transaction['assetName']} was approved. Expected return: " . showDate($returnAt, false) . '.');

                        $conn->commit();
                        header("Location: manager_transaction_view.php?transactionID=$transactionID&updated=1"); exit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                }
            }

            if (!$error && $action === 'reject') {
                if ($transaction['requestStatus'] !== 'Pending') $error = 'Only Pending requests can be rejected.';
                elseif (!$remark) $error = 'Please enter a manager remark explaining the rejection.';
                else {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("
                            UPDATE transactionrequests
                            SET requestStatus='Rejected',managerRemark=?,decisionDate=CURRENT_TIMESTAMP
                            WHERE transactionID=?
                        ");
                        $stmt->bind_param('ss', $remark, $transactionID);
                        $stmt->execute(); $stmt->close();

                        addLog($conn, $managerID, 'Reject', $transactionID, "Rejected borrowing request for {$transaction['assetName']}. Reason: $remark");
                        notify($conn, $transaction['requesterID'], 'Borrowing Request Rejected',
                            "Your request $transactionID for {$transaction['assetName']} was rejected. Reason: $remark");

                        $conn->commit();
                        header("Location: manager_transaction_view.php?transactionID=$transactionID&updated=1"); exit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                }
            }

            if (!$error && $action === 'start') {
                if ($transaction['requestStatus'] !== 'Approved' || !$borrowing) {
                    $error = 'Only an approved transaction with a borrowing record can be started.';
                } elseif ($transaction['availabilityStatus'] !== 'Reserved') {
                    $error = 'The asset must be Reserved before borrowing starts.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("UPDATE transactionrequests SET requestStatus='In Progress' WHERE transactionID=?");
                        $stmt->bind_param('s', $transactionID); $stmt->execute(); $stmt->close();

                        $stmt = $conn->prepare("UPDATE borrowings SET borrowingStatus='Borrowed',borrowDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                        $stmt->bind_param('s', $borrowing['borrowID']); $stmt->execute(); $stmt->close();

                        $stmt = $conn->prepare("UPDATE assets SET availabilityStatus='Borrowed',updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                        $stmt->bind_param('s', $transaction['assetID']); $stmt->execute(); $stmt->close();

                        history($conn, $transaction['assetID'], $managerID, 'Reserved', 'Borrowed', "Borrowing started for $transactionID");
                        addLog($conn, $managerID, 'Assign', $transactionID, "Borrowing started for {$transaction['assetName']}");
                        notify($conn, $transaction['requesterID'], 'Borrowing Started',
                            "Borrowing for {$transaction['assetName']} has started. Expected return: " . showDate($borrowing['expectedReturnDate'], false) . '.');

                        $conn->commit();
                        header("Location: manager_transaction_view.php?transactionID=$transactionID&updated=1"); exit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                }
            }

            if (!$error && $action === 'complete') {
                if (!in_array($transaction['requestStatus'], ['In Progress','Overdue'], true) || !$borrowing) {
                    $error = 'Only an active borrowing can be completed.';
                } else {
                    $conn->begin_transaction();
                    try {
                        $old = $transaction['availabilityStatus'];

                        $stmt = $conn->prepare("UPDATE transactionrequests SET requestStatus='Completed' WHERE transactionID=?");
                        $stmt->bind_param('s', $transactionID); $stmt->execute(); $stmt->close();

                        $stmt = $conn->prepare("UPDATE borrowings SET borrowingStatus='Completed',actualReturnDate=CURRENT_TIMESTAMP WHERE borrowID=?");
                        $stmt->bind_param('s', $borrowing['borrowID']); $stmt->execute(); $stmt->close();

                        $stmt = $conn->prepare("UPDATE assets SET availabilityStatus='Available',updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                        $stmt->bind_param('s', $transaction['assetID']); $stmt->execute(); $stmt->close();

                        history($conn, $transaction['assetID'], $managerID, $old, 'Available', "Borrowing completed for $transactionID");
                        addLog($conn, $managerID, 'Update', $transactionID, "Completed borrowing and returned {$transaction['assetName']}");
                        notify($conn, $transaction['requesterID'], 'Borrowing Completed',
                            "Borrowing transaction $transactionID for {$transaction['assetName']} has been completed.");

                        $conn->commit();
                        header("Location: manager_transaction_view.php?transactionID=$transactionID&updated=1"); exit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                }
            }
        }

        /* History */
        $stmt = $conn->prepare("
            SELECT sl.actionType,sl.description,sl.timeLogged,u.name userName
            FROM systemlog sl
            LEFT JOIN user u ON sl.userID=u.userID
            WHERE sl.refType='Transaction' AND sl.refID=?
            ORDER BY sl.timeLogged DESC
        ");
        $stmt->bind_param('s', $transactionID);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $conn->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to process the transaction. Please try again.';
}

$transactionInfo = $transaction ? [
    'Transaction ID' => $transaction['transactionID'],
    'Status' => $transaction['requestStatus'],
    'Request Date' => showDate($transaction['requestDate']),
    'Decision Date' => showDate($transaction['decisionDate']),
    'Expected Return Date' => showDate($borrowing['expectedReturnDate'] ?? null),
    'Actual Return Date' => showDate($borrowing['actualReturnDate'] ?? null),
    'Request Message' => $transaction['requestMessage'] ?: '-',
    'Manager Remark' => $transaction['managerRemark'] ?: '-'
] : [];

$assetInfo = $transaction ? [
    'Asset ID' => $transaction['assetID'],
    'Asset Name' => $transaction['assetName'],
    'Category' => $transaction['categoryName'],
    'Condition' => $transaction['assetCondition'],
    'Availability' => $transaction['availabilityStatus'],
    'Owner' => $transaction['ownerName'] . ' (' . $transaction['ownerID'] . ')'
] : [];

$requesterInfo = $transaction ? [
    'Member ID' => $transaction['requesterID'],
    'Name' => $transaction['requesterName'],
    'Email' => $transaction['requesterEmail']
] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction Details - CirculAPU</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb">
        <a href="manager_transactions.php">Transaction Management</a><span>›</span>
        <span class="breadcrumb-current">Transaction Details</span>
    </div>
    <h1>Transaction Details</h1>
    <p>Review the borrowing request and manage its borrowing lifecycle.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Transaction updated successfully.</div><?php endif; ?>

<?php if ($transaction): ?>
<section class="content-card asset-detail-card">
<div class="asset-detail-layout">

<div class="asset-detail-information">
    <h2>Transaction Information</h2>
    <?php foreach ($transactionInfo as $label => $value): ?>
    <div class="asset-detail-row <?= in_array($label, ['Request Message','Manager Remark'], true) ? 'description-row' : '' ?>">
        <span class="asset-detail-label"><?= e($label) ?></span>
        <div class="asset-detail-value"><?= e($value) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<aside class="asset-detail-side">
    <div>
        <h2>Asset Information</h2>
        <?php foreach ($assetInfo as $label => $value): ?>
        <div class="asset-detail-row">
            <span class="asset-detail-label"><?= e($label) ?></span>
            <div class="asset-detail-value"><?= e($value) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div>
        <h2>Requester Information</h2>
        <?php foreach ($requesterInfo as $label => $value): ?>
        <div class="asset-detail-row">
            <span class="asset-detail-label"><?= e($label) ?></span>
            <div class="asset-detail-value"><?= e($value) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="asset-detail-actions">
        <?php if ($transaction['requestStatus'] === 'Pending'): ?>
        <form method="POST">
            <label for="expectedReturnDate">Expected Return Date *</label>
            <input type="date" id="expectedReturnDate" name="expectedReturnDate" min="<?= date('Y-m-d') ?>" required>
            <label for="managerRemark">Manager Remark</label>
            <textarea id="managerRemark" name="managerRemark" maxlength="255" rows="3"></textarea>
            <button type="submit" name="action" value="approve" class="asset-form-submit"
                    onclick="return confirm('Approve this borrowing request?')">Approve Request</button>
            <button type="submit" name="action" value="reject" class="asset-form-reset"
                    formnovalidate onclick="return confirm('Reject this borrowing request?')">Reject Request</button>
        </form>

        <?php elseif ($transaction['requestStatus'] === 'Approved'): ?>
        <form method="POST" onsubmit="return confirm('Confirm that the asset has been handed over to the borrower?');">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="asset-form-submit">Start Borrowing</button>
        </form>

        <?php elseif (in_array($transaction['requestStatus'], ['In Progress','Overdue'], true)): ?>
        <form method="POST" onsubmit="return confirm('Confirm that the asset has been returned and complete this transaction?');">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="asset-form-submit">Complete Return</button>
        </form>
        <?php endif; ?>

        <a href="manager_transactions.php" class="asset-detail-back-btn">Back</a>
    </div>
</aside>

</div>
</section>

<section class="content-card">
    <div class="card-header"><h2>Transaction History</h2></div>
    <div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Date & Time</th><th>Action</th><th>Done By</th><th>Activity</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="4" class="dashboard-empty-state">No transaction activity recorded yet.</td></tr>
        <?php else: foreach ($logs as $log): ?>
            <tr>
                <td><?= e(showDate($log['timeLogged'])) ?></td>
                <td><?= e($log['actionType']) ?></td>
                <td><?= e($log['userName'] ?: 'System') ?></td>
                <td><?= e($log['description']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endif; ?>

</main>
</div>
</div>
</body>
</html>
