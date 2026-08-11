<?php
    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Auditor') {
        header("Location: ../login.php");
        exit();
    }

    $auditorID = $_SESSION['user_id'];

    require_once '../db.php';
    require_once '../print.php';

    $verificationID = trim($_GET['verificationID'] ?? $_GET['id'] ?? '');

    if ($verificationID === '') {
        header("Location: auditor_verification_management.php");
        exit();
    }

    $verification = null;
    $databaseError = '';

    try {
        $statement = $conn->prepare("
            SELECT
                v.verificationID,
                v.userID,
                v.targetID,
                v.verifiedBy,
                v.targetType,
                v.status,
                v.remarks,
                v.verifiedDate,
                u.name AS userName,
                a.name AS auditorName
            FROM verificationrecords v
            LEFT JOIN user u
                ON v.userID = u.userID
            LEFT JOIN user a
                ON v.verifiedBy = a.userID
            WHERE v.verificationID = ?
            LIMIT 1
        ");

        $statement->bind_param("s", $verificationID);

        $statement->execute();

        $verification = $statement->get_result()->fetch_assoc();

        $statement->close();

        if (!$verification) {
            $databaseError = "The selected verification record could not be found.";
        }
    } catch (Throwable $error) {
        error_log($error->getMessage());
        $databaseError = "Unable to retrieve the verification record. Please try again.";
    }

    printHeader("Verification Record");

    if ($databaseError !== ''):
?>

<div class="print-error">
    <?php echo htmlspecialchars($databaseError); ?>
</div>

<?php else: ?>
    <table class="print-table">
        <tr>
            <th>Verification ID</th>
            <td>
                <?php echo htmlspecialchars($verification['verificationID']); ?>
            </td>
        </tr>

        <tr>
            <th>User</th>
            <td>
                <?php echo htmlspecialchars($verification['userName'] . ' (' . $verification['userID'] . ')'); ?>
            </td>
        </tr>

        <tr>
            <th>Target ID</th>
            <td>
                <?php echo htmlspecialchars($verification['targetID']); ?>
            </td>
        </tr>

        <tr>
            <th>Target Type</th>
            <td>
                <?php echo htmlspecialchars($verification['targetType']); ?>
            </td>
        </tr>

        <tr>
            <th>Verified By</th>
            <td>
                <?php
                echo htmlspecialchars(($verification['auditorName'] ?? 'N/A') . ' (' . $verification['verifiedBy'] . ')'); ?>
            </td>
        </tr>

        <tr>
            <th>Status</th>
            <td>
                <?php echo htmlspecialchars($verification['status']); ?>
            </td>
        </tr>

        <tr>
            <th>Remarks</th>
            <td>
                <?php echo nl2br(htmlspecialchars($verification['remarks'] ?? '')); ?>
            </td>
        </tr>

        <tr>
            <th>Verification Date</th>
            <td>
                <?php echo !empty($verification['verifiedDate'])? htmlspecialchars(date('d M Y, h:i A', strtotime($verification['verifiedDate']))): 'N/A'; ?>
            </td>
        </tr>

    </table>

    <?php
        printInformation($auditorID, $_SESSION['user_role']);
    ?>

<?php endif; ?>

<?php printFooter(); ?>