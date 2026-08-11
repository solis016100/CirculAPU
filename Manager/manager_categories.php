<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../delete.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function categoryDate(?string $date): string {
    return $date && strtotime($date) ? date('d M Y', strtotime($date)) : '-';
}

function validDate(string $date): bool {
    if ($date === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function pageURL(int $page): string {
    $q = $_GET;
    $q['page'] = $page;
    return 'manager_categories.php?' . http_build_query($q);
}

$managerID = $_SESSION['user_id'];

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');

$categories = [];
$error = '';

if (!in_array($status, ['', 'Active', 'Inactive'], true)) $status = '';
if (!validDate($dateFrom)) $dateFrom = '';
if (!validDate($dateTo)) $dateTo = '';

$queryDateFrom = $dateFrom;
$queryDateTo = $dateTo;

if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $error = 'Date From cannot be later than Date To.';
    $queryDateFrom = $queryDateTo = '';
}

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));

$total = $offset = 0;
$totalPages = 1;

$hasCreate = file_exists(__DIR__ . '/manager_category_create.php');
$hasView = file_exists(__DIR__ . '/manager_category_view.php');
$hasEdit = file_exists(__DIR__ . '/manager_category_edit.php');

$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    // 1. Deactivate Category
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && ($_POST['action'] ?? '') === 'deactivate') {

        $categoryID = trim($_POST['categoryID'] ?? '');

        $stmt = $conn->prepare("
            SELECT categoryName, status
            FROM categories
            WHERE categoryID = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $categoryID);
        $stmt->execute();

        $category = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$category) {
            $error = 'Category not found.';

        } elseif ($category['status'] === 'Inactive') {
            $error = 'This category is already inactive.';

        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("
                    UPDATE categories
                    SET status = 'Inactive'
                    WHERE categoryID = ?
                ");

                $stmt->bind_param('s', $categoryID);
                $stmt->execute();
                $stmt->close();

                createDeleteLog(
                    $conn,
                    $managerID,
                    'Category',
                    $categoryID,
                    "Deactivated category: {$category['categoryName']}"
                );

                $conn->commit();

                header('Location: manager_categories.php?deactivated=1');
                exit();

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
    }

    // 2. Search and Filter
    $where = "
        WHERE (
            ? = ''
            OR categoryID LIKE CONCAT('%', ?, '%')
            OR categoryName LIKE CONCAT('%', ?, '%')
            OR description LIKE CONCAT('%', ?, '%')
        )
        AND (? = '' OR status = ?)
        AND (? = '' OR DATE(createdDate) >= ?)
        AND (? = '' OR DATE(createdDate) <= ?)
    ";

    // 3. Count
    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM categories
        $where
    ");

    $stmt->bind_param(
        'ssssssssss',
        $search, $search, $search, $search,
        $status, $status,
        $queryDateFrom, $queryDateFrom,
        $queryDateTo, $queryDateTo
    );

    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // 4. Category List
    $stmt = $conn->prepare("
        SELECT categoryID, categoryName, description,
            categoryIcon, status, createdDate
        FROM categories
        $where
        ORDER BY createdDate DESC, categoryName ASC
        LIMIT ? OFFSET ?
    ");

    $stmt->bind_param(
        'ssssssssssii',
        $search, $search, $search, $search,
        $status, $status,
        $queryDateFrom, $queryDateFrom,
        $queryDateTo, $queryDateTo,
        $perPage, $offset
    );

    $stmt->execute();

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $categories[] = $row;

    $stmt->close();

} catch (Throwable $e) {
    error_log($e->getMessage());

    $error = $error ?:
        'Unable to load category records. Please try again.';
}

if ($conn instanceof mysqli) $conn->close();

$start = $total ? $offset + 1 : 0;
$end = min($offset + $perPage, $total);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

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

    <!-- 1. Heading -->
    <section class="management-heading management-heading-action">

        <div class="management-heading-text">
            <h1>Category Management</h1>

            <p>
                Manage all asset categories in the
                CirculAPU platform.
            </p>
        </div>

        <?php if ($hasCreate): ?>

            <a
                href="manager_category_create.php"
                class="management-create-btn"
            >
                <img src="../assets/icons/create.png" alt="">
                Add New Category
            </a>

        <?php else: ?>

            <a
                href="#"
                class="management-create-btn"
                onclick="alert('Create Category page will be built next.'); return false;"
            >
                <img src="../assets/icons/create.png" alt="">
                Add New Category
            </a>

        <?php endif; ?>

    </section>

    <!-- 2. Messages -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (($_GET['deactivated'] ?? '') === '1'): ?>
        <div class="alert alert-success">
            Category deactivated successfully.
        </div>
    <?php endif; ?>

    <?php if (($_GET['created'] ?? '') === '1'): ?>
        <div class="alert alert-success">
            Category created successfully.
        </div>
    <?php endif; ?>

    <?php if (($_GET['updated'] ?? '') === '1'): ?>
        <div class="alert alert-success">
            Category updated successfully.
        </div>
    <?php endif; ?>

    <!-- 3. Filters -->
    <section class="management-filter">

        <form method="GET">

            <div class="management-filter-group search">

                <label for="search">
                    Search Category
                </label>

                <div class="management-search-input">

                    <img
                        src="../assets/icons/search.png"
                        alt=""
                    >

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Category ID or Category Name"
                    >

                </div>

            </div>

            <div class="management-filter-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                >
                    <option value="">
                        All Status
                    </option>

                    <option
                        value="Active"
                        <?= $status === 'Active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?= $status === 'Inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="management-filter-group">

                <label for="dateFrom">
                    Date From
                </label>

                <input
                    type="date"
                    id="dateFrom"
                    name="dateFrom"
                    value="<?= e($dateFrom) ?>"
                >

            </div>

            <div class="management-filter-group">

                <label for="dateTo">
                    Date To
                </label>

                <input
                    type="date"
                    id="dateTo"
                    name="dateTo"
                    value="<?= e($dateTo) ?>"
                >

            </div>

            <div class="management-filter-buttons">

                <button
                    type="submit"
                    class="management-filter-btn"
                >
                    <img
                        src="../assets/icons/filter.png"
                        alt=""
                    >

                    <span>Filter</span>
                </button>

                <a
                    href="manager_categories.php"
                    class="management-reset-btn"
                >
                    <img
                        src="../assets/icons/reset.png"
                        alt=""
                    >

                    <span>Reset</span>
                </a>

            </div>

        </form>

    </section>

    <!-- 4. Category List -->
    <section class="management-list">

        <div class="management-list-header">

            <h2>Category List</h2>

            <p>
                <?= $total ?>
                categor<?= $total === 1 ? 'y' : 'ies' ?>
                found
            </p>

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
                        <td
                            colspan="7"
                            class="management-empty-state"
                        >
                            No category records found.
                        </td>
                    </tr>

                <?php endif; ?>

                <?php foreach ($categories as $i => $category): ?>

                    <tr>

                        <td>
                            <?= $offset + $i + 1 ?>
                        </td>

                        <td>
                            <strong>
                                <?= e($category['categoryID']) ?>
                            </strong>
                        </td>

                        <td>
                            <div class="category-name-cell">

                                <?php if (!empty($category['categoryIcon'])): ?>
                                    <img
                                        src="../<?= e($category['categoryIcon']) ?>"
                                        alt=""
                                    >
                                <?php endif; ?>

                                <span><?= e($category['categoryName']) ?></span>

                            </div>
                        </td>

                        <td>
                            <?= e($category['description'] ?: '-') ?>
                        </td>

                        <td>
                            <span class="status-badge">
                                <?= e($category['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(categoryDate(
                                $category['createdDate']
                            )) ?>
                        </td>

                        <td>

                            <div class="management-actions">

                                <!-- View -->
                                <?php if ($hasView): ?>

                                    <a
                                        href="manager_category_view.php?categoryID=<?= e($category['categoryID']) ?>"
                                        class="management-action-btn"
                                        title="View Category"
                                    >
                                        <img
                                            src="../assets/icons/view.png"
                                            alt="View"
                                        >
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="management-action-btn"
                                        title="View Category"
                                        onclick="alert('Category Details page will be built later.');"
                                    >
                                        <img
                                            src="../assets/icons/view.png"
                                            alt="View"
                                        >
                                    </button>

                                <?php endif; ?>

                                <!-- Edit -->
                                <?php if ($hasEdit): ?>

                                    <a
                                        href="manager_category_edit.php?categoryID=<?= e($category['categoryID']) ?>"
                                        class="management-action-btn"
                                        title="Edit Category"
                                    >
                                        <img
                                            src="../assets/icons/edit.png"
                                            alt="Edit"
                                        >
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="management-action-btn"
                                        title="Edit Category"
                                        onclick="alert('Edit Category page will be built later.');"
                                    >
                                        <img
                                            src="../assets/icons/edit.png"
                                            alt="Edit"
                                        >
                                    </button>

                                <?php endif; ?>

                                <!-- Deactivate -->
                                <?php if ($category['status'] === 'Active'): ?>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Deactivate this category? Existing assets will remain linked to it.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >

                                        <input
                                            type="hidden"
                                            name="categoryID"
                                            value="<?= e($category['categoryID']) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="management-action-btn delete"
                                            title="Deactivate Category"
                                        >
                                            <img
                                                src="../assets/icons/delete.png"
                                                alt="Deactivate"
                                            >
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

            <p>
                Showing <?= $start ?> - <?= $end ?>
                of <?= $total ?>
                categor<?= $total === 1 ? 'y' : 'ies' ?>
            </p>

            <?php if ($totalPages > 1): ?>

                <nav
                    class="pagination"
                    aria-label="Category pagination"
                >

                    <?php if ($page > 1): ?>

                        <a href="<?= e(pageURL($page - 1)) ?>">
                            Previous
                        </a>

                    <?php endif; ?>

                    <?php for (
                        $p = 1;
                        $p <= $totalPages;
                        $p++
                    ): ?>

                        <a
                            href="<?= e(pageURL($p)) ?>"
                            class="<?= $p === $page
                                ? 'active'
                                : '' ?>"
                        >
                            <?= $p ?>
                        </a>

                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>

                        <a href="<?= e(pageURL($page + 1)) ?>">
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