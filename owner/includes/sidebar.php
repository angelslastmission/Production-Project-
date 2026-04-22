<?php
$active_page = $active_page ?? 'dashboard';
?>
<aside class="owner-sidebar">
    <div class="owner-logo">
        <span class="owner-logo-paw">🐾</span>
        <span class="owner-logo-title">PetCura</span>
    </div>

    <nav class="owner-nav">
        <a href="dashboard.php" class="owner-nav-link <?= ($active_page === 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>
        <a href="pet_records.php" class="owner-nav-link <?= ($active_page === 'pet_records') ? 'active' : '' ?>">
            <i class="bi bi-journal-medical"></i>
            <span>Pet Records</span>
        </a>
        <a href="notifications.php" class="owner-nav-link <?= ($active_page === 'notifications') ? 'active' : '' ?>">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="profile.php" class="owner-nav-link <?= ($active_page === 'profile') ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>My Profile</span>
        </a>
        <a href="settings.php" class="owner-nav-link <?= ($active_page === 'settings') ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
    </nav>

    <div class="owner-sidebar-footer">
        <a href="../logout.php" class="owner-nav-link">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
