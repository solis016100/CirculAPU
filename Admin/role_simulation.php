<?php
session_start();

 
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role View Simulation - CirculAPU</title>
     
    <link rel="stylesheet" href="../style.css">
     
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="role-admin">
    <div class="dashboard-container">
        
         
        <?php include '../sidebar.php'; ?>

        <div class="main-content">
            
             
            <?php include '../header.php'; ?>

            <main class="page-body">
                <?php if (($_GET['error'] ?? '') === 'no_preview_user'): ?>
                    <div class="alert alert-error" role="alert">
                        No active <?php echo htmlspecialchars($_GET['role'] ?? 'selected', ENT_QUOTES, 'UTF-8'); ?> account is available for simulation.
                    </div>
                <?php endif; ?>
                
                 
                <div class="admin-page-header">
                    <div>
                        <p class="header-breadcrumb">Role View Simulation</p>
                        <h1 class="header-title">Role Simulation</h1>
                    </div>
                </div>

                 
                <div class="simulation-grid">
                    
                     
                    <div class="simulation-row-two">
                        
                         
                        <div class="simulation-card">
                            <div class="simulation-card-header">
                                <h2>Resource Manager</h2>
                                <a href="launch_simulation.php?role=Manager" class="btn-launch-sim">Launch View</a>
                            </div>
                            <div class="iframe-container">
                                <iframe src="../Manager/manager_dashboard.php?preview=Manager" class="preview-iframe" title="Resource Manager Live View" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>
                                <a href="launch_simulation.php?role=Manager" class="iframe-overlay" aria-label="Launch Resource Manager preview"></a>
                            </div>
                        </div>

                         
                        <div class="simulation-card">
                            <div class="simulation-card-header">
                                <h2>Sustainability Auditor</h2>
                                <a href="launch_simulation.php?role=Auditor" class="btn-launch-sim">Launch View</a>
                            </div>
                            <div class="iframe-container">
                                <iframe src="../Auditor/auditor_dashboard.php?preview=Auditor" class="preview-iframe" title="Sustainability Auditor Live View" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>
                                <a href="launch_simulation.php?role=Auditor" class="iframe-overlay" aria-label="Launch Sustainability Auditor preview"></a>
                            </div>
                        </div>

                    </div>

                     
                    <div class="simulation-row-one">
                        
                         
                        <div class="simulation-card">
                            <div class="simulation-card-header">
                                <h2>Community Member</h2>
                                <a href="launch_simulation.php?role=Member" class="btn-launch-sim">Launch View</a>
                            </div>
                            <div class="iframe-container iframe-container-large">
                                <iframe src="../Members/member_dashboard.php?preview=Member" class="preview-iframe" title="Community Member Live View" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>
                                <a href="launch_simulation.php?role=Member" class="iframe-overlay" aria-label="Launch Community Member preview"></a>
                            </div>
                        </div>

                    </div>

                </div>

            </main>
        </div>
    </div>
</body>
</html>
