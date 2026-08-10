<?php
include_once 'simulation_header.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read user values directly from the active session
$user_name  = $_SESSION['user_name']  ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@circulapu.edu.my';
?>

<header class="app-header">
  <div class="header-search-container">
    <svg class="header-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input type="text" placeholder="Search......" aria-label="Search">
  </div>

  <div class="header-right-actions">
    <a href="notifications.php" class="header-action-icon" title="Notifications">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
      </svg>
    </a>

    <div class="header-divider"></div>

    <!-- Clickable Profile Pill to open Right Bar -->
    <div id="profile-dropdown-btn" class="header-user-profile" title="View Account Settings" style="cursor: pointer;">
      <div class="user-avatar-circle">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      </div>
      <div class="user-info-text">
        <div class="user-role-title">
          <span><?php echo htmlspecialchars($user_name); ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
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