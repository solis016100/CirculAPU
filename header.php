<?php
include_once 'simulation_header.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read user values directly from the active session
$user_name  = $_SESSION['user_name']  ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@circulapu.edu.my';

// Only Resource Manager uses the Manager-specific notification page.
// All other roles keep the existing shared notifications page.
$notification_url = ($_SESSION['user_role'] ?? '') === 'Manager'
    ? '/CirculAPU/Manager/manager_notifications.php'
    : '/CirculAPU/notifications.php';
?>

<header class="app-header">
  <div class="header-search-container">
    <img class="header-search-icon" src="/CirculAPU/assets/icons/search.png" alt="Search" width="20" height="20">
    <input type="text" placeholder="Search......" aria-label="Search">
  </div>

  <div class="header-right-actions">
    <a href="<?php echo htmlspecialchars($notification_url, ENT_QUOTES, 'UTF-8'); ?>" class="header-action-icon" title="Notifications">
      <img src="/CirculAPU/assets/icons/notification.png" alt="Notifications" width="20" height="20">
    </a>

    <div class="header-divider"></div>

    <!-- Clickable Profile Pill to open Right Bar -->
    <div id="profile-dropdown-btn" class="header-user-profile" title="View Account Settings" style="cursor: pointer;">
      <div class="user-avatar-circle">
        <img src="/CirculAPU/assets/icons/users.svg" alt="User Avatar" width="20" height="20">
      </div>
      <div class="user-info-text">
        <div class="user-role-title">
          <span><?php echo htmlspecialchars($user_name); ?></span>
          <img src="/CirculAPU/assets/icons/chevron-down.svg" alt="Dropdown" width="16" height="16">
        </div>
        <span class="user-email-subtitle"><?php echo htmlspecialchars($user_email); ?></span>
      </div>
    </div>
  </div>
</header>

<!-- Include the popup Right Bar -->
<?php include_once 'right_bar.php'; ?>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    const profileBtn = document.getElementById("profile-dropdown-btn");
    const rightBar = document.getElementById("right-sidebar");
    const closeRightBarBtn = document.getElementById("close-right-bar");

    // Open Sidebar
    profileBtn.addEventListener("click", function() {
      rightBar.classList.add("active");
    });

    // Close Sidebar via Chevron Button
    if (closeRightBarBtn) {
      closeRightBarBtn.addEventListener("click", function() {
        rightBar.classList.remove("active");
      });
    }
  });
</script>