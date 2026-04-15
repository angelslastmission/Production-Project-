<?php
// ═══════════════════════════════════════
// BACKEND — Owner Registration
// ═══════════════════════════════════════
session_start();
include '../config.php';

// If already logged in redirect
if (isset($_SESSION['user_role'])) {
    header('Location: ../login.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $address    = trim($_POST['address']    ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $terms      = isset($_POST['terms']);

    // ── Validation ───────────────────────
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
        // Check email already exists
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'An account with this email already exists.';
        } else {
          $hashed = password_hash($password, PASSWORD_DEFAULT);

          $ins = mysqli_prepare($conn,
            "INSERT INTO users
              (first_name, last_name, email, password,
               role, phone, address,
               status, is_active)
             VALUES (?, ?, ?, ?, 'owner', ?, ?, 'approved', 1)");

          mysqli_stmt_bind_param($ins, 'ssssss',
            $first_name, $last_name, $email,
            $hashed, $phone, $address);

          if (mysqli_stmt_execute($ins)) {
            $success = 'Account created! You can now sign in.';
            } else {
            $error = 'Registration failed. Please try again.';
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
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card" style="max-width:560px;">

    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 PetCura</div>
      <h5 style="font-family:'Sora',sans-serif; font-weight:700; color:#111827;">
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
    <div class="text-center mt-3">
      <a href="../login.php" class="auth-btn d-inline-block"
         style="text-decoration:none; padding:12px 32px; width:auto;">
        Sign in now
      </a>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert-error">
      <i class="bi bi-exclamation-circle-fill me-2"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <!-- Registration Form -->
    <form method="POST" action="owner_register.php">

      <!-- Name Row -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">First name</label>
          <input type="text"
                 name="first_name"
                 class="auth-input"
                 placeholder="Ram"
                 value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">Last name</label>
          <input type="text"
                 name="last_name"
                 class="auth-input"
                 placeholder="Sharma"
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
               placeholder="ram@gmail.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required/>
      </div>

      <!-- Phone + Address -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">Phone number</label>
          <input type="text"
                 name="phone"
                 class="auth-input"
                 placeholder="98XXXXXXXXX"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">City / address</label>
          <input type="text"
                 name="address"
                 class="auth-input"
                 placeholder="Kathmandu"
                 value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                 required/>
        </div>
      </div>

      <!-- Password Row -->
      <div class="row g-3 mb-4">
        <div class="col-6">
          <label class="auth-label">Password</label>
          <input type="password"
                 name="password"
                 class="auth-input"
                 placeholder="••••••••"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">Confirm password</label>
          <input type="password"
                 name="confirm_password"
                 class="auth-input"
                 placeholder="••••••••"
                 required/>
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
        Create account
      </button>

    </form>
    <?php endif; ?>

    <!-- Sign in link -->
    <div class="text-center mt-4">
      <a href="../login.php" class="auth-link" style="font-size:0.9rem;">
        Already have an account? Sign in here
      </a>
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>