<?php
include_once 'simulation_header.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Read user values directly from the active session
$user_name  = $_SESSION['user_name']  ?? 'User';
$user_email = $_SESSION['user_email'] ?? 'user@circulapu.edu.my';

$role = $_SESSION['user_role'] ?? '';
$preview_routes = [
    'Manager' => 'manager_dashboard.php',
    'Auditor' => 'auditor_dashboard.php',
    'Member' => 'member_dashboard.php',
];
$preview_role = $_GET['preview'] ?? '';
if ($role === 'Admin'
    && isset($preview_routes[$preview_role])
    && $preview_routes[$preview_role] === basename($_SERVER['PHP_SELF'])) {
    $role = $preview_role;
}

if ($role === 'Admin') {
    $notification_url = '/CirculAPU/Admin/admin_notifications.php';
} elseif ($role == 'Manager') {
    $notification_url = '/CirculAPU/Manager/manager_notifications.php';
} elseif ($role == 'Auditor') {
    $notification_url = '/CirculAPU/Auditor/auditor_notification.php';
}  elseif ($role === 'Member') {
    $notification_url = '/CirculAPU/Members/member_notifications.php';
} else {
    $notification_url = '/CirculAPU/notifications.php';
}

$home_urls = [
    'Admin' => '/CirculAPU/Admin/admin_dashboard.php',
    'Auditor' => '/CirculAPU/Auditor/auditor_dashboard.php',
    'Manager' => '/CirculAPU/Manager/manager_dashboard.php',
    'Member' => '/CirculAPU/Members/member_dashboard.php',
];
$home_url = $home_urls[$role] ?? '/CirculAPU/dashboard.php';

