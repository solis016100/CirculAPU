<?php
session_start();
require_once 'db.php';

/**
 * Record a successful authentication and return the database timestamp used.
 * Logging failures must not prevent an otherwise valid login.
 */
function recordSuccessfulLogin(mysqli $conn, string $userID): ?string
{
    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $idResult = $conn->query(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(logID, 4) AS UNSIGNED)), 0) + 1 AS nextID FROM systemlog"
            );
            $nextID = (int) $idResult->fetch_assoc()['nextID'];
            $logID = 'LOG' . str_pad((string) $nextID, 3, '0', STR_PAD_LEFT);

            $description = 'Logged in to system';
            $logStmt = $conn->prepare(
                "INSERT INTO systemlog (logID, userID, actionType, refType, refID, description)
                 VALUES (?, ?, 'Login', 'User', ?, ?)"
            );
            $logStmt->bind_param('ssss', $logID, $userID, $userID, $description);
            $logStmt->execute();
            $logStmt->close();

            $timeStmt = $conn->prepare("SELECT timeLogged FROM systemlog WHERE logID = ?");
            $timeStmt->bind_param('s', $logID);
            $timeStmt->execute();
            $loginTime = $timeStmt->get_result()->fetch_assoc()['timeLogged'] ?? null;
            $timeStmt->close();

            return $loginTime;
        } catch (Throwable $exception) {
            // A simultaneous login can claim the same generated ID; retry it.
            if ($attempt === 2) {
                error_log('Unable to record login activity: ' . $exception->getMessage());
            }
        }
    }

    return null;
}

// 1. AUTO-LOGIN CHECK VIA "REMEMBER ME" COOKIES
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user_id'])) {
    $_SESSION['user_id']    = $_COOKIE['remember_user_id'];
    $_SESSION['user_name']  = $_COOKIE['remember_user_name']  ?? 'User';
    $_SESSION['user_role']  = $_COOKIE['remember_user_role']  ?? 'Member';
    $_SESSION['user_email'] = $_COOKIE['remember_user_email'] ?? '';

    $rememberConn = new mysqli("localhost", "root", "", "circulapu_db");
    if (!$rememberConn->connect_error) {
        // Fallback: If email cookie was missing, query database using stored user ID
        if (empty($_SESSION['user_email'])) {
            $stmt = $rememberConn->prepare("SELECT email FROM User WHERE userID = ?");
            $stmt->bind_param("s", $_SESSION['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $_SESSION['user_email'] = $row['email'];
                // Set missing cookie for future visits
                setcookie('remember_user_email', $row['email'], time() + (86400 * 30), "/");
            }
            $stmt->close();
        }

        $_SESSION['last_login_at'] = recordSuccessfulLogin($rememberConn, $_SESSION['user_id'])
            ?? date('Y-m-d H:i:s');
        $rememberConn->close();
    }
}

// 2. REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// 3. FORM SUBMISSION (MANUAL LOGIN)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email         = trim($_POST['email']);
    $user_password = $_POST['password'];

    if (!empty($email) && !empty($user_password)) {
        // Fetch user ID, name, password, role AND email
        $stmt = $conn->prepare("SELECT userID, name, password, role, email FROM User WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify the password stored for this account. Plain-text comparison is
            // retained temporarily for legacy records and upgraded after login.
            $uses_password_hash = password_get_info($user['password'])['algo'] !== null;
            if (password_verify($user_password, $user['password']) ||
                $user_password === $user['password']) {

                if (!$uses_password_hash || password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $upgraded_password = password_hash($user_password, PASSWORD_DEFAULT);
                    $password_stmt = $conn->prepare('UPDATE User SET password = ? WHERE userID = ?');
                    $password_stmt->bind_param('ss', $upgraded_password, $user['userID']);
                    $password_stmt->execute();
                    $password_stmt->close();
                }

                // Set all session variables
                $_SESSION['user_id']    = $user['userID'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email']; // Saved directly to active session
                $_SESSION['last_login_at'] = recordSuccessfulLogin($conn, $user['userID'])
                    ?? date('Y-m-d H:i:s');

                // Set 30-day "Remember Me" cookies if box was checked
                if (isset($_POST['remember_me'])) {
                    $cookie_time = time() + (86400 * 30); // 30 Days
                    setcookie('remember_user_id',    $user['userID'], $cookie_time, "/");
                    setcookie('remember_user_name',  $user['name'],   $cookie_time, "/");
                    setcookie('remember_user_role',  $user['role'],   $cookie_time, "/");
                    setcookie('remember_user_email', $user['email'],  $cookie_time, "/");
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
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="hero-section">
        <img src="assets/images/hero-banner.png" alt="CirculAPU Hero Banner">
    </div>

    <div class="login-section">
        <div class="login-container">
            <div style="text-align: center; margin-bottom: 16px;">
                <img src="assets/images/logo.png" alt="CirculAPU Logo" style="max-width: 140px; height: auto;">
            </div>
            <h1 class="login-title">Welcome to CirculAPU</h1>

            <?php if (!empty($error_message)): ?>
                <div class="error-alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
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

                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="reset_password.php" class="forgot-password">Forgot your password?</a>
                </div>

                <button type="submit" class="login-submit-btn">LOGIN</button>

                <div class="register-prompt">
                    Don't have an account? <a href="signup.php">Register Now</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
