<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/smtp_config.php';

if (file_exists('PHPMailer/src/Exception.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
        exit();
    }

    $otp = rand(100000, 999999);
    $_SESSION['generated_otp'] = (string)$otp;
    $_SESSION['otp_email'] = $email;

    $mail = new PHPMailer(true);

    try {
        configureCirculApuSmtp($mail);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your CirculAPU Sign-Up OTP Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; max-width: 480px; border-radius: 8px;'>
                <h2 style='color: #2e7d32; margin-top: 0;'>CirculAPU Verification Code</h2>
                <p>Please enter the following 6-digit OTP code on the registration page:</p>
                <div style='background: #f4f4f4; padding: 15px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #333; margin: 15px 0;'>
                    $otp
                </div>
            </div>
        ";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully to ' . $email]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Email could not be sent. Please try again later.']);
    }
    exit();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$verification_code = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';

 
if ($_SERVER["REQUEST_METHOD"] == "POST") {

     
    if (isset($_POST['go_to_step2'])) {
        if (empty($name) || empty($email) || empty($verification_code)) {
            $error_message = "Please fill in all required fields including the verification code.";
            $step = 1;
        } elseif (!isset($_SESSION['generated_otp']) || $_SESSION['generated_otp'] !== $verification_code || $_SESSION['otp_email'] !== $email) {
            $error_message = "Invalid or expired verification code.";
            $step = 1;
        } else {
            $step = 2;
        }
    }

     
    if (isset($_POST['signup'])) {
        $user_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($user_password) || empty($confirm_password)) {
            $error_message = "Please enter and confirm your password.";
            $step = 2;
        } elseif ($user_password !== $confirm_password) {
            $error_message = "Passwords do not match.";
            $step = 2;
        } elseif (strlen($user_password) < 8 || 
                  !preg_match('/[A-Z]/', $user_password) || 
                  !preg_match('/[a-z]/', $user_password) || 
                  !preg_match('/[0-9!\@\#\$\%\^\&\*]/', $user_password)) {
            $error_message = "Password does not meet all security requirements.";
            $step = 2;
        } else {
            require_once 'db.php';
            $check_stmt = $conn->prepare("SELECT userID FROM User WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error_message = "An account with this email already exists.";
                $step = 1;
            } else {
                $id_query = "SELECT MAX(CAST(SUBSTRING(userID, 2) AS UNSIGNED)) as max_id FROM User";
                $id_result = $conn->query($id_query);
                $row = $id_result->fetch_assoc();
                $next_num = ($row['max_id'] ? $row['max_id'] : 6) + 1;
                $new_user_id = sprintf("U%03d", $next_num);

                $hashed_password = password_hash($user_password, PASSWORD_BCRYPT);

                $insert_stmt = $conn->prepare("INSERT INTO User (userID, name, email, password, role, status, ecopoint) VALUES (?, ?, ?, ?, 'Member', 'Active', 0)");
                $insert_stmt->bind_param("ssss", $new_user_id, $name, $email, $hashed_password);

                if ($insert_stmt->execute()) {
                    $tier_stmt = $conn->prepare("INSERT INTO UserTier (userTierID, userID, tierID) VALUES (CONCAT('UT', ?), ?, 'T001')");
                    $tier_stmt->bind_param("ss", $new_user_id, $new_user_id);
                    $tier_stmt->execute();
                    $tier_stmt->close();

                    unset($_SESSION['generated_otp']);
                    unset($_SESSION['otp_email']);

                    $success_message = "Account created successfully! Redirecting to login...";
                    header("refresh:2;url=login.php");
                } else {
                    $error_message = "Error creating account. Please try again.";
                    $step = 2;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="signup-page-body">

    <div class="signup-header">
        <div>
            <img src="assets/images/logo.png" alt="CirculAPU Logo">
        </div>
        <h1>Sign up</h1>
        <p>Sign up to continue</p>
    </div>

    <div class="step-indicator">
        <span class="step-pill <?php echo $step === 1 ? 'active' : ''; ?>">Step 1: Verification</span>
        <span class="step-pill <?php echo $step === 2 ? 'active' : ''; ?>">Step 2: Password</span>
    </div>

    <div class="signup-container">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="signup.php" method="POST">
            <input type="hidden" name="step" id="step_input" value="<?php echo $step; ?>">

            <?php if ($step === 1): ?>
                <div class="field-box">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Alex Tan" required>
                </div>

                <div style="margin-bottom: 20px;">
                    <div class="email-field-row">
                        <div class="field-box">
                            <label for="email">Email</label>
                            <div style="display: flex; align-items: center;">
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="alex@mail.apu.edu.my" required>
                                <button type="button" class="send-icon-btn" title="Send verification code" onclick="triggerSendOTP()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="verification-sidebar">
                            <input type="text" class="verification-input" name="verification_code" id="verification_code" value="<?php echo htmlspecialchars($verification_code); ?>" placeholder="Enter code..." required>
                            <button type="button" class="resend-btn" onclick="triggerSendOTP()">Resend code</button>
                        </div>
                    </div>
                </div>

                <div class="submit-btn-wrapper">
                    <button type="submit" name="go_to_step2" class="signup-submit-btn">Continue to Password &rarr;</button>
                </div>

            <?php else: ?>
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="verification_code" value="<?php echo htmlspecialchars($verification_code); ?>">

                <div style="text-align: center; margin-bottom: 15px;">
                    <button type="button" class="back-step-btn" onclick="goBackToStep1()">&larr; Edit Name or Email</button>
                </div>

                <div class="field-box">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('password', this)" title="Show/Hide Password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field-box">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Show/Hide Password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="requirements-box">
                    <div class="requirements-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <polyline points="9 12 11 14 15 10"></polyline>
                        </svg>
                        <span>Password Requirements</span>
                    </div>
                    <ul class="requirements-list">
                        <li>Minimum 8 characters</li>
                        <li>Include at least one lowercase letter (a-z)</li>
                        <li>Include at least one uppercase letter (A-Z)</li>
                        <li>Include at least one number (0-9) or special character</li>
                    </ul>
                </div>

                <div class="submit-btn-wrapper">
                    <button type="submit" name="signup" class="signup-submit-btn">Complete Sign Up</button>
                </div>
            <?php endif; ?>

            <div class="login-prompt">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <script>
        function triggerSendOTP() {
            const emailInput = document.getElementById('email').value.trim();

            if (!emailInput) {
                alert('Please enter your email address first.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('email', emailInput);

            fetch('signup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the OTP code.');
            });
        }

        function togglePasswordVisibility(fieldId, btn) {
            const input = document.getElementById(fieldId);
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            } else {
                input.type = 'password';
                btn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        }

        function goBackToStep1() {
            document.getElementById('step_input').value = '1';
            document.querySelector('form').submit();
        }
    </script>

</body>
</html>
