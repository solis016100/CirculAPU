<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Manager') {
    header('Location: ../login.php'); exit();
}

require_once '../delete.php';
require_once '../db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function assetDate($v): string { return $v && strtotime($v) ? date('d M Y', strtotime($v)) : '-'; }
function pageURL(int $page): string {
    $q = $_GET; $q['page'] = $page;
    return 'manager_assets.php?' . http_build_query($q);
}

$managerID = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$availability = trim($_GET['availability'] ?? $_GET['availabilityStatus'] ?? '');
$status = trim($_GET['listingStatus'] ?? '');
$verification = trim($_GET['verificationStatus'] ?? '');

$conditions = ['New','Like New','Good','Fair','Poor'];
$availabilities = ['Available','Reserved','Borrowed','Unavailable'];
$statuses = ['Draft','Pending Review','Ready for Verification','Approved','Published','Rejected','Removed'];

$assets=$categories=[];$error='';$pendingCount=0;$nextPending='';
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = 0; $totalPages = 1; $offset = 0;

try {
    $conn->set_charset('utf8mb4');

    $pending=$conn->query("SELECT assetID FROM assets WHERE listingStatus='Pending Review' ORDER BY createdDate ASC");
    $pendingCount=$pending->num_rows;
    $nextPending=$pending->fetch_assoc()['assetID']??'';

    /* 1. Soft Remove */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
        $removeID = trim($_POST['assetID'] ?? '');

        $stmt = $conn->prepare("SELECT assetName,listingStatus,availabilityStatus FROM assets WHERE assetID=? LIMIT 1");
        $stmt->bind_param('s', $removeID); $stmt->execute();
        $asset = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if (!$asset) $error = 'Asset not found.';
        elseif ($asset['listingStatus'] === 'Removed') $error = 'This asset has already been removed.';
        elseif (in_array($asset['availabilityStatus'], ['Reserved','Borrowed'], true))
            $error = 'Reserved or borrowed assets cannot be removed during an active transaction.';
        else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE assets SET listingStatus='Removed',updatedDate=CURRENT_TIMESTAMP WHERE assetID=?");
                $stmt->bind_param('s', $removeID); $stmt->execute(); $stmt->close();

                createDeleteLog($conn, $managerID, 'Asset', $removeID, "Removed asset listing: {$asset['assetName']}");
                $conn->commit();
                header('Location: manager_assets.php?removed=1'); exit();
            } catch (Throwable $e) {
                $conn->rollback(); throw $e;
            }
        }
    }

    /* 2. Categories: Active + currently selected inactive category */
    $stmt = $conn->prepare("
        SELECT categoryID,categoryName
        FROM categories
        WHERE status='Active' OR categoryID=?
        ORDER BY categoryName
    ");
    $stmt->bind_param('s', $category); $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    /* 3. Filters */
    $where = "
        WHERE (?='' OR a.assetID LIKE CONCAT('%',?,'%') OR a.assetName LIKE CONCAT('%',?,'%'))
          AND (?='' OR a.categoryID=?)
          AND (?='' OR a.assetCondition=?)
          AND (?='' OR a.availabilityStatus=?)
          AND ((?<>'') AND a.listingStatus=? OR (?='' AND a.listingStatus<>'Removed'))
          AND (?='' OR a.verificationStatus=?)
    ";

    $types = 'ssssssssssssss';
    $params = [
        $search,$search,$search,
        $category,$category,
        $condition,$condition,
        $availability,$availability,
        $status,$status,$status,
        $verification,$verification
    ];

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM assets a $where");
    $stmt->bind_param($types, ...$params); $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare("
        SELECT a.assetID,a.assetName,a.assetCondition,a.availabilityStatus,
               a.listingStatus,a.createdDate,c.categoryName,c.categoryIcon
        FROM assets a
        JOIN categories c ON a.categoryID=c.categoryID
        $where
        ORDER BY a.createdDate DESC
        LIMIT ? OFFSET ?
    ");
    $listParams = [...$params, $perPage, $offset];
    $stmt->bind_param($types . 'ii', ...$listParams); $stmt->execute();
    $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();


} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = 'Unable to load asset records. Please try again.';
}

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
        <h1>Asset Management</h1>
        <p>Review and manage asset listings submitted by CirculAPU Community Members.</p>
    </div>
    <?php if($nextPending):?>
    <a href="review_asset_submission.php?assetID=<?=e($nextPending)?>" class="management-create-btn">
        <img src="../assets/icons/pendingVerification.png" alt="">
        Review Pending Listings (<?=$pendingCount?>)
    </a>
    <?php endif;?>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (($_GET['removed'] ?? '') === '1'): ?><div class="alert alert-success">Asset listing removed successfully.</div><?php endif; ?>

