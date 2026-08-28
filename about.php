<?php
session_start();

 
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: /CirculAPU/login.php");
    exit();
}

 
$theme_class = 'role-' . strtolower($_SESSION['user_role']);
$user_role_display = htmlspecialchars($_SESSION['user_role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About CirculAPU</title>
    <link rel="stylesheet" href="/CirculAPU/style.css">
    <link rel="stylesheet" href="/CirculAPU/about_mobile.css?v=2">
</head>
<body class="<?php echo htmlspecialchars($theme_class); ?> about-page">
    <div class="dashboard-container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php include 'header.php'; ?>

            <main class="page-body">
                <div class="breadcrumb">
                    Dashboard > Settings > About
                </div>

                <div class="page-header">
                    <div class="header-titles">
                        <h1>About CirculAPU</h1>
                        <p class="subtitle">Learn more about the CirculAPU sustainability platform.</p>
                    </div>
                </div>

                <div class="content-card about-card">
                    <div class="about-top">
                        <div class="about-logo">
                            <img src="/CirculAPU/assets/images/logo.png" alt="CirculAPU Logo">
                        </div>
                        
                        <div class="about-details">
                            <div class="about-details-row">
                                <span class="about-details-label">Application Name</span>
                                <span class="about-details-separator">:</span>
                                <span class="about-details-value">CirculAPU</span>
                            </div>
                            <div class="about-details-row">
                                <span class="about-details-label">Version</span>
                                <span class="about-details-separator">:</span>
                                <span class="about-details-value">v1.0.0</span>
                            </div>
                            <div class="about-details-row">
                                <span class="about-details-label">Developed By</span>
                                <span class="about-details-separator">:</span>
                                <span class="about-details-value">AAPP012-4-2-RWDD Group 10</span>
                            </div>
                            <div class="about-details-row">
                                <span class="about-details-label">Developed For</span>
                                <span class="about-details-separator">:</span>
                                <span class="about-details-value">Asia Pacific University (APU)</span>
                            </div>
                            <div class="about-details-row">
                                <span class="about-details-label">Last Updated</span>
                                <span class="about-details-separator">:</span>
                                <span class="about-details-value">11 August 2026</span>
                            </div>
                        </div>
                    </div>

                    <hr class="card-divider">

                    <div class="about-description">
                        <p>CirculAPU is a sustainability web application designed to help the APU community reuse, borrow and lending items that are no longer needed. The main objective of this project is to reduce waste and encourage sustainable practices through a circular economy approach. Instead of throwing away useful items, members of the APU community can list them on the platform for others to claim and reuse. Users can also participate in sustainability missions, earn EcoPoints, and unlock achievement badges.</p>
                    </div>
                </div>

                <div class="about-footer">
                    &copy; 2026 CirculAPU. All Rights Reserved.
                </div>

            </main>
        </div>
    </div>
</body>
</html>
