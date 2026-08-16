<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../delete.php';
require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function validDate(string $v): bool {
    if ($v === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function iconPath(?string $v): string {
    if (!$v) return '';
    return preg_match('/^https?:\/\//i', $v) ? $v : '../' . ltrim($v, '/');
}
function go(string $q): never { header('Location: manager_categories.php?' . $q); exit(); }
function pageURL(array $q, int $p): string {
    $q['page'] = $p;
    return '?' . http_build_query(array_filter($q, fn($v) => $v !== ''));
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
$totalRows = 0;
$totalPages = 1;

try {
    $conn->set_charset('utf8mb4');

    /* Actions */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $categoryID = trim($_POST['categoryID'] ?? '');

        $stmt = $conn->prepare("SELECT categoryName,status FROM categories WHERE categoryID=?");
        $stmt->bind_param('s', $categoryID); $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$category) go('error=notfound');

        if (in_array($action, ['deactivate','reactivate'], true)) {
            $reactivate = $action === 'reactivate';
            $expected = $reactivate ? 'Inactive' : 'Active';
            $newStatus = $reactivate ? 'Active' : 'Inactive';

            if ($category['status'] !== $expected) go($reactivate ? 'error=active' : 'error=inactive');

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE categories SET status=? WHERE categoryID=?");
                $stmt->bind_param('ss', $newStatus, $categoryID); $stmt->execute(); $stmt->close();

                $logID = generateLogID($conn);
                $desc = ($reactivate ? 'Reactivated category: ' : 'Deactivated category: ') . $category['categoryName'];
                $stmt = $conn->prepare("INSERT INTO systemlog(logID,userID,actionType,refType,refID,description) VALUES (?,?,'Update','Category',?,?)");
                $stmt->bind_param('ssss', $logID, $managerID, $categoryID, $desc); $stmt->execute(); $stmt->close();

                $conn->commit();
                go($reactivate ? 'reactivated=1' : 'deactivated=1');
            } catch (Throwable $ex) { $conn->rollback(); throw $ex; }
        }

        if ($action === 'delete') {
            $stmt = $conn->prepare("SELECT COUNT(*) total FROM assets WHERE categoryID=?");
            $stmt->bind_param('s', $categoryID); $stmt->execute();
            $linked = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
            if ($linked > 0) go('error=linked');

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM categories WHERE categoryID=?");
                $stmt->bind_param('s', $categoryID); $stmt->execute(); $stmt->close();
                createDeleteLog($conn, $managerID, 'Category', $categoryID, 'Permanently deleted category: ' . $category['categoryName']);
                $conn->commit(); go('deleted=1');
            } catch (Throwable $ex) { $conn->rollback(); throw $ex; }
        }
    }

    /* Filters */
    if ($status && !in_array($status, ['Active','Inactive'], true)) $status = '';
    if (!validDate($dateFrom) || !validDate($dateTo)) $error = 'Please enter a valid date.';
    elseif ($dateFrom && $dateTo && $dateFrom > $dateTo) $error = 'Date From cannot be later than Date To.';

    $where = []; $params = []; $types = '';
    if ($search) {
        $where[] = '(c.categoryID LIKE ? OR c.categoryName LIKE ? OR c.description LIKE ?)';
        $like = "%$search%"; array_push($params, $like, $like, $like); $types .= 'sss';
    }
    if ($status)   { $where[] = 'c.status=?'; $params[] = $status; $types .= 's'; }
    if ($dateFrom) { $where[] = 'DATE(c.createdDate)>=?'; $params[] = $dateFrom; $types .= 's'; }
    if ($dateTo)   { $where[] = 'DATE(c.createdDate)<=?'; $params[] = $dateTo; $types .= 's'; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    if (!$error) {
        $stmt = $conn->prepare("SELECT COUNT(*) total FROM categories c $whereSQL");
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute(); $totalRows = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();

        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;
        $listParams = [...$params, $perPage, $offset];

        $stmt = $conn->prepare("SELECT c.*,(SELECT COUNT(*) FROM assets a WHERE a.categoryID=c.categoryID) linkedAssets FROM categories c $whereSQL ORDER BY c.createdDate DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types . 'ii', ...$listParams); $stmt->execute();
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }
} catch (Throwable $ex) {
    error_log($ex->getMessage());
    $error = 'Unable to load Category Management. Please try again.';
}

$query=compact('search','status','dateFrom','dateTo');
$back=http_build_query(array_filter($query+['page'=>$page],fn($v)=>$v!==''&&$v!==1));
$backParam=$back?'&back='.rawurlencode($back):'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="manager_style.css?v=<?=filemtime(__DIR__.'/manager_style.css')?>">
    <link rel="stylesheet" href="manager_mobile.css">
</head>
<body class="role-manager">
<div class="dashboard-container">
<?php include '../sidebar.php'; ?>
<div class="main-content">
<?php include '../header.php'; ?>
<main class="page-body">

<section class="management-heading management-heading-action">
    <div class="management-heading-text">
        <h1>Category Management</h1>
        <p>Create, edit and manage asset categories.</p>
    </div>
    <a href="manager_category_create.php" class="management-create-btn">
        <img src="../assets/icons/create.png" alt=""> Add New Category
    </a>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['updated'] ?? '') === '1'): ?><div class="alert alert-success">Category updated successfully.</div>
