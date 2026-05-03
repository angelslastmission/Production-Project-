<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'settings';

$success_message = '';
$error_message = '';

function is_strong_password($password) {
    return (bool)preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/', $password);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($admin_id <= 0) {
    header('Location: login.php');
    exit();
}

$create_settings_table = "
    CREATE TABLE IF NOT EXISTS admin_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        email_reminders TINYINT(1) NOT NULL DEFAULT 1,
        sms_reminders TINYINT(1) NOT NULL DEFAULT 1,
        auto_scheduler TINYINT(1) NOT NULL DEFAULT 1,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";

if (!mysqli_query($conn, $create_settings_table)) {
    $error_message = 'Could not initialize settings table.';
}

function ensure_settings_column(mysqli $conn, string $column_name, string $column_definition): bool {
    $safe_column = preg_replace('/[^a-zA-Z0-9_]/', '', $column_name);
    if ($safe_column === '') {
        return false;
    }

    $check_query = "SHOW COLUMNS FROM admin_settings LIKE '" . mysqli_real_escape_string($conn, $safe_column) . "'";
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        return true;
    }

    $alter_query = "ALTER TABLE admin_settings ADD COLUMN `{$safe_column}` {$column_definition}";
    return (bool)mysqli_query($conn, $alter_query);
}

