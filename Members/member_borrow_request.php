<?php
session_start();

/* Member Access */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

require_once '../db.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$message = "";
$messageType = "";


/* Get Asset ID */
$assetID = trim($_GET['id'] ?? $_POST['assetID'] ?? '');

if ($assetID == '') {
    header("Location: member_marketplace.php");
    exit();
}

$assetIDSafe = $conn->real_escape_string($assetID);


/* Get Asset Information */
$assetQuery = "
SELECT
    a.assetID,
    a.ownerID,
    a.assetName,
    a.assetCondition,
    a.availabilityStatus,
    a.listingStatus,
    a.verificationStatus,
    c.categoryName,
    u.name AS ownerName
FROM assets a

LEFT JOIN categories c
ON a.categoryID = c.categoryID

LEFT JOIN user u
ON a.ownerID = u.userID

WHERE a.assetID = '$assetIDSafe'
LIMIT 1
";

$assetResult = $conn->query($assetQuery);

if (!$assetResult || $assetResult->num_rows == 0) {
    die("Asset not found.");
}

$asset = $assetResult->fetch_assoc();


/* Cannot Borrow Own Asset */
if ($asset['ownerID'] == $userID) {
    die("You cannot borrow your own asset.");
}


/* Asset Must Be Published + Approved */
if (
    $asset['listingStatus'] != 'Published' ||
    $asset['verificationStatus'] != 'Approved'
) {
    die("This asset is not available for borrowing.");
}


