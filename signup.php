<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer classes (Ensure PHPMailer files exist in PHPMailer/src/)
if (file_exists('PHPMailer/src/Exception.php')) {
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

// ====================================================================
// 1. AJAX HANDLER: SEND OTP TO GMAIL
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
        exit();
    }

    // Generate 6-digit OTP code and store in PHP Session
    $otp = rand(100000, 999999);
    $_SESSION['generated_otp'] = (string)$otp;
    $_SESSION['otp_email'] = $email;

    $mail = new PHPMailer(true);

    try {
        // SMTP Server Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // YOUR GMAIL & APP PASSWORD
        $mail->Username   = 'leegynpaul123456@gmail.com'; // <--- Put your Gmail address here
        $mail->Password   = 'simwhsbszxrwmatu';               // <--- Your App Password
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Email Headers & Content
        $mail->setFrom('YOUR_GMAIL_ADDRESS@gmail.com', 'CirculAPU Verification');
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
                <p style='font-size: 0.85rem; color: #666;'>If you did not request this code, please ignore this email.</p>
            </div>
        ";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully to ' . $email]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Mail delivery failed: ' . $mail->ErrorInfo]);
    }
    exit();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

// ====================================================================
// 2. FORM SUBMISSION HANDLER: PROCESS SIGN UP
// ====================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "circulapu_db";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Database Connection failed: " . $conn->connect_error);
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $user_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $verification_code = trim($_POST['verification_code']);

    // Validation Checks
    if (empty($name) || empty($email) || empty($user_password) || empty($confirm_password) || empty($verification_code)) {
        $error_message = "Please fill in all required fields including the verification code.";
    } elseif (!isset($_SESSION['generated_otp']) || $_SESSION['generated_otp'] !== $verification_code || $_SESSION['otp_email'] !== $email) {
        $error_message = "Invalid or expired verification code.";
    } elseif ($user_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($user_password) < 8 || 
              !preg_match('/[A-Z]/', $user_password) || 
              !preg_match('/[a-z]/', $user_password) || 
              !preg_match('/[0-9!\@\#\$\%\^\&\*]/', $user_password)) {
        $error_message = "Password does not meet all security requirements.";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT userID FROM User WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // Generate next incremental userID (e.g., U007)
            $id_query = "SELECT MAX(CAST(SUBSTRING(userID, 2) AS UNSIGNED)) as max_id FROM User";
            $id_result = $conn->query($id_query);
            $row = $id_result->fetch_assoc();
            $next_num = ($row['max_id'] ? $row['max_id'] : 6) + 1;
            $new_user_id = sprintf("U%03d", $next_num);

            // Hash user password
            $hashed_password = password_hash($user_password, PASSWORD_BCRYPT);

            // Insert new user into database
            $insert_stmt = $conn->prepare("INSERT INTO User (userID, name, email, password, role, status, ecopoint) VALUES (?, ?, ?, ?, 'Member', 'Active', 0)");
            $insert_stmt->bind_param("ssss", $new_user_id, $name, $email, $hashed_password);

            if ($insert_stmt->execute()) {
                // Assign default Eco Beginner tier (T001)
                $tier_stmt = $conn->prepare("INSERT INTO UserTier (userTierID, userID, tierID) VALUES (CONCAT('UT', ?), ?, 'T001')");
                $tier_stmt->bind_param("ss", $new_user_id, $new_user_id);
                $tier_stmt->execute();
                $tier_stmt->close();

                // Clear OTP session variables
                unset($_SESSION['generated_otp']);
                unset($_SESSION['otp_email']);

                $success_message = "Account created successfully! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error_message = "Error creating account. Please try again.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CirculAPU</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .signup-header h1 {
            font-size: 2.2rem;
            font-weight: normal;
            margin-bottom: 4px;
        }

        .signup-header p {
            font-size: 0.9rem;
            color: #555;
        }

        .signup-container {
            width: 100%;
            max-width: 460px;
            position: relative;
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

        /* Outline Box Style Matching Wireframe */
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

        /* Email & Verification Code Sidebar Layout */
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

        .resend-btn:hover {
            background-color: #d0d0d0;
        }

        /* Password Requirements Box */
        .requirements-box {
            border: 1px solid #000;
            border-radius: 6px;
            padding: 14px 16px;
            margin-top: 10px;
            margin-bottom: 25px;
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            list-style: none;
            font-size: 0.75rem;
            color: #333;
        }

        .requirements-list li {
            display: flex;
            align-items: flex-start;
            gap: 4px;
        }

        .requirements-list li::before {
            content: "•";
            font-weight: bold;
        }

        /* Submit Button */
        .submit-btn-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }

        .signup-btn {
            width: 120px;
            padding: 10px;
            background-color: #e0e0e0;
            border: 1px solid #999;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .signup-btn:hover {
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

        .login-prompt a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="signup-header">
        <h1>Sign up</h1>
        <p>Sign up to continue</p>
    </div>

    <div class="signup-container">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="signup.php" method="POST">
            <!-- Name Field -->
            <div class="field-box">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <!-- Email & Verification Code Group -->
            <div style="margin-bottom: 20px;">
                <div class="email-field-row">
                    <div class="field-box">
                        <label for="email">Email</label>
                        <div style="display: flex; align-items: center;">
                            <input type="email" id="email" name="email" required>
                            <button type="button" class="send-icon-btn" title="Send verification code" onclick="triggerSendOTP()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="verification-sidebar">
                        <input type="text" class="verification-input" name="verification_code" id="verification_code" placeholder="Enter code..." required>
                        <button type="button" class="resend-btn" onclick="triggerSendOTP()">Resend code</button>
                    </div>
                </div>
            </div>

            <!-- Password Field -->
            <div class="field-box">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <!-- Confirm Password Field -->
            <div class="field-box">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <!-- Password Requirements Panel -->
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

            <!-- Submit Button -->
            <div class="submit-btn-wrapper">
                <button type="submit" name="signup" class="signup-btn">Sign up</button>
            </div>

            <!-- Navigation Redirect -->
            <div class="login-prompt">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <!-- JavaScript to trigger background OTP Mailer -->
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
    </script>

</body>
</html>