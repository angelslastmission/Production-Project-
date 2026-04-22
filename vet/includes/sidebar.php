<aside class="vet-sidebar">
    <div class="vet-brand">
        <div class="vet-brand-logo">
            <span class="vet-brand-paw">🐾</span>
            <span class="vet-brand-title">PetCura</span>
        </div>
    </div>

    <nav class="vet-nav">
        <a href="dashboard.php" class="vet-nav-link <?= ($active_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            <span>Dashboard</span>
        </a>

        <p class="vet-nav-label">PATIENTS</p>

        <a href="patients.php" class="vet-nav-link <?= ($active_page ?? '') === 'patients' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>My patients</span>
        </a>
        <a href="register_pet.php" class="vet-nav-link <?= ($active_page ?? '') === 'register_pet' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Register pet</span>
        </a>

        <p class="vet-nav-label">HEALTH RECORDS</p>

        <a href="vaccinations.php" class="vet-nav-link <?= ($active_page ?? '') === 'vaccinations' ? 'active' : '' ?>">
            <i class="bi bi-shield-plus"></i>
            <span>Vaccinations</span>
        </a>

        <a href="treatments.php" class="vet-nav-link <?= ($active_page ?? '') === 'treatments' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-pulse"></i>
            <span>Treatments</span>
        </a>

        <a href="reminders.php" class="vet-nav-link <?= ($active_page ?? '') === 'reminders' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i>
            <span>Reminders</span>
        </a>

        <p class="vet-nav-label">PEOPLE</p>

        <a href="owners.php" class="vet-nav-link <?= ($active_page ?? '') === 'owners' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i>
            <span>My owners</span>
        </a>

        <a href="settings.php" class="vet-nav-link <?= ($active_page ?? '') === 'settings' ? 'active' : '' ?>">
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