<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once '../db.php';
require_once 'admin_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = 'success';
$edit_id = trim($_GET['edit'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Your session token expired. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $tier_id = trim($_POST['tier_id'] ?? '');

        try {
            if ($action === 'save') {
                $tier_name = trim($_POST['tier_name'] ?? '');
                $description = trim($_POST['tier_description'] ?? '');
                $minimum_points = filter_var($_POST['minimum_points'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

                if ($tier_name === '' || mb_strlen($tier_name) > 50) {
                    throw new DomainException('Tier name is required and must not exceed 50 characters.');
                }
                if ($description === '' || mb_strlen($description) > 255) {
                    throw new DomainException('Description is required and must not exceed 255 characters.');
                }
                if ($minimum_points === false) {
                    throw new DomainException('Minimum EcoPoints must be a whole number of zero or more.');
                }
                if ($tier_id !== '') {
                    $exists = $conn->prepare('SELECT tierID FROM tiers WHERE tierID = ?');
                    $exists->bind_param('s', $tier_id);
                    $exists->execute();
                    $tier_exists = $exists->get_result()->num_rows > 0;
                    $exists->close();
                    if (!$tier_exists) {
                        throw new DomainException('Tier not found.');
                    }
                }

                $duplicate = $conn->prepare('SELECT tierID FROM tiers WHERE (tierName = ? OR minimumEcoPoints = ?) AND tierID != ? LIMIT 1');
                $duplicate->bind_param('sis', $tier_name, $minimum_points, $tier_id);
                $duplicate->execute();
                if ($duplicate->get_result()->num_rows > 0) {
                    throw new DomainException('Each tier must have a unique name and EcoPoints threshold.');
                }
                $duplicate->close();

                $tier_icon = trim($_POST['existing_icon'] ?? '');
                if (isset($_FILES['tier_icon']) && $_FILES['tier_icon']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['tier_icon']['error'] !== UPLOAD_ERR_OK || $_FILES['tier_icon']['size'] > 2 * 1024 * 1024) {
                        throw new DomainException('The icon could not be uploaded. Use a PNG, JPG, or WEBP file up to 2 MB.');
                    }
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['tier_icon']['tmp_name']);
                    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                    if (!isset($extensions[$mime])) {
                        throw new DomainException('Tier icons must be PNG, JPG, or WEBP images.');
                    }
                    $tier_icon = 'tier_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file($_FILES['tier_icon']['tmp_name'], __DIR__ . '/../assets/images/' . $tier_icon)) {
                        throw new RuntimeException('The icon could not be saved.');
                    }
                }

                if ($tier_id === '') {
                    $tier_id = adminNextIdentifier($conn, 'tier');
                    $stmt = $conn->prepare('INSERT INTO tiers (tierID, tierName, tierDescription, tierIcon, minimumEcoPoints) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssssi', $tier_id, $tier_name, $description, $tier_icon, $minimum_points);
                    $log_action = 'Create';
                    $message = 'Tier created successfully.';
                } else {
                    $stmt = $conn->prepare('UPDATE tiers SET tierName = ?, tierDescription = ?, tierIcon = ?, minimumEcoPoints = ? WHERE tierID = ?');
                    $stmt->bind_param('sssis', $tier_name, $description, $tier_icon, $minimum_points, $tier_id);
                    $log_action = 'Update';
                    $message = 'Tier updated successfully.';
                }
                $stmt->execute();
                $stmt->close();
                adminWriteSystemLog($conn, $_SESSION['user_id'], $log_action, 'Tier', $tier_id, "$log_action tier $tier_name ($tier_id)");
                $edit_id = '';
            } elseif ($action === 'delete') {
                $assigned = $conn->prepare('SELECT COUNT(*) AS total FROM usertier WHERE tierID = ?');
                $assigned->bind_param('s', $tier_id);
                $assigned->execute();
                $assigned_count = (int)$assigned->get_result()->fetch_assoc()['total'];
                $assigned->close();
                $tier_count = (int)$conn->query('SELECT COUNT(*) AS total FROM tiers')->fetch_assoc()['total'];
                if ($assigned_count > 0) {
                    throw new DomainException("This tier is assigned to $assigned_count user(s) and cannot be deleted.");
                }
                if ($tier_count <= 1) {
                    throw new DomainException('The platform must retain at least one tier.');
                }
                $lookup = $conn->prepare('SELECT tierName FROM tiers WHERE tierID = ?');
                $lookup->bind_param('s', $tier_id);
                $lookup->execute();
                $tier = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$tier) {
                    throw new DomainException('Tier not found.');
                }
                $stmt = $conn->prepare('DELETE FROM tiers WHERE tierID = ?');
                $stmt->bind_param('s', $tier_id);
                $stmt->execute();
                $stmt->close();
                adminWriteSystemLog($conn, $_SESSION['user_id'], 'Delete', 'Tier', $tier_id, "Deleted tier {$tier['tierName']} ($tier_id)");
                $message = 'Tier deleted successfully.';
                $edit_id = '';
            }
        } catch (DomainException | RuntimeException $exception) {
            $message = $exception->getMessage();
            $message_type = 'error';
            $edit_id = $tier_id !== '' ? $tier_id : 'new';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$search_like = '%' . $search . '%';
if ($search !== '') {
    $stmt = $conn->prepare('SELECT t.*, COUNT(ut.userTierID) AS assignedUsers FROM tiers t LEFT JOIN usertier ut ON ut.tierID = t.tierID WHERE t.tierName LIKE ? OR t.tierID LIKE ? GROUP BY t.tierID ORDER BY t.minimumEcoPoints');
    $stmt->bind_param('ss', $search_like, $search_like);
    $stmt->execute();
    $tiers = $stmt->get_result();
} else {
    $tiers = $conn->query('SELECT t.*, COUNT(ut.userTierID) AS assignedUsers FROM tiers t LEFT JOIN usertier ut ON ut.tierID = t.tierID GROUP BY t.tierID ORDER BY t.minimumEcoPoints');
}

$editing_tier = null;
if ($edit_id !== '' && $edit_id !== 'new') {
    $stmt_edit = $conn->prepare('SELECT * FROM tiers WHERE tierID = ?');
    $stmt_edit->bind_param('s', $edit_id);
    $stmt_edit->execute();
    $editing_tier = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
    if (!$editing_tier) {
        $message = 'Tier not found.';
        $message_type = 'error';
        $edit_id = '';
    }
}
$form_tier = $editing_tier ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && $message_type === 'error') {
    $form_tier = array_merge($form_tier, [
        'tierID' => $tier_id,
        'tierName' => trim($_POST['tier_name'] ?? ''),
        'tierDescription' => trim($_POST['tier_description'] ?? ''),
        'tierIcon' => trim($_POST['existing_icon'] ?? ''),
        'minimumEcoPoints' => trim($_POST['minimum_points'] ?? ''),
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tier Management - CirculAPU</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
<div class="dashboard-container">
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <?php include '../header.php'; ?>
        <main class="page-body tiers-page">
            <div class="admin-page-header">
                <div>
                    <p class="breadcrumb">Tier Management</p>
                    <h1 class="header-title">Tier Management</h1>
                    <p class="header-subtitle">Configure member progression levels and EcoPoints thresholds</p>
                </div>
                <a href="tiers.php?edit=new#tier-form" class="btn-new-account"><img src="../assets/icons/create.png" alt=""> New Tier</a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $message_type; ?> tier-alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($edit_id !== ''): ?>
                <section class="content-card tier-form-card" id="tier-form">
                    <div class="table-header-bar">
                        <div><h2><?php echo $editing_tier ? 'Edit Tier' : 'Create New Tier'; ?></h2><p>Thresholds must be unique so every member has one clear progression level.</p></div>
                        <a href="tiers.php" class="tier-close" aria-label="Close form">&times;</a>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="tier-form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="tier_id" value="<?php echo htmlspecialchars($form_tier['tierID'] ?? ''); ?>">
                        <input type="hidden" name="existing_icon" value="<?php echo htmlspecialchars($form_tier['tierIcon'] ?? ''); ?>">
                        <label><span>Tier Name</span><input class="filter-input" name="tier_name" maxlength="50" required value="<?php echo htmlspecialchars($form_tier['tierName'] ?? ''); ?>" placeholder="e.g. Eco Pioneer"></label>
                        <label><span>Minimum EcoPoints</span><input class="filter-input" type="number" name="minimum_points" min="0" step="1" required value="<?php echo htmlspecialchars((string)($form_tier['minimumEcoPoints'] ?? '')); ?>" placeholder="0"></label>
                        <label class="tier-description-field"><span>Description</span><textarea name="tier_description" maxlength="255" required placeholder="Describe what this tier represents"><?php echo htmlspecialchars($form_tier['tierDescription'] ?? ''); ?></textarea></label>
                        <label class="tier-icon-field"><span>Tier Icon <small>(PNG, JPG or WEBP, max 2 MB)</small></span><input type="file" name="tier_icon" accept="image/png,image/jpeg,image/webp"></label>
                        <div class="tier-form-actions"><a href="tiers.php" class="btn-filter-reset">Cancel</a><button class="btn-filter-submit" type="submit"><img src="../assets/icons/save.svg" alt=""> Save Tier</button></div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="content-card admin-filter-card">
                <form method="GET" class="admin-filter-form">
                    <div class="field-box tier-search-field"><label>Search</label><div class="input-wrapper"><img src="../assets/icons/search.png" class="search-icon" alt=""><input class="filter-input" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by tier name or ID..."></div></div>
                    <div class="admin-filter-actions"><button class="btn-filter-submit" type="submit"><img src="../assets/icons/filter.png" alt=""> Filter</button><a class="btn-filter-reset" href="tiers.php"><img src="../assets/icons/reset.png" alt=""> Reset</a></div>
                </form>
            </section>

            <section class="content-card admin-table-card">
                <div class="table-header-bar"><h2>Tier List</h2><span class="total-count">Total Tiers: <?php echo $tiers->num_rows; ?></span></div>
                <div class="tier-table-scroll"><table class="admin-user-table tier-table">
                    <thead><tr><th>Tier</th><th>Description</th><th>Minimum EcoPoints</th><th>Assigned Users</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if ($tiers->num_rows): while ($tier = $tiers->fetch_assoc()): ?>
                        <tr>
                            <td><div class="tier-identity">
                                <?php $icon_name = basename($tier['tierIcon']); $icon_path = '../assets/images/' . $icon_name; ?>
                                <?php if ($tier['tierIcon'] !== '' && is_file(__DIR__ . '/../assets/images/' . $icon_name)): ?><img src="<?php echo htmlspecialchars($icon_path); ?>" alt="">
                                <?php else: ?><span class="tier-icon-fallback"><?php echo htmlspecialchars(strtoupper(substr($tier['tierName'], 0, 1))); ?></span><?php endif; ?>
                                <div><strong><?php echo htmlspecialchars($tier['tierName']); ?></strong></div>
                            </div></td>
                            <td class="tier-description-cell"><?php echo htmlspecialchars($tier['tierDescription']); ?></td>
                            <td><span class="tier-points"><?php echo number_format((int)$tier['minimumEcoPoints']); ?> pts</span></td>
                            <td><?php echo number_format((int)$tier['assignedUsers']); ?></td>
                            <td><div class="tier-actions"><a class="action-icon edit-icon" href="tiers.php?edit=<?php echo urlencode($tier['tierID']); ?>#tier-form" title="Edit Tier"><img src="../assets/icons/edit.png" alt="Edit"></a>
                                <form method="POST" class="inline-action-form" onsubmit="return confirm('Delete this tier? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="tier_id" value="<?php echo htmlspecialchars($tier['tierID']); ?>"><button class="action-icon action-icon-button" type="submit" title="Delete Tier" <?php echo (int)$tier['assignedUsers'] > 0 ? 'disabled' : ''; ?>><img src="../assets/icons/delete.png" alt="Delete"></button></form>
                            </div></td>
                        </tr>
                    <?php endwhile; else: ?><tr><td colspan="5" class="table-empty-msg">No tiers match your search.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
