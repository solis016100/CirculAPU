<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists('PHPMailer/src/Exception.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

// ====================================================================
// 1. AJAX HANDLER: SEND RESET OTP TO USER'S GMAIL
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reset_otp') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
        exit();
    }

    // Verify if account exists in database
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
        $mail->Username   = 'leegynpaul123456@gmail.com'; // <--- Your Gmail address
        $mail->Password   = 'simwhsbszxrwmatu';             // <--- App Password
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
        echo json_encode(['status' => 'success', 'message' => 'Reset code sent to ' . $email]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Mail delivery failed: ' . $mail->ErrorInfo]);
    }
    exit();
}

$error_message = '';
$success_message = '';

// ====================================================================
// 2. FORM SUBMISSION HANDLER: PROCESS NEW PASSWORD
// ====================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $conn = new mysqli("localhost", "root", "", "circulapu_db");

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    $email             = trim($_POST['email']);
    $otp_entered       = trim($_POST['verification_code']);
    $new_password      = $_POST['new_password'];
    $confirm_password  = $_POST['confirm_password'];

    if (empty($email) || empty($otp_entered) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!isset($_SESSION['reset_otp']) || $_SESSION['reset_otp'] !== $otp_entered || $_SESSION['reset_email'] !== $email) {
        $error_message = "Invalid or expired verification code.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($new_password) < 8 || 
              !preg_match('/[A-Z]/', $new_password) || 
              !preg_match('/[a-z]/', $new_password) || 
              !preg_match('/[0-9!\@\#\$\%\^\&\*]/', $new_password)) {
        $error_message = "Password does not meet security requirements.";
    } else {
        // Hash and update user password
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
        }
        $update_stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CirculAPU</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
            margin-bottom: 25px;
        }

        .reset-header h1 {
            font-size: 2.2rem;
            font-weight: normal;
            margin-bottom: 4px;
        }

        .reset-header p {
            font-size: 0.9rem;
            color: #555;
        }

        .reset-container {
            width: 100%;
            max-width: 460px;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 20px;
        }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .field-box {
            position: relative;
            border: 1px solid #000;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 20px;
        }

        .field-box label {
            position: absolute;
            top: -10px;
            left: 12px;
            background: #fff;
            padding: 0 4px;
            font-size: 0.85rem;
            color: #000;
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
            width: 140px;
        }

        .verification-input {
            border: 1px solid #888;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 0.75rem;
            outline: none;
            text-align: center;
        }

        .resend-btn {
            background-color: #e0e0e0;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5px;
            font-size: 0.75rem;
            cursor: pointer;
            text-align: center;
        }

        .submit-btn-wrapper {
            text-align: center;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .reset-submit-btn {
            width: 160px;
            padding: 10px;
            background-color: #e0e0e0;
            border: 1px solid #999;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .reset-submit-btn:hover {
            background-color: #d0d0d0;
        }

        .login-prompt {
            text-align: center;
            font-size: 0.85rem;
        }

        .login-prompt a {
            color: #000;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body class="reset-page-body">

    <div class="reset-header">
        <div style="margin-bottom: 16px;">
            <img src="assets/images/logo.png" alt="CirculAPU Logo" style="max-width: 130px; height: auto;">
        </div>
        <h1>Reset Password</h1>
        <p>Recover your account password</p>
    </div>

    <div class="reset-container">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="reset_password.php" method="POST">
            <!-- Email & Verification Group -->
            <div style="margin-bottom: 20px;">
                <div class="email-field-row">
                    <div class="field-box">
                        <label for="email">Account Email</label>
                        <div style="display: flex; align-items: center;">
                            <input type="email" id="email" name="email" required>
                            <button type="button" class="send-icon-btn" title="Send code" onclick="triggerResetOTP()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="verification-sidebar">
                        <input type="text" class="verification-input" name="verification_code" id="verification_code" placeholder="Enter code..." required>
                        <button type="button" class="resend-btn" onclick="triggerResetOTP()">Resend code</button>
                    </div>
                </div>
            </div>

            <!-- New Password Field -->
            <div class="field-box">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <!-- Confirm Password Field -->
            <div class="field-box">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <!-- Submit Button -->
            <div class="submit-btn-wrapper">
                <button type="submit" name="reset_password" class="reset-submit-btn">Reset Password</button>
            </div>

            <!-- Navigation Redirect -->
            <div class="login-prompt">
                Remembered your password? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <!-- JavaScript to trigger background Reset OTP Mailer -->
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
    </script>

</body>
</html>