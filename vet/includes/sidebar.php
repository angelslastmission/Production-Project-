<aside class="vet-sidebar">
    <div class="vet-brand">
        <h4 class="vet-brand-title">PetCare HMS</h4>
        <div class="vet-brand-logo"></div>
        <p class="vet-brand-sub">Veterinary Management</p>
    </div>

    <nav class="vet-nav">
        <a href="dashboard.php" class="vet-nav-link <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>
        <a href="#" class="vet-nav-link">
            <i class="bi bi-people-fill"></i>
            <span>Patients</span>
        </a>
        <a href="#" class="vet-nav-link">
            <i class="bi bi-clipboard2-pulse"></i>
            <span>Health Records</span>
        </a>
        <a href="#" class="vet-nav-link">
            <i class="bi bi-bell"></i>
            <span>Reminders</span>
        </a>
        <a href="#" class="vet-nav-link">
            <i class="bi bi-person-badge"></i>
            <span>Owners</span>
        </a>
        <a href="#" class="vet-nav-link">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
    </nav>

    <div class="vet-sidebar-bottom">
        <a href="../logout.php" class="vet-logout-link">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>