<section class="management-filter desktop-management-filter">
<form method="GET">
    <div class="management-filter-group search">
        <label for="search">Search Asset</label>
        <div class="management-search-input">
            <img src="../assets/icons/search.png" alt="">
            <input id="search" name="search" placeholder="Asset ID or Asset Name" value="<?= e($search) ?>">
        </div>
    </div>

    <div class="management-filter-group">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['categoryID']) ?>" <?= $category === $c['categoryID'] ? 'selected' : '' ?>>
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
            <option value="<?= e($v) ?>" <?= $condition === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="management-filter-group">
        <label for="availability">Availability</label>
        <select id="availability" name="availability">
            <option value="">All Availability</option>
            <?php foreach ($availabilities as $v): ?>
            <option value="<?= e($v) ?>" <?= $availability === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="management-filter-group">
        <label for="listingStatus">Listing Status</label>
        <select id="listingStatus" name="listingStatus">
            <option value="">All Status</option>
            <?php foreach ($statuses as $v): ?>
            <option value="<?= e($v) ?>" <?= $status === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($verification): ?><input type="hidden" name="verificationStatus" value="<?= e($verification) ?>"><?php endif; ?>

    <div class="management-filter-buttons">
        <button type="submit" class="management-filter-btn"><img src="../assets/icons/filter.png" alt=""> Filter</button>
        <a href="manager_assets.php" class="management-reset-btn"><img src="../assets/icons/reset.png" alt=""> Reset</a>
    </div>
</form>
</section>

<section class="mobile-asset-tools">
    <details class="mobile-filter-card" <?=($search||$category||$condition||$availability||$status)?'open':''?>>
        <summary>
            <span>Filter Assets</span>
            <?php if($search||$category||$condition||$availability||$status):?><small>Filters active</small><?php endif;?>
        </summary>

        <form method="GET" class="mobile-filter-form">
            <label>
                <span>Search</span>
                <input name="search" placeholder="Asset ID or name" value="<?=e($search)?>">
            </label>

            <label>
                <span>Category</span>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $c):?>
                    <option value="<?=e($c['categoryID'])?>" <?=$category===$c['categoryID']?'selected':''?>><?=e($c['categoryName'])?></option>
                    <?php endforeach;?>
                </select>
            </label>

            <div class="mobile-filter-grid">
                <label>
                    <span>Condition</span>
                    <select name="condition">
                        <option value="">All</option>
                        <?php foreach($conditions as $v):?>
                        <option value="<?=e($v)?>" <?=$condition===$v?'selected':''?>><?=e($v)?></option>
                        <?php endforeach;?>
                    </select>
                </label>

                <label>
                    <span>Availability</span>
                    <select name="availability">
                        <option value="">All</option>
                        <?php foreach($availabilities as $v):?>
                        <option value="<?=e($v)?>" <?=$availability===$v?'selected':''?>><?=e($v)?></option>
                        <?php endforeach;?>
                    </select>
                </label>
            </div>

            <label>
                <span>Listing Status</span>
                <select name="listingStatus">
                    <option value="">All Statuses</option>
                    <?php foreach($statuses as $v):?>
                    <option value="<?=e($v)?>" <?=$status===$v?'selected':''?>><?=e($v)?></option>
                    <?php endforeach;?>
                </select>
            </label>

            <?php if($verification):?><input type="hidden" name="verificationStatus" value="<?=e($verification)?>"><?php endif;?>

            <div class="mobile-filter-actions">
                <a href="manager_assets.php">Reset</a>
                <button type="submit">Apply Filters</button>
            </div>
        </form>
    </details>
</section>

