<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/security.php';

$error = '';
$success = '';
$showForm = false;
$reset_success = false;
$rate_action = 'reset_owner';
$rate_error = '';
$csrf_valid = true;

function is_strong_password(string $password): bool {
    return (bool)preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/', $password);
}

function ensure_owner_password_resets_table(mysqli $conn): bool {
    $sql = "
        CREATE TABLE IF NOT EXISTS owner_password_resets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_owner_id (owner_id),
            INDEX idx_email (email),
            INDEX idx_used_at (used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    return (bool)mysqli_query($conn, $sql);
}

$request_id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

if (!ensure_owner_password_resets_table($conn)) {
    $error = 'Unable to prepare reset system.';
}

$resetRow = null;
if ($error === '' && $token !== '') {
  $sql = "
    SELECT id, owner_id, email, token_hash, expires_at
    FROM owner_password_resets
    WHERE used_at IS NULL
    ORDER BY created_at DESC
  ";

  if ($request_id > 0) {
    $sql = "
      SELECT id, owner_id, email, token_hash, expires_at
      FROM owner_password_resets
      WHERE id = ? AND used_at IS NULL
      LIMIT 1
    ";
  }

  $stmt = mysqli_prepare($conn, $sql);
  if ($stmt) {
    if ($request_id > 0) {
      mysqli_stmt_bind_param($stmt, 'i', $request_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = $result ? mysqli_fetch_assoc($result) : null) {
      $expiresAt = strtotime((string)$row['expires_at']);
      if ($expiresAt !== false && $expiresAt < time()) {
        continue;
      }

      if (password_verify($token, (string)$row['token_hash'])) {
        $resetRow = $row;
        $email = (string)$row['email'];
        break;
      }
      if ($request_id > 0) {
        break;
      }
    }
    mysqli_stmt_close($stmt);
  }

    if (!$resetRow) {
        $error = 'This reset link is invalid or expired. Please request a new one.';
    } else {
        $showForm = true;
    }
} elseif ($error === '') {
    $error = 'Missing reset link data. Please use the newest email link, not an old forwarded one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf_valid = validate_csrf_token($_POST['csrf_token'] ?? null);
  if (!$csrf_valid) {
    $error = 'Invalid request. Please refresh and try again.';
  } else {
    $rate_error = auth_rate_limit_check($conn, $rate_action);
    if ($rate_error !== '') {
      $error = $rate_error;
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm && $resetRow && $rate_error === '' && $csrf_valid) {
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($new_password === '' || $confirm_password === '') {
        $error = 'All password fields are required.';
    } elseif (!is_strong_password($new_password)) {
        $error = 'Weak password. Use at least 8 characters with uppercase, lowercase, number, special character, and no spaces.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } else {
        $userStmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? AND role = 'owner' LIMIT 1");
        if ($userStmt) {
            mysqli_stmt_bind_param($userStmt, 'i', $resetRow['owner_id']);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $userRow = $userResult ? mysqli_fetch_assoc($userResult) : null;
            mysqli_stmt_close($userStmt);

            if (!$userRow) {
                $error = 'Owner account not found.';
            } elseif (password_verify($new_password, (string)$userRow['password'])) {
                $error = 'New password must be different from your current password.';
            } else {
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'owner' LIMIT 1");
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, 'si', $newHash, $resetRow['owner_id']);
                    if (mysqli_stmt_execute($updateStmt)) {
                        $markStmt = mysqli_prepare($conn, "UPDATE owner_password_resets SET used_at = NOW() WHERE id = ? LIMIT 1");
                        if ($markStmt) {
                            mysqli_stmt_bind_param($markStmt, 'i', $resetRow['id']);
                            mysqli_stmt_execute($markStmt);
                            mysqli_stmt_close($markStmt);
                        }

                        $success = 'Password reset successfully. You can now log in with your new password.';
                        $showForm = false;
                        $reset_success = true;
                    } else {
                        $error = 'Unable to update password.';
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    $error = 'Unable to prepare password update.';
                }
            }
        } else {
            $error = 'Unable to verify owner account.';
        }
    }
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rate_error === '' && $csrf_valid) {
    auth_rate_limit_register_attempt($conn, $rate_action, $reset_success);
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/auth.css" rel="stylesheet"/>
  <style>
    .password-toggle-wrap { position: relative; }
    .password-toggle-wrap .auth-input { padding-right: 42px; }
    .password-toggle-btn {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      padding: 0;
      color: #6b7280;
      cursor: pointer;
      line-height: 1;
    }
    .password-toggle-btn:hover { color: #0d9488; }
  </style>
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 Pet<span>Cura</span></div>
      <p class="auth-subtitle">Set a new password</p>
    </div>

    <?php if ($success !== ''): ?>
      <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
      <div class="text-center mt-3">
        <a href="../login.php" class="auth-link">Back to Sign in</a>
      </div>
    <?php else: ?>
      <?php if ($error !== ''): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($showForm): ?>
      <form method="POST" id="resetPasswordForm">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= (int)$request_id ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

        <div class="mb-3">
          <label class="auth-label">New password</label>
          <div class="password-toggle-wrap">
            <input type="password" id="new_password" name="new_password" class="auth-input" minlength="8" required>
            <button type="button" class="password-toggle-btn" id="toggleNewPassword" aria-label="Toggle new password visibility">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <small class="text-muted">At least 8 chars: uppercase, lowercase, number, special character, no spaces.</small>
        </div>

        <div class="mb-3">
          <label class="auth-label">Confirm new password</label>
          <div class="password-toggle-wrap">
            <input type="password" id="confirm_password" name="confirm_password" class="auth-input" minlength="8" required>
            <button type="button" class="password-toggle-btn" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div id="passwordWeakAlert" class="alert alert-danger d-none" role="alert">Weak password. Use uppercase, lowercase, number, special character, and at least 8 characters.</div>
        <div id="passwordMismatchAlert" class="alert alert-danger d-none" role="alert">New password and confirm password do not match.</div>

        <button type="submit" id="updatePasswordBtn" class="auth-btn" disabled style="opacity:0.55; cursor:not-allowed;">Update Password</button>
      </form>
      <?php else: ?>
        <div class="text-center mt-3">
          <a href="forgot_password.php" class="auth-link">Request new reset link</a>
        </div>
      <?php endif; ?>

      <div class="text-center mt-3">
        <a href="../login.php" class="auth-link">Back to Sign in</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const newPasswordInput = document.getElementById('new_password');
  const confirmPasswordInput = document.getElementById('confirm_password');
  const updatePasswordBtn = document.getElementById('updatePasswordBtn');
  const weakAlert = document.getElementById('passwordWeakAlert');
  const mismatchAlert = document.getElementById('passwordMismatchAlert');
  const toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
  const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

  if (!newPasswordInput || !confirmPasswordInput || !updatePasswordBtn) {
    return;
  }

  function togglePassword(input, button) {
    const icon = button.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }

  function updateState() {
    const newVal = newPasswordInput.value;
    const confirmVal = confirmPasswordInput.value;
    const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])(?=\S+$).{8,}$/.test(newVal);
    const hasMismatch = newVal !== '' && confirmVal !== '' && newVal !== confirmVal;
    const canSubmit = newVal !== '' && confirmVal !== '' && strongPassword && !hasMismatch;

    weakAlert.classList.toggle('d-none', !(newVal !== '' && !strongPassword));
    mismatchAlert.classList.toggle('d-none', !hasMismatch);
    updatePasswordBtn.disabled = !canSubmit;
    updatePasswordBtn.style.opacity = canSubmit ? '1' : '0.55';
    updatePasswordBtn.style.cursor = canSubmit ? 'pointer' : 'not-allowed';
  }

  [newPasswordInput, confirmPasswordInput].forEach((input) => input.addEventListener('input', updateState));

  if (toggleNewPasswordBtn) {
    toggleNewPasswordBtn.addEventListener('click', () => togglePassword(newPasswordInput, toggleNewPasswordBtn));
  }
  if (toggleConfirmPasswordBtn) {
    toggleConfirmPasswordBtn.addEventListener('click', () => togglePassword(confirmPasswordInput, toggleConfirmPasswordBtn));
  }

  updateState();
})();
</script>
</body>
</html>
