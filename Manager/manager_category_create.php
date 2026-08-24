<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $start = strlen($prefix) + 1;
    $like = $prefix . '%';
    $stmt = $conn->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING($column,$start) AS UNSIGNED)),0)+1 nextID FROM $table WHERE $column LIKE ?");
    $stmt->bind_param('s', $like); $stmt->execute();
    $next = (int)$stmt->get_result()->fetch_assoc()['nextID']; $stmt->close();
    return sprintf($prefix . '%03d', $next);
}

$icons = [
    'assets/icons/category-electronics.png' => 'Electronics',
    'assets/icons/category-furniture.png' => 'Furniture & Household',
    'assets/icons/category-books.png' => 'Books',
    'assets/icons/category-stationery.png' => 'Stationery',
    'assets/icons/category-tools.png' => 'Tools & Equipment',
    'assets/icons/category-sports.png' => 'Sports Equipment',
    'assets/icons/category-kitchenware.png' => 'Kitchenware',
    'assets/icons/category-studyequipment.png' => 'Study Equipment',
    'assets/icons/category-clothing.png' => 'Clothing',
    'assets/icons/category-gaming.png' => 'Gaming & Recreation',
    'assets/icons/category-music.png' => 'Music',
    'assets/icons/category-others.png' => 'Others'
];

$managerID = $_SESSION['user_id'];
$name = $description = $categoryIcon = '';
$status = 'Active';
$error = '';

