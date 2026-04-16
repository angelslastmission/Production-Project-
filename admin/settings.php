<?php
// ═══════════════════════════════════════════
// FRONTEND ONLY — Admin Settings
// ═══════════════════════════════════════════
session_start();
include 'includes/auth.php';

$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings — PetCare HMS</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>

<div class="admin-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">

        <div class="settings-hero mb-4">
            <div>
                <p class="settings-kicker mb-1">ADMIN CONTROL</p>
                <h2 class="settings-hero-title mb-1">System settings</h2>
                <p class="settings-hero-sub mb-0">Manage profile, reminder channels, and account security</p>
            </div>
            <div class="settings-hero-badge">
                <i class="bi bi-shield-lock-fill me-2"></i>
                Protected area
            </div>
        </div>

        <div class="admin-topbar">
            <div>
                <h1 class="admin-page-title">Settings</h1>
                <p class="admin-page-sub">Profile and reminder preferences</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <div class="row g-3 settings-grid">

            <div class="col-lg-6">
                <div class="admin-card h-100 settings-card">
                    <div class="admin-card-header settings-header">
                        <h5 class="admin-card-title settings-title">
                            <i class="bi bi-person-vcard-fill me-2"></i>Admin profile
                        </h5>
                    </div>
                    <div class="p-3 settings-body">
                        <div class="settings-field">
                            <label class="form-label settings-label">Full name</label>
                            <input type="text" class="form-control settings-input" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">Role</label>
                            <input type="text" class="form-control settings-input" value="Administrator" readonly>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">Email</label>
                            <input type="email" class="form-control settings-input" value="admin@petcare.com" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card h-100 settings-card">
                    <div class="admin-card-header settings-header">
                        <h5 class="admin-card-title settings-title">
                            <i class="bi bi-bell-fill me-2"></i>Reminder preferences
                        </h5>
                    </div>
                    <div class="p-3 settings-body">
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable email reminders</p>
                                <small class="text-muted">Use SMTP mail delivery</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="email_reminders" checked>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable SMS reminders</p>
                                <small class="text-muted">Use SMS provider integration</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="sms_reminders" checked>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable auto reminder scheduler</p>
                                <small class="text-muted">Runs every morning at 9:00 AM</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="auto_scheduler" checked>
                            </div>
                        </div>
                        <button type="button" class="btn btn-dark w-100 settings-btn">Save preferences</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card settings-card">
                    <div class="admin-card-header settings-header">
                        <h5 class="admin-card-title settings-title">
                            <i class="bi bi-key-fill me-2"></i>Change password
                        </h5>
                    </div>
                    <div class="p-3 settings-body">
                        <div class="settings-field">
                            <label class="form-label settings-label">Current password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="current_password" class="form-control settings-input" placeholder="Current password">
                                <button type="button" class="btn settings-password-toggle" data-target="current_password" aria-label="Toggle current password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">New password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="new_password" class="form-control settings-input" placeholder="New password">
                                <button type="button" class="btn settings-password-toggle" data-target="new_password" aria-label="Toggle new password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="settings-field settings-field-compact">
                            <label class="form-label settings-label">Confirm new password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="confirm_password" class="form-control settings-input" placeholder="Confirm new password">
                                <button type="button" class="btn settings-password-toggle" data-target="confirm_password" aria-label="Toggle confirm password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-dark w-100 settings-btn">Update password</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-alert-warning h-100 mb-0 settings-note">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    This is a frontend placeholder settings page. Backend save logic can be added next.
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.settings-password-toggle').forEach(function(button) {
    button.addEventListener('click', function() {
        var input = document.getElementById(this.dataset.target);
        var icon = this.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});
</script>
</body>
</html>
