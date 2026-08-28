<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
require_once '../db.php';
require_once 'admin_helpers.php';

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = '';
$allowed_roles = ['Member', 'Auditor', 'Manager', 'Admin'];
$allowed_statuses = ['Active', 'Suspended'];

if (isset($_GET['created']) && preg_match('/^U\d+$/', $_GET['created'])) {
    $message = "Dummy account {$_GET['created']} created successfully. Temporary password: password123";
    $message_type = 'success';
}

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $ecopoint = (int)($_POST['ecopoint'] ?? 0);
    $email = trim($_POST['email'] ?? '');

     
    if (!adminVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh the page and try again.';
        $message_type = 'error';
    } elseif ($name === '' || mb_strlen($name) > 100 || $role === '' || $email === '') {
        $message = "Please fill in all required fields marked with *.";
        $message_type = "error";
    } elseif (!in_array($role, $allowed_roles, true) || !in_array($status, $allowed_statuses, true)) {
        $message = 'Select a valid role and account status.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $message = "Invalid email format.";
        $message_type = "error";
    } elseif ($ecopoint < 0) {
        $message = 'EcoPoints cannot be negative.';
        $message_type = 'error';
    } else {
        try {
            $conn->begin_transaction();

            $check_stmt = $conn->prepare('SELECT userID FROM user WHERE email = ? LIMIT 1');
            $check_stmt->bind_param('s', $email);
            $check_stmt->execute();
            $email_exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            if ($email_exists) {
                throw new DomainException('An account with this email already exists.');
            }

            $new_user_id = adminNextIdentifier($conn, 'user');
            $default_password = password_hash('password123', PASSWORD_DEFAULT);

            $insert_sql = 'INSERT INTO user (userID, name, email, password, role, status, ecopoint, dateCreated)
                           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
            $stmt_insert = $conn->prepare($insert_sql);
            $stmt_insert->bind_param('ssssssi', $new_user_id, $name, $email, $default_password, $role, $status, $ecopoint);
            $stmt_insert->execute();
            $stmt_insert->close();

            adminWriteEcoPointTransaction($conn, $new_user_id, $ecopoint, $_SESSION['user_id']);
            adminWriteSystemLog(
                $conn,
                $_SESSION['user_id'],
                'Create',
                'User',
                $new_user_id,
                "Created dummy account $new_user_id with role $role"
            );

            $conn->commit();
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: create_dummy_account.php?created=' . urlencode($new_user_id));
            exit();
        } catch (DomainException $exception) {
            $conn->rollback();
            $message = $exception->getMessage();
            $message_type = 'error';
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log($exception->getMessage());
            $message = 'Failed to create the dummy account. Please try again.';
            $message_type = 'error';
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
                     
                    <div class="content-card edit-user-card" style="margin: 0;">
                        <form method="POST" action="create_dummy_account.php" id="createDummyForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
                            
                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">User ID</label>
                                <div class="edit-field-input-box uneditable">
                                    <input type="text" value="(Auto Generated)" readonly>
                                </div>
                            </div>

                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">Name *</label>
                                <div class="edit-field-input-box editable">
                                    <input type="text" name="name" placeholder="Enter name..." value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                            </div>

                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">Role *</label>
                                <div class="edit-field-input-box editable">
                                    <select name="role" class="edit-select" required>
                                        <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select a role</option>
                                        <option value="Member" <?php echo (($_POST['role'] ?? '') === 'Member') ? 'selected' : ''; ?>>Communities</option>
                                        <option value="Auditor" <?php echo (($_POST['role'] ?? '') === 'Auditor') ? 'selected' : ''; ?>>Auditor</option>
                                        <option value="Manager" <?php echo (($_POST['role'] ?? '') === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Admin" <?php echo (($_POST['role'] ?? '') === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>

                             
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

                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">Date Joined</label>
                                <div class="edit-field-input-box uneditable">
                                    <input type="text" value="(Auto Generated)" readonly>
                                </div>
                            </div>

                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">Listed Asset</label>
                                <div class="edit-field-input-box uneditable">
                                    <input type="text" value="0 (create listings separately)" readonly>
                                </div>
                            </div>

                             
                            <div class="edit-field-row">
                                <label class="edit-field-label">Email *</label>
                                <div class="edit-field-input-box editable">
                                    <input type="email" name="email" placeholder="Enter Dummy Email..." value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                             
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

                     
                    <div class="note-box">
                        <strong class="note-title">Note:</strong>
                        <p class="note-text">Fields marked with * are required.</p>
                        <p class="note-text">Asset totals come from actual asset records and are not stored as a dummy number.</p>
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