// The header search is a role-aware feature finder. Keep these destinations
// explicit so a user is never offered a page outside their current role.
$role_search_items = [
    'Admin' => [
        ['label' => 'Dashboard', 'description' => 'View system activity and reports', 'url' => '/CirculAPU/Admin/admin_dashboard.php', 'keywords' => 'home overview logs reports'],
        ['label' => 'User Management', 'description' => 'Search and manage user accounts', 'url' => '/CirculAPU/Admin/user_management.php', 'keywords' => 'users accounts roles'],
        ['label' => 'New Dummy Account', 'description' => 'Create a test user account', 'url' => '/CirculAPU/Admin/create_dummy_account.php', 'keywords' => 'add create dummy user account'],
        ['label' => 'Tier Management', 'description' => 'View and manage EcoPoint tiers', 'url' => '/CirculAPU/Admin/tiers.php', 'keywords' => 'tiers levels ecopoints'],
        ['label' => 'New Tier', 'description' => 'Create a new EcoPoint tier', 'url' => '/CirculAPU/Admin/tiers.php?edit=new#tier-form', 'keywords' => 'add create tier level'],
        ['label' => 'Reports Queue', 'description' => 'Review submitted reports', 'url' => '/CirculAPU/Admin/reports_queue.php', 'keywords' => 'reports issues queue'],
        ['label' => 'Analytics', 'description' => 'Open platform analytics', 'url' => '/CirculAPU/Admin/analytics.php', 'keywords' => 'charts insights statistics'],
        ['label' => 'System Logs', 'description' => 'Audit recorded system activity', 'url' => '/CirculAPU/Admin/system_logs.php', 'keywords' => 'activity audit logs'],
        ['label' => 'Role View Simulation', 'description' => 'Preview another role dashboard', 'url' => '/CirculAPU/Admin/role_simulation.php', 'keywords' => 'simulate preview role'],
    ],
    'Auditor' => [
        ['label' => 'Dashboard', 'description' => 'View the auditor overview', 'url' => '/CirculAPU/Auditor/auditor_dashboard.php', 'keywords' => 'home overview'],
        ['label' => 'Verification Management', 'description' => 'Review verification submissions', 'url' => '/CirculAPU/Auditor/auditor_verification_management.php', 'keywords' => 'verify approve reject submissions'],
        ['label' => 'Mission Management', 'description' => 'View and manage missions', 'url' => '/CirculAPU/Auditor/auditor_missions_management.php', 'keywords' => 'missions activities'],
        ['label' => 'New Mission', 'description' => 'Create a sustainability mission', 'url' => '/CirculAPU/Auditor/auditor_mission_create.php', 'keywords' => 'add create mission'],
        ['label' => 'Reward Management', 'description' => 'View and manage rewards', 'url' => '/CirculAPU/Auditor/auditor_rewards_management.php', 'keywords' => 'rewards redemption'],
        ['label' => 'New Reward', 'description' => 'Create a new reward', 'url' => '/CirculAPU/Auditor/auditor_reward_create.php', 'keywords' => 'add create reward'],
        ['label' => 'Reward Redemption History', 'description' => 'Review reward redemptions', 'url' => '/CirculAPU/Auditor/auditor_reward_redemption_history.php', 'keywords' => 'rewards redeemed history'],
        ['label' => 'Achievement Badges', 'description' => 'View and manage badges', 'url' => '/CirculAPU/Auditor/auditor_achievement_badges_management.php', 'keywords' => 'badges achievements'],
        ['label' => 'New Achievement Badge', 'description' => 'Create a new badge', 'url' => '/CirculAPU/Auditor/auditor_achievement_badges_create.php', 'keywords' => 'add create achievement badge'],
    ],
    'Manager' => [
        ['label' => 'Dashboard', 'description' => 'View the resource overview', 'url' => '/CirculAPU/Manager/manager_dashboard.php', 'keywords' => 'home overview'],
        ['label' => 'Asset Management', 'description' => 'Review and manage asset listings', 'url' => '/CirculAPU/Manager/manager_assets.php', 'keywords' => 'assets inventory listings review'],
        ['label' => 'Category Management', 'description' => 'View and manage asset categories', 'url' => '/CirculAPU/Manager/manager_categories.php', 'keywords' => 'categories asset types'],
        ['label' => 'New Category', 'description' => 'Create a new asset category', 'url' => '/CirculAPU/Manager/manager_category_create.php', 'keywords' => 'add create category'],
        ['label' => 'Transaction Management', 'description' => 'View borrowing transactions', 'url' => '/CirculAPU/Manager/manager_transactions.php', 'keywords' => 'transactions borrowing requests'],
        ['label' => 'Manage Returns', 'description' => 'Inspect borrowed assets and returns', 'url' => '/CirculAPU/Manager/manager_borrowed_assets.php', 'keywords' => 'return borrowed asset inspection'],
        ['label' => 'Reports', 'description' => 'Open resource reports', 'url' => '/CirculAPU/Manager/manager_reports.php', 'keywords' => 'analytics report export'],
    ],
    'Member' => [
        ['label' => 'Dashboard', 'description' => 'View your community overview', 'url' => '/CirculAPU/Members/member_dashboard.php', 'keywords' => 'home overview'],
        ['label' => 'Assets Marketplace', 'description' => 'Browse available community assets', 'url' => '/CirculAPU/Members/member_marketplace.php', 'keywords' => 'marketplace assets browse borrow'],
        ['label' => 'List New Asset', 'description' => 'Submit an asset to the marketplace', 'url' => '/CirculAPU/Members/member_list_asset.php', 'keywords' => 'add create list share asset'],
        ['label' => 'My Requests', 'description' => 'Track your asset and borrowing requests', 'url' => '/CirculAPU/Members/member_requests.php', 'keywords' => 'requests submissions borrowing'],
        ['label' => 'Missions', 'description' => 'Explore sustainability missions', 'url' => '/CirculAPU/Members/member_missions.php', 'keywords' => 'mission activities join'],
        ['label' => 'My Missions', 'description' => 'View missions you have joined', 'url' => '/CirculAPU/Members/member_missions.php?tab=my', 'keywords' => 'joined mission progress'],
        ['label' => 'Rewards & Redemption', 'description' => 'Browse and redeem rewards', 'url' => '/CirculAPU/Members/member_rewards.php', 'keywords' => 'rewards redeem ecopoints'],
        ['label' => 'Achievement Badges', 'description' => 'View your tier and badges', 'url' => '/CirculAPU/Members/member_badges.php', 'keywords' => 'badges achievements tier'],
    ],
];

$search_items = $role_search_items[$role] ?? [];
$search_items[] = ['label' => 'Notifications', 'description' => 'View your notifications', 'url' => $notification_url, 'keywords' => 'alerts messages updates'];
$search_items[] = ['label' => 'Profile Settings', 'description' => 'Update your account profile', 'url' => '/CirculAPU/profile_setting.php', 'keywords' => 'account settings profile'];
$search_items[] = ['label' => 'Change Password', 'description' => 'Update your account password', 'url' => '/CirculAPU/change_password.php', 'keywords' => 'security password settings'];
$search_items[] = ['label' => 'About CirculAPU', 'description' => 'Learn about the platform', 'url' => '/CirculAPU/about.php', 'keywords' => 'help information about'];

?>

