<?php
session_start();

/* Member Access */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Member') {
    header("Location: ../login.php");
    exit();
}

/* Member Information */
$memberID = $_SESSION['user_id'];
$memberName = $_SESSION['user_name'] ?? 'Member';

/* Database */
require_once '../db.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


/* Get Filter Values */
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';
$sort = $_GET['sort'] ?? 'latest';


/* Get Categories */
$categoryQuery = "
SELECT
    categoryID,
    categoryName
FROM categories
ORDER BY categoryName ASC
";

$categoryResult = $conn->query($categoryQuery);


/* Safe Member ID */
$safeMemberID = $conn->real_escape_string($memberID);


/* Get Marketplace Assets */
$assetQuery = "
SELECT
    a.assetID,
    a.ownerID,
    a.categoryID,
    a.assetName,
    a.description,
    a.assetCondition,
    a.imageURL,
    a.availabilityStatus,
    a.listingStatus,
    a.createdDate,
    a.verificationStatus,
    c.categoryName,
    u.name AS ownerName
FROM assets a

LEFT JOIN categories c
ON a.categoryID = c.categoryID

LEFT JOIN user u
ON a.ownerID = u.userID

WHERE a.availabilityStatus = 'Available'
AND a.listingStatus = 'Published'
AND a.verificationStatus = 'Approved'

/* Do Not Show Own Assets */
AND a.ownerID <> '$safeMemberID'
";


/* Search Asset */
if ($search != '') {

    $safeSearch = $conn->real_escape_string($search);

    $assetQuery .= "
    AND (
        a.assetName LIKE '%$safeSearch%'
        OR a.description LIKE '%$safeSearch%'
        OR c.categoryName LIKE '%$safeSearch%'
    )
    ";
}


/* Category Filter */
if ($category != '') {

    $safeCategory = $conn->real_escape_string($category);

    $assetQuery .= "
    AND a.categoryID = '$safeCategory'
    ";
}


/* Condition Filter */
if ($condition != '') {

    $safeCondition = $conn->real_escape_string($condition);

    $assetQuery .= "
    AND a.assetCondition = '$safeCondition'
    ";
}


/* Sort Assets */
if ($sort == 'name') {

    $assetQuery .= "
    ORDER BY a.assetName ASC
    ";

} elseif ($sort == 'oldest') {

    $assetQuery .= "
    ORDER BY a.createdDate ASC
    ";

} else {

    $assetQuery .= "
    ORDER BY a.createdDate DESC
    ";
}


$assetResult = $conn->query($assetQuery);

