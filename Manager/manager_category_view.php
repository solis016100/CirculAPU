<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../print.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function categoryDate(?string $date): string {
    return $date && strtotime($date)
        ? date('d M Y, h:i A', strtotime($date))
        : '-';
}

function iconPath(?string $url): string {
    if (!$url) return '';

    $url = str_replace('\\', '/', trim($url));

    return preg_match('/^https?:\/\//i', $url)
        ? $url
        : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $url), '/');
}

$categoryID = trim($_GET['categoryID'] ?? '');

if ($categoryID === '') {
    header('Location: manager_categories.php'); exit();
}

$category = null;
$recentAssets = [];
$stats = [
    'total' => 0,
    'available' => 0,
    'pending' => 0,
    'unavailable' => 0
];

$error = '';
$hasEdit = file_exists(__DIR__ . '/manager_category_edit.php');
$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    // 1. Category
    $stmt = $conn->prepare("
        SELECT categoryID, categoryName, description,
               categoryIcon, status, createdDate
        FROM categories
        WHERE categoryID = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $categoryID);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$category) {
        $error = 'The selected category could not be found.';
    }

    // 2. Statistics
    if ($category) {
        $stmt = $conn->prepare("
            SELECT
                SUM(listingStatus <> 'Removed') total,
                SUM(
                    listingStatus = 'Published'
                    AND verificationStatus = 'Approved'
                    AND availabilityStatus = 'Available'
                ) available,
                SUM(
                    listingStatus IN (
                        'Draft',
                        'Pending Review',
                        'Ready for Verification'
                    )
                ) pending,
                SUM(
                    availabilityStatus = 'Unavailable'
                    AND listingStatus <> 'Removed'
                ) unavailable
            FROM assets
            WHERE categoryID = ?
        ");
        $stmt->bind_param('s', $categoryID);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        foreach ($stats as $key => $value) {
            $stats[$key] = (int)($row[$key] ?? 0);
        }

        $stmt->close();

        // 3. Recent Assets
        $stmt = $conn->prepare("
            SELECT assetID, assetName, assetCondition,
                   availabilityStatus, listingStatus, createdDate
            FROM assets
            WHERE categoryID = ?
              AND listingStatus <> 'Removed'
            ORDER BY createdDate DESC
            LIMIT 5
        ");
        $stmt->bind_param('s', $categoryID);
        $stmt->execute();

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $recentAssets[] = $row;

        $stmt->close();
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load category details. Please try again.';
}

if ($conn instanceof mysqli) $conn->close();

$categoryIcon = iconPath($category['categoryIcon'] ?? null);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Details - CirculAPU</title>

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
    <section class="management-heading category-heading">
        <div>
            <div class="breadcrumb">
                <a href="manager_categories.php">Category Management</a>
                <span>›</span>
                <span class="breadcrumb-current">Category Details</span>
            </div>

            <h1>Category Details</h1>
            <p>View and manage category information.</p>
        </div>

        <div class="category-page-actions">

            <?php if ($hasEdit): ?>
                <a href="manager_category_edit.php?categoryID=<?= e($categoryID) ?>">
                    <img src="../assets/icons/edit.png" alt=""> Edit
                </a>
            <?php else: ?>
                <button type="button"
                        onclick="alert('Edit Category page will be built next.');">
                    <img src="../assets/icons/edit.png" alt=""> Edit
                </button>
            <?php endif; ?>

            <?php if ($category && $category['status'] === 'Active'): ?>
                <form method="POST" action="manager_categories.php"
                    onsubmit="return confirm('Deactivate this category? Existing assets will remain linked to it.');">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="categoryID" value="<?= e($categoryID) ?>">

                    <button type="submit" class="delete">
                        <img src="../assets/icons/delete.png" alt=""> Deactivate
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($category): ?>
                <a href="manager_assets.php?category=<?= e($categoryID) ?>">
                    <img src="../assets/icons/view.png" alt=""> View Assets
                </a>

                <?php printButton('Print', '../assets/icons/print.png'); ?>
            <?php endif; ?>

        </div>
    </section>

    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

        <a href="manager_categories.php"
           class="management-create-btn">
            Back to Category Management
        </a>

    <?php elseif ($category): ?>

        <!-- 2. Details -->
        <section class="category-detail-layout">

            <!-- Category Information -->
            <article class="content-card category-info-card">

                <h2>Category Information</h2>

                <div class="category-info-content">

                    <div class="category-main-icon">

                        <?php if ($categoryIcon): ?>
                            <img
                                src="<?= e($categoryIcon) ?>"
                                alt="<?= e($category['categoryName']) ?>"
                            >
                        <?php endif; ?>

                    </div>

                    <div class="category-info-fields">

                        <?php
                        $details = [
                            'Category ID' => $category['categoryID'],
                            'Category Name' => $category['categoryName'],
                            'Description' => $category['description'] ?: '-',
                            'Status' => $category['status'],
                            'Date Created' => categoryDate($category['createdDate'])
                        ];
                        ?>

                        <?php foreach ($details as $label => $value): ?>

                            <div class="category-info-row">
                                <strong><?= e($label) ?></strong>
                                <span><?= e($value) ?></span>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </article>

            <!-- Statistics -->
            <article class="content-card category-stats-card">

                <h2>Category Statistics</h2>

                <div class="category-stats-grid">

                    <div class="category-stat">
                        <strong><?= $stats['total'] ?></strong>
                        <span>Total Assets</span>
                    </div>

                    <div class="category-stat">
                        <strong><?= $stats['available'] ?></strong>
                        <span>Available Assets</span>
                    </div>

                    <div class="category-stat">
                        <strong><?= $stats['pending'] ?></strong>
                        <span>Pending Assets</span>
                    </div>

                    <div class="category-stat">
                        <strong><?= $stats['unavailable'] ?></strong>
                        <span>Unavailable</span>
                    </div>

                </div>

            </article>
            
        </section>

        <!-- 3. Recent Assets -->
        <section class="management-list category-assets-list">

            <div class="management-list-header">

                <h2>Recent Assets in This Category</h2>

                <a href="manager_assets.php?category=<?= e($categoryID) ?>">
                    View All
                </a>

            </div>

            <div class="management-table-wrapper">

                <table class="management-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset ID</th>
                            <th>Asset Name</th>
                            <th>Condition</th>
                            <th>Availability</th>
                            <th>Listing Status</th>
                            <th>Date Listed</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$recentAssets): ?>

                        <tr>
                            <td colspan="8"
                                class="management-empty-state">
                                No assets found in this category.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentAssets as $i => $asset): ?>

                            <tr>

                                <td><?= $i + 1 ?></td>

                                <td>
                                    <strong>
                                        <?= e($asset['assetID']) ?>
                                    </strong>
                                </td>

                                <td><?= e($asset['assetName']) ?></td>

                                <td>
                                    <?= e($asset['assetCondition']) ?>
                                </td>

                                <td>
                                    <?= e($asset['availabilityStatus']) ?>
                                </td>

                                <td>
                                    <?= e($asset['listingStatus']) ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd M Y',
                                        strtotime($asset['createdDate'])
                                    )) ?>
                                </td>

                                <td>

                                    <a
                                        href="manager_asset_view.php?assetID=<?= e($asset['assetID']) ?>"
                                        class="management-action-btn"
                                        title="View Asset"
                                    >
                                        <img
                                            src="../assets/icons/view.png"
                                            alt="View"
                                        >
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

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