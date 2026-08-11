<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../delete.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function validDate($v): bool {
    if (!$v) return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function iconPath($v): string {
    if (!$v) return '';
    return preg_match('/^https?:\/\//i', $v) ? $v : '../' . ltrim($v, '/');
}
function pageURL($query, $page): string {
    $query['page'] = $page;
    return '?' . http_build_query(array_filter($query, fn($v) => $v !== ''));
}

$managerID = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$categories = [];
$error = '';

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    /* Actions */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $categoryID = trim($_POST['categoryID'] ?? '');

        $stmt = $conn->prepare("SELECT categoryName,status FROM categories WHERE categoryID=?");
        $stmt->bind_param('s', $categoryID);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$category) {
            header('Location: manager_categories.php?error=notfound'); exit();
        }

        /* Deactivate = Update */
        if ($action === 'deactivate') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE categories SET status='Inactive' WHERE categoryID=?");
                $stmt->bind_param('s', $categoryID);
                $stmt->execute();
                $stmt->close();

                $logID = generateLogID($conn);
                $desc = 'Deactivated category: ' . $category['categoryName'];
                $stmt = $conn->prepare("
                    INSERT INTO systemlog(logID,userID,actionType,refType,refID,description)
                    VALUES (?,?,'Update','Category',?,?)
                ");
                $stmt->bind_param('ssss', $logID, $managerID, $categoryID, $desc);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                header('Location: manager_categories.php?deactivated=1'); exit();
            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }

        /* Permanent Delete */
        if ($action === 'delete') {
            $stmt = $conn->prepare("SELECT COUNT(*) total FROM assets WHERE categoryID=?");
            $stmt->bind_param('s', $categoryID);
            $stmt->execute();
            $linked = (int)$stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            if ($linked > 0) {
                header('Location: manager_categories.php?error=linked'); exit();
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM categories WHERE categoryID=?");
                $stmt->bind_param('s', $categoryID);
                $stmt->execute();
                $stmt->close();

                createDeleteLog(
                    $conn, $managerID, 'Category', $categoryID,
                    'Permanently deleted category: ' . $category['categoryName']
                );

                $conn->commit();
                header('Location: manager_categories.php?deleted=1'); exit();
            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }
    }

    /* Filters */
    if ($status && !in_array($status, ['Active','Inactive'], true)) $status = '';

    if (!validDate($dateFrom) || !validDate($dateTo))
        $error = 'Please enter a valid date.';
    elseif ($dateFrom && $dateTo && $dateFrom > $dateTo)
        $error = 'Date From cannot be later than Date To.';

    $where = [];
    $params = [];
    $types = '';

    if ($search) {
        $where[] = "(c.categoryID LIKE ? OR c.categoryName LIKE ? OR c.description LIKE ?)";
        $like = "%$search%";
        array_push($params, $like, $like, $like);
        $types .= 'sss';
    }
    if ($status) { $where[] = "c.status=?"; $params[] = $status; $types .= 's'; }
    if ($dateFrom) { $where[] = "DATE(c.createdDate)>=?"; $params[] = $dateFrom; $types .= 's'; }
    if ($dateTo) { $where[] = "DATE(c.createdDate)<=?"; $params[] = $dateTo; $types .= 's'; }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if (!$error) {
        $stmt = $conn->prepare("SELECT COUNT(*) total FROM categories c $whereSQL");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $totalRows = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $listParams = $params;
        $listParams[] = $perPage;
        $listParams[] = $offset;

        $stmt = $conn->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM assets a WHERE a.categoryID=c.categoryID) linkedAssets
            FROM categories c
            $whereSQL
            ORDER BY c.createdDate DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types . 'ii', ...$listParams);
        $stmt->execute();
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $totalRows = 0;
        $totalPages = 1;
    }

    $conn->close();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load Category Management. Please try again.';
    $totalRows = 0;
    $totalPages = 1;
}

$query = compact('search','status','dateFrom','dateTo');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
</head>

<body class="role-manager">
<div class="dashboard-container">

<?php include '../sidebar.php'; ?>

<div class="main-content">
<?php include '../header.php'; ?>

<main class="page-body">

<section class="management-heading">
    <div>
        <h1>Category Management</h1>
        <p>Create, edit and manage asset categories.</p>
    </div>

    <div class="management-heading-action">
        <a href="manager_category_create.php" class="management-create-btn">
            <img src="../assets/icons/create.png" alt=""> Add New Category
        </a>
    </div>
</section>

<!-- Messages -->
<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (($_GET['created'] ?? '') === '1'): ?>
    <div class="alert alert-success">Category created successfully.</div>
<?php elseif (($_GET['updated'] ?? '') === '1'): ?>
    <div class="alert alert-success">Category updated successfully.</div>
