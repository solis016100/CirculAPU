<?php
session_start();
// Include central db.php from parent directory
require_once '../db.php';

// Security: Restrict access strictly to System Administrators
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $ecopoint = (int)($_POST['ecopoint'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    // Form Validation
    if (empty($name) || empty($role) || empty($email) || $role === 'Select a role') {
        $message = "Please fill in all required fields marked with *.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } else {
        // Auto-generate Next User ID (e.g., U007, U008)
        $id_query = "SELECT userID FROM user WHERE userID LIKE 'U%' ORDER BY CAST(SUBSTRING(userID, 2) AS UNSIGNED) DESC LIMIT 1";
        $id_result = $conn->query($id_query);
        if ($id_result && $id_result->num_rows > 0) {
            $last_id = $id_result->fetch_assoc()['userID'];
            $num = (int)substr($last_id, 1) + 1;
            $new_user_id = 'U' . str_pad($num, 3, '0', STR_PAD_LEFT);
        } else {
            $new_user_id = 'U001';
        }

        // Set default password for dummy account ('password123')
        $default_password = password_hash('password123', PASSWORD_DEFAULT);

        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT userID FROM user WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "An account with this email already exists.";
            $message_type = "error";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            // Insert New User into Database
            $insert_sql = "INSERT INTO user (userID, name, email, password, role, status, ecopoint, dateCreated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_insert = $conn->prepare($insert_sql);
            $stmt_insert->bind_param("ssssssi", $new_user_id, $name, $email, $default_password, $role, $status, $ecopoint);

            if ($stmt_insert->execute()) {
                $message = "New dummy account ($new_user_id) created successfully!";
                $message_type = "success";

                // System Audit Log
                $admin_id = $_SESSION['user_id'] ?? 'SYSTEM';
                $log_desc = "Created new dummy account ID: " . $new_user_id . " ($role)";
                $log_stmt = $conn->prepare("INSERT INTO systemlog (userID, actionType, refType, refID, description) VALUES (?, 'Create', 'User', ?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("sss", $admin_id, $new_user_id, $log_desc);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            } else {
                $message = "Failed to create account. " . $conn->error;
                $message_type = "error";
            }
            $stmt_insert->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Dummy Account - CirculAPU</title>
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
                        <p class="header-breadcrumb">User Management &gt; New Dummy Account</p>
                        <h1 class="header-title">User Details</h1>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="create-account-wrapper">
                    <!-- User Details Form -->
                    <div class="content-card edit-user-card" style="margin: 0;">
                        <form method="POST" action="create_dummy_account.php" id="createDummyForm">
                            
                            <!-- User ID (Uneditable - Auto Generated) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">User ID</label>
                                <div class="edit-field-input-box uneditable">
                                    <input type="text" value="(Auto Generated)" readonly>
                                </div>
                            </div>

                            <!-- Name Field (Required) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Name *</label>
                                <div class="edit-field-input-box editable">
                                    <input type="text" name="name" placeholder="Enter name..." value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <!-- Role Field (Required Dropdown) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Role *</label>
                                <div class="edit-field-input-box editable">
                                    <select name="role" class="edit-select" required>
                                        <option value="" disabled selected>Select a role</option>
                                        <option value="Member" <?php echo (($_POST['role'] ?? '') === 'Member') ? 'selected' : ''; ?>>Communities</option>
                                        <option value="Auditor" <?php echo (($_POST['role'] ?? '') === 'Auditor') ? 'selected' : ''; ?>>Auditor</option>
                                        <option value="Manager" <?php echo (($_POST['role'] ?? '') === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Admin" <?php echo (($_POST['role'] ?? '') === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Status Buttons (Active = Green, Suspended = Red) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Status</label>
                                <div class="status-toggle-group">
                                    <input type="hidden" name="status" id="statusInput" value="<?php echo htmlspecialchars($_POST['status'] ?? 'Active'); ?>">
                                    <button type="button" class="btn-status-toggle status-active <?php echo ($_POST['status'] ?? 'Active') === 'Active' ? 'active' : ''; ?>" id="btnStatusActive" onclick="setStatus('Active')">
                                        Active
                                    </button>
                                    <button type="button" class="btn-status-toggle status-suspended <?php echo ($_POST['status'] ?? '') === 'Suspended' ? 'active' : ''; ?>" id="btnStatusSuspended" onclick="setStatus('Suspended')">
                                        Suspended
                                    </button>
                                </div>
                            </div>

                            <!-- Eco Points Field with Increment/Decrement Controls -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Eco Points *</label>
                                <div class="ecopoint-input-container editable">
                                    <input type="number" name="ecopoint" id="ecopointInput" value="<?php echo (int)($_POST['ecopoint'] ?? 0); ?>" min="0">
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

                            <!-- Date Joined (Uneditable - Auto Generated) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Date Joined</label>
                                <div class="edit-field-input-box uneditable">
                                    <input type="text" value="(Auto Generated)" readonly>
                                </div>
                            </div>

                            <!-- Listed Asset Field -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Listed Asset *</label>
                                <div class="edit-field-input-box editable">
                                    <input type="text" name="listed_asset" placeholder="Enter Dummy Amount..." value="<?php echo htmlspecialchars($_POST['listed_asset'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Email Field (Required) -->
                            <div class="edit-field-row">
                                <label class="edit-field-label">Email *</label>
                                <div class="edit-field-input-box editable">
                                    <input type="email" name="email" placeholder="Enter Dummy Email..." value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="edit-form-footer">
                                <div style="display: flex; gap: 12px;">
                                    <button type="submit" name="save_account" class="btn-save-changes">
                                        <img src="../assets/icons/save.svg" alt="Save">
                                        Save Changes
                                    </button>
                                    <button type="button" class="btn-clear-form" onclick="clearForm()">
                                        <img src="../assets/icons/reset.png" alt="Reset">
                                        Clear Form
                                    </button>
                                </div>
                                <a href="user_management.php" class="btn-back">
                                    Back
                                </a>
                            </div>

                        </form>
                    </div>

                    <!-- Side Note Card (Wireframe exact match) -->
                    <div class="note-box">
                        <strong class="note-title">Note:</strong>
                        <p class="note-text">Fields marked with * are required.</p>
                    </div>
                </div>

            </main>
        </div>
    </div>

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

        function clearForm() {
            document.getElementById('createDummyForm').reset();
            setStatus('Active');
            document.getElementById('ecopointInput').value = 0;
        }
    </script>
</body>
</html>