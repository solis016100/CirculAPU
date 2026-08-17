<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Include db.php from parent directory
require_once '../db.php';
require_once 'admin_helpers.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Get User ID from GET request
$user_id = trim($_GET['id'] ?? '');

if (empty($user_id)) {
    header("Location: user_management.php");
    exit();
}

$message = '';
$message_type = '';
$allowed_roles = ['Member', 'Auditor', 'Manager', 'Admin'];
$allowed_statuses = ['Active', 'Suspended'];

// Handle Form Submission (Save Changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $ecopoint = (int)($_POST['ecopoint'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    if (!adminVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh the page and try again.';
        $message_type = 'error';
    } elseif ($name === '' || mb_strlen($name) > 100) {
        $message = 'Name is required and must not exceed 100 characters.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = 'Enter a valid email address.';
        $message_type = 'error';
    } elseif (!in_array($role, $allowed_roles, true) || !in_array($status, $allowed_statuses, true)) {
        $message = 'Select a valid role and account status.';
        $message_type = 'error';
    } elseif ($ecopoint < 0) {
        $message = 'EcoPoints cannot be negative.';
        $message_type = 'error';
    } elseif ($user_id === $_SESSION['user_id'] && ($role !== 'Admin' || $status !== 'Active')) {
        $message = 'You cannot remove your own Admin role or suspend your own account.';
        $message_type = 'error';
    } else {
        try {
            $conn->begin_transaction();

            $current_stmt = $conn->prepare("SELECT * FROM user WHERE userID = ? AND status != 'Deleted' FOR UPDATE");
            $current_stmt->bind_param('s', $user_id);
            $current_stmt->execute();
            $current = $current_stmt->get_result()->fetch_assoc();
            $current_stmt->close();
            if (!$current) {
                throw new RuntimeException('User account not found.');
            }

            $email_stmt = $conn->prepare('SELECT userID FROM user WHERE email = ? AND userID <> ? LIMIT 1');
            $email_stmt->bind_param('ss', $email, $user_id);
            $email_stmt->execute();
            $email_exists = $email_stmt->get_result()->num_rows > 0;
            $email_stmt->close();
            if ($email_exists) {
                throw new DomainException('That email address is already used by another account.');
            }

            if ($current['role'] === 'Admin' && $current['status'] === 'Active'
                && ($role !== 'Admin' || $status !== 'Active')) {
                $admin_count = (int)$conn->query(
                    "SELECT COUNT(*) AS total FROM user WHERE role = 'Admin' AND status = 'Active'"
                )->fetch_assoc()['total'];
                if ($admin_count <= 1) {
                    throw new DomainException('The system must retain at least one active administrator.');
                }
            }

            $changed_fields = [];
            foreach (['name', 'role', 'status', 'email'] as $field) {
                if ((string)$current[$field] !== (string)$$field) {
                    $changed_fields[] = $field;
                }
            }
            $point_difference = $ecopoint - (int)$current['ecopoint'];
            if ($point_difference !== 0) {
                $changed_fields[] = 'EcoPoints';
            }

            if ($changed_fields) {
                $update_sql = 'UPDATE user SET name = ?, role = ?, status = ?, ecopoint = ?, email = ? WHERE userID = ?';
                $stmt_update = $conn->prepare($update_sql);
                $stmt_update->bind_param('sssiss', $name, $role, $status, $ecopoint, $email, $user_id);
                $stmt_update->execute();
                $stmt_update->close();

                adminWriteEcoPointTransaction($conn, $user_id, $point_difference, $_SESSION['user_id']);
                adminWriteSystemLog(
                    $conn,
                    $_SESSION['user_id'],
                    'Update',
                    'User',
                    $user_id,
                    'Updated ' . implode(', ', $changed_fields) . " for user $user_id"
                );
            }

            $conn->commit();

            if ($user_id === $_SESSION['user_id']) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
            }
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: admin_edit_user.php?id=' . urlencode($user_id) . '&updated=' . ($changed_fields ? '1' : '0'));
            exit();
        } catch (DomainException $exception) {
            $conn->rollback();
            $message = $exception->getMessage();
            $message_type = 'error';
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log($exception->getMessage());
            $message = 'Failed to update the user. Please try again.';
            $message_type = 'error';
        }
    }
}

// Fetch Current User Details
$sql = "SELECT * FROM user WHERE userID = ? AND status != 'Deleted'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: user_management.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch Count of Listed Assets owned by this user
$asset_sql = "SELECT COUNT(*) as asset_count FROM assets WHERE ownerID = ? AND listingStatus != 'Removed'";
$stmt_asset = $conn->prepare($asset_sql);
$stmt_asset->bind_param("s", $user_id);
$stmt_asset->execute();
$listed_assets = $stmt_asset->get_result()->fetch_assoc()['asset_count'] ?? 0;
$stmt_asset->close();

