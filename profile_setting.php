<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit();
}

// Dynamically set the theme class based on the logged-in user's role
$theme_class = 'role-' . strtolower($_SESSION['user_role'] ?? 'member');
$user_role_display = $_SESSION['user_role'] ?? 'Member';
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

            <main class="page-body">
                <div class="breadcrumb">
                    <?php echo htmlspecialchars($user_role_display); ?> Dashboard > Settings > Profile
                </div>

                <div class="page-header">
                    <div class="header-titles">
                        <h1>Profile</h1>
                        <p class="subtitle">View and manage your personal information</p>
                    </div>
                    <button class="action-btn outline-btn">
                        <img src="assets/icons/edit.png" alt="Edit" width="16" height="16"> Edit Profile
                    </button>
                </div>

                <div class="profile-grid">
                    <!-- Profile Information Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <img src="assets/icons/users.svg" alt="User" width="20" height="20">
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
                                    <img src="assets/icons/upload.svg" alt="Upload" width="16" height="16"> Change Photo
                                </button>
                                <span class="file-limits">JPG, PNG (Max, 2MB)</span>
                            </div>
                            <div class="profile-info-list">
                                <div class="info-row">
                                    <span class="info-label">User ID</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">U001</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">Lee Miao Miao</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">LeeMiao@gmail.com</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Role</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><span class="badge neutral-badge outline"><?php echo htmlspecialchars($user_role_display); ?></span></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value"><span class="badge success-badge outline"><span class="dot"></span> Active</span></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Date Created</span>
                                    <span class="info-separator">:</span>
                                    <span class="info-value">22 Jan 2026</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Summary Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <img src="assets/icons/users.svg" alt="Account" width="20" height="20">
                            <h2>Account Summary</h2>
                        </div>
                        <hr class="card-divider">
                        <div class="profile-info-list padded-list">
                            <div class="info-row">
                                <span class="info-label wide-label">Last Login</span>
                                <span class="info-separator">:</span>
                                <span class="info-value">20 May 2026, 09:15 AM</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label wide-label">Last Password Change</span>
                                <span class="info-separator">:</span>
                                <span class="info-value">19 May 2026, 04:32 PM</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label wide-label">Account Type</span>
                                <span class="info-separator">:</span>
                                <span class="info-value">Staff Account</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Account Activity Card -->
                <div class="content-card full-width-card">
                    <div class="card-header space-between">
                        <div class="header-left">
                            <img src="assets/icons/users.svg" alt="Activity" width="20" height="20">
                            <h2>Recent Account Activity</h2>
                        </div>
                        <a href="#" class="view-all-link">View All Activities &rarr;</a>
                    </div>
                    <hr class="card-divider">
                    <div class="activity-list">
                        <div class="activity-row">
                            <span class="activity-date">20 May 2026, 11:33 AM</span>
                            <span class="activity-desc">Resolved Report</span>
                            <span class="badge neutral-badge outline">Success</span>
                        </div>
                        <div class="activity-row">
                            <span class="activity-date">20 May 2026, 09:15 AM</span>
                            <span class="activity-desc">Login</span>
                            <span class="badge neutral-badge outline">Success</span>
                        </div>
                        <div class="activity-row">
                            <span class="activity-date">19 May 2026, 13:45 AM</span>
                            <span class="activity-desc">Resolved Report</span>
                            <span class="badge neutral-badge outline">Success</span>
                        </div>
                        <div class="activity-row">
                            <span class="activity-date">19 May 2026, 10:56 AM</span>
                            <span class="activity-desc">Update Profile</span>
                            <span class="badge neutral-badge outline">Success</span>
                        </div>
                    </div>
                </div>

                <!-- Security Footer -->
                <div class="security-footer">
                    <div class="security-left">
                        <img src="assets/icons/shield.svg" alt="Secure" width="20" height="20">
                        <span>Your information is secure and only visible to authorized personnel.</span>
                    </div>
                    <div class="security-right">
                        <span>Last updated: 20 May 2026, 09:15AM</span>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>