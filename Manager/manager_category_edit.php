<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nextID(mysqli $conn, string $table, string $column, string $prefix): string {
    $row = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING($column,4) AS UNSIGNED)),0)+1 nextID FROM $table")->fetch_assoc();
    return sprintf($prefix . '%03d', (int)$row['nextID']);
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
$categoryID = trim($_GET['categoryID'] ?? '');
if (!$categoryID) { header('Location: manager_categories.php'); exit(); }

$category = null;
$error = '';

try {
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("SELECT * FROM categories WHERE categoryID=? LIMIT 1");
    $stmt->bind_param('s', $categoryID);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$category) $error = 'The selected category could not be found.';

    if ($category && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['categoryName'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryIcon = trim($_POST['categoryIcon'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if (!$name) $error = 'Category Name is required.';
        elseif (strlen($name) > 100) $error = 'Category Name must not exceed 100 characters.';
        elseif (strlen($description) > 255) $error = 'Description must not exceed 255 characters.';
        elseif (!isset($icons[$categoryIcon])) $error = 'Please select a valid category icon.';
        elseif (!in_array($status, ['Active','Inactive'], true)) $error = 'Please select a valid category status.';

        if (!$error) {
            $stmt = $conn->prepare("SELECT categoryID FROM categories WHERE categoryName=? AND categoryID<>? LIMIT 1");
            $stmt->bind_param('ss', $name, $categoryID);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $error = 'A category with this name already exists.';
            $stmt->close();
        }

        if (!$error) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE categories SET categoryName=?,description=?,categoryIcon=?,status=? WHERE categoryID=?");
                $stmt->bind_param('sssss', $name, $description, $categoryIcon, $status, $categoryID);
                $stmt->execute();
                $stmt->close();

                $logID = nextID($conn, 'systemlog', 'logID', 'LOG');
                $logText = "Updated category: $name";
                $stmt = $conn->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Update','Category',?,?)");
                $stmt->bind_param('ssss', $logID, $managerID, $categoryID, $logText);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                header("Location: manager_category_view.php?categoryID=" . rawurlencode($categoryID) . "&updated=1"); exit();
            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }

        $category['categoryName'] = $name;
        $category['description'] = $description;
        $category['categoryIcon'] = $categoryIcon;
        $category['status'] = $status;
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $error ?: 'Unable to update the category. Please try again.';
}

$name = $category['categoryName'] ?? '';
$description = $category['description'] ?? '';
$categoryIcon = $category['categoryIcon'] ?? '';
$status = $category['status'] ?? 'Active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css">
    <style>
        .category-selected-preview [hidden]{display:none!important}
        .category-selected-preview img{display:block}
        .category-selected-preview span{padding:8px;line-height:1.25}
    </style>
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>
<main class="page-body">

<section class="management-heading">
    <div class="breadcrumb">
        <a href="manager_categories.php">Category Management</a><span>›</span>
        <a href="manager_category_view.php?categoryID=<?= e($categoryID) ?>">Category Details</a><span>›</span>
        <span class="breadcrumb-current">Edit Category</span>
    </div>
    <h1>Edit Category</h1>
    <p>Update category information and its display icon.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($category): ?>
<section class="content-card category-form-card">
<form method="POST" id="categoryEditForm">
<div class="category-create-layout">

<div class="category-form-fields">
    <div class="category-form-row">
        <label>Category ID</label>
        <div><input value="<?= e($categoryID) ?>" readonly></div>
    </div>

    <div class="category-form-row">
        <label for="categoryName">Category Name *</label>
        <div><input id="categoryName" name="categoryName" maxlength="100" value="<?= e($name) ?>" required><small>Enter a clear and short category name.</small></div>
    </div>

    <div class="category-form-row">
        <label for="description">Description</label>
        <div><textarea id="description" name="description" maxlength="255" rows="5"><?= e($description) ?></textarea><small>Provide a brief description of this category.</small></div>
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
            <small>Active categories can be selected for new asset listings.</small>
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
            <div class="category-preview-image"><img id="previewIcon" src="../<?= e($categoryIcon) ?>" alt=""></div>
            <div><strong id="previewName"><?= e($name) ?></strong><p id="previewDescription"><?= e($description ?: 'Category description will appear here.') ?></p></div>
        </div>
    </div>
</aside>

</div>

<div class="category-form-actions">
    <a href="manager_category_view.php?categoryID=<?= e($categoryID) ?>" class="category-form-back">Back</a>
    <button type="reset" class="category-form-reset"><img src="../assets/icons/reset.png" alt=""> Reset</button>
    <button type="button" class="category-form-submit" onclick="openEditConfirm()">
        <img src="../assets/icons/edit.png" alt=""> Save Changes
    </button>
</div>
</form>
</section>
<?php endif; ?>

</main>
</div>
</div>

<dialog id="editCategoryConfirm" class="flow-dialog">
<h2>Confirm Category Changes</h2>
<p>Save these changes to this category?</p>

<div class="flow-dialog-summary">
    <div><strong>Category ID</strong><span><?=e($categoryID)?></span></div>
    <div><strong>Category Name</strong><span id="editConfirmName"></span></div>
    <div><strong>Icon</strong><span id="editConfirmIcon"></span></div>
    <div><strong>Status</strong><span id="editConfirmStatus"></span></div>
    <div><strong>Description</strong><span id="editConfirmDescription"></span></div>
</div>

<div class="flow-dialog-actions">
    <button type="button" class="category-form-back" onclick="editCategoryConfirm.close()">No</button>
    <button type="button" class="category-form-submit" onclick="confirmCategoryEdit()">Yes, Save Changes</button>
</div>
</dialog>

<script>
const editForm=document.getElementById('categoryEditForm'),
      nameInput=document.getElementById('categoryName'), descInput=document.getElementById('description'), selectedIcon=document.getElementById('selectedIcon'), previewIcon=document.getElementById('previewIcon'), iconPlaceholder=document.getElementById('iconPlaceholder'), previewName=document.getElementById('previewName'), previewDescription=document.getElementById('previewDescription');


function openEditConfirm(){
    if(!editForm.checkValidity()){editForm.reportValidity();return}

    const icon=editForm.querySelector('input[name="categoryIcon"]:checked');
    editConfirmName.textContent=nameInput.value.trim();
    editConfirmIcon.textContent=icon?.closest('label')?.title||'-';
    editConfirmStatus.textContent=document.getElementById('status').value;
    editConfirmDescription.textContent=descInput.value.trim()||'-';
    editCategoryConfirm.showModal();
}
function confirmCategoryEdit(){
    document.getElementById('categoryEditForm').submit();
}

document.querySelectorAll('input[name="categoryIcon"]').forEach(input=>input.addEventListener('change',()=>{
    document.querySelectorAll('.category-icon-option').forEach(option=>option.classList.remove('selected'));
    input.closest('.category-icon-option').classList.add('selected');
    selectedIcon.src=previewIcon.src='../'+input.value;
    selectedIcon.hidden=previewIcon.hidden=false;
    if(iconPlaceholder) iconPlaceholder.hidden=true;
}));
nameInput?.addEventListener('input',()=>previewName.textContent=nameInput.value.trim()||'Category Name');
descInput?.addEventListener('input',()=>previewDescription.textContent=descInput.value.trim()||'Category description will appear here.');
</script>
</body>
</html>
