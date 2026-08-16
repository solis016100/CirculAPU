<?php $page = basename($_SERVER['PHP_SELF']); ?>

<nav class="mobile-bottom-nav">

    <a href="auditor_dashboard.php" class="<?= $page == 'auditor_dashboard.php' ? 'active' : '' ?>">
        <img src="../assets/icons/home.png" alt="Home">
        <span>Home</span>
    </a>

    <a href="auditor_verification_management.php" class="<?= $page == 'auditor_verification_management.php' ? 'active' : '' ?>">
        <img src="../assets/icons/verification.png" alt="Verification">
        <span>Verification</span>
    </a>

    <a href="auditor_missions_management.php" class="<?= $page == 'auditor_missions_management.php' ? 'active' : '' ?>">
        <img src="../assets/icons/mission.png" alt="Missions">
        <span>Missions</span>
    </a>

    <a href="auditor_rewards_management.php" class="<?= $page == 'auditor_rewards_management.php' ? 'active' : '' ?>">
        <img src="../assets/icons/reward.png" alt="Rewards">
        <span>Rewards</span>
    </a>

    <a href="auditor_achievement_badges_management.php" class="<?= $page == 'auditor_achievement_badges_management.php' ? 'active' : '' ?>">
        <img src="../assets/icons/badge.png" alt="Achievement Badges">
        <span>Badge</span>
    </a>

</nav>