<?php elseif (($_GET['deactivated'] ?? '') === '1'): ?><div class="alert alert-success">Category deactivated successfully. Existing assets were not affected.</div>
<?php elseif (($_GET['reactivated'] ?? '') === '1'): ?><div class="alert alert-success">Category reactivated successfully. It can now be used for new asset listings.</div>
<?php elseif (($_GET['deleted'] ?? '') === '1'): ?><div class="alert alert-success">Category permanently deleted successfully.</div>
<?php elseif (($_GET['error'] ?? '') === 'linked'): ?><div class="alert alert-error">This category has linked assets and cannot be permanently deleted. Deactivate it instead.</div>
<?php elseif (($_GET['error'] ?? '') === 'inactive'): ?><div class="alert alert-error">This category is already inactive.</div>
<?php elseif (($_GET['error'] ?? '') === 'active'): ?><div class="alert alert-error">This category is already active.</div>
<?php elseif (($_GET['error'] ?? '') === 'notfound'): ?><div class="alert alert-error">Category not found.</div><?php endif; ?>


<section class="mobile-category-ui">
<details class="mobile-filter-card" <?=($search||$status||$dateFrom||$dateTo)?'open':''?>>
<summary><span>Filter Categories</span><?php if($search||$status||$dateFrom||$dateTo):?><small>Filters active</small><?php endif;?></summary>
<form method="GET" class="mobile-filter-form">
<label><span>Search</span><input name="search" placeholder="ID, name or description" value="<?=e($search)?>"></label>
<div class="mobile-filter-grid">
<label><span>Status</span><select name="status">
<option value="">All</option>
<option value="Active" <?=$status==='Active'?'selected':''?>>Active</option>
<option value="Inactive" <?=$status==='Inactive'?'selected':''?>>Inactive</option>
</select></label>
<label><span>Date From</span><input type="date" name="dateFrom" value="<?=e($dateFrom)?>"></label>
</div>
<label><span>Date To</span><input type="date" name="dateTo" value="<?=e($dateTo)?>"></label>
<div class="mobile-filter-actions"><a href="manager_categories.php">Reset</a><button>Apply Filters</button></div>
</form>
</details>

<div class="mobile-section-heading">
<div><h2>Category List</h2><p><?=$totalRows?> categor<?=$totalRows===1?'y':'ies'?></p></div>
<span class="mobile-page-count"><?=$page?> / <?=$totalPages?></span>
</div>

<?php if(!$categories):?>
<div class="mobile-empty-card">No categories found.</div>
<?php else:foreach($categories as $c):
$icon=iconPath($c['categoryIcon']);$canDelete=(int)$c['linkedAssets']===0;?>
<article class="mobile-category-card">
<div class="mobile-category-top">
<div class="mobile-category-identity">
<?php if($icon):?><span class="mobile-category-icon"><img src="<?=e($icon)?>" alt=""></span><?php endif;?>
<div><span class="mobile-record-id"><?=e($c['categoryID'])?></span><h3><?=e($c['categoryName'])?></h3></div>
</div>
<span class="mobile-status-pill" data-status="<?=e($c['status'])?>"><?=e($c['status'])?></span>
</div>