try {
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['categoryName'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryIcon = trim($_POST['categoryIcon'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($name === '') $error = 'Category Name is required.';
        elseif (strlen($name) > 100) $error = 'Category Name must not exceed 100 characters.';
        elseif (strlen($description) > 255) $error = 'Description must not exceed 255 characters.';
        elseif (!isset($icons[$categoryIcon])) $error = 'Please select a valid category icon.';
        elseif (!in_array($status, ['Active', 'Inactive'], true)) $error = 'Please select a valid category status.';

        if (!$error) {
            $stmt = $conn->prepare("SELECT 1 FROM categories WHERE categoryName=? LIMIT 1");
            $stmt->bind_param('s', $name); $stmt->execute();
            if ($stmt->get_result()->fetch_row()) $error = 'A category with this name already exists.';
            $stmt->close();
        }

        if (!$error) {
            $conn->begin_transaction();
            try {
                $categoryID = nextID($conn, 'categories', 'categoryID', 'C');
                $stmt = $conn->prepare("INSERT INTO categories(categoryID,categoryName,description,categoryIcon,status) VALUES (?,?,?,?,?)");
                $stmt->bind_param('sssss', $categoryID, $name, $description, $categoryIcon, $status);
                $stmt->execute(); $stmt->close();

                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');
                $logText = "Created category: $name";
                $stmt = $conn->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Create','Category',?,?)");
                $stmt->bind_param('ssss', $logID, $managerID, $categoryID, $logText);
                $stmt->execute(); $stmt->close();

                $conn->commit();
                header('Location: manager_categories.php?created=1'); exit();
            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $error ?: 'Unable to create the category. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Category - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
    <link rel="stylesheet" href="manager_mobile.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>
<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb"><a href="manager_categories.php">Category Management</a><span>›</span><span class="breadcrumb-current">Create New Category</span></div>
    <h1>Create New Category</h1>
    <p>Add a new asset category to help organize CirculAPU resources.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="content-card category-form-card">
<form method="POST" id="categoryForm">
<div class="category-create-layout">
    <div class="category-form-fields">
        <div class="category-form-row">
            <label for="categoryName">Category Name *</label>
            <div><input id="categoryName" name="categoryName" maxlength="100" value="<?= e($name) ?>" placeholder="Enter category name" required><small>Enter a clear and short category name.</small></div>
        </div>

        <div class="category-form-row category-form-textarea">
            <label for="description">Description</label>
            <div><textarea id="description" name="description" maxlength="255" rows="5" placeholder="Enter category description"><?= e($description) ?></textarea><small>Provide a brief description of this category.</small></div>
        </div>

        <div class="category-form-row">
            <label>Category Icon *</label>
            <div class="category-selected-icon">
                <div class="category-selected-preview">
                    <span id="iconPlaceholder" <?= $categoryIcon ? 'hidden' : '' ?>>Choose an icon</span>
                    <img id="selectedIcon" src="<?= $categoryIcon ? '../'.e($categoryIcon) : '' ?>" alt="" <?= $categoryIcon ? '' : 'hidden' ?>>
                </div>
                <small>Select an icon that best represents this category.</small>
            </div>
        </div>

        <div class="category-form-row">
            <label for="status">Status *</label>
            <div>
                <select id="status" name="status" required>
                    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <small>Active categories can be used for new asset listings.</small>
            </div>
        </div>
    </div>

    <aside class="category-icon-panel">
        <h2>Select Category Icon</h2>
        <div class="category-icon-grid">
            <?php foreach ($icons as $path => $label): $id = 'icon-' . md5($path); ?>
                <label class="category-icon-option <?= $categoryIcon === $path ? 'selected' : '' ?>" for="<?= e($id) ?>" title="<?= e($label) ?>">
                    <input type="radio" id="<?= e($id) ?>" name="categoryIcon" value="<?= e($path) ?>" <?= $categoryIcon === $path ? 'checked' : '' ?> required>
                    <img src="../<?= e($path) ?>" alt="<?= e($label) ?>">
                </label>
            <?php endforeach; ?>
        </div>

        <div class="category-icon-preview">
            <h3>Icon Preview</h3>
            <div class="category-preview-content">
                <div class="category-preview-image"><img id="previewIcon" src="<?= $categoryIcon ? '../'.e($categoryIcon) : '' ?>" alt="" <?= $categoryIcon ? '' : 'hidden' ?>></div>
                <div><strong id="previewName"><?= e($name ?: 'Category Name') ?></strong><p id="previewDescription"><?= e($description ?: 'Category description will appear here.') ?></p></div>
            </div>
        </div>
    </aside>
</div>

<div class="category-form-actions">
    <a href="manager_categories.php" class="category-form-back">Back</a>
    <button type="button" class="category-form-reset" id="clearForm"><img src="../assets/icons/reset.png" alt=""> Clear Form</button>
    <button type="button" class="category-form-submit" onclick="openCategoryConfirm()">
        <img src="../assets/icons/create.png" alt=""> Create Category
    </button>
</div>
</form>
</section>

</main>

</div>
</div>

<dialog id="categoryConfirm" class="flow-dialog">
<h2>Confirm Category Creation</h2>
<p>Are you sure you want to create this category with these details?</p>

<div class="flow-dialog-summary">
    <div><strong>Category Name</strong><span id="confirmCategoryName"></span></div>
    <div><strong>Icon</strong><span id="confirmCategoryIcon"></span></div>
    <div><strong>Status</strong><span id="confirmCategoryStatus"></span></div>
    <div><strong>Description</strong><span id="confirmCategoryDescription"></span></div>
</div>

<div class="flow-dialog-actions">
    <button type="button" class="category-form-back" onclick="categoryConfirm.close()">No</button>
    <button type="button" class="category-form-submit" onclick="confirmCategoryCreate()">Yes, Create</button>
</div>
</dialog>

<script>
const form=document.getElementById('categoryForm'), nameInput=document.getElementById('categoryName'), descInput=document.getElementById('description'), statusInput=document.getElementById('status'), selectedIcon=document.getElementById('selectedIcon'), previewIcon=document.getElementById('previewIcon'), placeholder=document.getElementById('iconPlaceholder'), previewName=document.getElementById('previewName'), previewDescription=document.getElementById('previewDescription');

function openCategoryConfirm(){
    if(!form.checkValidity()){form.reportValidity();return}

    const icon=form.querySelector('input[name="categoryIcon"]:checked');
    confirmCategoryName.textContent=nameInput.value.trim();
    confirmCategoryIcon.textContent=icon?.closest('label')?.title||'-';
    confirmCategoryStatus.textContent=statusInput.value;
    confirmCategoryDescription.textContent=descInput.value.trim()||'-';
    categoryConfirm.showModal();
}

function confirmCategoryCreate(){
    document.getElementById('categoryForm').submit();
}

document.querySelectorAll('input[name="categoryIcon"]').forEach(input=>input.addEventListener('change',()=>{
    document.querySelectorAll('.category-icon-option').forEach(x=>x.classList.remove('selected'));
    input.closest('.category-icon-option').classList.add('selected');
    selectedIcon.src=previewIcon.src='../'+input.value;
    selectedIcon.hidden=previewIcon.hidden=false;
    placeholder.hidden=true;
}));

nameInput.addEventListener('input',()=>previewName.textContent=nameInput.value.trim()||'Category Name');
descInput.addEventListener('input',()=>previewDescription.textContent=descInput.value.trim()||'Category description will appear here.');

document.getElementById('clearForm').addEventListener('click',()=>{
    form.reset(); nameInput.value=''; descInput.value=''; statusInput.value='Active';
    document.querySelectorAll('input[name="categoryIcon"]').forEach(x=>x.checked=false);
    document.querySelectorAll('.category-icon-option').forEach(x=>x.classList.remove('selected'));
    selectedIcon.hidden=previewIcon.hidden=true; placeholder.hidden=false;
    previewName.textContent='Category Name'; previewDescription.textContent='Category description will appear here.';
});
</script>
</body>
</html>
