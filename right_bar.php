<div id="right-sidebar" class="right-sidebar">
  <!-- Protruding Collapse Button (Chevron) -->
  <button id="close-right-bar" class="right-sidebar-toggle" aria-label="Close Settings">
    <img src="/CirculAPU/assets/icons/chevron-right.svg" alt="Close Sidebar" width="20" height="20">
  </button>

  <!-- Settings Header -->
  <div class="right-sidebar-header">
    <span>Settings</span>
    <img src="/CirculAPU/assets/icons/setting.svg" alt="Settings Icon">
  </div>

  <!-- Navigation Links -->
  <div class="right-sidebar-menu">
    <a href="/CirculAPU/profile_setting.php" class="right-sidebar-link">
      <img src="/CirculAPU/assets/icons/users.svg" alt="Profile Icon">
      Profile
    </a>

    <a href="/CirculAPU/change_password.php" class="right-sidebar-link">
      <img src="/CirculAPU/assets/icons/password.svg" alt="Change Password Icon">
      Change Password
    </a>

    <a href="/CirculAPU/about.php" class="right-sidebar-link">
      <img src="/CirculAPU/assets/icons/about.svg" alt="About Icon">
      About
    </a>

    <button type="button" class="right-sidebar-link" id="openLogoutConfirm"
            style="width:100%;border:0;background:transparent;text-align:left;cursor:pointer">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
           style="flex:0 0 24px;margin-right:20px">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </button>
  </div>
</div>


<!-- Shared Logout Confirmation - same red theme for all roles -->
<div id="logoutConfirmDialog" class="logout-confirm-overlay" aria-hidden="true">
  <div class="logout-confirm-card" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle">
    <div class="logout-confirm-icon" aria-hidden="true">!</div>
    <h3 id="logoutConfirmTitle">Log out?</h3>
    <p>Are you sure you want to log out of CirculAPU?</p>

    <div class="logout-confirm-actions">
      <button type="button" class="logout-confirm-cancel" id="cancelLogoutConfirm">Cancel</button>
      <form action="/CirculAPU/logout.php" method="POST">
        <button type="submit" class="logout-confirm-submit">Yes, Log Out</button>
      </form>
    </div>
  </div>
</div>

<style>
.logout-confirm-overlay{
  position:fixed;inset:0;z-index:12000;display:none;align-items:center;justify-content:center;
  padding:20px;background:rgba(15,23,42,.42);
}
.logout-confirm-overlay.active{display:flex}
.logout-confirm-card{
  width:min(390px,100%);padding:26px 24px;background:#fff;border:1px solid #fecaca;
  border-radius:14px;box-shadow:0 20px 55px rgba(15,23,42,.18);text-align:center;
  font-family:Arial,Helvetica,sans-serif;
}
.logout-confirm-icon{
  width:48px;height:48px;margin:0 auto 14px;display:grid;place-items:center;border-radius:50%;
  background:#fef2f2;color:#dc2626;font-size:24px;font-weight:700;
}
.logout-confirm-card h3{margin:0 0 8px;color:#172033;font-size:20px}
.logout-confirm-card p{margin:0;color:#64748b;font-size:14px;line-height:1.5}
.logout-confirm-actions{display:flex;justify-content:center;gap:10px;margin-top:22px}
.logout-confirm-actions form{margin:0}
.logout-confirm-cancel,.logout-confirm-submit{
  min-width:110px;height:40px;padding:0 14px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer
}
.logout-confirm-cancel{border:1px solid #cbd5e1;background:#fff;color:#334155}
.logout-confirm-submit{border:1px solid #dc2626;background:#dc2626;color:#fff}
.logout-confirm-cancel:hover{background:#f8fafc}
.logout-confirm-submit:hover{background:#b91c1c;border-color:#b91c1c}
@media(max-width:600px){
  .logout-confirm-card{padding:22px 18px}
  .logout-confirm-actions{display:grid;grid-template-columns:1fr 1fr}
  .logout-confirm-cancel,.logout-confirm-submit{width:100%;min-width:0}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const dialog = document.getElementById('logoutConfirmDialog');
  const openBtn = document.getElementById('openLogoutConfirm');
  const sidebarBtn = document.getElementById('openSidebarLogoutConfirm');
  const cancelBtn = document.getElementById('cancelLogoutConfirm');
  let lastTrigger = null;

  if (!dialog || !cancelBtn) return;

  const openDialog = (event) => {
    lastTrigger = event?.currentTarget || null;
    dialog.classList.add('active');
    dialog.setAttribute('aria-hidden', 'false');
    cancelBtn.focus();
  };

  const closeDialog = () => {
    dialog.classList.remove('active');
    dialog.setAttribute('aria-hidden', 'true');
    if (lastTrigger) lastTrigger.focus();
  };

  if (openBtn) openBtn.addEventListener('click', openDialog);
  if (sidebarBtn) sidebarBtn.addEventListener('click', openDialog);
  cancelBtn.addEventListener('click', closeDialog);

  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) closeDialog();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && dialog.classList.contains('active')) closeDialog();
  });
});
</script>