if (!$assetResult) {
    die("Asset Query Failed: " . $conn->error);
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

    <title>Assets Marketplace - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="member_style.css">

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
            <div class="marketplace-header">


                <div>

                    <h1>
                        Assets Marketplace
                    </h1>

                    <p>
                        Browse and borrow reusable assets shared by the CirculAPU community.
                    </p>

                </div>


                <a
                    href="member_list_asset.php"
                    class="marketplace-add-btn"
                >
                    + List New Asset
                </a>


            </div>



            <!-- Search and Filter -->
            <div class="marketplace-filter-box">


                <form
                    method="GET"
                    class="marketplace-filter-form"
                >


                    <!-- Search -->
                    <div
                        class="marketplace-filter-group marketplace-search"
                    >

                        <label>
                            Search Asset
                        </label>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search asset name..."
                        >

                    </div>



                    <!-- Category -->
                    <div class="marketplace-filter-group">


                        <label>
                            Category
                        </label>


                        <select name="category">


                            <option value="">
                                All Categories
                            </option>


                            <?php
                            if (
                                $categoryResult &&
                                $categoryResult->num_rows > 0
                            ):
                            ?>


                                <?php
                                while (
                                    $categoryRow =
                                    $categoryResult->fetch_assoc()
                                ):
                                ?>


                                    <option
                                        value="<?php
                                        echo htmlspecialchars(
                                            $categoryRow['categoryID']
                                        );
                                        ?>"
                                        <?php
                                        echo
                                        $category == $categoryRow['categoryID']
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $categoryRow['categoryName']
                                        );
                                        ?>

                                    </option>


                                <?php endwhile; ?>


                            <?php endif; ?>


                        </select>


                    </div>



                    <!-- Condition -->
                    <div class="marketplace-filter-group">


                        <label>
                            Condition
                        </label>


                        <select name="condition">


                            <option value="">
                                All Conditions
                            </option>


                            <option
                                value="New"
                                <?php
                                echo $condition == 'New'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                New
                            </option>


                            <option
                                value="Like New"
                                <?php
                                echo $condition == 'Like New'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Like New
                            </option>


                            <option
                                value="Good"
                                <?php
                                echo $condition == 'Good'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Good
                            </option>


                            <option
                                value="Fair"
                                <?php
                                echo $condition == 'Fair'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Fair
                            </option>


                        </select>


                    </div>



                    <!-- Sort -->
                    <div class="marketplace-filter-group">


                        <label>
                            Sort By
                        </label>


                        <select name="sort">


                            <option
                                value="latest"
                                <?php
                                echo $sort == 'latest'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Latest
                            </option>


                            <option
                                value="oldest"
                                <?php
                                echo $sort == 'oldest'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Oldest
                            </option>


                            <option
                                value="name"
                                <?php
                                echo $sort == 'name'
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                Asset Name
                            </option>


                        </select>


                    </div>



                    <!-- Buttons -->
                    <div class="marketplace-filter-buttons">


                        <button
                            type="submit"
                            class="marketplace-filter-btn"
                        >
                            Filter
                        </button>


                        <a
                            href="member_marketplace.php"
                            class="marketplace-reset-btn"
                        >
                            Reset
                        </a>


                    </div>


                </form>


            </div>



            <!-- Result Header -->
            <div class="marketplace-result-header">


                <h2>
                    Available Assets
                </h2>


                <span>

                    <?php
                    echo $assetResult->num_rows;
                    ?>
                    assets found

                </span>


            </div>



            <!-- Asset Cards -->
            <div class="marketplace-grid">


                <?php if ($assetResult->num_rows > 0): ?>


                    <?php
                    while (
                        $asset =
                        $assetResult->fetch_assoc()
                    ):
                    ?>


                        <div class="marketplace-card">


                            <!-- Asset Image -->
                            <div class="marketplace-card-image">


                                <?php
                                if (!empty($asset['imageURL'])):
                                ?>


                                    <img
                                        src="../<?php
                                        echo htmlspecialchars(
                                            $asset['imageURL']
                                        );
                                        ?>"
                                        alt="<?php
                                        echo htmlspecialchars(
                                            $asset['assetName']
                                        );
                                        ?>"
                                    >


                                <?php else: ?>


                                    <div class="marketplace-no-image">

                                        No Image

                                    </div>


                                <?php endif; ?>


                            </div>



                            <!-- Asset Information -->
                            <div class="marketplace-card-body">


                                <span class="marketplace-category">

                                    <?php
                                    echo htmlspecialchars(
                                        $asset['categoryName']
                                        ?? 'Uncategorized'
                                    );
                                    ?>

                                </span>


                                <h3>

                                    <?php
                                    echo htmlspecialchars(
                                        $asset['assetName']
                                    );
                                    ?>

                                </h3>



                                <p class="marketplace-owner">

                                    Posted by:

                                    <?php
                                    echo htmlspecialchars(
                                        $asset['ownerName']
                                        ?? $asset['ownerID']
                                    );
                                    ?>

                                </p>



                                <p class="marketplace-description">

                                    <?php

                                    $description =
                                        $asset['description'] ?? '';

                                    if (
                                        strlen($description) > 90
                                    ) {

                                        $description =
                                            substr(
                                                $description,
                                                0,
                                                90
                                            )
                                            . '...';
                                    }

                                    echo htmlspecialchars(
                                        $description
                                    );

                                    ?>

                                </p>



                                <div class="marketplace-card-info">


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
                                            Status
                                        </span>

                                        <strong
                                            class="marketplace-available"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $asset['availabilityStatus']
                                            );
                                            ?>

                                        </strong>

                                    </div>


                                </div>



                                <!-- View Details -->
                                <a
                                    href="member_asset_details.php?id=<?php
                                    echo urlencode(
                                        $asset['assetID']
                                    );
                                    ?>"
                                    class="marketplace-view-btn"
                                >
                                    View Details
                                </a>


                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="marketplace-empty">

                        No available assets found.

                    </div>


                <?php endif; ?>


            </div>


        </main>


    </div>


</div>


</body>

</html>