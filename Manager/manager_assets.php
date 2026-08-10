<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$assets = [];
$categories = [];
$databaseError = '';

// Filter Options
$conditionOptions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];

$availabilityOptions = [
    'Available',
    'Reserved',
    'Borrowed',
    'Unavailable'
];

$listingStatusOptions = [
    'Draft',
    'Pending Review',
    'Ready for Verification',
    'Approved',
    'Published',
    'Rejected',
    'Removed'
];

// Filter Values
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$conditionFilter = trim($_GET['condition'] ?? '');

$availabilityFilter = trim(
    $_GET['availability']
    ?? $_GET['availabilityStatus']
    ?? ''
);

$listingStatusFilter = trim($_GET['listingStatus'] ?? '');
$verificationFilter = trim($_GET['verificationStatus'] ?? '');

// Pagination
$recordsPerPage = 8;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

$totalRecords = 0;
$totalPages = 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Format Asset Date
function formatManagerAssetDate(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date('d M Y', $timestamp)
        : '-';
}

// Keep Filters During Pagination
function buildAssetPageURL(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;

    return 'manager_assets.php?' . http_build_query($query);
}

$conn = null;

try {
    $conn = new mysqli(
        "127.0.0.1",
        "root",
        "",
        "circulapu_db",
        3306
    );

    $conn->set_charset("utf8mb4");

    // Retrieve Active Categories
    $categoryResult = $conn->query("
        SELECT categoryID, categoryName
        FROM categories
        WHERE status = 'Active'
        ORDER BY categoryName ASC
    ");

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category;
    }

    // Asset Filter Conditions
    $whereSQL = "
        WHERE
        (
            ? = ''
            OR a.assetID LIKE CONCAT('%', ?, '%')
            OR a.assetName LIKE CONCAT('%', ?, '%')
        )
        AND (? = '' OR a.categoryID = ?)
        AND (? = '' OR a.assetCondition = ?)
        AND (? = '' OR a.availabilityStatus = ?)
        AND (
            (? <> '' AND a.listingStatus = ?)
            OR
            (? = '' AND a.listingStatus <> 'Removed')
        )
        AND (? = '' OR a.verificationStatus = ?)
    ";

    // Count Filtered Assets
    $countStatement = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM assets a
        $whereSQL
    ");

    $countStatement->bind_param(
        "ssssssssssssss",
        $search,
        $search,
        $search,
        $categoryFilter,
        $categoryFilter,
        $conditionFilter,
        $conditionFilter,
        $availabilityFilter,
        $availabilityFilter,
        $listingStatusFilter,
        $listingStatusFilter,
        $listingStatusFilter,
        $verificationFilter,
        $verificationFilter
    );

    $countStatement->execute();

    $countRow = $countStatement->get_result()->fetch_assoc();

    $totalRecords = (int) ($countRow['total'] ?? 0);
    $totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));

    $countStatement->close();

    // Prevent Invalid Page Number
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $recordsPerPage;

    // Retrieve Asset Records
    $assetStatement = $conn->prepare("
        SELECT
            a.assetID,
            a.assetName,
            a.assetCondition,
            a.availabilityStatus,
            a.listingStatus,
            a.verificationStatus,
            a.createdDate,
            c.categoryName
        FROM assets a
        INNER JOIN categories c
            ON a.categoryID = c.categoryID
        $whereSQL
        ORDER BY a.createdDate DESC
        LIMIT ? OFFSET ?
    ");

    $assetStatement->bind_param(
        "ssssssssssssssii",
        $search,
        $search,
        $search,
        $categoryFilter,
        $categoryFilter,
        $conditionFilter,
        $conditionFilter,
        $availabilityFilter,
        $availabilityFilter,
        $listingStatusFilter,
        $listingStatusFilter,
        $listingStatusFilter,
        $verificationFilter,
        $verificationFilter,
        $recordsPerPage,
        $offset
    );

    $assetStatement->execute();

    $assetResult = $assetStatement->get_result();

    while ($asset = $assetResult->fetch_assoc()) {
        $assets[] = $asset;
    }

    $assetStatement->close();

} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to load asset records. Please make sure MySQL is running and the database is available.";
}

if ($conn instanceof mysqli) {
    $conn->close();
}

// Table Record Range
$startRecord = $totalRecords > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $recordsPerPage, $totalRecords);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asset Management - CirculAPU</title>

    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager.css">
    <link rel="stylesheet" href="../darktheme.css">
</head>

<body class="role-manager">

