<div class="admin-sidebar">
    <div class="sidebar-logo">
        <span>🐾</span>
        <span>PetCura</span>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i>
            <span>Dashboard</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="../logout.php" class="sidebar-logout">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</div>