<!-- admin/includes/sidebar.php -->
<div class="admin-sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <span>🐾</span>
        <span>PetCura</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <a href="dashboard.php"
           class="sidebar-link <?= $active_page === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i>
            <span>Dashboard</span>
        </a>

        <p class="sidebar-section-label">MANAGE</p>

        <a href="vet_registrations.php"
           class="sidebar-link <?= $active_page === 'vet_registrations' ? 'active' : '' ?>">
            <i class="bi bi-person-check-fill"></i>
            <span>Vet registrations</span>
        </a>

        <a href="manage_vets.php"
           class="sidebar-link <?= $active_page === 'manage_vets' ? 'active' : '' ?>">
            <i class="bi bi-hospital-fill"></i>
            <span>Manage vets</span>
        </a>

        <a href="manage_owners.php"
           class="sidebar-link <?= $active_page === 'manage_owners' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Manage owners</span>
        </a>

        <p class="sidebar-section-label">SYSTEM</p>

        <a href="reminder_logs.php"
           class="sidebar-link <?= $active_page === 'reminder_logs' ? 'active' : '' ?>">
            <i class="bi bi-bell-fill"></i>
            <span>Reminder logs</span>
        </a>

        <a href="settings.php"
           class="sidebar-link <?= $active_page === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>

    </nav>

    <!-- Logout -->
    <div class="sidebar-bottom">
        <a href="../logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>

</div>