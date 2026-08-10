<?php
session_start();
// Include db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Get User ID from GET request
$user_id = $_GET['id'] ?? '';

if (empty($user_id)) {
    header("Location: user_management.php");
    exit();
}

$message = '';
$message_type = '';

// Handle Form Submission (Save Changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $ecopoint = (int)($_POST['ecopoint'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } else {
        // Update user details in database
        $update_sql = "UPDATE user SET name = ?, role = ?, status = ?, ecopoint = ?, email = ? WHERE userID = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("sssiss", $name, $role, $status, $ecopoint, $email, $user_id);
        
        if ($stmt_update->execute()) {
            $message = "User details updated successfully.";
            $message_type = "success";

            // System Log Insertion
            $log_desc = "Updated details for user ID: " . $user_id;
            $admin_id = $_SESSION['user_id'] ?? $user_id;
            $log_sql = "INSERT INTO systemlog (userID, actionType, refType, refID, description) VALUES (?, 'Update', 'User', ?, ?)";
            $stmt_log = $conn->prepare($log_sql);
            if ($stmt_log) {
                $stmt_log->bind_param("sss", $admin_id, $user_id, $log_desc);
                $stmt_log->execute();
                $stmt_log->close();
            }
        } else {
            $message = "Failed to update user details. " . $conn->error;
            $message_type = "error";
        }
        $stmt_update->close();
    }
}

// Fetch Current User Details
$sql = "SELECT * FROM user WHERE userID = ?";
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
$asset_sql = "SELECT COUNT(*) as asset_count FROM assets WHERE ownerID = ?";
$stmt_asset = $conn->prepare($asset_sql);
$stmt_asset->bind_param("s", $user_id);
$stmt_asset->execute();
$listed_assets = $stmt_asset->get_result()->fetch_assoc()['asset_count'] ?? 0;
$stmt_asset->close();
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

                        <!-- Role Field (Uneditable - Gray) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Role</label>
                            <div class="edit-field-input-box uneditable">
                                <input type="text" id="roleDisplayInput" value="<?php echo $user['role'] === 'Member' ? 'Communities' : htmlspecialchars($user['role']); ?>" readonly>
                                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($user['role']); ?>">
                            </div>
                        </div>

                        <!-- Role Promotion Button -->
                        <div class="edit-field-row">
                            <div class="edit-field-label"></div>
                            <div class="edit-field-action-box">
                                <button type="button" class="btn-role-promote" onclick="promoteToAuditor()">
                                    Promote to become auditor
                                </button>
                            </div>
                        </div>

                        <!-- Status Buttons (Active = Green, Suspended = Red) -->
                        <div class="edit-field-row">
                            <label class="edit-field-label">Status</label>
                            <div class="status-toggle-group">
                                <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($user['status']); ?>">
                                <button type="button" class="btn-status-toggle status-active <?php echo $user['status'] === 'Active' ? 'active' : ''; ?>" id="btnStatusActive" onclick="setStatus('Active')">
                                    Active
                                </button>
                                <button type="button" class="btn-status-toggle status-suspended <?php echo $user['status'] === 'Suspended' ? 'active' : ''; ?>" id="btnStatusSuspended" onclick="setStatus('Suspended')">
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
        function promoteToAuditor() {
            document.getElementById('roleInput').value = 'Auditor';
            document.getElementById('roleDisplayInput').value = 'Auditor';
        }

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