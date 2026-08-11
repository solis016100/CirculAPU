<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

require_once '../delete.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function assetDate(?string $date): string {
    return $date && strtotime($date) ? date('d M Y', strtotime($date)) : '-';
}

function pageURL(int $page): string {
    $q = $_GET;
    $q['page'] = $page;
    return 'manager_assets.php?' . http_build_query($q);
}

$managerID = $_SESSION['user_id'];
$assets = $categories = [];
$error = '';

$conditions = ['New', 'Like New', 'Good', 'Fair', 'Poor'];
$availabilities = ['Available', 'Reserved', 'Borrowed', 'Unavailable'];
$statuses = ['Draft', 'Pending Review', 'Ready for Verification',
             'Approved', 'Published', 'Rejected', 'Removed'];

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$availability = trim($_GET['availability'] ?? $_GET['availabilityStatus'] ?? '');
$status = trim($_GET['listingStatus'] ?? '');
$verification = trim($_GET['verificationStatus'] ?? '');

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $offset = 0;
$totalPages = 1;
$conn = null;

try {
    $conn = new mysqli("127.0.0.1", "root", "", "circulapu_db", 3306);
    $conn->set_charset("utf8mb4");

    /* 1. Soft Remove */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
        $removeID = trim($_POST['assetID'] ?? '');

        $stmt = $conn->prepare("
            SELECT assetName, listingStatus, availabilityStatus
            FROM assets WHERE assetID = ? LIMIT 1
        ");
        $stmt->bind_param("s", $removeID);
        $stmt->execute();
        $removeAsset = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$removeAsset) {
            $error = "Asset not found.";
        } elseif ($removeAsset['listingStatus'] === 'Removed') {
            $error = "This asset has already been removed.";
        } elseif (in_array($removeAsset['availabilityStatus'], ['Reserved', 'Borrowed'], true)) {
            $error = "Reserved or borrowed assets cannot be removed during an active transaction.";
        } else {
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("
                    UPDATE assets
                    SET listingStatus = 'Removed', updatedDate = CURRENT_TIMESTAMP
                    WHERE assetID = ?
                ");
                $stmt->bind_param("s", $removeID);
                $stmt->execute();
                $stmt->close();

                createDeleteLog(
                    $conn,
                    $managerID,
                    'Asset',
                    $removeID,
                    "Removed asset listing: {$removeAsset['assetName']}"
                );

                $conn->commit();
                header("Location: manager_assets.php?removed=1");
                exit();

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
    }

    /* 2. Categories */
    $result = $conn->query("
        SELECT categoryID, categoryName
        FROM categories
        WHERE status = 'Active'
        ORDER BY categoryName
    ");
    while ($row = $result->fetch_assoc()) $categories[] = $row;

    /* 3. Filters */
    $where = "
        WHERE (? = '' OR a.assetID LIKE CONCAT('%', ?, '%')
                       OR a.assetName LIKE CONCAT('%', ?, '%'))
        AND (? = '' OR a.categoryID = ?)
        AND (? = '' OR a.assetCondition = ?)
        AND (? = '' OR a.availabilityStatus = ?)
        AND ((? <> '' AND a.listingStatus = ?)
             OR (? = '' AND a.listingStatus <> 'Removed'))
        AND (? = '' OR a.verificationStatus = ?)
    ";

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM assets a $where");
    $stmt->bind_param(
        "ssssssssssssss",
        $search, $search, $search,
        $category, $category,
        $condition, $condition,
        $availability, $availability,
        $status, $status, $status,
        $verification, $verification
    );
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare("
        SELECT a.assetID, a.assetName, a.assetCondition,
               a.availabilityStatus, a.listingStatus, a.createdDate,
               c.categoryName
        FROM assets a
        JOIN categories c ON a.categoryID = c.categoryID
        $where
        ORDER BY a.createdDate DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param(
        "ssssssssssssssii",
        $search, $search, $search,
        $category, $category,
        $condition, $condition,
        $availability, $availability,
        $status, $status, $status,
        $verification, $verification,
        $perPage, $offset
    );
    $stmt->execute();

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $assets[] = $row;
    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = "Unable to load asset records. Please try again.";
}

if ($conn instanceof mysqli) $conn->close();

$start = $total ? $offset + 1 : 0;
$end = min($offset + $perPage, $total);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

    <!-- 1. Heading -->
    <section class="management-heading">
        <div class="management-heading-text">
            <h1>Asset Management</h1>
            <p>Review and manage asset listings submitted by CirculAPU Community Members.</p>
        </div>
    </section>

    <!-- 2. Messages -->
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (($_GET['removed'] ?? '') === '1'): ?>
        <div class="alert alert-success">Asset listing removed successfully.</div>
    <?php endif; ?>

    <?php if (($_GET['reviewed'] ?? '') === '1'): ?>
        <div class="alert alert-success">Asset review completed successfully.</div>
    <?php endif; ?>

    <!-- 3. Filters -->
    <section class="management-filter">
        <form method="GET">

            <div class="management-filter-group search">
                <label for="search">Search Asset</label>
                <div class="management-search-input">
                    <img src="../assets/icons/search.png" alt="">
                    <input type="text" id="search" name="search"
                           placeholder="Asset ID or Asset Name" value="<?= e($search) ?>">
                </div>
            </div>

            <div class="management-filter-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['categoryID']) ?>"
                            <?= $category === $c['categoryID'] ? 'selected' : '' ?>>
                            <?= e($c['categoryName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="management-filter-group">
                <label for="condition">Condition</label>
                <select id="condition" name="condition">
                    <option value="">All Conditions</option>
                    <?php foreach ($conditions as $v): ?>
                        <option value="<?= e($v) ?>" <?= $condition === $v ? 'selected' : '' ?>>
                            <?= e($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="management-filter-group">
                <label for="availability">Availability</label>
                <select id="availability" name="availability">
                    <option value="">All Availability</option>
                    <?php foreach ($availabilities as $v): ?>
                        <option value="<?= e($v) ?>" <?= $availability === $v ? 'selected' : '' ?>>
                            <?= e($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="management-filter-group">
                <label for="listingStatus">Listing Status</label>
                <select id="listingStatus" name="listingStatus">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $v): ?>
                        <option value="<?= e($v) ?>" <?= $status === $v ? 'selected' : '' ?>>
                            <?= e($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($verification): ?>
                <input type="hidden" name="verificationStatus" value="<?= e($verification) ?>">
            <?php endif; ?>

            <div class="management-filter-buttons">
                <button type="submit" class="management-filter-btn">
                    <img src="../assets/icons/filter.png" alt=""> <span>Filter</span>
                </button>
                <a href="manager_assets.php" class="management-reset-btn">
                    <img src="../assets/icons/reset.png" alt=""> <span>Reset</span>
                </a>
            </div>

        </form>
    </section>

    <!-- 4. Asset List -->
    <section class="management-list">

        <div class="management-list-header">
            <h2>Asset Listings</h2>
            <p><?= $total ?> asset<?= $total === 1 ? '' : 's' ?> found</p>
        </div>

        <div class="management-table-wrapper">
            <table class="management-table">

                <thead>
                <tr>
                    <th>#</th>
                    <th>Asset ID</th>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Condition</th>
                    <th>Availability</th>
                    <th>Listing Status</th>
                    <th>Date Listed</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>

                <?php if (!$assets): ?>
                    <tr>
                        <td colspan="9" class="management-empty-state">
                            No asset records found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($assets as $i => $asset): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td><strong><?= e($asset['assetID']) ?></strong></td>
                        <td><?= e($asset['assetName']) ?></td>
                        <td><?= e($asset['categoryName']) ?></td>
                        <td><?= e($asset['assetCondition']) ?></td>
                        <td><?= e($asset['availabilityStatus']) ?></td>
                        <td><?= e($asset['listingStatus']) ?></td>
                        <td><?= e(assetDate($asset['createdDate'])) ?></td>

                        <td>
                            <div class="management-actions">

                                <a href="manager_asset_view.php?assetID=<?= e($asset['assetID']) ?>"
                                   class="management-action-btn" title="View Asset">
                                    <img src="../assets/icons/view.png" alt="View">
                                </a>

                                <?php if ($asset['listingStatus'] === 'Pending Review'): ?>
                                    <a href="review_asset_submission.php?assetID=<?= e($asset['assetID']) ?>"
                                       class="management-action-btn" title="Review Asset Submission">
                                        <img src="../assets/icons/edit.png" alt="Review">
                                    </a>
                                <?php elseif ($asset['listingStatus'] !== 'Removed'): ?>
                                    <a href="manager_asset_edit.php?assetID=<?= e($asset['assetID']) ?>"
                                       class="management-action-btn" title="Edit Asset Information">
                                        <img src="../assets/icons/edit.png" alt="Edit">
                                    </a>
                                <?php endif; ?>

                                <?php if (
                                    $asset['listingStatus'] !== 'Removed' &&
                                    !in_array($asset['availabilityStatus'], ['Reserved', 'Borrowed'], true)
                                ): ?>
                                    <form method="POST"
                                        onsubmit="return confirm('Remove this asset listing? The record will be retained for auditing.');">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="assetID" value="<?= e($asset['assetID']) ?>">

                                        <button type="submit"
                                                class="management-action-btn delete"
                                                title="Remove Asset">
                                            <img src="../assets/icons/delete.png" alt="Remove">
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- 5. Pagination -->
        <div class="management-table-footer">

            <p>Showing <?= $start ?> - <?= $end ?> of <?= $total ?> assets</p>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Asset pagination">

                    <?php if ($page > 1): ?>
                        <a href="<?= e(pageURL($page - 1)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?= e(pageURL($p)) ?>"
                           class="<?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e(pageURL($page + 1)) ?>">Next</a>
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