<div class="dashboard-container">

    <?php include '../sidebar.php'; ?>

    <div class="main-content">

        <?php include '../header.php'; ?>

        <main class="page-body">

            <!-- 1. Page Heading -->
            <section class="management-heading management-heading-action">

                <div class="management-heading-text">
                    <h1>Asset Management</h1>
                    <p>
                        Manage asset listings available in the
                        CirculAPU resource-sharing community.
                    </p>
                </div>

                <a href="manager_asset_create.php" class="management-create-btn">
                    <img src="../assets/icons/create.png" alt="">
                    <span>Add New Asset</span>
                </a>

            </section>

            <!-- 2. Database Error -->
            <?php if (!empty($databaseError)): ?>
                <div class="dashboard-error-message">
                    <?php echo htmlspecialchars($databaseError); ?>
                </div>
            <?php endif; ?>

            <!-- 3. Asset Filters -->
            <section class="management-filter">

                <form action="manager_assets.php" method="GET">

                    <!-- Search -->
                    <div class="management-filter-group search">
                        <label for="search">Search Asset</label>

                        <div class="management-search-input">
                            <img src="../assets/icons/search.png" alt="">

                            <input
                                type="text"
                                id="search"
                                name="search"
                                placeholder="Asset ID or Asset Name"
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="management-filter-group">
                        <label for="category">Category</label>

                        <select id="category" name="category">
                            <option value="">All Categories</option>

                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?php echo htmlspecialchars($category['categoryID']); ?>"
                                    <?php echo $categoryFilter === $category['categoryID'] ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($category['categoryName']); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Condition -->
                    <div class="management-filter-group">
                        <label for="condition">Condition</label>

                        <select id="condition" name="condition">
                            <option value="">All Conditions</option>

                            <?php foreach ($conditionOptions as $condition): ?>
                                <option
                                    value="<?php echo htmlspecialchars($condition); ?>"
                                    <?php echo $conditionFilter === $condition ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($condition); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Availability -->
                    <div class="management-filter-group">
                        <label for="availability">Availability</label>

                        <select id="availability" name="availability">
                            <option value="">All Availability</option>

                            <?php foreach ($availabilityOptions as $availability): ?>
                                <option
                                    value="<?php echo htmlspecialchars($availability); ?>"
                                    <?php echo $availabilityFilter === $availability ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($availability); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Listing Status -->
                    <div class="management-filter-group">
                        <label for="listingStatus">Listing Status</label>

                        <select id="listingStatus" name="listingStatus">
                            <option value="">All Status</option>

                            <?php foreach ($listingStatusOptions as $status): ?>
                                <option
                                    value="<?php echo htmlspecialchars($status); ?>"
                                    <?php echo $listingStatusFilter === $status ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Dashboard Verification Filter -->
                    <?php if ($verificationFilter !== ''): ?>
                        <input
                            type="hidden"
                            name="verificationStatus"
                            value="<?php echo htmlspecialchars($verificationFilter); ?>"
                        >
                    <?php endif; ?>

                    <!-- Filter Actions -->
                    <div class="management-filter-buttons">

                        <button type="submit" class="management-filter-btn">
                            <img src="../assets/icons/filter.png" alt="">
                            <span>Filter</span>
                        </button>

                        <a href="manager_assets.php" class="management-reset-btn">
                            <img src="../assets/icons/reset.png" alt="">
                            <span>Reset</span>
                        </a>

                    </div>

                </form>

            </section>

            <!-- 4. Asset Listings -->
            <section class="management-list">

                <div class="management-list-header">
                    <h2>Asset Listings</h2>

                    <p>
                        <?php echo $totalRecords; ?>
                        asset<?php echo $totalRecords === 1 ? '' : 's'; ?> found
                    </p>
                </div>

                <div class="management-table-wrapper">

                    <table class="management-table">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Asset ID</th>
                                <th>Asset Name</th>
                                <th>Categories</th>
                                <th>Condition</th>
                                <th>Availability</th>
                                <th>Listing Status</th>
                                <th>Date Listed</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (empty($assets)): ?>

                            <tr>
                                <td colspan="9" class="management-empty-state">
                                    No asset records found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($assets as $index => $asset): ?>
                                <?php $rowNumber = $offset + $index + 1; ?>

                                <tr>
                                    <td><?php echo $rowNumber; ?></td>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($asset['assetID']); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($asset['assetName']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($asset['categoryName']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($asset['assetCondition']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($asset['availabilityStatus']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($asset['listingStatus']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            formatManagerAssetDate($asset['createdDate'])
                                        ); ?>
                                    </td>

                                    <td>
                                        <div class="management-actions">

                                            <a
                                                href="manager_asset_view.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                                class="management-action-btn"
                                                title="View Asset"
                                            >
                                                <img src="../assets/icons/view.png" alt="View">
                                            </a>

                                            <a
                                                href="manager_asset_edit.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                                class="management-action-btn"
                                                title="Edit Asset"
                                            >
                                                <img src="../assets/icons/edit.png" alt="Edit">
                                            </a>

                                            <a
                                                href="manager_asset_delete.php?assetID=<?php echo urlencode($asset['assetID']); ?>"
                                                class="management-action-btn delete"
                                                title="Remove Asset"
                                            >
                                                <img src="../assets/icons/delete.png" alt="Remove">
                                            </a>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <!-- 5. Table Footer -->
                <div class="management-table-footer">

                    <p>
                        Showing
                        <?php echo $startRecord; ?> -
                        <?php echo $endRecord; ?>
                        of <?php echo $totalRecords; ?> assets
                    </p>

                    <?php if ($totalPages > 1): ?>

                        <nav class="pagination" aria-label="Asset pagination">

                            <?php if ($currentPage > 1): ?>
                                <a href="<?php echo htmlspecialchars(
                                    buildAssetPageURL($currentPage - 1)
                                ); ?>">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <a
                                    href="<?php echo htmlspecialchars(buildAssetPageURL($page)); ?>"
                                    class="<?php echo $page === $currentPage ? 'active' : ''; ?>"
                                >
                                    <?php echo $page; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?php echo htmlspecialchars(
                                    buildAssetPageURL($currentPage + 1)
                                ); ?>">
                                    Next
                                </a>
                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                </div>

            </section>

        </main>

    </div>

</div>

</body>
</html>