<header class="app-header">
  <a class="mobile-header-brand" href="<?php echo htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="CirculAPU dashboard">
    <img src="/CirculAPU/assets/images/logo.png" alt="">
    <span>CirculAPU</span>
  </a>
  <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="4" y1="7" x2="20" y2="7"></line>
      <line x1="4" y1="12" x2="20" y2="12"></line>
      <line x1="4" y1="17" x2="20" y2="17"></line>
    </svg>
  </button>
  <div class="header-search-container" id="roleFeatureSearch">
    <img class="header-search-icon" src="/CirculAPU/assets/icons/search.png" alt="" width="20" height="20">
    <input type="search" id="roleFeatureSearchInput" placeholder="Search <?php echo htmlspecialchars(strtolower($role), ENT_QUOTES, 'UTF-8'); ?> functions..." aria-label="Search <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?> functions" aria-autocomplete="list" aria-controls="roleFeatureSearchResults" aria-expanded="false" autocomplete="off">
    <div class="header-search-results" id="roleFeatureSearchResults" role="listbox" aria-label="Available functions" hidden></div>
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
<?php include_once __DIR__ . '/bottom_nav.php'; ?>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    const profileBtn = document.getElementById("profile-dropdown-btn");
    const rightBar = document.getElementById("right-sidebar");
    const closeRightBarBtn = document.getElementById("close-right-bar");

    const openSettings = () => rightBar?.classList.add("active");
    profileBtn?.addEventListener("click", openSettings);

    const featureSearch = document.getElementById('roleFeatureSearch');
    const featureInput = document.getElementById('roleFeatureSearchInput');
    const featureResults = document.getElementById('roleFeatureSearchResults');
    const featureItems = <?php echo json_encode($search_items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let visibleItems = [];
    let activeIndex = -1;

    const normalise = (value) => value.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    const searchableText = (item) => normalise(`${item.label} ${item.description} ${item.keywords || ''}`);
    const scoreItem = (item, query) => {
      const label = normalise(item.label);
      const text = searchableText(item);
      const words = query.split(' ').filter(Boolean);
      if (!words.every((word) => text.includes(word))) return -1;
      if (label === query) return 100;
      if (label.startsWith(query)) return 80;
      if (label.includes(query)) return 60;
      return 40 - Math.max(0, text.indexOf(words[0] || ''));
    };
    const closeFeatureSearch = () => {
      featureResults.hidden = true;
      featureInput.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
    };
    const setActiveResult = (index) => {
      const options = featureResults.querySelectorAll('.header-search-result');
      options.forEach((option, optionIndex) => option.classList.toggle('active', optionIndex === index));
      activeIndex = index;
      if (options[index]) {
        featureInput.setAttribute('aria-activedescendant', options[index].id);
        options[index].scrollIntoView({ block: 'nearest' });
      } else {
        featureInput.removeAttribute('aria-activedescendant');
      }
    };
    const renderFeatureResults = () => {
      const query = normalise(featureInput.value);
      visibleItems = featureItems
        .map((item) => ({ ...item, score: query ? scoreItem(item, query) : 0 }))
        .filter((item) => item.score >= 0)
        .sort((a, b) => b.score - a.score || a.label.localeCompare(b.label))
        .slice(0, 7);

      featureResults.replaceChildren();
      if (!visibleItems.length) {
        const empty = document.createElement('div');
        empty.className = 'header-search-empty';
        empty.textContent = `No ${<?php echo json_encode(strtolower($role)); ?>} function found`;
        featureResults.appendChild(empty);
      } else {
        visibleItems.forEach((item, index) => {
          const link = document.createElement('a');
          link.className = 'header-search-result';
          link.id = `roleFeatureResult${index}`;
          link.href = item.url;
          link.setAttribute('role', 'option');
          link.innerHTML = `<span>${item.label}</span><small>${item.description}</small>`;
          link.addEventListener('mousemove', () => setActiveResult(index));
          featureResults.appendChild(link);
        });
      }
      featureResults.hidden = false;
      featureInput.setAttribute('aria-expanded', 'true');
      setActiveResult(-1);
    };

    featureInput?.addEventListener('focus', renderFeatureResults);
    featureInput?.addEventListener('input', renderFeatureResults);
    featureInput?.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (featureResults.hidden) renderFeatureResults();
        if (visibleItems.length) setActiveResult((activeIndex + 1) % visibleItems.length);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (featureResults.hidden) renderFeatureResults();
        if (visibleItems.length) setActiveResult((activeIndex - 1 + visibleItems.length) % visibleItems.length);
      } else if (event.key === 'Enter' && visibleItems.length) {
        event.preventDefault();
        window.location.href = visibleItems[activeIndex >= 0 ? activeIndex : 0].url;
      } else if (event.key === 'Escape') {
        closeFeatureSearch();
        featureInput.blur();
      }
    });
    document.addEventListener('click', (event) => {
      if (featureSearch && !featureSearch.contains(event.target)) closeFeatureSearch();
    });

    // Close Sidebar via Chevron Button
    if (closeRightBarBtn) {
      closeRightBarBtn.addEventListener("click", function() {
        rightBar.classList.remove("active");
      });
    }
  });
</script>
