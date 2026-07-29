<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('PHPMailer/src/Exception.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

// 1. AJAX HANDLER: SEND RESET OTP TO USER'S GMAIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reset_otp') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
        exit();
    }

    // Direct MySQLi connection without db.php
    $conn = new mysqli("localhost", "root", "", "circulapu_db");
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT userID FROM User WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No account found with this email address.']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();
    $conn->close();

    // Generate 6-digit Reset OTP
    $otp = rand(100000, 999999);
    $_SESSION['reset_otp'] = (string)$otp;
    $_SESSION['reset_email'] = $email;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'leegynpaul123456@gmail.com'; // Your Gmail address
        $mail->Password   = 'simwhsbszxrwmatu';               // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('leegynpaul123456@gmail.com', 'CirculAPU Password Recovery');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP Code - CirculAPU';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; max-width: 480px; border-radius: 8px;'>
                <h2 style='color: #d32f2f; margin-top: 0;'>CirculAPU Password Reset</h2>
                <p>We received a request to reset your password. Use the code below to set a new password:</p>
                <div style='background: #f4f4f4; padding: 15px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #333; margin: 15px 0;'>
                    $otp
                </div>
                <p style='font-size: 0.85rem; color: #666;'>If you did not request this, please ignore this email.</p>
            </div>
        ";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'Reset code sent successfully to ' . $email]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Mail delivery failed: ' . $mail->ErrorInfo]);
    }
    exit();
}

$error_message = '';
$success_message = '';

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$verification_code = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';

