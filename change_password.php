<?php
session_start();
require_once 'db.php'; // Centralized database connection

// Ensure user is logged in
if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// Dynamically set the theme class based on the logged-in user's role
$theme_class = 'role-' . strtolower($_SESSION['user_role']);
$user_role_display = htmlspecialchars($_SESSION['user_role']);

$error = '';
$success = '';

// Handle Password Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['user_email'];

    // 1. Fetch current user data to verify old password
    $stmt = $conn->prepare("SELECT password FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Verify current password (supports both plain text fallback and hashes)
        if (!password_verify($current_password, $row['password']) && $current_password !== $row['password']) {
            $error = "The current password you entered is incorrect.";
        } else {
            // 2. Validate New Password Requirements
            $uppercase = preg_match('@[A-Z]@', $new_password);
            $lowercase = preg_match('@[a-z]@', $new_password);
            $number    = preg_match('@[0-9]@', $new_password);
            $specialChars = preg_match('@[^\w]@', $new_password);

            if (!$uppercase || !$lowercase || (!$number && !$specialChars) || strlen($new_password) < 8) {
                $error = "New password does not meet the minimum security requirements.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif ($current_password === $new_password) {
                $error = "New password cannot be the same as your current password.";
            } else {
                // 3. Hash and Update Password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare("UPDATE user SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hashed_password, $email);
                
                if ($update_stmt->execute()) {
                    $success = "Your password has been successfully updated!";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
                $update_stmt->close();
            }
        }
    } else {
        $error = "User record not found.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?php echo htmlspecialchars($theme_class); ?>">
    <div class="dashboard-container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php include 'header.php'; ?>
            <?php include 'right_bar.php'; ?>

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

                <form method="POST" action="">
                    <div class="content-card settings-card">
                        <div class="card-header">
                            <img src="/CirculAPU/assets/icons/password.svg" alt="Lock" width="20" height="20">
                            <h2>Change Password</h2>
                        </div>
                        <hr class="card-divider">
                        
                        <div class="settings-form-body">
                            <!-- Current Password -->
                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>Current Password</h4>
                                    <p>Enter your current password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="current_password" id="current_password" placeholder="••••••••" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('current_password', this)">
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>New Password</h4>
                                    <p>Enter your new password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="new_password" id="new_password" placeholder="••••••••" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('new_password', this)">
                                </div>
                            </div>

                            <!-- Confirm New Password -->
                            <div class="form-row-split">
                                <div class="row-text-info">
                                    <h4>Confirm New Password</h4>
                                    <p>Re-enter your new password.</p>
                                </div>
                                <div class="row-input-box">
                                    <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                                    <img src="/CirculAPU/assets/icons/view.png" alt="Toggle" class="toggle-icon-img" onclick="togglePassword('confirm_password', this)">
                                </div>
                            </div>

                            <!-- Password Requirements Box -->
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
            const pathPrefix = "<?php echo $path_prefix; ?>";
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