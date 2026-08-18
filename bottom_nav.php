<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bottom_nav_role = $role ?? ($_SESSION['user_role'] ?? 'Member');
$bottom_nav_page = basename($_SERVER['PHP_SELF']);

$bottom_nav_items = [
    'Admin' => [
        ['Dashboard', '/CirculAPU/Admin/admin_dashboard.php', 'dashboard', ['admin_dashboard.php', 'system_logs.php', 'notifications.php']],
        ['Users', '/CirculAPU/Admin/user_management.php', 'users', ['user_management.php', 'admin_edit_user.php', 'create_dummy_account.php']],
        ['Reports', '/CirculAPU/Admin/reports_queue.php', 'report', ['reports_queue.php', 'admin_report_details.php']],
        ['Analytics', '/CirculAPU/Admin/analytics.php', 'analytics', ['analytics.php']],
        ['Simulation', '/CirculAPU/Admin/role_simulation.php', 'simulation', ['role_simulation.php']],
    ],
    'Auditor' => [
        ['Dashboard', '/CirculAPU/Auditor/auditor_dashboard.php', 'dashboard', ['auditor_dashboard.php', 'auditor_notification.php', 'auditor_recent_activities.php', 'notifications.php']],
        ['Verification', '/CirculAPU/Auditor/auditor_verification_management.php', 'verification', ['auditor_verification_management.php', 'auditor_verification_view.php', 'auditor_verification_edit.php']],
        ['Missions', '/CirculAPU/Auditor/auditor_missions_management.php', 'missions', ['auditor_missions_management.php', 'auditor_mission_create.php', 'auditor_mission_view.php', 'auditor_mission_edit.php']],
        ['Rewards', '/CirculAPU/Auditor/auditor_rewards_management.php', 'rewards', ['auditor_rewards_management.php', 'auditor_reward_create.php', 'auditor_reward_view.php', 'auditor_reward_edit.php']],
        ['Badges', '/CirculAPU/Auditor/auditor_achievement_badges_management.php', 'badge', ['auditor_achievement_badges_management.php', 'auditor_achievement_badges_create.php', 'auditor_achievement_badges_view.php', 'auditor_achievement_badges_edit.php']],
    ],
    'Manager' => [
        ['Dashboard', '/CirculAPU/Manager/manager_dashboard.php', 'dashboard', ['manager_dashboard.php', 'manager_notifications.php', 'manager_recent_activities.php', 'notifications.php']],
        ['Assets', '/CirculAPU/Manager/manager_assets.php', 'assets', ['manager_assets.php', 'manager_asset_view.php', 'manager_asset_edit.php', 'review_asset_submission.php']],
        ['Categories', '/CirculAPU/Manager/manager_categories.php', 'categories', ['manager_categories.php', 'manager_category_create.php', 'manager_category_view.php', 'manager_category_edit.php']],
        ['Transactions', '/CirculAPU/Manager/manager_transactions.php', 'transactions', ['manager_transactions.php', 'manager_transaction_view.php', 'manager_borrowed_assets.php', 'manager_borrowed_asset_view.php', 'inspect_asset_return.php']],
        ['Reports', '/CirculAPU/Manager/manager_reports.php', 'report', ['manager_reports.php']],
    ],
    'Member' => [
        ['Dashboard', '/CirculAPU/Members/member_dashboard.php', 'dashboard', ['member_dashboard.php', 'member_notifications.php', 'notifications.php']],
        ['Marketplace', '/CirculAPU/Members/member_marketplace.php', 'marketplace', ['member_marketplace.php', 'member_asset_details.php', 'member_list_asset.php', 'member_edit_asset.php']],
        ['Requests', '/CirculAPU/Members/member_requests.php', 'requests', ['member_requests.php', 'member_borrow_request.php']],
        ['Missions', '/CirculAPU/Members/member_missions.php', 'missions', ['member_missions.php']],
        ['Rewards', '/CirculAPU/Members/member_rewards.php', 'rewards', ['member_rewards.php']],
        ['Badges', '/CirculAPU/Members/member_badges.php', 'badge', ['member_badges.php']],
    ],
];

$bottom_nav_icons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
    'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
    'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
    'analytics' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
    'simulation' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.18V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.17 14H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 10 3.17V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.83 10H21a2 2 0 1 1 0 4h-.09A1.65 1.65 0 0 0 19.4 15z"></path>',
    'verification' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
    'missions' => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
    'rewards' => '<polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>',
    'badge' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.74z"></path><polyline points="9 12 11 14 15 10"></polyline>',
    'assets' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
    'categories' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
    'transactions' => '<polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path>',
    'marketplace' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>',
    'requests' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
];

$bottom_nav_links = $bottom_nav_items[$bottom_nav_role] ?? $bottom_nav_items['Member'];
?>
<nav class="role-bottom-nav" aria-label="<?php echo htmlspecialchars($bottom_nav_role, ENT_QUOTES, 'UTF-8'); ?> mobile navigation" style="--bottom-nav-count: <?php echo count($bottom_nav_links); ?>">
<?php foreach ($bottom_nav_links as [$bottom_nav_label, $bottom_nav_href, $bottom_nav_icon, $bottom_nav_active_pages]):
    $bottom_nav_is_active = in_array($bottom_nav_page, $bottom_nav_active_pages, true);
?>
    <a href="<?php echo htmlspecialchars($bottom_nav_href, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $bottom_nav_is_active ? 'active' : ''; ?>"<?php echo $bottom_nav_is_active ? ' aria-current="page"' : ''; ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $bottom_nav_icons[$bottom_nav_icon]; ?></svg>
        <span><?php echo htmlspecialchars($bottom_nav_label, ENT_QUOTES, 'UTF-8'); ?></span>
    </a>
<?php endforeach; ?>
</nav>
