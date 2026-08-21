<?php
session_start();

/* Member Access */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

/* Database */
require_once '../db.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


/* Get Asset ID */
$assetID = trim($_GET['id'] ?? '');

if ($assetID == '') {
    header("Location: member_marketplace.php");
    exit();
}

$assetID = $conn->real_escape_string($assetID);


/* Get Asset Details */
$assetQuery = "
SELECT
    a.assetID,
    a.ownerID,
    a.assetName,
    a.description,
    a.assetCondition,
    a.imageURL,
    a.availabilityStatus,
    a.listingStatus,
    a.verificationStatus,
    a.createdDate,
    c.categoryName,
    u.name AS ownerName
FROM assets a
LEFT JOIN categories c
    ON a.categoryID = c.categoryID
LEFT JOIN user u
    ON a.ownerID = u.userID
WHERE a.assetID = '$assetID'
LIMIT 1
";

$assetResult = $conn->query($assetQuery);

if (!$assetResult || $assetResult->num_rows == 0) {
    die("Asset not found.");
}

$asset = $assetResult->fetch_assoc();


/* Check If This Is User's Own Asset */
$isOwner = ($asset['ownerID'] == $userID);


/* Check Whether Asset Can Be Borrowed */
$canBorrow =
    !$isOwner &&
    $asset['listingStatus'] == 'Published' &&
    $asset['verificationStatus'] == 'Approved' &&
    $asset['availabilityStatus'] == 'Available';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Asset Details - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_new_style.css">
    <link rel="stylesheet" href="member_asset_details.css">

</head>


<body class="role-member">

<div class="dashboard-container">


    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>


    <div class="main-content">


        <!-- Header -->
        <?php include '../header.php'; ?>


        <main class="page-body">


            <!-- Page Header -->
            <div class="asset-details-header">

                <div>

                    <h1>Asset Details</h1>

                    <p>
                        View more information about this asset.
                    </p>

                </div>


                <a
                    href="member_marketplace.php"
                    class="asset-back-btn"
                >
                    ← Back to Marketplace
                </a>

            </div>



            <!-- Asset Details Container -->
            <div class="asset-details-wrapper">


                <!-- Main Asset Information -->
                <div class="asset-main-box">


                    <!-- Asset Image -->
                    <div class="asset-image-box">


                        <?php if (!empty($asset['imageURL'])): ?>

                            <img
                                src="../<?php echo htmlspecialchars($asset['imageURL']); ?>"
                                alt="<?php echo htmlspecialchars($asset['assetName']); ?>"
                            >

                        <?php else: ?>

                            <img
                                src="../assets/icons/myrequest.png"
                                alt="Asset Image"
                            >

                        <?php endif; ?>


                    </div>



                    <!-- Asset Information -->
                    <div class="asset-info">


                        <h2>
                            <?php echo htmlspecialchars($asset['assetName']); ?>
                        </h2>


                        <table class="asset-info-table">


                            <!-- Category -->
                            <tr>

                                <td>Category</td>

                                <td>:</td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['categoryName'] ?? '-'
                                    );
                                    ?>
                                </td>

                            </tr>


                            <!-- Condition -->
                            <tr>

                                <td>Condition</td>

                                <td>:</td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['assetCondition']
                                    );
                                    ?>
                                </td>

                            </tr>


                            <!-- Posted Date -->
                            <tr>

                                <td>Posted Date</td>

                                <td>:</td>

                                <td>
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime($asset['createdDate'])
                                    );
                                    ?>
                                </td>

                            </tr>


                            <!-- Asset ID -->
                            <tr>

                                <td>Asset ID</td>

                                <td>:</td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['assetID']
                                    );
                                    ?>
                                </td>

                            </tr>


                            <!-- Availability -->
                            <tr>

                                <td>Availability</td>

                                <td>:</td>

                                <td
                                    class="<?php
                                    echo strtolower($asset['availabilityStatus']) == 'available'
                                        ? 'availability-available'
                                        : 'availability-unavailable';
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $asset['availabilityStatus']
                                    );
                                    ?>

                                </td>

                            </tr>


                            <!-- Owner -->
                            <tr>

                                <td>Owner</td>

                                <td>:</td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $asset['ownerName'] ?? 'Member'
                                    );
                                    ?>
                                </td>

                            </tr>


                        </table>



                        <!-- Request Button -->
                        <div class="asset-detail-actions">


                            <!-- Own Asset -->
                            <?php if ($isOwner): ?>

                                <span class="request-borrow-btn disabled">

                                    Your Asset

                                </span>


                            <!-- Available Asset -->
                            <?php elseif ($canBorrow): ?>

                                <a
                                    href="member_borrow_request.php?id=<?php echo urlencode($asset['assetID']); ?>"
                                    class="request-borrow-btn"
                                >
                                    Request to Borrow
                                </a>


                            <!-- Unavailable Asset -->
                            <?php else: ?>

                                <span class="request-borrow-btn disabled">

                                    Not Available

                                </span>

                            <?php endif; ?>


                        </div>


                    </div>


                </div>



                <!-- Description -->
                <div class="asset-description-box">


                    <h3>Description</h3>


                    <p>
                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $asset['description']
                            )
                        );
                        ?>
                    </p>


                </div>


            </div>


        </main>


    </div>


</div>


</body>

</html>