if (($_GET['updated'] ?? '') === '1') {
    $message = 'User details updated successfully and recorded in the system log.';
    $message_type = 'success';
} elseif (($_GET['updated'] ?? '') === '0') {
    $message = 'No changes were detected.';
    $message_type = 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - CirculAPU</title>
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
                        <p class="header-breadcrumb">User Management &gt; User Details</p>
                        <h1 class="header-title">User Details</h1>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- User Details Form Container -->
                <div class="content-card edit-user-card">
                    <form method="POST" action="admin_edit_user.php?id=<?php echo urlencode($user_id); ?>" id="editUserForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
                        
                        <!-- User ID Field (Uneditable - Gray) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">User ID</label>
                            <div class="edit-field-input-box uneditable">
                                <input type="text" name="userID" value="<?php echo htmlspecialchars($user['userID']); ?>" readonly>
                            </div>
                        </div>

                        <!-- Name Field (Editable - White) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Name</label>
                            <div class="edit-field-input-box editable">
                                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                        </div>

                        <!-- Role Field -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Role</label>
                            <div class="edit-field-input-box <?php echo $user_id === $_SESSION['user_id'] ? 'uneditable' : 'editable'; ?>">
                                <?php if ($user_id === $_SESSION['user_id']): ?>
                                    <input type="text" value="Admin" readonly>
                                    <input type="hidden" name="role" value="Admin">
                                <?php else: ?>
                                    <select name="role" class="edit-select" required>
                                        <option value="Member" <?php echo $user['role'] === 'Member' ? 'selected' : ''; ?>>Communities</option>
                                        <option value="Auditor" <?php echo $user['role'] === 'Auditor' ? 'selected' : ''; ?>>Auditor</option>
                                        <option value="Manager" <?php echo $user['role'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Admin" <?php echo $user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Status Buttons (Active = Green, Suspended = Red) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Status</label>
                            <div class="status-toggle-group">
                                <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($user['status']); ?>">
                                <button type="button" class="btn-status-toggle status-active <?php echo $user['status'] === 'Active' ? 'active' : ''; ?>" id="btnStatusActive" onclick="setStatus('Active')" <?php echo $user_id === $_SESSION['user_id'] ? 'disabled title="Your Admin account must remain active"' : ''; ?>>
                                    Active
                                </button>
                                <button type="button" class="btn-status-toggle status-suspended <?php echo $user['status'] === 'Suspended' ? 'active' : ''; ?>" id="btnStatusSuspended" onclick="setStatus('Suspended')" <?php echo $user_id === $_SESSION['user_id'] ? 'disabled title="You cannot suspend your own account"' : ''; ?>>
                                    Suspended
                                </button>
                            </div>
                        </div>

                        <!-- Eco Points Field (Editable - White with Controls) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Eco Points</label>
                            <div class="ecopoint-input-container editable">
                                <input type="number" name="ecopoint" id="ecopointInput" value="<?php echo (int)$user['ecopoint']; ?>" min="0">
                                <div class="ecopoint-controls">
                                    <button type="button" class="btn-point-control" onclick="adjustPoints(10)" title="Add 10 Points">
                                        <img src="../assets/icons/create.png" alt="Add Points">
                                    </button>
                                    <button type="button" class="btn-point-control" onclick="adjustPoints(-10)" title="Deduct 10 Points">
                                        <img src="../assets/icons/minus-circle.svg" alt="Deduct Points">
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Date Joined Field (Uneditable - Gray) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Date Joined</label>
                            <div class="edit-field-input-box uneditable">
                                <input type="text" value="<?php echo date('j M Y', strtotime($user['dateCreated'])); ?>" readonly>
                            </div>
                        </div>

                        <!-- Listed Asset Count Field (Uneditable - Gray) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Listed Asset</label>
                            <div class="edit-field-input-box uneditable">
                                <input type="text" value="<?php echo $listed_assets; ?>" readonly>
                            </div>
                        </div>

                        <!-- Email Field (Editable - White) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Email</label>
                            <div class="edit-field-input-box editable">
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <!-- Bottom Form Buttons -->
                        <div class="edit-form-footer">
                            <button type="submit" name="save_changes" class="btn-save-changes">
                                <img src="../assets/icons/save.svg" alt="Save">
                                Save Changes
                            </button>
                            <a href="user_management.php" class="btn-back">
                                Back
                            </a>
                        </div>

                    </form>
                </div>

            </main>
        </div>
    </div>

    <!-- Interactive Control Scripts -->
    <script>
        function setStatus(statusValue) {
            document.getElementById('statusInput').value = statusValue;
            
            const btnActive = document.getElementById('btnStatusActive');
            const btnSuspended = document.getElementById('btnStatusSuspended');

            if (statusValue === 'Active') {
                btnActive.classList.add('active');
                btnSuspended.classList.remove('active');
            } else {
                btnSuspended.classList.add('active');
                btnActive.classList.remove('active');
            }
        }

        function adjustPoints(amount) {
            const pointInput = document.getElementById('ecopointInput');
            let currentVal = parseInt(pointInput.value) || 0;
            currentVal += amount;
            if (currentVal < 0) currentVal = 0;
            pointInput.value = currentVal;
        }
    </script>
</body>
</html>
