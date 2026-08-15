<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = $_SESSION['user_role'] ?? 'Member';
?>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo-container">
      <img src="/CirculAPU/assets/images/logo.png" alt="CirculAPU Logo" class="sidebar-logo">
      <span class="sidebar-logo-text">CirculAPU</span>
    </div>
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Navigation">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>
  </div>

  <nav class="sidebar-nav">
    <?php switch ($user_role): 
      case 'Admin': ?>
        <a href="/CirculAPU/Admin/admin_dashboard.php" class="nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          <span class="nav-text">Dashboard</span>
        </a>
        <a href="/CirculAPU/Admin/user_management.php" class="nav-item <?php echo ($current_page == 'user_management.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          <span class="nav-text">User Management</span>
        </a>
        <a href="/CirculAPU/Admin/reports_queue.php" class="nav-item <?php echo ($current_page == 'reports_queue.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span class="nav-text">Reports Queue</span>
        </a>
        <a href="/CirculAPU/Admin/analytics.php" class="nav-item <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
          <span class="nav-text">Analytics</span>
        </a>
        <a href="/CirculAPU/Admin/role_simulation.php" class="nav-item <?php echo ($current_page == 'role_simulation.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
          <span class="nav-text">Role View Simulation</span>
        </a>
        <?php break; ?>

      <?php case 'Auditor': ?>
        <a href="/CirculAPU/Auditor/auditor_dashboard.php" class="nav-item <?php echo ($current_page == 'auditor_dashboard.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          <span class="nav-text">Dashboard</span>
        </a>
        <a href="/CirculAPU/Auditor/auditor_verification_management.php" class="nav-item <?php echo ($current_page == 'auditor_verification_management.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          <span class="nav-text">Verification</span>
        </a>
        <a href="/CirculAPU/Auditor/auditor_missions_management.php" class="nav-item <?php echo ($current_page == 'auditor_missions_management.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
          <span class="nav-text">Missions</span>
        </a>
        <a href="/CirculAPU/Auditor/auditor_rewards_management.php" class="nav-item <?php echo ($current_page == 'auditor_rewards_management.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
          <span class="nav-text">Rewards</span>
        </a>
        <a href="/CirculAPU/Auditor/auditor_achievement_badges_management.php" class="nav-item <?php echo ($current_page == 'auditor_achievement_badges_management.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.74z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
          <span class="nav-text">Achievement Badges</span>
        </a>
        <?php break; ?>

      <?php case 'Manager': ?>
        <a href="/CirculAPU/Manager/manager_dashboard.php" class="nav-item <?php echo ($current_page == 'manager_dashboard.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          <span class="nav-text">Dashboard</span>
        </a>
        <a href="/CirculAPU/Manager/manager_assets.php" class="nav-item <?php echo ($current_page == 'manager_assets.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
          <span class="nav-text">Asset Management</span>
        </a>
        <a href="/CirculAPU/Manager/manager_categories.php" class="nav-item <?php echo ($current_page == 'manager_categories.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
          <span class="nav-text">Category Management</span>
        </a>
        <a href="/CirculAPU/Manager/manager_transactions.php" class="nav-item <?php echo ($current_page == 'manager_transactions.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
          <span class="nav-text">Transaction Management</span>
        </a>
        <a href="/CirculAPU/Manager/manager_reports.php" class="nav-item <?php echo ($current_page == 'manager_reports.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span class="nav-text">Reports</span>
        </a>
        <?php break; ?>

      <?php default: 'Member' ?>
        <a href="/CirculAPU/Member/member_dashboard.php" class="nav-item <?php echo ($current_page == 'member_dashboard.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          <span class="nav-text">Dashboard</span>
        </a>
        <a href="/CirculAPU/Member/member_marketplace.php" class="nav-item <?php echo ($current_page == 'member_marketplace.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
          <span class="nav-text">Assets Marketplace</span>
        </a>
        <a href="/CirculAPU/Member/member_requests.php" class="nav-item <?php echo ($current_page == 'member_requests.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span class="nav-text">My Request</span>
        </a>
        <a href="/CirculAPU/Member/member_missions.php" class="nav-item <?php echo ($current_page == 'member_missions.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
          <span class="nav-text">Missions</span>
        </a>
        <a href="/CirculAPU/Member/member_rewards.php" class="nav-item <?php echo ($current_page == 'member_rewards.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
          <span class="nav-text">Rewards & Redemption</span>
        </a>
        <a href="/CirculAPU/Member/member_badges.php" class="nav-item <?php echo ($current_page == 'member_badges.php') ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.74z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
          <span class="nav-text">Achievement Badges</span>
        </a>
        
        <?php break; ?>
    <?php endswitch; ?>
  </nav>

  <div class="sidebar-footer">
    <button type="button" class="logout-btn" id="openSidebarLogoutConfirm">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      <span class="logout-text">Logout</span>
    </button>
  </div>
</aside>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const sidebar   = document.getElementById('sidebar');
    const container = document.querySelector('.dashboard-container');
    const toggleBtn = document.getElementById('sidebarToggleBtn');

    if (!sidebar || !toggleBtn) return;

    // Restore state from LocalStorage
    const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
    if (isCollapsed) {
      sidebar.classList.add('collapsed');
      if (container) container.classList.add('sidebar-collapsed');
    }

    // Toggle click listener
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      if (container) container.classList.toggle('sidebar-collapsed');

      const collapsedState = sidebar.classList.contains('collapsed');
      localStorage.setItem('sidebar_collapsed', collapsedState);
    });
  });
</script>