<section class="mobile-asset-list">
    <div class="mobile-section-heading">
        <div>
            <h2>Asset Listings</h2>
            <p><?=$total?> asset<?=$total===1?'':'s'?> found</p>
        </div>
        <span class="mobile-page-count"><?=$page?> / <?=$totalPages?></span>
    </div>

    <?php if(!$assets):?>
        <div class="mobile-empty-card">No asset records found.</div>
    <?php else:foreach($assets as $a):?>
    <article class="mobile-asset-card">
        <div class="mobile-asset-card-top">
            <div class="mobile-asset-identity">
                <?php if($a['categoryIcon']):?>
                    <span class="mobile-asset-category-icon"><img src="../<?=e($a['categoryIcon'])?>" alt=""></span>
                <?php endif;?>
                <div>
                    <span class="mobile-record-id"><?=e($a['assetID'])?></span>
                    <h3><?=e($a['assetName'])?></h3>
                    <p><?=e($a['categoryName'])?></p>
                </div>
            </div>
            <span class="mobile-status-pill" data-status="<?=e($a['listingStatus'])?>"><?=e($a['listingStatus'])?></span>
        </div>

        <div class="mobile-asset-meta">
            <div><span>Condition</span><strong><?=e($a['assetCondition'])?></strong></div>
            <div><span>Availability</span><span class="status-badge" data-status="<?=e($a['availabilityStatus'])?>"><?=e($a['availabilityStatus'])?></span></div>
            <div><span>Listed</span><strong><?=e(assetDate($a['createdDate']))?></strong></div>
        </div>

        <div class="mobile-card-actions">
            <a href="manager_asset_view.php?assetID=<?=e($a['assetID'])?>">View</a>

            <?php if($a['listingStatus']==='Pending Review'):?>
                <a class="primary" href="review_asset_submission.php?assetID=<?=e($a['assetID'])?>">Review</a>
            <?php elseif($a['listingStatus']!=='Removed'):?>
                <a class="primary" href="manager_asset_edit.php?assetID=<?=e($a['assetID'])?>">Edit</a>
            <?php endif;?>

            <?php if($a['listingStatus']!=='Removed'&&!in_array($a['availabilityStatus'],['Reserved','Borrowed'],true)):?>
                <button type="button" class="danger" data-id="<?=e($a['assetID'])?>" data-name="<?=e($a['assetName'])?>" onclick="openRemoveDialog(this)">Remove</button>
            <?php endif;?>
        </div>
    </article>
    <?php endforeach;endif;?>

    <?php if($totalPages>1):?>
    <nav class="mobile-pagination" aria-label="Asset pagination">
        <?php if($page>1):?><a href="<?=e(pageURL($page-1))?>">‹</a><?php endif;?>
        <?php for($p=1;$p<=$totalPages;$p++):?>
            <a href="<?=e(pageURL($p))?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
        <?php endfor;?>
        <?php if($page<$totalPages):?><a href="<?=e(pageURL($page+1))?>">›</a><?php endif;?>
    </nav>
    <?php endif;?>
</section>

<section class="management-list desktop-asset-list">
    <div class="management-list-header">
        <h2>Asset Listings</h2>
        <p><?= $total ?> asset<?= $total === 1 ? '' : 's' ?> found</p>
    </div>

    <div class="management-table-wrapper">
    <table class="management-table">
        <thead>
        <tr>
            <th>#</th><th>Asset ID</th><th>Asset Name</th><th>Category</th><th>Condition</th>
            <th>Availability</th><th>Listing Status</th><th>Date Listed</th><th>Action</th>
        </tr>
        </thead>

        <tbody>
        <?php if (!$assets): ?>
            <tr><td colspan="9" class="management-empty-state">No asset records found.</td></tr>
        <?php else: foreach ($assets as $i => $a): ?>
            <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td><strong><?= e($a['assetID']) ?></strong></td>
                <td><?= e($a['assetName']) ?></td>
                <td>
                    <div class="category-name-cell">
                        <?php if ($a['categoryIcon']): ?>
                            <img src="../<?= e($a['categoryIcon']) ?>" alt="">
                        <?php endif; ?>
                        <span><?= e($a['categoryName']) ?></span>
                    </div>
                </td>
                <td><?= e($a['assetCondition']) ?></td>
                <td><span class="status-badge" data-status="<?=e($a['availabilityStatus'])?>"><?=e($a['availabilityStatus'])?></span></td>
                <td><span class="status-badge" data-status="<?=e($a['listingStatus'])?>"><?=e($a['listingStatus'])?></span></td>
                <td><?= e(assetDate($a['createdDate'])) ?></td>
                <td>
                    <div class="management-actions">
                        <a href="manager_asset_view.php?assetID=<?= e($a['assetID']) ?>" class="management-action-btn" title="View Asset">
                            <img src="../assets/icons/view.png" alt="View">
                        </a>

                        <?php if ($a['listingStatus'] === 'Pending Review'): ?>
                        <a href="review_asset_submission.php?assetID=<?= e($a['assetID']) ?>" class="management-action-btn" title="Review Asset Submission">
                            <img src="../assets/icons/edit.png" alt="Review">
                        </a>
                        <?php elseif ($a['listingStatus'] !== 'Removed'): ?>
                        <a href="manager_asset_edit.php?assetID=<?= e($a['assetID']) ?>" class="management-action-btn" title="Edit Asset Information">
                            <img src="../assets/icons/edit.png" alt="Edit">
                        </a>
                        <?php endif; ?>

                        <?php if ($a['listingStatus'] !== 'Removed' && !in_array($a['availabilityStatus'], ['Reserved','Borrowed'], true)): ?>
                        <button type="button" class="management-action-btn delete" title="Remove Asset"
                                data-id="<?=e($a['assetID'])?>" data-name="<?=e($a['assetName'])?>"
                                onclick="openRemoveDialog(this)">
                            <img src="../assets/icons/delete.png" alt="Remove">
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <div class="management-table-footer">
        <p>Showing <?= $start ?> - <?= $end ?> of <?= $total ?> assets</p>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Asset pagination">
            <?php if ($page > 1): ?><a href="<?= e(pageURL($page - 1)) ?>">Previous</a><?php endif; ?>
            <?php for ($p=1; $p<=$totalPages; $p++): ?>
                <a href="<?= e(pageURL($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(pageURL($page + 1)) ?>">Next</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</section>