/* Generate Transaction ID */
function generateTransactionID($conn)
{
    $result = $conn->query("
        SELECT transactionID
        FROM transactionrequests
        WHERE transactionID LIKE 'TR%'
        ORDER BY CAST(SUBSTRING(transactionID,3) AS UNSIGNED) DESC
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {

        $lastID =
            $result->fetch_assoc()['transactionID'];

        $number =
            (int)substr($lastID, 2) + 1;

    } else {

        $number = 1;
    }

    return "TR" .
        str_pad(
            $number,
            3,
            "0",
            STR_PAD_LEFT
        );
}


/* Generate Notification ID */
function generateNotificationID($conn)
{
    $result = $conn->query("
        SELECT notificationID
        FROM notification
        WHERE notificationID LIKE 'N%'
        AND notificationID NOT LIKE 'NOT%'
        ORDER BY CAST(SUBSTRING(notificationID,2) AS UNSIGNED) DESC
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {

        $row = $result->fetch_assoc();

        $number =
            (int)substr(
                $row['notificationID'],
                1
            ) + 1;

    } else {

        $number = 1;
    }

    return "N" .
        str_pad(
            $number,
            3,
            "0",
            STR_PAD_LEFT
        );
}


/* Submit Borrow Request */
if (isset($_POST['submitRequest'])) {

    $borrowDate =
        trim($_POST['borrowDate'] ?? '');

    $returnDate =
        trim($_POST['returnDate'] ?? '');

    $purpose =
        trim($_POST['purpose'] ?? '');

    $additionalNotes =
        trim($_POST['additionalNotes'] ?? '');

    $term1 = isset($_POST['term1']);
    $term2 = isset($_POST['term2']);
    $term3 = isset($_POST['term3']);

    $today = date('Y-m-d');


    /* Validation */
    if (
        $borrowDate == '' ||
        $returnDate == '' ||
        $purpose == ''
    ) {

        $message =
            "Please complete all required fields.";

        $messageType =
            "error";


    } elseif (
        !$term1 ||
        !$term2 ||
        !$term3
    ) {

        $message =
            "Please agree to all Terms & Conditions.";

        $messageType =
            "error";


    } elseif ($borrowDate < $today) {

        $message =
            "Borrow date cannot be in the past.";

        $messageType =
            "error";


    } elseif ($returnDate <= $borrowDate) {

        $message =
            "Expected return date must be after the borrow date.";

        $messageType =
            "error";


    } else {

        $userIDSafe =
            $conn->real_escape_string($userID);


        /* Prevent Duplicate Active Request */
        $duplicateQuery = "
        SELECT transactionID
        FROM transactionrequests
        WHERE assetID = '$assetIDSafe'
        AND requesterID = '$userIDSafe'
        AND requestStatus IN ('Pending','Approved')
        LIMIT 1
        ";

        $duplicateResult =
            $conn->query($duplicateQuery);


        if (
            $duplicateResult &&
            $duplicateResult->num_rows > 0
        ) {

            $message =
                "You already have an active request for this asset.";

            $messageType =
                "error";


        } else {


            /* Generate Transaction ID */
            $transactionID =
                generateTransactionID($conn);


            /*
            Borrow Date + Return Date are stored
            inside requestMessage.

            Member side DOES NOT create borrowings.
            */

            $requestMessage =
                "Borrow Date: " .
                $borrowDate .
                "\n" .

                "Expected Return Date: " .
                $returnDate .
                "\n" .

                "Purpose: " .
                $purpose;


            if ($additionalNotes != '') {

                $requestMessage .=
                    "\nAdditional Notes: " .
                    $additionalNotes;
            }


            $requestMessageSafe =
                $conn->real_escape_string(
                    $requestMessage
                );


            /* =====================================
               Insert Transaction Request ONLY
            ===================================== */

            $insertQuery = "
            INSERT INTO transactionrequests
            (
                transactionID,
                assetID,
                requesterID,
                requestDate,
                requestStatus,
                managerRemark,
                decisionDate,
                requestMessage
            )
            VALUES
            (
                '$transactionID',
                '$assetIDSafe',
                '$userIDSafe',
                NOW(),
                'Pending',
                NULL,
                NULL,
                '$requestMessageSafe'
            )
            ";


            if ($conn->query($insertQuery)) {


                /* =====================================
                   Get Member Name
                ===================================== */

                $memberResult =
                    $conn->query("
                    SELECT name
                    FROM user
                    WHERE userID = '$userIDSafe'
                    LIMIT 1
                    ");


                $memberName = "Member";


                if (
                    $memberResult &&
                    $memberResult->num_rows > 0
                ) {

                    $memberData =
                        $memberResult->fetch_assoc();

                    $memberName =
                        $memberData['name'];
                }



                /* =====================================
                   Notify Exchange Manager
                ===================================== */

                $managerResult =
                    $conn->query("
                    SELECT userID
                    FROM user
                    WHERE role = 'Exchange Manager'
                    AND status = 'Active'
                    ");


                if ($managerResult) {


                    while (
                        $manager =
                        $managerResult->fetch_assoc()
                    ) {


                        $notificationID =
                            generateNotificationID($conn);


                        $managerID =
                            $conn->real_escape_string(
                                $manager['userID']
                            );


                        $managerTitle =
                            "Borrow Request";


                        $managerContent =
                            $memberName .
                            " (" . $userID . ")" .
                            " requested to borrow " .
                            $asset['assetName'] .
                            " (" . $asset['assetID'] . "). " .
                            "Ready for approval.";


                        $safeManagerTitle =
                            $conn->real_escape_string(
                                $managerTitle
                            );


                        $safeManagerContent =
                            $conn->real_escape_string(
                                $managerContent
                            );


                        $conn->query("
                            INSERT INTO notification
                            (
                                notificationID,
                                userID,
                                title,
                                content,
                                status,
                                createdDate
                            )
                            VALUES
                            (
                                '$notificationID',
                                '$managerID',
                                '$safeManagerTitle',
                                '$safeManagerContent',
                                'Unread',
                                NOW()
                            )
                        ");
                    }
                }



                /* =====================================
                   Notify Member
                ===================================== */

                $notificationID =
                    generateNotificationID($conn);


                $memberTitle =
                    "Borrow Request Submitted Successfully";


                $memberContent =
                    "Your request to borrow " .
                    $asset['assetName'] .
                    " (" . $asset['assetID'] . ") " .
                    "has been submitted successfully and is waiting for approval.";


                $safeMemberTitle =
                    $conn->real_escape_string(
                        $memberTitle
                    );


                $safeMemberContent =
                    $conn->real_escape_string(
                        $memberContent
                    );


                $conn->query("
                    INSERT INTO notification
                    (
                        notificationID,
                        userID,
                        title,
                        content,
                        status,
                        createdDate
                    )
                    VALUES
                    (
                        '$notificationID',
                        '$userIDSafe',
                        '$safeMemberTitle',
                        '$safeMemberContent',
                        'Unread',
                        NOW()
                    )
                ");



                /* Redirect */
                header(
                    "Location: member_requests.php?borrowSubmitted=1"
                );

                exit();


            } else {

                $message =
                    "Unable to submit request: " .
                    $conn->error;

                $messageType =
                    "error";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Borrow Request - CirculAPU
    </title>

    <link
        rel="stylesheet"
        href="../style.css"
    >

    <link
        rel="stylesheet"
        href="member_new_style.css"
    >

</head>


<body class="role-member">

<div class="dashboard-container">


    <?php include '../sidebar.php'; ?>


    <div class="main-content">


        <?php include '../header.php'; ?>


        <main class="page-body">


            <!-- Header -->
            <div class="borrow-form-header">

                <div>

                    <h1>
                        Request Form
                    </h1>

                    <p>
                        Request to borrow assets with the APU community.
                    </p>

                </div>


                <a
                    href="member_asset_details.php?id=<?php echo urlencode($asset['assetID']); ?>"
                    class="borrow-back-btn"
                >
                    ← Back to Asset Details
                </a>

            </div>



            <!-- Error Message -->
            <?php if ($message != ''): ?>

                <div
                    class="borrow-alert <?php echo $messageType; ?>"
                >

                    <?php
                    echo htmlspecialchars($message);
                    ?>

                </div>

            <?php endif; ?>



            <!-- Form -->
            <form
                method="POST"
                class="borrow-form"
            >


                <input
                    type="hidden"
                    name="assetID"
                    value="<?php echo htmlspecialchars($asset['assetID']); ?>"
                >



                <!-- Asset Summary -->
                <div class="borrow-asset-summary">


                    <div>

                        <span>
                            Asset
                        </span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $asset['assetName']
                            );
                            ?>

                        </strong>

                    </div>



                    <div>

                        <span>
                            Category
                        </span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $asset['categoryName']
                                ?? '-'
                            );
                            ?>

                        </strong>

                    </div>



                    <div>

                        <span>
                            Condition
                        </span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $asset['assetCondition']
                            );
                            ?>

                        </strong>

                    </div>



                    <div>

                        <span>
                            Owner
                        </span>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $asset['ownerName']
                                ?? 'Member'
                            );
                            ?>

                        </strong>

                    </div>


                </div>



                <!-- Borrow Details -->
                <section class="borrow-form-section">


                    <h2>
                        Borrow Details
                    </h2>


                    <div class="borrow-date-grid">


                        <!-- Borrow Date -->
                        <div class="borrow-form-group">


                            <label>

                                Borrow Date

                                <span>
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                name="borrowDate"
                                min="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo htmlspecialchars($_POST['borrowDate'] ?? ''); ?>"
                                required
                            >


                        </div>



                        <!-- Expected Return Date -->
                        <div class="borrow-form-group">


                            <label>

                                Expected Return Date

                                <span>
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                name="returnDate"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                value="<?php echo htmlspecialchars($_POST['returnDate'] ?? ''); ?>"
                                required
                            >


                        </div>


                    </div>



                    <!-- Purpose -->
                    <div class="borrow-form-group">


                        <label>

                            Purpose

                            <span>
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            name="purpose"
                            placeholder="Write down your purpose"
                            value="<?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?>"
                            required
                        >


                    </div>



                    <!-- Additional Notes -->
                    <div class="borrow-form-group">


                        <label>

                            Additional Notes

                            <small>
                                (Optional)
                            </small>

                        </label>


                        <textarea
                            name="additionalNotes"
                            rows="3"
                            placeholder="Type your notes here..."
                        ><?php echo htmlspecialchars($_POST['additionalNotes'] ?? ''); ?></textarea>


                    </div>


                </section>



                <!-- Terms -->
                <section class="borrow-form-section borrow-terms">


                    <h2>
                        Terms & Conditions
                    </h2>


                    <label>

                        <input
                            type="checkbox"
                            name="term1"
                            required
                        >

                        I agree to return the item on or before the expected return date.

                    </label>


                    <label>

                        <input
                            type="checkbox"
                            name="term2"
                            required
                        >

                        I agree to keep the item in good condition and use it responsibly.

                    </label>


                    <label>

                        <input
                            type="checkbox"
                            name="term3"
                            required
                        >

                        I understand that late returns or damages may affect my account.

                    </label>


                </section>



                <!-- Buttons -->
                <div class="borrow-form-actions">


                    <a
                        href="member_asset_details.php?id=<?php echo urlencode($asset['assetID']); ?>"
                        class="borrow-cancel-btn"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        name="submitRequest"
                        class="borrow-submit-btn"
                    >
                        Submit Request →
                    </button>


                </div>


            </form>


        </main>


    </div>


</div>



<script>

const borrowDate =
    document.querySelector(
        '[name="borrowDate"]'
    );

const returnDate =
    document.querySelector(
        '[name="returnDate"]'
    );


borrowDate.addEventListener(
    'change',
    function () {

        if (!this.value) {
            return;
        }


        const start =
            new Date(this.value);


        start.setDate(
            start.getDate() + 1
        );


        returnDate.min =
            start
            .toISOString()
            .split('T')[0];


        if (
            returnDate.value &&
            returnDate.value <= this.value
        ) {

            returnDate.value = '';
        }
    }
);

</script>


</body>

</html>