<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// 1. Connect to the database
$conn = new mysqli("localhost", "root", "", "circulapu_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 2. Fetch the active user's actual data from the database
$email = $_SESSION['user_email'];
$stmt = $conn->prepare(
    "SELECT u.userID, u.name, u.email, u.role, u.status, u.dateCreated,
            (SELECT MAX(sl.timeLogged)
             FROM systemlog sl
             WHERE sl.userID = u.userID AND sl.actionType = 'Login') AS lastLogin
     FROM user u
     WHERE u.email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$user_exists = (bool) $user_data;

// Fallback in case session exists but user is missing from DB
if (!$user_data) {
    $user_data = [
        'userID' => 'N/A',
        'name' => 'Unknown',
        'email' => $email,
        'role' => $_SESSION['user_role'],
        'status' => 'Unknown',
        'dateCreated' => null,
        'lastLogin' => null
    ];
}

$profile_error = '';
$is_editing = isset($_GET['edit']) && $_GET['edit'] === '1';
$edit_name = $user_data['name'];
$edit_email = $user_data['email'];

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $is_editing = true;
    $edit_name = trim($_POST['name'] ?? '');
    $edit_email = strtolower(trim($_POST['email'] ?? ''));
    $submitted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['profile_csrf_token'], $submitted_token)) {
        $profile_error = 'Your session has expired. Please refresh the page and try again.';
    } elseif (!$user_exists) {
        $profile_error = 'The profile could not be found.';
    } elseif ($edit_name === '' || strlen($edit_name) > 100) {
        $profile_error = 'Name is required and must not exceed 100 characters.';
    } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL) || strlen($edit_email) > 100) {
        $profile_error = 'Please enter a valid email address.';
    } else {
        $duplicate_stmt = $conn->prepare("SELECT userID FROM user WHERE email = ? AND userID <> ? LIMIT 1");
        $duplicate_stmt->bind_param('ss', $edit_email, $user_data['userID']);
        $duplicate_stmt->execute();
        $email_in_use = $duplicate_stmt->get_result()->num_rows > 0;
        $duplicate_stmt->close();

        if ($email_in_use) {
            $profile_error = 'That email address is already used by another account.';
        } else {
            $update_stmt = $conn->prepare("UPDATE user SET name = ?, email = ? WHERE userID = ?");
            $update_stmt->bind_param('sss', $edit_name, $edit_email, $user_data['userID']);
            $update_stmt->execute();
            $update_stmt->close();

            $_SESSION['user_name'] = $edit_name;
            $_SESSION['user_email'] = $edit_email;
            $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));

            if (isset($_COOKIE['remember_user_name'])) {
                setcookie('remember_user_name', $edit_name, time() + (86400 * 30), '/');
            }
            if (isset($_COOKIE['remember_user_email'])) {
                setcookie('remember_user_email', $edit_email, time() + (86400 * 30), '/');
            }

            $conn->close();
            header('Location: profile_setting.php?updated=1');
            exit();
        }
    }
}

$conn->close();

$last_login_at = $user_data['lastLogin'] ?: ($_SESSION['last_login_at'] ?? null);
$last_login_display = 'Not recorded';
if ($last_login_at && ($last_login_timestamp = strtotime($last_login_at)) !== false) {
    $last_login_display = date('Y-m-d', $last_login_timestamp) === date('Y-m-d')
        ? 'Today, ' . date('h:i A', $last_login_timestamp)
        : date('d M Y, h:i A', $last_login_timestamp);
}