<?php if($c['description']):?><p class="mobile-category-desc"><?=e($c['description'])?></p><?php endif;?>

<div class="mobile-category-meta">
<div><span>Linked Assets</span><strong><?=$c['linkedAssets']?></strong></div>
<div><span>Created</span><strong><?=date('d M Y',strtotime($c['createdDate']))?></strong></div>
</div>

<div class="mobile-card-actions">
<a href="manager_category_view.php?categoryID=<?=e($c['categoryID'])?><?=$backParam?>">View</a>
<a class="primary" href="manager_category_edit.php?categoryID=<?=e($c['categoryID'])?><?=$backParam?>">Edit</a>
<button type="button" onclick="openCategoryAction('<?=$c['status']==='Active'?'deactivate':'reactivate'?>','<?=e($c['categoryID'])?>','<?=e($c['categoryName'])?>')">
<?=$c['status']==='Active'?'Deactivate':'Reactivate'?>
</button>
<?php if($canDelete):?><button type="button" class="danger" onclick="openCategoryAction('delete','<?=e($c['categoryID'])?>','<?=e($c['categoryName'])?>')">Delete</button><?php endif;?>
</div>
</article>
<?php endforeach;endif;?>

<?php if($totalPages>1):?>
<nav class="mobile-pagination" aria-label="Category pagination">
<?php if($page>1):?><a href="<?=e(pageURL($query,$page-1))?>">‹</a><?php endif;?>
<?php for($p=1;$p<=$totalPages;$p++):?><a href="<?=e(pageURL($query,$p))?>" class="<?=$p===$page?'active':''?>"><?=$p?></a><?php endfor;?>
<?php if($page<$totalPages):?><a href="<?=e(pageURL($query,$page+1))?>">›</a><?php endif;?>
</nav>
<?php endif;?>
</section>

<section class="management-filter desktop-category-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Category</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" placeholder="Category ID, name or description" value="<?= e($search) ?>">
        </div>
    </div>
    <div class="management-filter-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All Status</option>
            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="management-filter-group">
        <label for="dateFrom">Date From</label>
        <input type="date" id="dateFrom" name="dateFrom" value="<?= e($dateFrom) ?>">
    </div>
    <div class="management-filter-group">
        <label for="dateTo">Date To</label>
        <input type="date" id="dateTo" name="dateTo" value="<?= e($dateTo) ?>">
    </div>
    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
        <a href="manager_categories.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
    </div>
</form>
</section>

<section class="management-list desktop-category-list">
<div class="management-list-header">
    <h2>Category List</h2>
    <strong><?= $totalRows ?> categor<?= $totalRows === 1 ? 'y' : 'ies' ?></strong>
</div>
<div class="management-table-wrapper">
<table class="management-table">
<thead><tr><th>#</th><th>Category ID</th><th>Category Name</th><th>Description</th><th>Status</th><th>Date Created</th><th>Action</th></tr></thead>
<tbody>
<?php if (!$categories): ?>
<tr><td colspan="7" class="management-empty-state">No categories found.</td></tr>
<?php else: foreach ($categories as $i => $c): $icon = iconPath($c['categoryIcon']); $canDelete = (int)$c['linkedAssets'] === 0; ?>
<tr>
    <td><?= (($page - 1) * $perPage) + $i + 1 ?></td>
    <td><strong><?= e($c['categoryID']) ?></strong></td>
    <td><div class="category-name-cell"><?php if ($icon): ?><img src="<?= e($icon) ?>" alt=""><?php endif; ?><span><?= e($c['categoryName']) ?></span></div></td>
    <td><?= e($c['description'] ?: '-') ?></td>
    <td><span class="status-badge" data-status="<?=e($c['status'])?>"><?=e($c['status'])?></span></td>
    <td><?= date('d M Y', strtotime($c['createdDate'])) ?></td>
    <td><div class="management-actions">
        <a href="manager_category_view.php?categoryID=<?= e($c['categoryID']) ?><?=e($backParam)?>" class="management-action-btn" title="View"><img src="../assets/icons/view.png" alt="View"></a>
        <a href="manager_category_edit.php?categoryID=<?= e($c['categoryID']) ?><?=e($backParam)?>" class="management-action-btn" title="Edit"><img src="../assets/icons/edit.png" alt="Edit"></a>
        <?php if ($c['status'] === 'Active'): ?>
        <button type="button" class="management-action-btn deactivate" title="Deactivate"
                onclick="openCategoryAction('deactivate','<?=e($c['categoryID'])?>','<?=e($c['categoryName'])?>')">
            Deactivate
        </button>
        <?php else: ?>
        <button type="button" class="management-action-btn" title="Reactivate"
                onclick="openCategoryAction('reactivate','<?=e($c['categoryID'])?>','<?=e($c['categoryName'])?>')">
            Reactivate
        </button>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <button type="button" class="management-action-btn delete" title="Delete Permanently"
                onclick="openCategoryAction('delete','<?=e($c['categoryID'])?>','<?=e($c['categoryName'])?>')">
            <img src="../assets/icons/delete.png" alt="Delete">
        </button>
        <?php endif; ?>
    </div></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination management-table-footer">
    <?php if ($page > 1): ?><a href="<?= e(pageURL($query, $page - 1)) ?>">‹</a><?php endif; ?>
    <?php for ($p = 1; $p <= $totalPages; $p++): ?><a href="<?= e(pageURL($query, $p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a><?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?= e(pageURL($query, $page + 1)) ?>">›</a><?php endif; ?>
