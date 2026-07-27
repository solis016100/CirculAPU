<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access Control: Kick user to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Member';
?>

<!-- Global Dashboard Navbar -->
<header class="dashboard-header">
    <div class="welcome-container">
        <h2>Welcome, <span class="user-name"><?php echo htmlspecialchars($userName); ?></span></h2>
        <span class="role-badge"><?php echo htmlspecialchars($userRole); ?></span>
    </div>

    <div class="header-actions">
        <a href="logout.php" class="logout-btn">
            <svg class="logout-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            Logout
        </a>
    </div>
</header>

<style>
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #ffffff;
        padding: 16px 28px;
        border-bottom: 1px solid #e0e0e0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
        font-family: Arial, Helvetica, sans-serif;
    }

    .welcome-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .welcome-container h2 {
        font-size: 1.25rem;
        font-weight: 500;
        color: #222;
        margin: 0;
    }

    .user-name {
        font-weight: bold;
        color: #111;
    }

    .role-badge {
        background-color: #e8f5e9;
        color: #2e7d32;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 12px;
        border: 1px solid #c8e6c9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .logout-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background-color: #ffffff;
        color: #d32f2f;
        border: 1px solid #d32f2f;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .logout-btn:hover {
        background-color: #d32f2f;
        color: #ffffff;
    }

    .logout-btn:hover .logout-icon {
        stroke: #ffffff;
    }
</style>