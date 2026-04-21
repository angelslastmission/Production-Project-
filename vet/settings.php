<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'settings';
$success = '';
$error = '';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    $error = 'Invalid session. Please login again.';
}

$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'clinic_name' => '',
    'clinic_address' => '',
    'reminder_window' => (string)($_SESSION['vet_reminder_window'] ?? '7'),
    'email_notifications' => (string)($_SESSION['vet_email_notifications'] ?? 'enabled')
];

if ($vet_id > 0) {
    $vet_result = mysqli_query(
        $conn,
        "SELECT first_name, last_name, email, phone, clinic_name, clinic_address, password
         FROM users
         WHERE id = $vet_id AND role = 'vet'
         LIMIT 1"
    );

    if ($vet_result && mysqli_num_rows($vet_result) > 0) {
        $vet = mysqli_fetch_assoc($vet_result);
        $form_data['first_name'] = $vet['first_name'] ?? '';
        $form_data['last_name'] = $vet['last_name'] ?? '';
        $form_data['email'] = $vet['email'] ?? '';
        $form_data['phone'] = $vet['phone'] ?? '';
        $form_data['clinic_name'] = $vet['clinic_name'] ?? '';
        $form_data['clinic_address'] = $vet['clinic_address'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vet_id > 0) {
    $form_action = trim($_POST['form_action'] ?? 'profile');

    if ($form_action === 'change_password') {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($current_password === '') {
            $error = 'Current password is required.';
        } elseif ($new_password === '') {
            $error = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($confirm_password === '') {
            $error = 'Please confirm your new password.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirm password do not match.';
        }

        if ($error === '') {
            $password_check_result = mysqli_query(
                $conn,
                "SELECT password FROM users WHERE id = $vet_id AND role = 'vet' LIMIT 1"
            );

            if (!$password_check_result || mysqli_num_rows($password_check_result) === 0) {
                $error = 'Unable to verify current password.';
            } else {
                $password_row = mysqli_fetch_assoc($password_check_result);
                $stored_password_hash = $password_row['password'] ?? '';

                if (!password_verify($current_password, $stored_password_hash)) {
                    $error = 'Current password is incorrect.';
                } else {
                    $new_password_hash = mysqli_real_escape_string($conn, password_hash($new_password, PASSWORD_DEFAULT));
                    $password_update_query = "
                        UPDATE users
                        SET password = '$new_password_hash'
                        WHERE id = $vet_id AND role = 'vet'
                    ";

                    if (mysqli_query($conn, $password_update_query)) {
                        $success = 'Password changed successfully.';
                    } else {
                        $error = 'Database error: ' . mysqli_error($conn);
                    }
                }
            }
        }
    } else {
        $form_data['first_name'] = trim($_POST['first_name'] ?? '');
        $form_data['last_name'] = trim($_POST['last_name'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['phone'] = trim($_POST['phone'] ?? '');
        $form_data['clinic_name'] = trim($_POST['clinic_name'] ?? '');
        $form_data['clinic_address'] = trim($_POST['clinic_address'] ?? '');
        $form_data['reminder_window'] = trim($_POST['reminder_window'] ?? '7');
        $form_data['email_notifications'] = trim($_POST['email_notifications'] ?? 'enabled');

        if ($form_data['first_name'] === '') {
            $error = 'First name is required.';
        } elseif ($form_data['last_name'] === '') {
            $error = 'Last name is required.';
        } elseif ($form_data['email'] === '') {
            $error = 'Email is required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        }

        if ($error === '') {
            $first_name = mysqli_real_escape_string($conn, $form_data['first_name']);
            $last_name = mysqli_real_escape_string($conn, $form_data['last_name']);
            $email = mysqli_real_escape_string($conn, $form_data['email']);
            $phone = mysqli_real_escape_string($conn, $form_data['phone']);
            $clinic_name = mysqli_real_escape_string($conn, $form_data['clinic_name']);
            $clinic_address = mysqli_real_escape_string($conn, $form_data['clinic_address']);

            $update_query = "
                UPDATE users
                SET first_name = '$first_name',
                    last_name = '$last_name',
                    email = '$email',
                    phone = '$phone',
                    clinic_name = '$clinic_name',
                    clinic_address = '$clinic_address'
                WHERE id = $vet_id AND role = 'vet'
            ";

            if (mysqli_query($conn, $update_query)) {
                // Keep simple preference fields in session to avoid DB schema changes.
                $_SESSION['vet_reminder_window'] = $form_data['reminder_window'];
                $_SESSION['vet_email_notifications'] = $form_data['email_notifications'];
                $_SESSION['user_name'] = trim($form_data['first_name'] . ' ' . $form_data['last_name']);

                $success = 'Settings updated successfully.';
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <?php if ($success !== ''): ?>
        <div class="vet-alert mb-3">
            <div class="vet-alert-text">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <div style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 10px; padding: 13px 16px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #991b1b; font-size: 0.82rem; font-weight: 700;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Vet settings</h3>
            </div>
            <form method="POST" id="profileSettingsForm" class="p-3 p-md-4">
                <input type="hidden" name="form_action" value="profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">First name <span style="color: #dc2626;">*</span></label>
                        <input type="text" name="first_name" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['first_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Last name <span style="color: #dc2626;">*</span></label>
                        <input type="text" name="last_name" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['last_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Email <span style="color: #dc2626;">*</span></label>
                        <input type="email" name="email" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Phone</label>
                        <input type="text" name="phone" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['phone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Clinic name</label>
                        <input type="text" name="clinic_name" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['clinic_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Clinic address</label>
                        <input type="text" name="clinic_address" class="form-control profile-edit-field" value="<?= htmlspecialchars($form_data['clinic_address']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Reminder window</label>
                        <select name="reminder_window" class="form-select profile-edit-field">
                            <option value="3" <?= $form_data['reminder_window'] === '3' ? 'selected' : '' ?>>3 days before</option>
                            <option value="7" <?= $form_data['reminder_window'] === '7' ? 'selected' : '' ?>>7 days before</option>
                            <option value="14" <?= $form_data['reminder_window'] === '14' ? 'selected' : '' ?>>14 days before</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Email notifications</label>
                        <select name="email_notifications" class="form-select profile-edit-field">
                            <option value="enabled" <?= $form_data['email_notifications'] === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                            <option value="disabled" <?= $form_data['email_notifications'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="button" id="profileEditBtn" class="patients-view-btn">Edit</button>
                    <button type="submit" id="profileSaveBtn" class="vet-alert-btn">SAVE</button>
                    <button type="reset" id="profileClearBtn" class="patients-view-btn">Clear</button>
                </div>
            </form>
        </section>

        <section class="vet-panel mt-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Change password</h3>
            </div>
            <form method="POST" class="p-3 p-md-4">
                <input type="hidden" name="form_action" value="change_password">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Current password <span style="color: #dc2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('current_password', this)" aria-label="Toggle current password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">New password <span style="color: #dc2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('new_password', this)" aria-label="Toggle new password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary">Confirm new password <span style="color: #dc2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password', this)" aria-label="Toggle confirm password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="vet-alert-btn">UPDATE PASSWORD</button>
                    <button type="reset" class="patients-view-btn">Clear</button>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
function setProfileEditMode(isEditable) {
    var fields = document.querySelectorAll('.profile-edit-field');
    var editBtn = document.getElementById('profileEditBtn');
    var saveBtn = document.getElementById('profileSaveBtn');
    var clearBtn = document.getElementById('profileClearBtn');

    for (var i = 0; i < fields.length; i++) {
        fields[i].disabled = !isEditable;
    }

    if (editBtn) {
        editBtn.style.display = isEditable ? 'none' : 'inline-block';
    }
    if (saveBtn) {
        saveBtn.style.display = isEditable ? 'inline-block' : 'none';
    }
    if (clearBtn) {
        clearBtn.style.display = isEditable ? 'inline-block' : 'none';
    }
}

function togglePassword(inputId, buttonEl) {
    var inputEl = document.getElementById(inputId);
    if (!inputEl || !buttonEl) {
        return;
    }

    var iconEl = buttonEl.querySelector('i');

    if (inputEl.type === 'password') {
        inputEl.type = 'text';
        if (iconEl) {
            iconEl.className = 'bi bi-eye-slash';
        }
    } else {
        inputEl.type = 'password';
        if (iconEl) {
            iconEl.className = 'bi bi-eye';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var editBtn = document.getElementById('profileEditBtn');
    var firstField = document.querySelector('.profile-edit-field');

    setProfileEditMode(false);

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            setProfileEditMode(true);
            if (firstField) {
                firstField.focus();
            }
        });
    }
});
</script>
</body>
</html>