$column_ok = true;
$column_ok = $column_ok && ensure_settings_column($conn, 'remind_7days', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_scheduler');
$column_ok = $column_ok && ensure_settings_column($conn, 'remind_3days', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER remind_7days');
$column_ok = $column_ok && ensure_settings_column($conn, 'remind_due', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER remind_3days');
$column_ok = $column_ok && ensure_settings_column($conn, 'remind_overdue', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER remind_due');

if (!$column_ok && $error_message === '') {
    $error_message = 'Could not initialize all reminder preference columns.';
}

mysqli_query($conn, "INSERT IGNORE INTO admin_settings (id) VALUES (1)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error_message = 'Invalid request token. Please refresh and try again.';
    } elseif ($action === 'save_preferences') {
        $remind_7days = isset($_POST['remind_7days']) ? 1 : 0;
        $remind_3days = isset($_POST['remind_3days']) ? 1 : 0;
        $remind_due = isset($_POST['remind_due']) ? 1 : 0;
        $remind_overdue = isset($_POST['remind_overdue']) ? 1 : 0;
        $email_reminders = isset($_POST['email_reminders']) ? 1 : 0;
        $sms_reminders = isset($_POST['sms_reminders']) ? 1 : 0;
        $auto_scheduler = isset($_POST['auto_scheduler']) ? 1 : 0;

        $pref_stmt = mysqli_prepare(
            $conn,
            "UPDATE admin_settings
             SET remind_7days = ?, remind_3days = ?, remind_due = ?, remind_overdue = ?,
                 email_reminders = ?, sms_reminders = ?, auto_scheduler = ?, updated_by = ?
             WHERE id = 1"
        );

        if ($pref_stmt) {
            mysqli_stmt_bind_param(
                $pref_stmt,
                'iiiiiiii',
                $remind_7days,
                $remind_3days,
                $remind_due,
                $remind_overdue,
                $email_reminders,
                $sms_reminders,
                $auto_scheduler,
                $admin_id
            );

            if (mysqli_stmt_execute($pref_stmt)) {
                $success_message = 'Reminder preferences updated successfully.';
            } else {
                $error_message = 'Failed to update reminder preferences.';
            }

            mysqli_stmt_close($pref_stmt);
        } else {
            $error_message = 'Failed to prepare reminder preferences update.';
        }
    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error_message = 'Please fill all password fields.';
        } elseif (!is_strong_password($new_password)) {
            $error_message = 'Weak password. Use at least 8 characters with uppercase, lowercase, number, special character, and no spaces.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New password and confirm password do not match.';
        } else {
            $user_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");

            if ($user_stmt) {
                mysqli_stmt_bind_param($user_stmt, 'i', $admin_id);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                $user_row = $user_result ? mysqli_fetch_assoc($user_result) : null;
                mysqli_stmt_close($user_stmt);

                if (!$user_row || !password_verify($current_password, $user_row['password'])) {
                    $error_message = 'Current password is incorrect.';
                } elseif (password_verify($new_password, $user_row['password'])) {
                    $error_message = 'New password must be different from current password.';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");

                    if ($password_stmt) {
                        mysqli_stmt_bind_param($password_stmt, 'si', $new_hash, $admin_id);

                        if (mysqli_stmt_execute($password_stmt)) {
                            $success_message = 'Password updated successfully.';
                        } else {
                            $error_message = 'Failed to update password.';
                        }

                        mysqli_stmt_close($password_stmt);
                    } else {
                        $error_message = 'Failed to prepare password update.';
                    }
                }
            } else {
                $error_message = 'Failed to verify current password.';
            }
        }
    }
}

$admin_profile = [
    'first_name' => 'Admin',
    'last_name' => '',
    'email' => 'admin@petcare.com',
    'role' => 'admin'
];

$profile_stmt = mysqli_prepare($conn, "SELECT first_name, last_name, email, role FROM users WHERE id = ? AND role = 'admin' LIMIT 1");

if ($profile_stmt) {
    mysqli_stmt_bind_param($profile_stmt, 'i', $admin_id);
    mysqli_stmt_execute($profile_stmt);
    $profile_result = mysqli_stmt_get_result($profile_stmt);
    $profile_row = $profile_result ? mysqli_fetch_assoc($profile_result) : null;

    if ($profile_row) {
        $admin_profile = $profile_row;
        $_SESSION['user_name'] = trim(($profile_row['first_name'] ?? '') . ' ' . ($profile_row['last_name'] ?? ''));
    }

    mysqli_stmt_close($profile_stmt);
}

$preferences = [
    'remind_7days' => 1,
    'remind_3days' => 1,
    'remind_due' => 1,
    'remind_overdue' => 1,
    'email_reminders' => 1,
    'sms_reminders' => 1,
    'auto_scheduler' => 1
];

$pref_load = mysqli_query(
    $conn,
    "SELECT remind_7days, remind_3days, remind_due, remind_overdue,
            email_reminders, sms_reminders, auto_scheduler
     FROM admin_settings
     WHERE id = 1
     LIMIT 1"
);
if ($pref_load && mysqli_num_rows($pref_load) > 0) {
    $pref_row = mysqli_fetch_assoc($pref_load);
    $preferences = [
        'remind_7days' => (int)($pref_row['remind_7days'] ?? 1),
        'remind_3days' => (int)($pref_row['remind_3days'] ?? 1),
        'remind_due' => (int)($pref_row['remind_due'] ?? 1),
        'remind_overdue' => (int)($pref_row['remind_overdue'] ?? 1),
        'email_reminders' => (int)($pref_row['email_reminders'] ?? 1),
        'sms_reminders' => (int)($pref_row['sms_reminders'] ?? 1),
        'auto_scheduler' => (int)($pref_row['auto_scheduler'] ?? 1)
    ];
}

$admin_full_name = trim(($admin_profile['first_name'] ?? '') . ' ' . ($admin_profile['last_name'] ?? ''));
if ($admin_full_name === '') {
    $admin_full_name = 'Administrator';
}
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

        <?php if ($success_message !== ''): ?>
            <div class="admin-alert-success mb-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="admin-alert-error mb-3">
                <i class="bi bi-x-circle-fill me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

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
                            <input type="text" class="form-control settings-input" value="<?= htmlspecialchars($admin_full_name) ?>" readonly>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">Role</label>
                            <input type="text" class="form-control settings-input" value="<?= htmlspecialchars(ucfirst((string)$admin_profile['role'])) ?>" readonly>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">Email</label>
                            <input type="email" class="form-control settings-input" value="<?= htmlspecialchars((string)$admin_profile['email']) ?>" readonly>
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
                    <form method="post" class="p-3 settings-body" id="adminPasswordForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="save_preferences">
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Send reminder 7 days before due date</p>
                                <small class="text-muted">Early notice for upcoming vaccination/deworming</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="remind_7days" name="remind_7days" <?= $preferences['remind_7days'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Send reminder 3 days before due date</p>
                                <small class="text-muted">Short-term reminder near due date</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="remind_3days" name="remind_3days" <?= $preferences['remind_3days'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Send reminder on the due date</p>
                                <small class="text-muted">Same-day due reminder</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="remind_due" name="remind_due" <?= $preferences['remind_due'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Send overdue alerts</p>
                                <small class="text-muted">Notify owners and vets when due date is passed</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="remind_overdue" name="remind_overdue" <?= $preferences['remind_overdue'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable email reminders</p>
                                <small class="text-muted">Sends reminder emails to pet owners</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="email_reminders" name="email_reminders" <?= $preferences['email_reminders'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable SMS reminders</p>
                                <small class="text-muted">Sends SMS reminders from the SMS sender page</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="sms_reminders" name="sms_reminders" <?= $preferences['sms_reminders'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="settings-switch-row settings-row-gap">
                            <div>
                                <p class="settings-switch-title mb-0">Enable auto reminder scheduler</p>
                                <small class="text-muted">Allows automatic reminder processing</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="auto_scheduler" name="auto_scheduler" <?= $preferences['auto_scheduler'] ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 settings-btn">Save preferences</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card settings-card">
                    <div class="admin-card-header settings-header">
                        <h5 class="admin-card-title settings-title">
                            <i class="bi bi-key-fill me-2"></i>Change password
                        </h5>
                    </div>
                    <form method="post" class="p-3 settings-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_password">
                        <div class="settings-field">
                            <label class="form-label settings-label">Current password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="current_password" name="current_password" class="form-control settings-input" placeholder="Current password">
                                <button type="button" class="btn settings-password-toggle" data-target="current_password" aria-label="Toggle current password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="settings-field">
                            <label class="form-label settings-label">New password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="new_password" name="new_password" class="form-control settings-input" placeholder="New password" minlength="8">
                                <button type="button" class="btn settings-password-toggle" data-target="new_password" aria-label="Toggle new password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">At least 8 chars: uppercase, lowercase, number, special character, no spaces.</small>
                        </div>
                        <div class="settings-field settings-field-compact">
                            <label class="form-label settings-label">Confirm new password</label>
                            <div class="input-group settings-password-group">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control settings-input" placeholder="Confirm new password" minlength="8">
                                <button type="button" class="btn settings-password-toggle" data-target="confirm_password" aria-label="Toggle confirm password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div id="adminPasswordMismatchAlert" class="admin-alert-error mb-3" style="display:none;">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            New password and confirm password do not match.
                        </div>
                        <div id="adminPasswordWeakAlert" class="admin-alert-error mb-3" style="display:none;">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            Weak password. Use uppercase, lowercase, number, special character, and at least 8 characters.
                        </div>
                        <button type="submit" id="adminUpdatePasswordBtn" class="btn btn-dark w-100 settings-btn" disabled style="opacity:0.55; cursor:not-allowed;">Update password</button>
                        <div class="text-end mt-2">
                            <a href="forgot_password.php" class="auth-link" style="font-size: 0.9rem;">Forgot password?</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-alert-warning h-100 mb-0 settings-note">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    These settings are saved in database. Email reminders follow these preferences. SMS reminders are managed from the SMS sender page.
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

(function() {
    var current = document.getElementById('current_password');
    var next = document.getElementById('new_password');
    var confirm = document.getElementById('confirm_password');
    var submitBtn = document.getElementById('adminUpdatePasswordBtn');
    var mismatchAlert = document.getElementById('adminPasswordMismatchAlert');
    var weakAlert = document.getElementById('adminPasswordWeakAlert');
    var passwordForm = document.getElementById('adminPasswordForm');

    if (!current || !next || !confirm || !submitBtn || !mismatchAlert || !weakAlert) {
        return;
    }

    function updatePasswordState() {
        var currentVal = current.value.trim();
        var nextVal = next.value;
        var confirmVal = confirm.value;
        var strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/.test(nextVal);
        var mismatch = nextVal !== '' && confirmVal !== '' && nextVal !== confirmVal;
        var valid = currentVal !== '' && strong && confirmVal !== '' && !mismatch;

        mismatchAlert.style.display = mismatch ? 'block' : 'none';
        weakAlert.style.display = (nextVal !== '' && !strong) ? 'block' : 'none';
        submitBtn.disabled = !valid;
        submitBtn.style.opacity = valid ? '1' : '0.55';
        submitBtn.style.cursor = valid ? 'pointer' : 'not-allowed';
    }

    [current, next, confirm].forEach(function(input) {
        input.addEventListener('input', updatePasswordState);
    });

    if (passwordForm) {
        passwordForm.addEventListener('reset', function() {
            window.setTimeout(updatePasswordState, 0);
        });
    }

    updatePasswordState();
})();
</script>
</body>
</html>
