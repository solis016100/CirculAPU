<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING($column,4) AS UNSIGNED)),0)+1 nextID
        FROM $table
    ")->fetch_assoc();

    return sprintf($prefix . '%03d', (int)$row['nextID']);
}

$icons = [
    'assets/icons/category-electronics.png'    => 'Electronics',
    'assets/icons/category-furniture.png'      => 'Furniture & Household',
    'assets/icons/category-books.png'          => 'Books',
    'assets/icons/category-stationery.png'     => 'Stationery',
    'assets/icons/category-tools.png'          => 'Tools & Equipment',
    'assets/icons/category-sports.png'         => 'Sports Equipment',
    'assets/icons/category-kitchenware.png'    => 'Kitchenware',
    'assets/icons/category-studyequipment.png' => 'Study Equipment',
    'assets/icons/category-clothing.png'       => 'Clothing',
    'assets/icons/category-gaming.png'         => 'Gaming & Recreation',
    'assets/icons/category-music.png'          => 'Music',
    'assets/icons/category-others.png'         => 'Others'
];

$managerID = $_SESSION['user_id'];
$name = $description = $categoryIcon = '';
$status = 'Active';
$error = '';
$conn = null;

try {
    $conn = new mysqli('127.0.0.1', 'root', '', 'circulapu_db', 3306);
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['categoryName'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryIcon = trim($_POST['categoryIcon'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($name === '') {
            $error = 'Category Name is required.';
        } elseif (strlen($name) > 100) {
            $error = 'Category Name must not exceed 100 characters.';
        } elseif (strlen($description) > 255) {
            $error = 'Description must not exceed 255 characters.';
        } elseif (!isset($icons[$categoryIcon])) {
            $error = 'Please select a valid category icon.';
        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $error = 'Please select a valid category status.';
        }

        // Duplicate Category Name
        if (!$error) {
            $stmt = $conn->prepare("
                SELECT categoryID
                FROM categories
                WHERE categoryName = ?
                LIMIT 1
            ");
            $stmt->bind_param('s', $name);
            $stmt->execute();

            if ($stmt->get_result()->fetch_assoc()) {
                $error = 'A category with this name already exists.';
            }

            $stmt->close();
        }

        // Create Category
        if (!$error) {
            try {
                $conn->begin_transaction();

                $categoryID = nextID($conn, 'categories', 'categoryID', 'CAT');

                $stmt = $conn->prepare("
                    INSERT INTO categories
                        (categoryID, categoryName, description, categoryIcon, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'sssss',
                    $categoryID, $name, $description, $categoryIcon, $status
                );
                $stmt->execute();
                $stmt->close();

                // System Log
                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');
                $logText = "Created category: $name";

                $stmt = $conn->prepare("
                    INSERT INTO systemlog
                        (logID, userID, actionType, refType, refID, description)
                    VALUES (?, ?, 'Create', 'Category', ?, ?)
                ");
                $stmt->bind_param(
                    'ssss',
                    $logID, $managerID, $categoryID, $logText
                );
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                header('Location: manager_categories.php?created=1');
                exit();

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $error ?: 'Unable to create the category. Please try again.';
}

if ($conn instanceof mysqli) $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create New Category - CirculAPU</title>

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
    <div class="breadcrumb">
        <a href="manager_categories.php">Category Management</a>
        <span>›</span>
        <span class="breadcrumb-current">Create New Category</span>
    </div>

    <h1>Create New Category</h1>
    <p>Add a new asset category to help organize CirculAPU resources.</p>
</section>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<section class="content-card category-form-card">

<form method="POST">

<div class="category-create-layout">

    <!-- Left Form -->
    <div class="category-form-fields">

        <div class="category-form-row">
            <label for="categoryName">Category Name *</label>

            <div>
                <input
                    type="text"
                    id="categoryName"
                    name="categoryName"
                    maxlength="100"
                    value="<?= e($name) ?>"
                    placeholder="Enter category name"
                    required
                >
                <small>Enter a clear and short category name.</small>
            </div>
        </div>

        <div class="category-form-row category-form-textarea">
            <label for="description">Description</label>

            <div>
                <textarea
                    id="description"
                    name="description"
                    maxlength="255"
                    rows="5"
                    placeholder="Enter category description"
                ><?= e($description) ?></textarea>

                <small>Provide a brief description of this category.</small>
            </div>
        </div>

        <div class="category-form-row">
            <label>Category Icon *</label>

            <div class="category-selected-icon">
                <div class="category-selected-preview" id="selectedIconBox">
                    <?php if ($categoryIcon): ?>
                        <img
                            id="selectedIcon"
                            src="../<?= e($categoryIcon) ?>"
                            alt=""
                        >
                    <?php else: ?>
                        <span id="iconPlaceholder">Choose an icon</span>
                        <img id="selectedIcon" src="" alt="" hidden>
                    <?php endif; ?>
                </div>

                <small>Select an icon that best represents this category.</small>
            </div>
        </div>

        <div class="category-form-row">
            <label for="status">Status *</label>

            <div>
                <select id="status" name="status" required>
                    <option value="Active"
                        <?= $status === 'Active' ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option value="Inactive"
                        <?= $status === 'Inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>

                <small>Active categories can be used for new asset listings.</small>
            </div>
        </div>

    </div>

    <!-- Right Icon Panel -->
    <aside class="category-icon-panel">

        <h2>Select Category Icon</h2>

        <div class="category-icon-grid">

            <?php foreach ($icons as $path => $label): ?>

                <?php $id = 'icon-' . md5($path); ?>

                <label
                    class="category-icon-option <?= $categoryIcon === $path ? 'selected' : '' ?>"
                    for="<?= e($id) ?>"
                    title="<?= e($label) ?>"
                >
                    <input
                        type="radio"
                        id="<?= e($id) ?>"
                        name="categoryIcon"
                        value="<?= e($path) ?>"
                        data-label="<?= e($label) ?>"
                        <?= $categoryIcon === $path ? 'checked' : '' ?>
                        required
                    >

                    <img src="../<?= e($path) ?>" alt="<?= e($label) ?>">
                </label>

            <?php endforeach; ?>

        </div>

        <div class="category-icon-preview">
            <h3>Icon Preview</h3>

            <div class="category-preview-content">

                <div class="category-preview-image">
                    <?php if ($categoryIcon): ?>
                        <img
                            id="previewIcon"
                            src="../<?= e($categoryIcon) ?>"
                            alt=""
                        >
                    <?php else: ?>
                        <img id="previewIcon" src="" alt="" hidden>
                    <?php endif; ?>
                </div>

                <div>
                    <strong id="previewName">
                        <?= e($name ?: 'Category Name') ?>
                    </strong>

                    <p id="previewDescription">
                        <?= e($description ?: 'Category description will appear here.') ?>
                    </p>
                </div>

            </div>
        </div>

    </aside>

</div>

<!-- Actions -->
<div class="category-form-actions">

    <a href="manager_categories.php" class="category-form-back">
        Back
    </a>

    <button type="reset" class="category-form-reset" id="clearForm">
        <img src="../assets/icons/reset.png" alt="">
        Clear Form
    </button>

    <button type="submit" class="category-form-submit">
        <img src="../assets/icons/create.png" alt="">
        Create Category
    </button>

</div>

</form>

</section>

</main>
</div>
</div>

<script>
const nameInput = document.getElementById('categoryName');
const descInput = document.getElementById('description');
const selectedIcon = document.getElementById('selectedIcon');
const previewIcon = document.getElementById('previewIcon');
const placeholder = document.getElementById('iconPlaceholder');
const previewName = document.getElementById('previewName');
const previewDescription = document.getElementById('previewDescription');

document.querySelectorAll('input[name="categoryIcon"]').forEach(input => {
    input.addEventListener('change', () => {
        document.querySelectorAll('.category-icon-option')
            .forEach(option => option.classList.remove('selected'));

        input.closest('.category-icon-option').classList.add('selected');

        const src = '../' + input.value;

        selectedIcon.src = src;
        selectedIcon.hidden = false;

        previewIcon.src = src;
        previewIcon.hidden = false;

        if (placeholder) placeholder.hidden = true;
    });
});

nameInput.addEventListener('input', () => {
    previewName.textContent = nameInput.value.trim() || 'Category Name';
});

descInput.addEventListener('input', () => {
    previewDescription.textContent =
        descInput.value.trim() || 'Category description will appear here.';
});

document.getElementById('clearForm').addEventListener('click', () => {
    setTimeout(() => {
        document.querySelectorAll('.category-icon-option')
            .forEach(option => option.classList.remove('selected'));

        selectedIcon.hidden = true;
        previewIcon.hidden = true;

        if (placeholder) placeholder.hidden = false;

        previewName.textContent = 'Category Name';
        previewDescription.textContent =
            'Category description will appear here.';
    });
});
</script>

</body>
</html>