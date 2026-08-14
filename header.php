<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read user values directly from the active session
$user_name  = $_SESSION['user_name']  ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@circulapu.edu.my';
$user_role  = $_SESSION['user_role']  ?? 'Member';

// Map database role string to human-readable UI label
$role_labels = [
    'Admin'   => 'System Administrator',
    'Manager' => 'Resource Manager',
    'Auditor' => 'Sustainability Auditor',
    'Member'  => 'Community Member'
];

$display_role = $role_labels[$user_role] ?? $user_role;

$script_dir = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
$app_root = preg_replace('#/(Admin|Auditor|Manager|Member|Members)$#i','',$script_dir);
if ($app_root === '/') $app_root = '';
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars($app_root); ?>/darktheme.css">

<header class="app-header">
  <div class="header-search-container">
    <svg class="header-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input type="text" placeholder="Search......" aria-label="Search">
  </div>

  <div class="header-right-actions">
    <button type="button" id="theme-toggle-btn" class="header-action-icon" title="Toggle Light/Dark Theme" aria-label="Toggle Theme">
      <svg id="theme-icon-sun" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5"></circle>
        <line x1="12" y1="1" x2="12" y2="3"></line>
        <line x1="12" y1="21" x2="12" y2="23"></line>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
        <line x1="1" y1="12" x2="3" y2="12"></line>
        <line x1="21" y1="12" x2="23" y2="12"></line>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
      </svg>
      <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
      </svg>
    </button>

    <a href="<?php echo htmlspecialchars($app_root); ?>/notifications.php" class="header-action-icon" title="Notifications">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
      </svg>
    </a>

    <div class="header-divider"></div>

    <a href="<?php echo htmlspecialchars($app_root); ?>/settings.php" class="header-user-profile" title="View Account Profile">
      <div class="user-avatar-circle">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      </div>
      <div class="user-info-text">
        <div class="user-role-title">
          <span><?php echo htmlspecialchars($display_role); ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </div>
        <span class="user-email-subtitle"><?php echo htmlspecialchars($user_email); ?></span>
      </div>
    </a>

    <a href="<?php echo htmlspecialchars($app_root); ?>/settings.php" class="header-action-icon" title="Settings">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
      </svg>
    </a>
  </div>
</header>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const themeBtn = document.getElementById("theme-toggle-btn");
  const sunIcon = document.getElementById("theme-icon-sun");
  const moonIcon = document.getElementById("theme-icon-moon");

  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-mode");
    sunIcon.style.display = "block";
    moonIcon.style.display = "none";
  }

  themeBtn?.addEventListener("click", function() {
    document.body.classList.toggle("dark-mode");
    const isDark = document.body.classList.contains("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    sunIcon.style.display = isDark ? "block" : "none";
    moonIcon.style.display = isDark ? "none" : "block";
  });
});
</script>
