<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'change_password';
$vet_id = (int)($_SESSION['user_id'] ?? 0);

if ($vet_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

function is_strong_password($password) {
    return (bool)preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = 'All password fields are required.';
    } elseif (!is_strong_password($new_password)) {
        $error = 'Weak password. Use at least 8 characters with uppercase, lowercase, number, special character, and no spaces.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } else {
        $vet_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? AND role = 'vet' LIMIT 1");
        if ($vet_stmt) {
            mysqli_stmt_bind_param($vet_stmt, 'i', $vet_id);
            mysqli_stmt_execute($vet_stmt);
            $vet_result = mysqli_stmt_get_result($vet_stmt);
            $vet_row = $vet_result ? mysqli_fetch_assoc($vet_result) : null;
            mysqli_stmt_close($vet_stmt);

            if (!$vet_row || !password_verify($current_password, (string)$vet_row['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (password_verify($new_password, (string)$vet_row['password'])) {
                $error = 'New password must be different from your current password.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'vet' LIMIT 1");

                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'si', $new_hash, $vet_id);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $success = 'Password changed successfully.';
                    } else {
                        $error = 'Unable to update password. Please try again.';
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $error = 'Unable to prepare password update statement.';
                }
            }
        } else {
            $error = 'Unable to verify current password.';
        }
    }
}

$vet_profile = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
];

$profile_stmt = mysqli_prepare(
    $conn,
    "SELECT first_name, last_name, email FROM users WHERE id = ? AND role = 'vet' LIMIT 1"
);
if ($profile_stmt) {
    mysqli_stmt_bind_param($profile_stmt, 'i', $vet_id);
    mysqli_stmt_execute($profile_stmt);
    $profile_result = mysqli_stmt_get_result($profile_stmt);
    $profile_row = $profile_result ? mysqli_fetch_assoc($profile_result) : null;
    if ($profile_row) {
        $vet_profile = $profile_row;
    }
    mysqli_stmt_close($profile_stmt);
}

$vet_name = trim(($vet_profile['first_name'] ?? '') . ' ' . ($vet_profile['last_name'] ?? ''));
if ($vet_name === '') {
    $vet_name = $_SESSION['user_name'] ?? 'Veterinarian';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Change Password - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
    <style>
        .password-toggle-wrap {
            position: relative;
        }
        .password-toggle-wrap .form-control {
            padding-right: 44px;
        }
        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            padding: 0;
            color: #6b7280;
            line-height: 1;
            cursor: pointer;
        }
        .password-toggle-btn:hover {
            color: #0d9488;
        }
    </style>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <section class="vet-greeting" style="margin-bottom: 20px;">
            <h1>Change Password</h1>
            <p>Update your vet account password securely.</p>
        </section>

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

        <section class="vet-panel" style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
                    <div style="font-size: 0.8rem; color: #334155; font-weight: 600; text-transform: uppercase;">Veterinarian</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($vet_name) ?></div>
                </div>
                <div style="background: #ecfeff; border: 1px solid #cffafe; border-radius: 10px; padding: 16px;">
                    <div style="font-size: 0.8rem; color: #155e75; font-weight: 600; text-transform: uppercase;">Login Email</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #164e63;"><?= htmlspecialchars((string)$vet_profile['email']) ?></div>
                </div>
            </div>
        </section>

        <section class="vet-panel">
            <div class="vet-panel-header" style="margin-bottom: 12px;">
                <h3 class="vet-panel-title">Password Update Form</h3>
            </div>

            <form method="POST" novalidate id="changePasswordForm">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" style="font-weight: 600;">Current Password</label>
                        <div class="password-toggle-wrap">
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <button type="button" class="password-toggle-btn" id="toggleCurrentPassword" aria-label="Toggle current password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">New Password</label>
                        <div class="password-toggle-wrap">
                            <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" required>
                            <button type="button" class="password-toggle-btn" id="toggleNewPassword" aria-label="Toggle new password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small style="color: #6b7280;">At least 8 chars: uppercase, lowercase, number, special character, no spaces.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">Confirm New Password</label>
                        <div class="password-toggle-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                            <button type="button" class="password-toggle-btn" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="passwordMismatchAlert" class="alert alert-danger mt-3 d-none" role="alert">
                    Passwords do not match with each other.
                </div>

                <div id="passwordWeakAlert" class="alert alert-danger mt-3 d-none" role="alert">
                    Weak password. Use uppercase, lowercase, number, special character, and at least 8 characters.
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="reset" class="btn btn-light" id="clearPasswordBtn">Clear</button>
                    <button type="submit" class="btn" id="updatePasswordBtn" style="background: #0d9488; color: #fff; opacity: 0.55; cursor: not-allowed;" disabled>Update Password</button>
                </div>
            </form>

            <div style="margin-top: 12px; color: #6b7280; font-size: 0.9rem;">
                Forgot your password?
                <a href="forgot_password.php" style="color: #0d9488; font-weight: 600; text-decoration: none;">Reset here</a>.
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const changePasswordForm = document.getElementById('changePasswordForm');
const currentPasswordInput = document.getElementById('current_password');
const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');
const updatePasswordBtn = document.getElementById('updatePasswordBtn');
const clearPasswordBtn = document.getElementById('clearPasswordBtn');
const passwordMismatchAlert = document.getElementById('passwordMismatchAlert');
const passwordWeakAlert = document.getElementById('passwordWeakAlert');
const toggleCurrentPasswordBtn = document.getElementById('toggleCurrentPassword');
const toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

function togglePasswordVisibility(input, button) {
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function updateSubmitState() {
    const currentVal = currentPasswordInput.value.trim();
    const newVal = newPasswordInput.value;
    const confirmVal = confirmPasswordInput.value;
    const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/.test(newVal);
    const hasMismatch = newVal !== '' && confirmVal !== '' && newVal !== confirmVal;
    const valid = currentVal !== '' && strongPassword && confirmVal !== '' && !hasMismatch;

    passwordMismatchAlert.classList.toggle('d-none', !hasMismatch);
    passwordWeakAlert.classList.toggle('d-none', !(newVal !== '' && !strongPassword));

    updatePasswordBtn.disabled = !valid;
    updatePasswordBtn.style.opacity = valid ? '1' : '0.55';
    updatePasswordBtn.style.cursor = valid ? 'pointer' : 'not-allowed';
}

[currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(function (input) {
    input.addEventListener('input', updateSubmitState);
});

clearPasswordBtn.addEventListener('click', function () {
    window.setTimeout(updateSubmitState, 0);
});

toggleCurrentPasswordBtn.addEventListener('click', function () {
    togglePasswordVisibility(currentPasswordInput, toggleCurrentPasswordBtn);
});

toggleNewPasswordBtn.addEventListener('click', function () {
    togglePasswordVisibility(newPasswordInput, toggleNewPasswordBtn);
});

toggleConfirmPasswordBtn.addEventListener('click', function () {
    togglePasswordVisibility(confirmPasswordInput, toggleConfirmPasswordBtn);
});

updateSubmitState();
</script>
</body>
</html>