</div>
<?php endif; ?>
</section>

<dialog id="categoryActionDialog" class="flow-dialog">
<h2 id="categoryActionTitle"></h2>
<p id="categoryActionMessage"></p>

<div class="flow-dialog-summary">
    <div><strong>Category ID</strong><span id="categoryActionID"></span></div>
    <div><strong>Category Name</strong><span id="categoryActionName"></span></div>
    <div><strong>Action</strong><span id="categoryActionLabel"></span></div>
</div>

<form method="POST" id="categoryActionForm" class="flow-dialog-actions">
    <input type="hidden" name="action" id="categoryActionInput">
    <input type="hidden" name="categoryID" id="categoryActionCategoryID">
    <button type="button" class="category-form-back" onclick="categoryActionDialog.close()">No</button>
    <button type="submit" class="category-form-submit" id="categoryActionConfirm">Confirm</button>
</form>
</dialog>

<script>
function openCategoryAction(action,id,name){
    const deleting=action==='delete', reactivating=action==='reactivate';

    categoryActionTitle.textContent=deleting
        ?'Confirm Category Deletion'
        :(reactivating?'Confirm Category Reactivation':'Confirm Category Deactivation');

    categoryActionMessage.textContent=deleting
        ?'Permanently delete this unused category? This action cannot be undone.'
        :(reactivating
            ?'Reactivate this category? It will become available for new asset listings again.'
            :'Deactivate this category? Existing assets will remain unchanged.');

    categoryActionID.textContent=id;
    categoryActionName.textContent=name;
    categoryActionLabel.textContent=deleting?'Delete Permanently':(reactivating?'Reactivate':'Deactivate');
    categoryActionConfirm.textContent=deleting?'Yes, Delete':(reactivating?'Yes, Reactivate':'Yes, Deactivate');
    categoryActionInput.value=action;
    categoryActionCategoryID.value=id;
    categoryActionDialog.showModal();
}
</script>

<?php if(($_GET['created']??'')==='1'):?>
<dialog id="categoryCreatedDialog" class="flow-dialog">
    <h2>Category Created Successfully</h2>
    <p>Do you want to create another category?</p>
    <div class="flow-dialog-actions">
        <button type="button" class="category-form-back"
                onclick="categoryCreatedDialog.close()">No, Done</button>
        <button type="button" class="category-form-submit"
                onclick="location.href='manager_category_create.php'">Yes, Create Another</button>
    </div>
</dialog>
<script>categoryCreatedDialog.showModal()</script>
<?php endif;?>

</main>
<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span></a>
<a href="manager_assets.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span></a>
<a href="manager_categories.php" class="active" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span></a>
<a href="manager_transactions.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span></a>
<a href="manager_reports.php"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span></a>
</nav>
</div>
</div>
</body>
</html>
