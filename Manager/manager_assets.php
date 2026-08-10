<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control Guard for Resource Manager Role
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['user_role'] !== 'Manager'
) {
    header("Location: ../login.php");
    exit();
}

// Default values
$assets = [];
$categories = [];

$databaseError = '';

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$conditionFilter = trim($_GET['condition'] ?? '');
$availabilityFilter = trim($_GET['availability'] ?? '');
$listingStatusFilter = trim($_GET['listingStatus'] ?? '');

// Pagination
$currentPage = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

$recordsPerPage = 8;
$totalRecords = 0;
$totalPages = 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Database connection
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

    // Get active categories for the filter
    $categoryResult = $conn->query(
        "SELECT
            categoryID,
            categoryName
        FROM categories
        WHERE status = 'Active'
        ORDER BY categoryName ASC"
    );

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category;
    }

    // Build asset filtering conditions
    $conditions = [];
    $parameters = [];
    $types = '';

    // Do not display removed records by default
    $conditions[] = "a.listingStatus <> 'Removed'";

    if ($search !== '') {
        $conditions[] =
            "(a.assetID LIKE ? OR a.assetName LIKE ?)";

        $searchValue = '%' . $search . '%';

        $parameters[] = $searchValue;
        $parameters[] = $searchValue;

        $types .= 'ss';
    }

    if ($categoryFilter !== '') {
        $conditions[] = "a.categoryID = ?";

        $parameters[] = $categoryFilter;
        $types .= 's';
    }

    if ($conditionFilter !== '') {
        $conditions[] = "a.assetCondition = ?";

        $parameters[] = $conditionFilter;
        $types .= 's';
    }

    if ($availabilityFilter !== '') {
        $conditions[] = "a.availabilityStatus = ?";

        $parameters[] = $availabilityFilter;
        $types .= 's';
    }

    if ($listingStatusFilter !== '') {
        $conditions[] = "a.listingStatus = ?";

        $parameters[] = $listingStatusFilter;
        $types .= 's';
    }

    $whereClause = '';

    if (!empty($conditions)) {
        $whereClause =
            'WHERE ' . implode(' AND ', $conditions);
    }

    // Count filtered records
    $countQuery = "
        SELECT COUNT(*) AS total
        FROM assets a
        $whereClause
    ";

    $countStatement = $conn->prepare($countQuery);

    if (!empty($parameters)) {
        $countStatement->bind_param(
            $types,
            ...$parameters
        );
    }

    $countStatement->execute();

    $countResult = $countStatement->get_result();
    $countRow = $countResult->fetch_assoc();

    $totalRecords = (int) ($countRow['total'] ?? 0);

    $countStatement->close();

    $totalPages = max(
        1,
        (int) ceil(
            $totalRecords / $recordsPerPage
        )
    );

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset =
            ($currentPage - 1) * $recordsPerPage;
    }

    // Retrieve asset records
    $assetQuery = "
        SELECT
            a.assetID,
            a.assetName,
            a.assetCondition,
            a.availabilityStatus,
            a.listingStatus,
            a.createdDate,
            c.categoryName
        FROM assets a
        LEFT JOIN categories c
            ON a.categoryID = c.categoryID
        $whereClause
        ORDER BY a.createdDate DESC
        LIMIT ? OFFSET ?
    ";

    $assetStatement = $conn->prepare($assetQuery);

    $assetParameters = $parameters;
    $assetTypes = $types . 'ii';

    $assetParameters[] = $recordsPerPage;
    $assetParameters[] = $offset;

    $assetStatement->bind_param(
        $assetTypes,
        ...$assetParameters
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

// Build pagination links while preserving filters
function buildAssetPageUrl(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;

    return '?' . http_build_query($query);
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
        Asset Management - CirculAPU
    </title>

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

            <!-- Page Heading -->
            <section
                class="management-heading management-heading-action"
            >

                <div class="management-heading-text">

                    <h1>Asset Management</h1>

                    <p>
                        Manage asset listings available in
                        the CirculAPU community.
                    </p>

                </div>

                <a
                    href="manager_asset_create.php"
                    class="management-create-btn"
                >
                    <img
                        src="../assets/icons/create.png"
                        alt=""
                    >

                    Add New Asset
                </a>

            </section>


            <?php if (!empty($databaseError)): ?>

                <div class="dashboard-error-message">

                    <?php
                        echo htmlspecialchars(
                            $databaseError
                        );
                    ?>

                </div>

            <?php endif; ?>


            <!-- Asset Filters -->
            <section class="management-filter">

                <form
                    method="GET"
                    action="manager_assets.php"
                >

                    <!-- Search -->
                    <div
                        class="management-filter-group search"
                    >

                        <label for="search">
                            Search
                        </label>

                        <div
                            class="management-search-input"
                        >

                            <img
                                src="../assets/icons/search.png"
                                alt=""
                            >

                            <input
                                type="text"
                                id="search"
                                name="search"
                                placeholder="Search Asset ID or Asset Name"
                                value="<?php
                                    echo htmlspecialchars(
                                        $search
                                    );
                                ?>"
                            >

                        </div>

                    </div>


                    <!-- Category -->
                    <div class="management-filter-group">

                        <label for="category">
                            Category
                        </label>

                        <select
                            id="category"
                            name="category"
                        >

                            <option value="">
                                All Categories
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $category['categoryID']
                                        );
                                    ?>"
                                    <?php
                                        echo
                                            $categoryFilter ===
                                            $category['categoryID']
                                                ? 'selected'
                                                : '';
                                    ?>
                                >
                                    <?php
                                        echo htmlspecialchars(
                                            $category['categoryName']
                                        );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Condition -->
                    <div class="management-filter-group">

                        <label for="condition">
                            Condition
                        </label>

                        <select
                            id="condition"
                            name="condition"
                        >

                            <option value="">
                                All Conditions
                            </option>

                            <option
                                value="New"
                                <?php
                                    echo $conditionFilter === 'New'
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                New
                            </option>

                            <option
                                value="Good"
                                <?php
                                    echo $conditionFilter === 'Good'
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                Good
                            </option>

                            <option
                                value="Fair"
                                <?php
                                    echo $conditionFilter === 'Fair'
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                Fair
                            </option>

                            <option
                                value="Poor"
                                <?php
                                    echo $conditionFilter === 'Poor'
                                        ? 'selected'
                                        : '';
                                ?>
                            >
                                Poor
                            </option>

                        </select>

                    </div>


                    <!-- Availability -->
                    <div class="management-filter-group">

                        <label for="availability">
                            Availability
                        </label>

                        <select
                            id="availability"
                            name="availability"
                        >

                            <option value="">
                                All Availability
                            </option>

                            <option
                                value="Available"
                                <?php
                                    echo
                                        $availabilityFilter ===
                                        'Available'
                                            ? 'selected'
                                            : '';
                                ?>
                            >
                                Available
                            </option>

                            <option
                                value="Borrowed"
                                <?php
                                    echo
                                        $availabilityFilter ===
                                        'Borrowed'
                                            ? 'selected'
                                            : '';
                                ?>
                            >
                                Borrowed
                            </option>

                            <option
                                value="Unavailable"
                                <?php
                                    echo
                                        $availabilityFilter ===
                                        'Unavailable'
                                            ? 'selected'
                                            : '';
                                ?>
                            >
                                Unavailable
                            </option>

                        </select>

                    </div>


                    <!-- Listing Status -->
                    <div class="management-filter-group">

                        <label for="listingStatus">
                            Listing Status
                        </label>

                        <select
                            id="listingStatus"
                            name="listingStatus"
                        >

                            <option value="">
                                All Status
                            </option>

                            <option
                                value="Active"
                                <?php
                                    echo
                                        $listingStatusFilter ===
                                        'Active'
                                            ? 'selected'
                                            : '';
                                ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?php
                                    echo
                                        $listingStatusFilter ===
                                        'Inactive'
                                            ? 'selected'
                                            : '';
                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- Filter Buttons -->
                    <div
                        class="management-filter-buttons"
                    >

                        <button
                            type="submit"
                            class="management-filter-btn"
                        >

                            <img
                                src="../assets/icons/filter.png"
                                alt=""
                            >

                            Filter

                        </button>

                        <a
                            href="manager_assets.php"
                            class="management-reset-btn"
                        >

                            <img
                                src="../assets/icons/reset.png"
                                alt=""
                            >

                            Reset

                        </a>

                    </div>

                </form>

            </section>


            <!-- Asset Listing -->
            <section class="management-list">

                <div class="management-list-header">

                    <h2>Asset Listings</h2>

                    <p>
                        Total:
                        <strong>
                            <?php echo $totalRecords; ?>
                        </strong>
                    </p>

                </div>


                <div class="management-table-wrapper">

                    <table
                        class="management-table asset-table"
                    >

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

                                <td
                                    colspan="9"
                                    class="management-empty-state"
                                >
                                    No asset records found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php
                                foreach (
                                    $assets as $index => $asset
                                ):
                            ?>

                                <tr>

                                    <td>
                                        <?php
                                            echo
                                                $offset +
                                                $index +
                                                1;
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['assetID']
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['assetName']
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['categoryName']
                                                ?? '-'
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['assetCondition']
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['availabilityStatus']
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo htmlspecialchars(
                                                $asset['listingStatus']
                                            );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                            echo date(
                                                'd M Y',
                                                strtotime(
                                                    $asset['createdDate']
                                                )
                                            );
                                        ?>
                                    </td>

                                    <td>

                                        <div
                                            class="management-actions"
                                        >

                                            <!-- View -->
                                            <a
                                                href="manager_asset_view.php?id=<?php
                                                    echo urlencode(
                                                        $asset['assetID']
                                                    );
                                                ?>"
                                                class="management-action-btn"
                                                title="View Asset"
                                                aria-label="View Asset"
                                            >
                                                <img
                                                    src="../assets/icons/view.png"
                                                    alt=""
                                                >
                                            </a>


                                            <!-- Edit -->
                                            <a
                                                href="manager_asset_edit.php?id=<?php
                                                    echo urlencode(
                                                        $asset['assetID']
                                                    );
                                                ?>"
                                                class="management-action-btn"
                                                title="Edit Asset"
                                                aria-label="Edit Asset"
                                            >
                                                <img
                                                    src="../assets/icons/edit.png"
                                                    alt=""
                                                >
                                            </a>


                                            <!-- Remove -->
                                            <form
                                                method="POST"
                                                action="manager_asset_remove.php"
                                                onsubmit="return confirm('Are you sure you want to remove this asset?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="assetID"
                                                    value="<?php
                                                        echo htmlspecialchars(
                                                            $asset['assetID']
                                                        );
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="management-action-btn delete"
                                                    title="Remove Asset"
                                                    aria-label="Remove Asset"
                                                >
                                                    <img
                                                        src="../assets/icons/delete.png"
                                                        alt=""
                                                    >
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- Pagination -->
                <div class="management-table-footer">

                    <p>
                        Showing
                        <?php
                            echo empty($assets)
                                ? 0
                                : $offset + 1;
                        ?>
                        -
                        <?php
                            echo min(
                                $offset +
                                count($assets),
                                $totalRecords
                            );
                        ?>
                        of
                        <?php echo $totalRecords; ?>
                        records
                    </p>


                    <?php if ($totalPages > 1): ?>

                        <nav
                            class="pagination"
                            aria-label="Asset pages"
                        >

                            <?php if ($currentPage > 1): ?>

                                <a
                                    href="<?php
                                        echo htmlspecialchars(
                                            buildAssetPageUrl(
                                                $currentPage - 1
                                            )
                                        );
                                    ?>"
                                    aria-label="Previous page"
                                >
                                    &lsaquo;
                                </a>

                            <?php endif; ?>


                            <?php
                                for (
                                    $page = 1;
                                    $page <= $totalPages;
                                    $page++
                                ):
                            ?>

                                <a
                                    href="<?php
                                        echo htmlspecialchars(
                                            buildAssetPageUrl(
                                                $page
                                            )
                                        );
                                    ?>"
                                    class="<?php
                                        echo
                                            $page === $currentPage
                                                ? 'active'
                                                : '';
                                    ?>"
                                >
                                    <?php echo $page; ?>
                                </a>

                            <?php endfor; ?>


                            <?php if ($currentPage < $totalPages): ?>

                                <a
                                    href="<?php
                                        echo htmlspecialchars(
                                            buildAssetPageUrl(
                                                $currentPage + 1
                                            )
                                        );
                                    ?>"
                                    aria-label="Next page"
                                >
                                    &rsaquo;
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