// 2. FORM SUBMISSION HANDLERS
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Step 1 -> Step 2 Validation
    if (isset($_POST['go_to_step2'])) {
        if (empty($email) || empty($verification_code)) {
            $error_message = "Please fill in your email and the verification code.";
            $step = 1;
        } elseif (!isset($_SESSION['reset_otp']) || $_SESSION['reset_otp'] !== $verification_code || $_SESSION['reset_email'] !== $email) {
            $error_message = "Invalid or expired verification code.";
            $step = 1;
        } else {
            // OTP is valid! Proceed to Step 2
            $step = 2;
        }
    }

    // Final Reset Submission (Step 2)
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $error_message = "Please enter and confirm your new password.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Passwords do not match.";
            $step = 2;
        } elseif (strlen($new_password) < 8 || 
                  !preg_match('/[A-Z]/', $new_password) || 
                  !preg_match('/[a-z]/', $new_password) || 
                  !preg_match('/[0-9!\@\#\$\%\^\&\*]/', $new_password)) {
            $error_message = "Password does not meet all security requirements.";
            $step = 2;
        } else {
            // Direct MySQLi connection without db.php
            $conn = new mysqli("localhost", "root", "", "circulapu_db");

            if ($conn->connect_error) {
                die("Database Connection failed: " . $conn->connect_error);
            }

            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_stmt = $conn->prepare("UPDATE User SET password = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $hashed_password, $email);

            if ($update_stmt->execute()) {
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_email']);

                $success_message = "Password successfully reset! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error_message = "Failed to update password. Please try again.";
                $step = 2;
            }
            $update_stmt->close();
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
    <title>Reset Password - CirculAPU</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body.reset-page-body {
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .reset-header img {
            max-width: 120px;
            height: auto;
            margin-bottom: 12px;
        }

        .reset-header h1 {
            font-size: 2rem;
            font-weight: normal;
            margin-bottom: 4px;
            color: #000000;
        }

        .reset-header p {
            font-size: 0.9rem;
            color: #555555;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 25px;
        }

        .step-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            background: #e0e0e0;
            color: #666666;
        }

        .step-pill.active {
            background: #16a34a;
            color: #ffffff;
        }

        .reset-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 20px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .field-box {
            position: relative;
            border: 1px solid #000000;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 20px;
            background-color: #ffffff;
        }

        .field-box label {
            position: absolute;
            top: -10px;
            left: 12px;
            background: #ffffff;
            padding: 0 4px;
            font-size: 0.85rem;
            color: #000000;
        }

        .field-box input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 6px 0;
            background: transparent;
        }

        .email-field-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .email-field-row .field-box {
            flex: 1;
            margin-bottom: 0;
        }

        .send-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0 4px;
            display: flex;
            align-items: center;
        }

        .verification-sidebar {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 130px;
        }

        .verification-input {
            border: 1px solid #888888;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 0.8rem;
            outline: none;
            text-align: center;
        }

        .resend-btn {
            background-color: #e0e0e0;
            border: 1px solid #cccccc;
            border-radius: 4px;
            padding: 5px;
            font-size: 0.75rem;
            cursor: pointer;
            text-align: center;
        }

        .password-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toggle-password-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .toggle-password-btn svg {
            width: 20px;
            height: 20px;
            display: block;
        }

        .requirements-box {
            border: 1px solid #000000;
            border-radius: 6px;
            padding: 14px 16px;
            margin-top: 10px;
            margin-bottom: 25px;
            background-color: #ffffff;
        }

        .requirements-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 10px;
        }

        .requirements-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            list-style: none;
            font-size: 0.8rem;
            color: #333333;
            padding-left: 0;
            margin: 0;
        }

        .requirements-list li::before {
            content: "•";
            font-weight: bold;
            margin-right: 6px;
        }

        .submit-btn-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }

        .reset-submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #e0e0e0;
            border: 1px solid #999999;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .reset-submit-btn:hover {
            background-color: #d0d0d0;
        }

        .back-step-btn {
            background: none;
            border: none;
            color: #555555;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: underline;
            margin-bottom: 15px;
        }

        .login-prompt {
            text-align: center;
            font-size: 0.85rem;
        }

        .login-prompt a {
            color: #000000;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body class="reset-page-body">

    <div class="reset-header">
        <div>
            <img src="assets/images/logo.png" alt="CirculAPU Logo">
        </div>
        <h1>Reset Password</h1>
        <p>Recover your account password</p>
    </div>

    <div class="step-indicator">
        <span class="step-pill <?php echo $step === 1 ? 'active' : ''; ?>">Step 1: Verification</span>
        <span class="step-pill <?php echo $step === 2 ? 'active' : ''; ?>">Step 2: Password</span>
    </div>

    <div class="reset-container">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="reset_password.php" method="POST">
            <input type="hidden" name="step" id="step_input" value="<?php echo $step; ?>">

            <?php if ($step === 1): ?>
                <div style="margin-bottom: 20px;">
                    <div class="email-field-row">
                        <div class="field-box">
                            <label for="email">Account Email</label>
                            <div style="display: flex; align-items: center;">
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="alex@mail.apu.edu.my" required>
                                <button type="button" class="send-icon-btn" title="Send code" onclick="triggerResetOTP()">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="verification-sidebar">
                            <input type="text" class="verification-input" name="verification_code" id="verification_code" value="<?php echo htmlspecialchars($verification_code); ?>" placeholder="Enter code..." required>
                            <button type="button" class="resend-btn" onclick="triggerResetOTP()">Resend code</button>
                        </div>
                    </div>
                </div>

                <div class="submit-btn-wrapper">
                    <button type="submit" name="go_to_step2" class="reset-submit-btn">Continue to Password &rarr;</button>
                </div>

            <?php else: ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="verification_code" value="<?php echo htmlspecialchars($verification_code); ?>">

                <div style="text-align: center; margin-bottom: 15px;">
                    <button type="button" class="back-step-btn" onclick="goBackToStep1()">&larr; Edit Email</button>
                </div>

                <div class="field-box">
                    <label for="new_password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" placeholder="••••••••" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)" title="Show/Hide Password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="field-box">
                    <label for="confirm_password">Confirm New Password</label>
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
                    <button type="submit" name="reset_password" class="reset-submit-btn">Reset Password</button>
                </div>
            <?php endif; ?>

            <div class="login-prompt">
                Remembered your password? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <script>
        function triggerResetOTP() {
            const emailInput = document.getElementById('email').value.trim();

            if (!emailInput) {
                alert('Please enter your account email address first.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_reset_otp');
            formData.append('email', emailInput);

            fetch('reset_password.php', {
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