<?php
// ═══════════════════════════════════════
// BACKEND — Owner Registration
// ═══════════════════════════════════════
session_start();
include '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If already logged in redirect
if (isset($_SESSION['user_role'])) {
    header('Location: ../login.php');
    exit();
}

$error   = '';
$success = '';
$isAccountCreated = false;
$pendingRegistration = $_SESSION['owner_registration_pending'] ?? null;
$showVerificationStep = is_array($pendingRegistration);

function send_owner_registration_code(string $toEmail, string $toName, string $code, string $gmailUsername, string $gmailAppPassword): bool {
  if ($gmailUsername === '' || $gmailAppPassword === '') {
    throw new RuntimeException('Mail sender is not configured in config.php.');
  }

  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = $gmailUsername;
  $mail->Password = $gmailAppPassword;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;
  $mail->CharSet = 'UTF-8';
  $mail->setFrom($gmailUsername, 'PetCura');
  $mail->addAddress($toEmail, $toName !== '' ? $toName : 'Pet Owner');
  $mail->isHTML(true);
  $mail->Subject = 'PetCura - Verify your owner registration email';
  $mail->Body = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">'
    . '<h2 style="margin-bottom:12px;">Email verification code</h2>'
    . '<p>Use this code to complete your owner registration:</p>'
    . '<p style="font-size:28px;font-weight:700;letter-spacing:3px;color:#0f766e;margin:18px 0;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p>This code expires in 10 minutes.</p>'
    . '<p>If you did not request this, you can ignore this email.</p>'
    . '</div>';
  $mail->AltBody = 'Your PetCura owner registration verification code is: ' . $code . '. It expires in 10 minutes.';

  return $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'start_registration';

  if ($action === 'start_registration') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $terms      = isset($_POST['terms']);

    if (empty($first_name) || empty($last_name) ||
        empty($email) || empty($phone) ||
        empty($address) || empty($password) || empty($confirm)) {
      $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
      $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
      $error = 'Passwords do not match.';
    } elseif (!$terms) {
      $error = 'Please agree to the terms and privacy policy.';
    } else {
      $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
          $error = 'An account with this email already exists.';
        }
        mysqli_stmt_close($stmt);
      } else {
        $error = 'Could not verify email availability.';
      }

      if ($error === '') {
        $code = (string)random_int(100000, 999999);
        $_SESSION['owner_registration_pending'] = [
          'first_name' => $first_name,
          'last_name' => $last_name,
          'email' => strtolower($email),
          'phone' => $phone,
          'address' => $address,
          'password_hash' => password_hash($password, PASSWORD_DEFAULT),
          'code_hash' => password_hash($code, PASSWORD_DEFAULT),
          'expires_at' => time() + 600,
        ];

        try {
          send_owner_registration_code(
            $email,
            trim($first_name . ' ' . $last_name),
            $code,
            trim((string)($mail_gmail_username ?? '')),
            trim((string)($mail_gmail_app_password ?? ''))
          );
          $success = 'Verification code sent. Please enter the code to complete registration.';
          $pendingRegistration = $_SESSION['owner_registration_pending'];
          $showVerificationStep = true;
        } catch (Exception $e) {
          $error = 'Mailer error: ' . $e->getMessage();
          unset($_SESSION['owner_registration_pending']);
          $pendingRegistration = null;
          $showVerificationStep = false;
        } catch (RuntimeException $e) {
          $error = $e->getMessage();
          unset($_SESSION['owner_registration_pending']);
          $pendingRegistration = null;
          $showVerificationStep = false;
        }
      }
    }
  } elseif ($action === 'resend_code') {
    if (!is_array($pendingRegistration)) {
      $error = 'Registration details not found. Please fill the form again.';
      $showVerificationStep = false;
    } else {
      $code = (string)random_int(100000, 999999);
      $_SESSION['owner_registration_pending']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
      $_SESSION['owner_registration_pending']['expires_at'] = time() + 600;

      try {
        send_owner_registration_code(
          (string)$pendingRegistration['email'],
          trim((string)$pendingRegistration['first_name'] . ' ' . (string)$pendingRegistration['last_name']),
          $code,
          trim((string)($mail_gmail_username ?? '')),
          trim((string)($mail_gmail_app_password ?? ''))
        );
        $success = 'New verification code sent. Check your email.';
      } catch (Exception $e) {
        $error = 'Mailer error: ' . $e->getMessage();
      } catch (RuntimeException $e) {
        $error = $e->getMessage();
      }

      $pendingRegistration = $_SESSION['owner_registration_pending'];
      $showVerificationStep = true;
    }
  } elseif ($action === 'edit_registration') {
    unset($_SESSION['owner_registration_pending']);
    $pendingRegistration = null;
    $showVerificationStep = false;
  } elseif ($action === 'verify_and_create') {
    $verification_code = trim($_POST['verification_code'] ?? '');

    if (!is_array($pendingRegistration)) {
      $error = 'Registration details not found. Please fill the form again.';
      $showVerificationStep = false;
    } elseif ($verification_code === '') {
      $error = 'Please enter the verification code.';
      $showVerificationStep = true;
    } elseif (time() > (int)($pendingRegistration['expires_at'] ?? 0)) {
      $error = 'Verification code expired. Please resend code.';
      $showVerificationStep = true;
    } elseif (!password_verify($verification_code, (string)($pendingRegistration['code_hash'] ?? ''))) {
      $error = 'Invalid verification code.';
      $showVerificationStep = true;
    } else {
      $email = (string)$pendingRegistration['email'];
      $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
          $error = 'An account with this email already exists.';
          $showVerificationStep = false;
          unset($_SESSION['owner_registration_pending']);
          $pendingRegistration = null;
        }
        mysqli_stmt_close($stmt);
      } else {
        $error = 'Could not verify email availability.';
        $showVerificationStep = true;
      }

      if ($error === '') {
        $ins = mysqli_prepare(
          $conn,
          "INSERT INTO users
            (first_name, last_name, email, password,
             role, phone, address,
             status, is_active)
           VALUES (?, ?, ?, ?, 'owner', ?, ?, 'approved', 1)"
        );

        if ($ins) {
          mysqli_stmt_bind_param(
            $ins,
            'ssssss',
            $pendingRegistration['first_name'],
            $pendingRegistration['last_name'],
            $pendingRegistration['email'],
            $pendingRegistration['password_hash'],
            $pendingRegistration['phone'],
            $pendingRegistration['address']
          );

          if (mysqli_stmt_execute($ins)) {
            $success = 'Account created! You can now sign in.';
            $isAccountCreated = true;
            $showVerificationStep = false;
            unset($_SESSION['owner_registration_pending']);
            $pendingRegistration = null;
          } else {
            $error = 'Registration failed. Please try again.';
            $showVerificationStep = true;
          }
          mysqli_stmt_close($ins);
        } else {
          $error = 'Unable to prepare account creation.';
          $showVerificationStep = true;
        }
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
  <title>Owner Registration — PetCura</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <!-- Auth CSS -->
  <link href="../assets/css/auth.css" rel="stylesheet"/>
  <style>
    .password-toggle-wrap { position: relative; }
    .password-toggle-wrap .auth-input { padding-right: 42px; }
    .password-toggle-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #6b7280;
      cursor: pointer;
      line-height: 1;
      padding: 0;
    }
    .password-toggle-btn:hover { color: #0d9488; }
  </style>
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card auth-card-wide">

    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 PetCura</div>
      <h5 class="auth-title">
        Create owner account
      </h5>
      <p class="auth-subtitle">
        Register to view your pet's health records and receive reminders
      </p>
    </div>

    <!-- Success Message -->
    <?php if ($success): ?>
    <div class="alert-success">
      <i class="bi bi-check-circle-fill me-2"></i>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php if ($isAccountCreated): ?>
      <div class="text-center mt-3">
        <a href="../login.php" class="auth-btn auth-btn-inline d-inline-block">
          Sign in now
        </a>
      </div>
    <?php else: ?>
      <div class="text-center mt-3">
        <a href="#owner-register-form" class="auth-link">Back to registration form</a>
      </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert-error">
      <i class="bi bi-exclamation-circle-fill me-2"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!$isAccountCreated): ?>
    <?php if (!$showVerificationStep): ?>
    <!-- Step 1: Registration Details -->
    <form method="POST" action="owner_register.php" id="owner-register-form">
      <input type="hidden" name="action" value="start_registration"/>

      <!-- Name Row -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">First name</label>
          <input type="text"
                 name="first_name"
                 class="auth-input"
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">Last name</label>
          <input type="text"
                 name="last_name"
                 class="auth-input"
                 value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                 required/>
        </div>
      </div>

      <!-- Email -->
      <div class="mb-3">
        <label class="auth-label">Email address</label>
        <input type="email"
               name="email"
               class="auth-input"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required/>
        <small class="text-muted">Tip for demos: Gmail aliases like yourmail+owner1@gmail.com are supported.</small>
      </div>

      <!-- Phone + Address -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">Phone number</label>
          <input type="text"
                 name="phone"
                 class="auth-input"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">City / address</label>
          <input type="text"
                 name="address"
                 class="auth-input"
                 value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                 required/>
        </div>
      </div>

      <!-- Password Row -->
      <div class="row g-3 mb-4">
        <div class="col-6">
          <label class="auth-label">Password</label>
          <div class="password-toggle-wrap">
            <input type="password"
                   name="password"
                   id="password"
                   class="auth-input"
                   required/>
            <button type="button"
                    class="password-toggle-btn"
                    id="toggleNewPassword"
                    aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="col-6">
          <label class="auth-label">Confirm password</label>
          <div class="password-toggle-wrap">
            <input type="password"
                   name="confirm_password"
                   id="confirm_password"
                   class="auth-input"
                   required/>
            <button type="button"
                    class="password-toggle-btn"
                    id="toggleConfirmPassword"
                    aria-label="Show confirm password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Terms Checkbox -->
      <div class="mb-4">
        <label class="checkbox-label">
          <input type="checkbox" name="terms" required
                 <?= isset($_POST['terms']) ? 'checked' : '' ?>/>
          <span>
            I agree to the
            <a href="#" class="auth-link">terms and privacy policy</a>
          </span>
        </label>
      </div>

      <!-- Submit -->
      <button type="submit" class="auth-btn">
        Continue to verification
      </button>

    </form>
    <?php else: ?>
    <!-- Step 2: Code Verification -->
    <div class="alert-success">
      <i class="bi bi-envelope-check me-2"></i>
      A verification code was sent to <strong><?= htmlspecialchars((string)$pendingRegistration['email']) ?></strong>.
    </div>

    <form method="POST" action="owner_register.php" class="mb-3">
      <input type="hidden" name="action" value="verify_and_create"/>
      <div class="mb-3">
        <label class="auth-label">Email verification code</label>
        <input type="text"
               name="verification_code"
               class="auth-input"
               inputmode="numeric"
               pattern="\d{6}"
               maxlength="6"
               placeholder="Enter 6-digit code"
               required/>
        <small class="text-muted">Code expires in 10 minutes.</small>
      </div>
      <button type="submit" class="auth-btn">Create account</button>
    </form>

    <div class="d-flex gap-2">
      <form method="POST" action="owner_register.php" class="w-100">
        <input type="hidden" name="action" value="resend_code"/>
        <button type="submit" class="auth-btn" style="background:#0f766e;">Resend code</button>
      </form>
      <form method="POST" action="owner_register.php" class="w-100">
        <input type="hidden" name="action" value="edit_registration"/>
        <button type="submit" class="auth-btn" style="background:#334155;">Back to details</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Sign in link -->
    <div class="text-center mt-4">
      <a href="../login.php" class="auth-link auth-link-small">
        Already have an account? Sign in here
      </a>
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var newPasswordInput = document.getElementById('password');
  var confirmPasswordInput = document.getElementById('confirm_password');
  var toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
  var toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

  function togglePassword(input, button) {
    if (!input || !button) {
      return;
    }
    var icon = button.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }

  if (toggleNewPasswordBtn) {
    toggleNewPasswordBtn.addEventListener('click', function () {
      togglePassword(newPasswordInput, toggleNewPasswordBtn);
    });
  }

  if (toggleConfirmPasswordBtn) {
    toggleConfirmPasswordBtn.addEventListener('click', function () {
      togglePassword(confirmPasswordInput, toggleConfirmPasswordBtn);
    });
  }
})();
</script>

</body>
</html>