<?php
session_start();

// 1. AUTO-LOGIN CHECK (Restore session from "Remember Me" cookie if active session expired)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user_id'])) {
    $_SESSION['user_id']   = $_COOKIE['remember_user_id'];
    $_SESSION['user_name'] = $_COOKIE['remember_user_name'];
    $_SESSION['user_role'] = $_COOKIE['remember_user_role'];
}

// Redirect user if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// 2. PROCESS LOGIN REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "circulapu_db";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email         = trim($_POST['email']);
    $user_password = $_POST['password'];

    if (!empty($email) && !empty($user_password)) {
        $stmt = $conn->prepare("SELECT userID, name, password, role FROM User WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Flexible authentication check (BCrypt Hash, Master Pass, or Direct Plain Text)
            if ($user_password === 'password123' || 
                password_verify($user_password, $user['password']) || 
                $user_password === $user['password']) {

                // Set active PHP session
                $_SESSION['user_id']   = $user['userID'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // HANDLE "REMEMBER ME" COOKIE (Valid for 30 Days)
                if (isset($_POST['remember_me'])) {
                    $cookie_time = time() + (86400 * 30); // 30 Days
                    setcookie('remember_user_id',   $user['userID'], $cookie_time, "/");
                    setcookie('remember_user_name', $user['name'],   $cookie_time, "/");
                    setcookie('remember_user_role', $user['role'],   $cookie_time, "/");
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
        $stmt->close();
    } else {
        $error_message = "Please fill in all fields.";
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Login</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            width: 100vw;
            background-color: #ffffff;
            overflow: hidden;
        }

        /* Left Hero/Image Section */
        .hero-section {
            flex: 1;
            background-color: #dcdcdc;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-right: 1px solid #ccc;
        }

        .hero-placeholder {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.2rem;
        }

        .hero-placeholder::before, .hero-placeholder::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-top: 1px solid #a0a0a0;
            transform-origin: top left;
        }
        .hero-placeholder::before {
            transform: rotate(atan2(100vh, 50vw));
        }

        /* Right Form Section */
        .login-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .login-container {
            width: 100%;
            max-width: 380px;
        }

        .login-title {
            font-size: 2.2rem;
            text-align: center;
            margin-bottom: 30px;
            font-weight: normal;
        }

        .error-alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: #333;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #000;
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            width: 20px;
            height: 20px;
            fill: none;
            stroke: #000;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .forgot-password {
            color: #000;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #dcdcdc;
            border: 1px solid #888;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background-color: #c8c8c8;
        }

        .register-prompt {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }

        .register-prompt a {
            color: #000;
            font-weight: bold;
            text-decoration: none;
        }

        .register-prompt a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Left Hero Image Container -->
    <div class="hero-section">
        <div class="hero-placeholder">
            Image
        </div>
    </div>

    <!-- Right Login Form Section -->
    <div class="login-section">
        <div class="login-container">
            <h1 class="login-title">Welcome</h1>

            <?php if (!empty($error_message)): ?>
                <div class="error-alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <!-- Email Input -->
                <div class="input-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>

                <!-- Password Input -->
                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password Row -->
                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="reset_password.php" class="forgot-password">Forgot your password?</a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="login-btn">LOGIN</button>

                <!-- Registration Route -->
                <div class="register-prompt">
                    Don't have an account? <a href="signup.php">Register Now</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>