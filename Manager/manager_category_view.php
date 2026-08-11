<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../print.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function iconPath($v): string {
    if (!$v) return '';
    $v = str_replace('\\', '/', trim($v));
    return preg_match('/^https?:\/\//i', $v) ? $v : '../' . ltrim(preg_replace('#^(\.\./)+#', '', $v), '/');
}

$categoryID = trim($_GET['categoryID'] ?? '');
if (!$categoryID) { header('Location: manager_categories.php'); exit(); }

$category = null;
$assets = [];
$stats = ['total'=>0,'available'=>0,'pending'=>0,'unavailable'=>0];
$linkedAssets = 0;
$error = '';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("
        SELECT categoryID,categoryName,description,categoryIcon,status,createdDate
        FROM categories WHERE categoryID=? LIMIT 1
    ");
    $stmt->bind_param('s', $categoryID);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$category) {
        $error = 'The selected category could not be found.';
    } else {
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) linked,
                SUM(listingStatus<>'Removed') total,
                SUM(listingStatus='Published' AND verificationStatus='Approved' AND availabilityStatus='Available') available,
                SUM(listingStatus IN ('Draft','Pending Review','Ready for Verification')) pending,
                SUM(availabilityStatus='Unavailable' AND listingStatus<>'Removed') unavailable
            FROM assets WHERE categoryID=?
        ");
        $stmt->bind_param('s', $categoryID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $linkedAssets = (int)($row['linked'] ?? 0);
        foreach ($stats as $k => $v) $stats[$k] = (int)($row[$k] ?? 0);

        $stmt = $conn->prepare("
            SELECT assetID,assetName,assetCondition,availabilityStatus,listingStatus,createdDate
            FROM assets
            WHERE categoryID=? AND listingStatus<>'Removed'
            ORDER BY createdDate DESC LIMIT 5
        ");
        $stmt->bind_param('s', $categoryID);
        $stmt->execute();
        $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $conn->close();
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load category details. Please try again.';
}

$icon = iconPath($category['categoryIcon'] ?? '');
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

<?php if ($error): ?>
<section class="management-heading">
    <div class="breadcrumb">
        <a href="manager_categories.php">Category Management</a>
        <span>›</span><span class="breadcrumb-current">Category Details</span>
    </div>
    <h1>Category Details</h1>
</section>
<div class="alert alert-error"><?= e($error) ?></div>

<?php else: ?>

<!-- Heading -->
<section class="management-heading category-heading">
    <div>
        <div class="breadcrumb">
            <a href="manager_categories.php">Category Management</a>
            <span>›</span><span class="breadcrumb-current">Category Details</span>
        </div>
        <h1>Category Details</h1>
        <p>View and manage category information.</p>
    </div>

    <div class="category-page-actions">
        <a href="manager_category_edit.php?categoryID=<?= e($categoryID) ?>">
            <img src="../assets/icons/edit.png" alt=""> Edit
        </a>

        <?php if ($category['status'] === 'Active'): ?>
        <form method="POST" action="manager_categories.php"
              onsubmit="return confirm('Deactivate this category? Existing assets will not be affected.');">
            <input type="hidden" name="action" value="deactivate">
            <input type="hidden" name="categoryID" value="<?= e($categoryID) ?>">
            <button type="submit" class="deactivate">Deactivate</button>
        </form>
        <?php endif; ?>

        <?php if ($linkedAssets === 0): ?>
        <form method="POST" action="manager_categories.php"
              onsubmit="return confirm('Permanently delete this unused category? This cannot be undone.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="categoryID" value="<?= e($categoryID) ?>">
            <button type="submit" class="delete">
                <img src="../assets/icons/delete.png" alt=""> Delete
            </button>
        </form>
        <?php else: ?>
        <button type="button" disabled title="Cannot delete because assets are linked to this category.">
            <img src="../assets/icons/delete.png" alt=""> Delete
        </button>
        <?php endif; ?>

        <a href="manager_assets.php?category=<?= e($categoryID) ?>">
            <img src="../assets/icons/view.png" alt=""> View Assets
        </a>

        <?php printButton('Print', '../assets/icons/print.png'); ?>
    </div>
</section>

<?php if (($_GET['updated'] ?? '') === '1'): ?>
    <div class="alert alert-success">Category updated successfully.</div>
<?php endif; ?>

<!-- Information + Statistics -->
<section class="category-detail-layout">

<article class="content-card category-info-card">
    <h2>Category Information</h2>

    <div class="category-info-content">
        <div class="category-main-icon">
            <?php if ($icon): ?><img src="<?= e($icon) ?>" alt="<?= e($category['categoryName']) ?>"><?php endif; ?>
        </div>

        <div class="category-info-fields">
            <?php
            $details = [
                'Category ID'   => $category['categoryID'],
                'Category Name' => $category['categoryName'],
                'Description'   => $category['description'] ?: '-',
                'Status'        => $category['status'],
                'Date Created'  => date('d M Y, h:i A', strtotime($category['createdDate']))
            ];
            foreach ($details as $label => $value):
            ?>
            <div class="category-info-row">
                <strong><?= e($label) ?></strong>
                <span><?= e($value) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</article>

<article class="content-card category-stats-card">
    <h2>Category Statistics</h2>
    <div class="category-stats-grid">
        <div class="category-stat"><strong><?= $stats['total'] ?></strong><span>Total Assets</span></div>
        <div class="category-stat"><strong><?= $stats['available'] ?></strong><span>Available Assets</span></div>
        <div class="category-stat"><strong><?= $stats['pending'] ?></strong><span>Pending Assets</span></div>
        <div class="category-stat"><strong><?= $stats['unavailable'] ?></strong><span>Unavailable</span></div>
    </div>
</article>

</section>

<!-- Recent Assets -->
<section class="management-list category-assets-list">
    <div class="management-list-header">
        <h2>Recent Assets in This Category</h2>
        <a href="manager_assets.php?category=<?= e($categoryID) ?>">View All</a>
    </div>

    <div class="management-table-wrapper">
    <table class="management-table">
        <thead>
        <tr>
            <th>#</th><th>Asset ID</th><th>Asset Name</th><th>Condition</th>
            <th>Availability</th><th>Listing Status</th><th>Date Listed</th><th>Action</th>
        </tr>
        </thead>

        <tbody>
        <?php if (!$assets): ?>
            <tr><td colspan="8" class="management-empty-state">No assets found in this category.</td></tr>
        <?php else: foreach ($assets as $i => $a): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= e($a['assetID']) ?></strong></td>
                <td><?= e($a['assetName']) ?></td>
                <td><?= e($a['assetCondition']) ?></td>
                <td><?= e($a['availabilityStatus']) ?></td>
                <td><?= e($a['listingStatus']) ?></td>
                <td><?= date('d M Y', strtotime($a['createdDate'])) ?></td>
                <td>
                    <a href="manager_asset_view.php?assetID=<?= e($a['assetID']) ?>"
                       class="management-action-btn" title="View">
                        <img src="../assets/icons/view.png" alt="View">
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
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
