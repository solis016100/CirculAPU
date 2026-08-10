<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';
$date_filter = $_GET['date_joined'] ?? '';

// Pagination Setup
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base SQL Queries (Using `user` table from schema)
$sql = "SELECT * FROM user WHERE status != 'Deleted'";
$count_sql = "SELECT COUNT(*) as total FROM user WHERE status != 'Deleted'";
$params = [];
$types = "";

// Apply Dynamic Filters
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR userID LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR email LIKE ? OR userID LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= "sss";
}
if (!empty($status_filter) && $status_filter !== 'All Status') {
    $sql .= " AND status = ?";
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($role_filter) && $role_filter !== 'All Roles') {
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

// Get Total Records for Pagination
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_users = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_users / $limit));
$stmt_count->close();

// Append Ordering and Pagination Limits
$sql .= " ORDER BY dateCreated DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Fetch User Data
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard - CirculAPU</title>
    <!-- Primary Global Stylesheet -->
    <link rel="stylesheet" href="../style.css">
    <!-- Secondary Admin Stylesheet -->
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
        <!-- Reusable Sidebar Component from parent directory -->
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
            <!-- Global Header Component from parent directory -->
            <?php include '../header.php'; ?>

            <main class="page-body">
                
                <!-- Page Breadcrumb & Title Bar -->
                <div class="admin-page-header">
                    <div>
                        <p class="header-breadcrumb">User Management</p>
                        <h1 class="header-title">User Management Dashboard</h1>
                        <p class="header-subtitle">Manage all existing users listed on the platform</p>
                    </div>
                    <a href="create_dummy_account.php" class="btn-new-account">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
                        New Dummy Account
                    </a>
                </div>

                <!-- Search and Filter Bar -->
                <div class="content-card filter-card">
                    <form method="GET" action="admin_user_management.php" class="filter-form">
                        
                        <div class="field-box" style="flex: 2; min-width: 220px;">
                            <label>Search</label>
                            <div class="input-wrapper">
                                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" name="search" class="filter-input" placeholder="Search by name, email, userID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <div class="field-box" style="flex: 1; min-width: 140px;">
                            <label>Status</label>
                            <select name="status" class="filter-select">
                                <option>All Status</option>
                                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Suspended" <?php echo $status_filter === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="Deactivated" <?php echo $status_filter === 'Deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                            </select>
                        </div>

                        <div class="field-box" style="flex: 1; min-width: 140px;">
                            <label>Role</label>
                            <select name="role" class="filter-select">
                                <option>All Roles</option>
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

                        <div class="filter-actions">
                            <button type="submit" class="btn-filter-submit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Filter
                            </button>
                            <a href="admin_user_management.php" class="btn-filter-reset">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- User Data Table -->
                <div class="content-card table-card">
                    <div class="table-header-bar">
                        <h2>User List</h2>
                        <span class="total-count">Total User: <?php echo $total_users; ?></span>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Eco Points</th>
                                <th>Status</th>
                                <th>Date Listed</th>
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
                                        <td><?php echo $user['ecopoint'] > 0 ? htmlspecialchars($user['ecopoint']) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($user['status']); ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('j M Y', strtotime($user['dateCreated'])); ?></td>
                                        <td>
                                            <!-- Edit User Button -->
                                            <a href="admin_edit_user.php?id=<?php echo urlencode($user['userID']); ?>" title="Edit User" class="action-icon edit-icon">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </a>
                                            <!-- Toggle Status Button -->
                                            <a href="admin_toggle_status.php?id=<?php echo urlencode($user['userID']); ?>" title="Toggle Status" class="action-icon">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                            </a>
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

                    <!-- Pagination Navigation -->
                    <div class="pagination-footer">
                        <span class="pagination-info">
                            Showing <?php echo ($total_users > 0) ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
                        </span>
                        
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" class="pagination-btn">&lt;</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= min(3, $total_pages); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" class="pagination-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($total_pages > 4): ?>
                                <span class="pagination-ellipsis">...</span>
                                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" class="pagination-btn <?php echo $page == $total_pages ? 'active' : ''; ?>"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>" class="pagination-btn">&gt;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>