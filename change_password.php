<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once 'db.php'; 
require_once 'Admin/admin_helpers.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

$theme_class = 'role-' . strtolower($_SESSION['user_role']);
$user_role_display = htmlspecialchars($_SESSION['user_role']);

$error = '';
$success = isset($_GET['updated']) && $_GET['updated'] === '1'
    ? 'Your password has been successfully updated.'
    : '';

if (empty($_SESSION['password_csrf_token'])) {
    $_SESSION['password_csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_password'])) {
    $current_password = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new_password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm_password = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $submitted_token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password, role FROM user WHERE userID = ? AND status = 'Active'");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!hash_equals($_SESSION['password_csrf_token'], $submitted_token)) {
        $error = 'Your session has expired. Refresh the page and try again.';
    } elseif ($row = $result->fetch_assoc()) {
        
        if (!password_verify($current_password, $row['password']) && $current_password !== $row['password']) {
            $error = "The current password you entered is incorrect.";
        } else {
            
            $uppercase = preg_match('@[A-Z]@', $new_password);
            $lowercase = preg_match('@[a-z]@', $new_password);
            $number    = preg_match('@[0-9]@', $new_password);
            $specialChars = preg_match('@[^\w]@', $new_password);

            if (!$uppercase || !$lowercase || (!$number && !$specialChars) || strlen($new_password) < 8 || strlen($new_password) > 128) {
                $error = "New password does not meet the minimum security requirements.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif ($current_password === $new_password) {
                $error = "New password cannot be the same as your current password.";
            } else {
                
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $conn->begin_transaction();
                try {
                    $update_stmt = $conn->prepare("UPDATE user SET password = ? WHERE userID = ?");
                    $update_stmt->bind_param("ss", $hashed_password, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    if ($row['role'] === 'Admin') {
                        adminWriteSystemLog(
                            $conn,
                            $user_id,
                            'Update',
                            'User',
                            $user_id,
                            'Changed own account password'
                        );
                    }
                    $conn->commit();
                    $_SESSION['password_csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: change_password.php?updated=1');
                    exit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    error_log($exception->getMessage());
                    $error = 'Unable to update your password right now. Please try again.';
                }
            }
        }
    } else {
        $error = "User record not found.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
    <?php if (($_SESSION['user_role'] ?? '') === 'Manager'): ?><link rel="stylesheet" href="/CirculAPU/Manager/manager_mobile.css?v=<?=filemtime(__DIR__.'/Manager/manager_mobile.css')?>"><?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($theme_class); ?> change-password-page">
    <div class="dashboard-container">
        
            <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php include 'header.php'; ?>

            <main class="page-body">
                <div class="breadcrumb">
                    <?php echo $user_role_display; ?> Dashboard > Settings > Change Password
                </div>

                <div class="page-header">
                    <div class="header-titles">
                        <h1>Change Password</h1>
                        <p class="subtitle">Update your password regularly to keep your account secure</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="change_password.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['password_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="content-card settings-card">
                        <div class="card-header">
                            <img src="/CirculAPU/assets/icons/password.svg" alt="Lock" width="20" height="20">
                            <h2>Change Password</h2>
                        </div>
                        <hr class="card-divider">
                        
                        <div class="settings-form-body">
                            
                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>Current Password</h4>
                                    <p>Enter your current password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="current_password" id="current_password" placeholder="••••••••" autocomplete="current-password" maxlength="128" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('current_password', this)">
                                </div>
                            </div>

                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>New Password</h4>
                                    <p>Enter your new password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="new_password" id="new_password" placeholder="••••••••" autocomplete="new-password" minlength="8" maxlength="128" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('new_password', this)">
                                </div>
                            </div>

                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>Confirm New Password</h4>
                                    <p>Re-enter your new password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" autocomplete="new-password" minlength="8" maxlength="128" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('confirm_password', this)">
                                </div>
                            </div>

                            <div class="requirements-panel">
                                <div class="req-header">
                                    <img src="/CirculAPU/assets/icons/shield.svg" alt="Shield" width="18" height="18">
                                    <h4>Password Requirements</h4>
                                </div>
                                <ul class="req-list-grid">
                                    <li>Minimum 8 characters</li>
                                    <li>Include at least one lowercase letter (a-z)</li>
                                    <li>Include at least one uppercase letter (A-Z)</li>
                                    <li>Include at least one number (0-9) or special character</li>
                                </ul>
                            </div>

                            <div class="settings-submit-wrapper">
                                <button type="submit" name="update_password" class="action-btn outline-btn update-btn">
                                    <img src="/CirculAPU/assets/icons/edit.png" alt="Edit" width="18" height="18"> Update Password
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <button class="action-btn outline-btn back-btn" onclick="history.back()">
                    <img src="/CirculAPU/assets/icons/arrow-left.svg" alt="Back" width="16" height="16"> Back
                </button>

            </main>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.src = "/CirculAPU/assets/icons/eye-off.svg";
            } else {
                input.type = 'password';
                iconElement.src = "/CirculAPU/assets/icons/view.png";
            }
        }
    </script>
</body>
</html>
