<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
require_once '../db.php';
require_once 'admin_helpers.php';

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

 
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$date_filter = $_GET['date_joined'] ?? '';
$allowed_statuses = ['Active', 'Suspended'];
$allowed_roles = ['Admin', 'Manager', 'Auditor', 'Member'];
if (!in_array($status_filter, $allowed_statuses, true)) $status_filter = '';
if (!in_array($role_filter, $allowed_roles, true)) $role_filter = '';

 
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

 
$sql = "SELECT * FROM user WHERE status != 'Deleted'";
$count_sql = "SELECT COUNT(*) as total FROM user WHERE status != 'Deleted'";
$params = [];
$types = "";

 
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR userID LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR email LIKE ? OR userID LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= "sss";
}
if ($status_filter !== '') {
    $sql .= " AND status = ?";
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($role_filter !== '') {
    $sql .= " AND role = ?";
    $count_sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}
if (!empty($date_filter)) {
    $sql .= " AND DATE(dateCreated) = ?";
    $count_sql .= " AND DATE(dateCreated) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

 
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_users = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_users / $limit));
$stmt_count->close();
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

 
$sql .= " ORDER BY dateCreated DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

 
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

function userManagementUrl(int $target_page): string {
    global $search, $status_filter, $role_filter, $date_filter;
    $query = array_filter([
        'page' => $target_page,
        'search' => $search,
        'status' => $status_filter,
        'role' => $role_filter,
        'date_joined' => $date_filter
    ], static fn($value) => $value !== '');
    return '?' . http_build_query($query);
}

$return_query = http_build_query(array_filter([
    'page' => $page,
    'search' => $search,
    'status' => $status_filter,
    'role' => $role_filter,
    'date_joined' => $date_filter
], static fn($value) => $value !== ''));
$notice = $_GET['notice'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard - CirculAPU</title>
     
    <link rel="stylesheet" href="../style.css">
     
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
         
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
             
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                 
                <div class="admin-page-header">
                    <div>
                        <p class="breadcrumb">User Management</p>
                        <h1 class="header-title">User Management Dashboard</h1>
                        <p class="header-subtitle">Manage all existing users listed on the platform</p>
                    </div>
                    <a href="create_dummy_account.php" class="btn-new-account">
                        <img src="../assets/icons/create.png" alt="Add">
                        New Dummy Account
                    </a>
                </div>

                <?php if ($notice === 'status-updated'): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">User status updated and recorded in the system log.</div>
                <?php elseif ($notice === 'self-status-blocked'): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">You cannot suspend your own administrator account.</div>
                <?php elseif ($notice === 'toggle-failed'): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">The user status could not be updated.</div>
                <?php endif; ?>

                 
                <div class="content-card admin-filter-card">
                    <form method="GET" action="user_management.php" class="admin-filter-form">
                        
                        <div class="field-box" style="flex: 2; min-width: 220px;">
                            <label>Search</label>
                            <div class="input-wrapper">
                                <img src="../assets/icons/search.png" alt="Search" class="search-icon">
                                <input type="text" name="search" class="filter-input" placeholder="Search by name, email, userID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <div class="field-box" style="flex: 1; min-width: 140px;">
                            <label>Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Suspended" <?php echo $status_filter === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>

                        <div class="field-box" style="flex: 1; min-width: 140px;">
                            <label>Role</label>
                            <select name="role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="Admin" <?php echo $role_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Manager" <?php echo $role_filter === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="Auditor" <?php echo $role_filter === 'Auditor' ? 'selected' : ''; ?>>Auditor</option>
                                <option value="Member" <?php echo $role_filter === 'Member' ? 'selected' : ''; ?>>Communities</option>
                            </select>
                        </div>

                        <div class="field-box" style="flex: 1; min-width: 140px;">
                            <label>Date Joined</label>
                            <input type="date" name="date_joined" class="filter-input" style="padding-left: 10px;" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>

                        <div class="admin-filter-actions">
                            <button type="submit" class="btn-filter-submit">
                                <img src="../assets/icons/filter.png" alt="Filter"> Filter
                            </button>
                            <a href="user_management.php" class="btn-filter-reset">
                                <img src="../assets/icons/reset.png" alt="Reset"> Reset
                            </a>
                        </div>
                    </form>
                </div>

                 
                <div class="content-card admin-table-card">
                    <div class="table-header-bar">
                        <h2>User List</h2>
                        <span class="total-count">Total User: <?php echo $total_users; ?></span>
                    </div>

                    <table class="admin-user-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Eco Points</th>
                                <th>Status</th>
                                <th>Date Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows > 0): ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="col-userid"><?php echo htmlspecialchars($user['userID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td>
                                            <?php 
                                                $role_display = $user['role'] === 'Member' ? 'Communities' : htmlspecialchars($user['role']);
                                                echo $role_display;
                                            ?>
                                        </td>
                                        <td><?php echo (int)$user['ecopoint']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($user['status']); ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('j M Y', strtotime($user['dateCreated'])); ?></td>
                                        <td>
                                             
                                            <a href="admin_edit_user.php?id=<?php echo urlencode($user['userID']); ?>" title="Edit User" class="action-icon edit-icon">
                                                <img src="../assets/icons/edit.png" alt="Edit">
                                            </a>
                                            <?php if ($user['userID'] !== $_SESSION['user_id']): ?>
                                                <form method="POST" action="admin_toggle_status.php" class="inline-action-form" onsubmit="return confirm('Change this user’s status to <?php echo $user['status'] === 'Active' ? 'Suspended' : 'Active'; ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['userID']); ?>">
                                                    <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($return_query); ?>">
                                                    <button type="submit" title="<?php echo $user['status'] === 'Active' ? 'Suspend User' : 'Activate User'; ?>" class="action-icon action-icon-button">
                                                        <img src="../assets/icons/ban.svg" alt="Toggle Status">
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="table-empty-msg">No users found matching your search criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                     
                    <div class="admin-pagination-footer">
                        <span class="pagination-info">
                            Showing <?php echo ($total_users > 0) ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
                        </span>
                        
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo htmlspecialchars(userManagementUrl($page - 1)); ?>" class="pagination-btn">&lt;</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="<?php echo htmlspecialchars(userManagementUrl($i)); ?>" class="pagination-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo htmlspecialchars(userManagementUrl($page + 1)); ?>" class="pagination-btn">&gt;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