// Dynamically set the theme class based on the logged-in user's role
$theme_class = 'role-' . strtolower($user_data['role']);
$user_role_display = htmlspecialchars($user_data['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - CirculAPU</title>
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
                    <?php echo $user_role_display; ?> Dashboard > Settings > Profile
                </div>

                <div class="page-header">
                    <div class="header-titles">
                        <h1>Profile</h1>
                        <p class="subtitle">View and manage your personal information</p>
                    </div>
                    <div class="profile-page-actions">
                        <?php if ($is_editing): ?>
                            <a href="profile_setting.php" class="action-btn outline-btn">Cancel</a>
                            <button type="submit" name="save_profile" value="1" form="profile-edit-form" class="action-btn outline-btn">
                                Save Changes
                            </button>
                        <?php else: ?>
                            <a href="profile_setting.php?edit=1" class="action-btn outline-btn profile-edit-button">
                                <img src="/CirculAPU/assets/icons/edit.png" alt="" width="16" height="16"> Edit Profile
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                    <div class="alert alert-success">Profile updated successfully.</div>
                <?php endif; ?>
                <?php if ($profile_error !== ''): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($profile_error); ?></div>
                <?php endif; ?>

                <div class="profile-grid">
                    <!-- Profile Information Card -->
                    <form id="profile-edit-form" method="POST" class="content-card">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
                        <input type="hidden" name="save_profile" value="1">
                        <div class="card-header">
                            <img src="/CirculAPU/assets/icons/users.svg" alt="User" width="20" height="20">
                            <h2>Profile Information</h2>
                        </div>
                        <hr class="card-divider">
                        <div class="profile-details-wrapper">
                            <div class="profile-info-list">
                                <div class="info-row">
                                    <span class="info-label">User ID</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user_data['userID']); ?></span>
                                </div>
                                <div class="info-row">
                                    <?php if ($is_editing): ?>
                                        <label class="info-label" for="profile-name">Name</label>
                                    <?php else: ?>
                                        <span class="info-label">Name</span>
                                    <?php endif; ?>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">
                                        <?php if ($is_editing): ?>
                                            <input id="profile-name" class="profile-edit-input" type="text" name="name" maxlength="100" value="<?php echo htmlspecialchars($edit_name); ?>" required>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($user_data['name']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <?php if ($is_editing): ?>
                                        <label class="info-label" for="profile-email">Email</label>
                                    <?php else: ?>
                                        <span class="info-label">Email</span>
                                    <?php endif; ?>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">
                                        <?php if ($is_editing): ?>
                                            <input id="profile-email" class="profile-edit-input" type="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($edit_email); ?>" required>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($user_data['email']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Role</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><span class="badge neutral-badge outline"><?php echo $user_role_display; ?></span></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">
                                        <?php if(strtolower($user_data['status']) === 'active'): ?>
                                            <span class="badge success-badge outline"><span class="dot"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge outline" style="color:#991b1b; border-color:#fca5a5; background-color:#fee2e2;"><span class="dot" style="background-color:#991b1b;"></span> <?php echo htmlspecialchars($user_data['status']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Date Created</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><?php echo $user_data['dateCreated'] ? date("d M Y", strtotime($user_data['dateCreated'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Account Summary Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <img src="/CirculAPU/assets/icons/users.svg" alt="Account" width="20" height="20">
                            <h2>Account Summary</h2>
                        </div>
                        <hr class="card-divider">
                        <div class="profile-info-list padded-list">
                            <div class="info-row">
                                <span class="info-label wide-label">Last Login</span>
                                <span class="info-separator">:</span>
                                <span class="info-value"><?php echo htmlspecialchars($last_login_display); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label wide-label">Last Password Change</span>
                                <span class="info-separator">:</span>
                                <span class="info-value">N/A</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label wide-label">Account Type</span>
                                <span class="info-separator">:</span>
                                <span class="info-value"><?php echo $user_role_display; ?> Account</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Account Activity Card -->
                <div class="content-card full-width-card">
                    <div class="card-header space-between">
                        <div class="header-left">
                            <img src="/CirculAPU/assets/icons/users.svg" alt="Activity" width="20" height="20">
                            <h2>Recent Account Activity</h2>
                        </div>
                        <a href="#" class="view-all-link">View All Activities &rarr;</a>
                    </div>
                    <hr class="card-divider">
                    <div class="activity-list">
                        <div class="activity-row">
                            <span class="activity-date"><?php echo $last_login_at ? date("d M Y, h:i A", strtotime($last_login_at)) : 'Not recorded'; ?></span>
                            <span class="activity-desc">Logged in to system</span>
                            <span class="badge neutral-badge outline">Success</span>
                        </div>
                        <!-- Additional mock activity logs can be fetched here later -->
                    </div>
                </div>

                <!-- Security Footer -->
                <div class="security-footer">
                    <div class="security-left">
                        <img src="/CirculAPU/assets/icons/shield.svg" alt="Secure" width="20" height="20">
                        <span>Your information is secure and only visible to authorized personnel.</span>
                    </div>
                    <div class="security-right">
                        <span>Last profile fetch: <?php echo date("d M Y, h:i A"); ?></span>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
