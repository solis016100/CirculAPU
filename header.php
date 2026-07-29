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
?>

<style>
  .app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.5rem;
    background-color: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 1.5rem;
  }

  /* Left Search Container */
  .header-search-container {
    position: relative;
    width: 320px;
  }

  .header-search-container input {
    width: 100%;
    padding: 0.55rem 0.85rem 0.55rem 2.5rem;
    border: 1px solid #1e293b;
    border-radius: 4px;
    font-size: 0.9rem;
    outline: none;
    color: #0f172a;
    background-color: #ffffff;
  }

  .header-search-container input::placeholder {
    color: #475569;
  }

  .header-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #0f172a;
    pointer-events: none;
  }

  /* Right Side Utilities */
  .header-right-actions {
    display: flex;
    align-items: center;
    gap: 1.25rem;
  }

  .header-action-icon {
    background: transparent;
    border: none;
    cursor: pointer;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem;
    border-radius: 50%;
    transition: background 0.2s;
    text-decoration: none;
  }

  .header-action-icon:hover {
    background-color: #f1f5f9;
  }

  .header-action-icon svg {
    width: 22px;
    height: 22px;
  }

  .header-divider {
    width: 1px;
    height: 32px;
    background-color: #cbd5e1;
  }

  /* User Profile Pill */
  .header-user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
  }

  .user-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1.5px solid #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #ffffff;
    flex-shrink: 0;
  }

  .user-avatar-circle svg {
    width: 22px;
    height: 22px;
    color: #0f172a;
  }

  .user-info-text {
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .user-role-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #0f172a;
    line-height: 1.2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
  }

  .user-role-title svg {
    width: 16px;
    height: 16px;
    stroke-width: 2.5;
  }

  .user-email-subtitle {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 2px;
  }
</style>

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

    <a href="settings.php" class="header-user-profile" title="View Account Profile">
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

    <a href="settings.php" class="header-action-icon" title="Settings">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
      </svg>
    </a>
  </div>
</header>