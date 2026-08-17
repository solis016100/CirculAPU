<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role_labels = [
    'Manager' => 'Resource Manager',
    'Auditor' => 'Sustainability Auditor',
    'Member'  => 'Community Member',
];

$preview_role = $_GET['preview'] ?? '';
$is_admin_preview = ($_SESSION['user_role'] ?? '') === 'Admin' && isset($role_labels[$preview_role]);
$is_legacy_simulation = isset($_SESSION['admin_true_id']) && !empty($_SESSION['simulated_role']);
$display_role = $is_admin_preview
    ? $role_labels[$preview_role]
    : ($role_labels[$_SESSION['simulated_role'] ?? ''] ?? 'Role');

if ($is_admin_preview || $is_legacy_simulation):
?>
    <div class="simulation-banner" role="status">
        <span>Read-only preview: <strong><?php echo htmlspecialchars($display_role, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a href="/CirculAPU/Admin/role_simulation.php" class="simulation-link" data-exit-simulation>Return to Role Simulation</a>
    </div>
    <?php if ($is_admin_preview): ?>
        <script>
            document.addEventListener('click', (event) => {
                const link = event.target.closest('a');
                if (link && !link.hasAttribute('data-exit-simulation')) {
                    event.preventDefault();
                }
            }, true);
            document.addEventListener('submit', (event) => event.preventDefault(), true);
        </script>
    <?php endif; ?>
<?php endif; ?>
