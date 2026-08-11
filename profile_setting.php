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
$stmt = $conn->prepare("SELECT userID, name, email, role, status, dateCreated FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Fallback in case session exists but user is missing from DB
if (!$user_data) {
    $user_data = [
        'userID' => 'N/A',
        'name' => 'Unknown',
        'email' => $email,
        'role' => $_SESSION['user_role'],
        'status' => 'Unknown',
        'dateCreated' => 'N/A'
    ];
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
                    <button class="action-btn outline-btn">
                        <img src="/CirculAPU/assets/icons/edit.png" alt="Edit" width="16" height="16"> Edit Profile
                    </button>
                </div>

                <div class="profile-grid">
                    <!-- Profile Information Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <img src="/CirculAPU/assets/icons/users.svg" alt="User" width="20" height="20">
                            <h2>Profile Information</h2>
                        </div>
                        <hr class="card-divider">
                        <div class="profile-details-wrapper">
                            <div class="profile-avatar-section">
                                <div class="avatar-placeholder">
                                    <div class="avatar-x-line-1"></div>
                                    <div class="avatar-x-line-2"></div>
                                </div>
                                <button class="action-btn outline-btn full-width">
                                    <img src="/CirculAPU/assets/icons/upload.svg" alt="Upload" width="16" height="16"> Change Photo
                                </button>
                                <span class="file-limits">JPG, PNG (Max, 2MB)</span>
                            </div>
                            <div class="profile-info-list">
                                <div class="info-row">
                                    <span class="info-label">User ID</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user_data['userID']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
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
                                    <span class="info-value"><?php echo date("d M Y", strtotime($user_data['dateCreated'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                <span class="info-value">Today, Just Now</span>
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
                            <span class="activity-date"><?php echo date("d M Y, h:i A"); ?></span>
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