<dialog id="removeAssetDialog" class="flow-dialog">
<h2>Confirm Asset Removal</h2>
<p>Remove this listing from normal use? The record will be retained for auditing.</p>

<div class="flow-dialog-summary">
    <div><strong>Asset ID</strong><span id="removeAssetIDText"></span></div>
    <div><strong>Asset Name</strong><span id="removeAssetName"></span></div>
</div>

<form method="POST" class="flow-dialog-actions">
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="assetID" id="removeAssetID">
    <button type="button" class="category-form-back" onclick="removeAssetDialog.close()">No</button>
    <button type="submit" class="category-form-submit">Yes, Remove</button>
</form>
</dialog>

<script>
function openRemoveDialog(btn){
    removeAssetID.value=removeAssetIDText.textContent=btn.dataset.id;
    removeAssetName.textContent=btn.dataset.name;
    removeAssetDialog.showModal();
}
</script>

<?php if(in_array($_GET['reviewDone']??'',['verify','return'],true)):?>
<dialog id="nextReviewDialog" class="flow-dialog">
    <h2>Asset Review Completed</h2>
    <p><?=($_GET['reviewDone']==='verify')
        ?'The listing was sent for Sustainability Auditor verification.'
        :'The listing was returned to the member for correction.'?></p>

    <?php if($nextPending):?>
    <p style="margin-top:12px">There are <strong><?=$pendingCount?></strong> pending listing<?=$pendingCount===1?'':'s'?> remaining. Review another?</p>
    <div class="flow-dialog-actions">
        <button type="button" class="category-form-back" onclick="nextReviewDialog.close()">Done</button>
        <button type="button" class="category-form-submit"
                onclick="location.href='review_asset_submission.php?assetID=<?=e($nextPending)?>'">Review Another</button>
    </div>
    <?php else:?>
    <p style="margin-top:12px">There are no more pending listings to review.</p>
    <div class="flow-dialog-actions">
        <button type="button" class="category-form-submit" onclick="nextReviewDialog.close()">Done</button>
    </div>
    <?php endif;?>
</dialog>
<script>nextReviewDialog.showModal()</script>
<?php endif;?>

</main>

<nav class="manager-mobile-nav" aria-label="Manager mobile navigation">
<a href="manager_dashboard.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Home</span>
</a>
<a href="manager_assets.php" class="active" aria-current="page">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg><span>Assets</span>
</a>
<a href="manager_categories.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2Z"/></svg><span>Categories</span>
</a>
<a href="manager_transactions.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m17 1 4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3"/></svg><span>Transactions</span>
</a>
<a href="manager_reports.php">
<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg><span>Reports</span>
</a>
</nav>

</div>
</div>
</body>
</html>
