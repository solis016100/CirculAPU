<?php
// Render banner if an active impersonation session exists
if (isset($_SESSION['admin_true_id']) && !empty($_SESSION['simulated_role'])): 
    $role_labels = [
        'Manager' => 'Resource Manager',
        'Auditor' => 'Sustainability Auditor',
        'Member'  => 'Community Member',
        'Admin'   => 'System Administrator'
    ];
    $display_role = $role_labels[$_SESSION['simulated_role']] ?? $_SESSION['simulated_role'];
?>
    <div class="simulation-banner">
        <span>Currently Viewing as <strong><?php echo htmlspecialchars($display_role); ?></strong>, click <a href="../revert_simulation.php" class="simulation-link">here</a> to go back</span>
    </div>
<?php endif; ?>