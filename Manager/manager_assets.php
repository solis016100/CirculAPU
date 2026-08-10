<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Access Control
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

// Filter values
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$conditionFilter = trim($_GET['condition'] ?? '');
$availabilityFilter = trim($_GET['availability'] ?? '');
$listingStatusFilter = trim($_GET['listingStatus'] ?? '');

// Filter options based on the data dictionary
$conditionOptions = [
    'New',
    'Like New',
    'Good',
    'Fair',
    'Poor'
];

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

    // Retrieve active categories
    $categoryQuery = "
        SELECT
            categoryID,
            categoryName
        FROM categories
        WHERE status = 'Active'
        ORDER BY categoryName ASC
    ";

    $categoryResult = $conn->query($categoryQuery);

    while ($category = $categoryResult->fetch_assoc()) {
        $categories[] = $category;
    }

} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());

    $databaseError =
        "Unable to load asset records. Please make sure MySQL is running and the database is available.";
}

if ($conn instanceof mysqli && $databaseError === '') {
    try {
        // Base filter conditions
        $whereConditions = [];
        $parameters = [];
        $parameterTypes = '';

        // Search by Asset ID or Asset Name
        if ($search !== '') {
            $whereConditions[] = "
                (
                    a.assetID LIKE ?
                    OR a.assetName LIKE ?
                )
            ";

            $searchValue = '%' . $search . '%';

            $parameters[] = $searchValue;
            $parameters[] = $searchValue;

            $parameterTypes .= 'ss';
        }

        // Category filter
        if ($categoryFilter !== '') {
            $whereConditions[] = "a.categoryID = ?";
            $parameters[] = $categoryFilter;
            $parameterTypes .= 's';
        }

        // Condition filter
        if ($conditionFilter !== '') {
            $whereConditions[] = "a.assetCondition = ?";
            $parameters[] = $conditionFilter;
            $parameterTypes .= 's';
        }

        // Availability filter
        if ($availabilityFilter !== '') {
            $whereConditions[] = "a.availabilityStatus = ?";
            $parameters[] = $availabilityFilter;
            $parameterTypes .= 's';
        }

        // Listing status filter
        if ($listingStatusFilter !== '') {
            $whereConditions[] = "a.listingStatus = ?";
            $parameters[] = $listingStatusFilter;
            $parameterTypes .= 's';
        } else {
            // Removed assets are hidden by default
            $whereConditions[] = "a.listingStatus <> 'Removed'";
        }

        $whereSQL = '';

        if (!empty($whereConditions)) {
            $whereSQL = 'WHERE ' . implode(' AND ', $whereConditions);
        }

        // Count filtered records for pagination
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM assets a
            $whereSQL
        ";

        $countStatement = $conn->prepare($countQuery);

        if (!empty($parameters)) {
            $bindValues = [];
            $bindValues[] = $parameterTypes;

            foreach ($parameters as $key => $value) {
                $bindValues[] = &$parameters[$key];
            }

            call_user_func_array(
                [$countStatement, 'bind_param'],
                $bindValues
            );
        }

        $countStatement->execute();

        $countResult = $countStatement->get_result();
        $countRow = $countResult->fetch_assoc();

        $totalRecords = (int) $countRow['total'];

        $totalPages = max(
            1,
            (int) ceil($totalRecords / $recordsPerPage)
        );

        // Prevent invalid page numbers
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
            $offset = ($currentPage - 1) * $recordsPerPage;
        }

        $countStatement->close();

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
            INNER JOIN categories c
                ON a.categoryID = c.categoryID
            $whereSQL
            ORDER BY a.createdDate DESC
            LIMIT ? OFFSET ?
        ";

        $assetStatement = $conn->prepare($assetQuery);

        $assetParameters = $parameters;
        $assetParameterTypes = $parameterTypes . 'ii';

        $assetParameters[] = $recordsPerPage;
        $assetParameters[] = $offset;

        $bindValues = [];
        $bindValues[] = $assetParameterTypes;

        foreach ($assetParameters as $key => $value) {
            $bindValues[] = &$assetParameters[$key];
        }

        call_user_func_array(
            [$assetStatement, 'bind_param'],
            $bindValues
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
            "Unable to load asset records. Please try again.";
    }

    $conn->close();
}
?>