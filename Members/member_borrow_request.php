<?php
session_start();

/* Member access */
if (($_SESSION['user_role'] ?? '') !== 'Member' || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../db.php';
$conn->set_charset('utf8mb4');
$userID = $_SESSION['user_id'];
$assetID = trim($_GET['id'] ?? $_POST['assetID'] ?? '');
$message = '';
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Generate the next ID, for example TR001, B001 or N001. */
function nextID(mysqli $conn, string $table, string $column, string $prefix): string
{
    $start = strlen($prefix) + 1;
    $extra = $prefix === 'N' ? " AND $column NOT LIKE 'NOT%'" : '';
    $sql = "SELECT $column FROM $table
            WHERE $column LIKE '{$prefix}%' $extra
            ORDER BY CAST(SUBSTRING($column,$start) AS UNSIGNED) DESC LIMIT 1";
    $row = $conn->query($sql)->fetch_assoc();
    $number = $row ? (int)substr($row[$column], strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
}

/* Send one notification. */
function notify(mysqli $conn, string $userID, string $title, string $content): void
{
    $notificationID = nextID($conn, 'notification', 'notificationID', 'N');
    $stmt = $conn->prepare(
        "INSERT INTO notification
         (notificationID,userID,title,content,status,createdDate)
         VALUES (?,?,?,?,'Unread',NOW())"
    );
    $stmt->bind_param('ssss', $notificationID, $userID, $title, $content);
    $stmt->execute();
}
if ($assetID === '') {
    header('Location: member_marketplace.php');
    exit;
}

/* Load the selected asset. */
$stmt = $conn->prepare(
    "SELECT a.assetID,a.ownerID,a.assetName,a.assetCondition,
            a.listingStatus,a.verificationStatus,c.categoryName,u.name ownerName
     FROM assets a
     LEFT JOIN categories c ON c.categoryID=a.categoryID
     LEFT JOIN user u ON u.userID=a.ownerID
     WHERE a.assetID=? LIMIT 1"
);
$stmt->bind_param('s', $assetID);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
if (!$asset) die('Asset not found.');
if ($asset['ownerID'] === $userID) die('You cannot borrow your own asset.');
if ($asset['listingStatus'] !== 'Published' || $asset['verificationStatus'] !== 'Approved') {
    die('This asset is not available for borrowing.');
}

/* Submit request. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $borrowDate = trim($_POST['borrowDate'] ?? '');
    $returnDate = trim($_POST['returnDate'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $notes = trim($_POST['additionalNotes'] ?? '');
    $termsAccepted = isset($_POST['term1'], $_POST['term2'], $_POST['term3']);
    if (!$borrowDate || !$returnDate || !$purpose) {
        $message = 'Please complete all required fields.';
    } elseif (!$termsAccepted) {
        $message = 'Please agree to all Terms & Conditions.';
    } elseif ($borrowDate < date('Y-m-d')) {
        $message = 'Borrow date cannot be in the past.';
    } elseif ($returnDate <= $borrowDate) {
        $message = 'Expected return date must be after the borrow date.';
    } else {
        /* Prevent duplicate active requests. */
        $stmt = $conn->prepare(
            "SELECT 1 FROM transactionrequests
             WHERE assetID=? AND requesterID=?
             AND requestStatus IN ('Pending','Approved') LIMIT 1"
        );
        $stmt->bind_param('ss', $assetID, $userID);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) {
            $message = 'You already have an active request for this asset.';
        } else {
            $conn->begin_transaction();
            try {
                $transactionID = nextID($conn, 'transactionrequests', 'transactionID', 'TR');
                $borrowID = nextID($conn, 'borrowings', 'borrowID', 'B');
                $requestMessage = "Purpose: $purpose" . ($notes ? "\nAdditional Notes: $notes" : '');
                $stmt = $conn->prepare(
                    "INSERT INTO transactionrequests
                     (transactionID,assetID,requesterID,requestDate,requestStatus,requestMessage)
                     VALUES (?,?,?,NOW(),'Pending',?)"
                );
                $stmt->bind_param('ssss', $transactionID, $assetID, $userID, $requestMessage);
                $stmt->execute();
                $stmt = $conn->prepare(
                    "INSERT INTO borrowings
                     (borrowID,assetID,borrowerID,transactionID,borrowDate,expectedReturnDate,borrowingStatus)
                     VALUES (?,?,?,?,?,?,'Pending')"
                );
                $stmt->bind_param('ssssss', $borrowID, $assetID, $userID, $transactionID, $borrowDate, $returnDate);
                $stmt->execute();

                /* Notify every active manager. */
                $nameStmt = $conn->prepare('SELECT name FROM user WHERE userID=? LIMIT 1');
                $nameStmt->bind_param('s', $userID);
                $nameStmt->execute();
                $memberName = $nameStmt->get_result()->fetch_assoc()['name'] ?? 'Member';
                $managers = $conn->query("SELECT userID FROM user WHERE role='Manager' AND status='Active'");
                while ($manager = $managers->fetch_assoc()) {
                    notify(
                        $conn,
                        $manager['userID'],
                        'Borrow Request',
                        "$memberName ($userID) requested {$asset['assetName']} ($assetID). Ready for approval."
                    );
                }

                /* Confirm submission to the member. */
                $period = date('d M Y', strtotime($borrowDate)) . ' to ' . date('d M Y', strtotime($returnDate));
                notify(
                    $conn,
                    $userID,
                    'Borrow Request Submitted Successfully',
                    "Your request to borrow {$asset['assetName']} ($assetID) from $period is waiting for approval."
                );
                $conn->commit();
                header('Location: member_requests.php?borrowSubmitted=1');
                exit;
            } catch (Throwable $error) {
                $conn->rollback();
                $message = 'Unable to submit borrow request. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Request - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_styles.css">
</head>
<body class="role-member">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <?php include '../header.php'; ?>
        <main class="page-body">
            <!-- Page heading -->
            <div class="borrow-form-header">
                <div>
                    <h1>Request Form</h1>
                    <p>Request to borrow assets with the APU community.</p>
                </div>
                <a href="member_asset_details.php?id=<?= urlencode($assetID) ?>"
                   class="borrow-back-btn member-btn secondary">← Back to Asset Details</a>
            </div>
            <?php if ($message): ?>
                <div class="borrow-alert error"><?= e($message) ?></div>
            <?php endif; ?>
            <form method="POST" class="borrow-form">
                <input type="hidden" name="assetID" value="<?= e($assetID) ?>">

                <!-- Asset summary -->
                <div class="borrow-asset-summary">
                    <?php foreach ([
                        'Asset' => $asset['assetName'],
                        'Category' => $asset['categoryName'] ?? '-',
                        'Condition' => $asset['assetCondition'],
                        'Owner' => $asset['ownerName'] ?? 'Member'
                    ] as $label => $value): ?>
                        <div><span><?= $label ?></span><strong><?= e($value) ?></strong></div>
                    <?php endforeach; ?>
                </div>

                <!-- Borrow details -->
                <section class="borrow-form-section">
                    <h2>Borrow Details</h2>
                    <div class="borrow-date-grid">
                        <div class="borrow-form-group member-field">
                            <label>Borrow Date <span>*</span></label>
                            <input type="date" name="borrowDate" min="<?= date('Y-m-d') ?>"
                                   value="<?= e($_POST['borrowDate'] ?? '') ?>" required>
                        </div>
                        <div class="borrow-form-group member-field">
                            <label>Expected Return Date <span>*</span></label>
                            <input type="date" name="returnDate" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                   value="<?= e($_POST['returnDate'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="borrow-form-group member-field">
                        <label>Purpose <span>*</span></label>
                        <input type="text" name="purpose" placeholder="Write down your purpose"
                               value="<?= e($_POST['purpose'] ?? '') ?>" required>
                    </div>
                    <div class="borrow-form-group member-field">
                        <label>Additional Notes <small>(Optional)</small></label>
                        <textarea name="additionalNotes" rows="3"
                                  placeholder="Type your notes here..."><?= e($_POST['additionalNotes'] ?? '') ?></textarea>
                    </div>
                </section>

                <!-- Terms and conditions -->
                <section class="borrow-form-section borrow-terms">
                    <h2>Terms & Conditions</h2>
                    <label><input type="checkbox" name="term1" required> I will return the item on time.</label>
                    <label><input type="checkbox" name="term2" required> I will keep and use the item responsibly.</label>
                    <label><input type="checkbox" name="term3" required> I understand late returns or damage may affect my account.</label>
                </section>
                <div class="borrow-form-actions">
                    <a href="member_asset_details.php?id=<?= urlencode($assetID) ?>"
                       class="borrow-cancel-btn member-btn secondary">Cancel</a>
                    <button type="submit" class="borrow-submit-btn member-btn primary">Submit Request →</button>
                </div>
            </form>
        </main>
    </div>
</div>
<script>
/* Return date must be later than the borrow date. */
const borrowDate = document.querySelector('[name="borrowDate"]');
const returnDate = document.querySelector('[name="returnDate"]');
borrowDate.addEventListener('change', () => {
    if (!borrowDate.value) return;
    const nextDay = new Date(borrowDate.value);
    nextDay.setDate(nextDay.getDate() + 1);
    returnDate.min = nextDay.toISOString().split('T')[0];
    if (returnDate.value <= borrowDate.value) returnDate.value = '';
});
</script>
</body>
</html>