<?php elseif (($_GET['deactivated'] ?? '') === '1'): ?>
    <div class="alert alert-success">Category deactivated successfully. Existing assets were not affected.</div>
<?php elseif (($_GET['deleted'] ?? '') === '1'): ?>
    <div class="alert alert-success">Category permanently deleted successfully.</div>
<?php elseif (($_GET['error'] ?? '') === 'linked'): ?>
    <div class="alert alert-error">This category has linked assets and cannot be permanently deleted. Deactivate it instead.</div>
<?php elseif (($_GET['error'] ?? '') === 'notfound'): ?>
    <div class="alert alert-error">Category not found.</div>
<?php endif; ?>

<!-- Filters -->
<section class="content-card management-filter">
<form method="GET">
    <div class="management-filter-group">

        <div class="management-filter-field">
            <label>Search</label>
            <input name="search" placeholder="Category ID, name or description" value="<?= e($search) ?>">
        </div>

        <div class="management-filter-field">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="management-filter-field">
            <label>Date From</label>
            <input type="date" name="dateFrom" value="<?= e($dateFrom) ?>">
        </div>

        <div class="management-filter-field">
            <label>Date To</label>
            <input type="date" name="dateTo" value="<?= e($dateTo) ?>">
        </div>

    </div>

    <div class="management-filter-buttons">
        <button class="management-filter-submit">
            <img src="../assets/icons/search.png" alt=""> Search
        </button>

        <a href="manager_categories.php" class="management-filter-reset">
            <img src="../assets/icons/reset.png" alt=""> Reset
        </a>
    </div>
</form>
</section>

<!-- List -->
<section class="management-list">

<div class="management-list-header">
    <h2>Category List</h2>
    <span><?= $totalRows ?> categor<?= $totalRows === 1 ? 'y' : 'ies' ?></span>
</div>

<div class="management-table-wrapper">
<table class="management-table">

<thead>
<tr>
    <th>#</th>
    <th>Category ID</th>
    <th>Category Name</th>
    <th>Description</th>
    <th>Status</th>
    <th>Date Created</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php if (!$categories): ?>
<tr>
    <td colspan="7" class="management-empty-state">No categories found.</td>
</tr>

<?php else: foreach ($categories as $i => $c):
    $icon = iconPath($c['categoryIcon']);
    $canDelete = (int)$c['linkedAssets'] === 0;
?>

<tr>
    <td><?= (($page - 1) * $perPage) + $i + 1 ?></td>
    <td><strong><?= e($c['categoryID']) ?></strong></td>

    <td>
        <div class="category-name-cell">
            <?php if ($icon): ?><img src="<?= e($icon) ?>" alt=""><?php endif; ?>
            <span><?= e($c['categoryName']) ?></span>
        </div>
    </td>

    <td><?= e($c['description'] ?: '-') ?></td>

    <td>
        <span class="status-badge <?= strtolower($c['status']) ?>">
            <?= e($c['status']) ?>
        </span>
    </td>

    <td><?= date('d M Y', strtotime($c['createdDate'])) ?></td>

    <td>
        <div class="management-actions">

            <a href="manager_category_view.php?categoryID=<?= e($c['categoryID']) ?>"
               class="management-action-btn" title="View">
                <img src="../assets/icons/view.png" alt="View">
            </a>

            <a href="manager_category_edit.php?categoryID=<?= e($c['categoryID']) ?>"
               class="management-action-btn" title="Edit">
                <img src="../assets/icons/edit.png" alt="Edit">
            </a>

            <?php if ($c['status'] === 'Active'): ?>
            <form method="POST"
                  onsubmit="return confirm('Deactivate this category? Existing assets will not be affected.');">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="categoryID" value="<?= e($c['categoryID']) ?>">
                <button class="management-action-btn" title="Deactivate">
                    <img src="../assets/icons/delete.png" alt="Deactivate">
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canDelete): ?>
            <form method="POST"
                  onsubmit="return confirm('Permanently delete this unused category? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="categoryID" value="<?= e($c['categoryID']) ?>">
                <button class="management-action-btn" title="Delete Permanently">
                    <img src="../assets/icons/delete.png" alt="Delete">
                </button>
            </form>
            <?php endif; ?>

        </div>
    </td>
</tr>

<?php endforeach; endif; ?>

</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="management-pagination">

    <?php if ($page > 1): ?>
        <a href="<?= e(pageURL($query, $page - 1)) ?>">‹ Previous</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="<?= e(pageURL($query, $p)) ?>" class="<?= $p === $page ? 'active' : '' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= e(pageURL($query, $page + 1)) ?>">Next ›</a>
    <?php endif; ?>

</div>
<?php endif; ?>

</section>

</main>
</div>
</div>
</body>
</html>