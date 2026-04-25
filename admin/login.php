<?php
// ═══════════════════════════════════
// BACKEND — Admin Login Logic
// ═══════════════════════════════════
session_start();

// Already logged in as admin?
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: dashboard.php');
    exit();
}

include '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT id, first_name, last_name, password, is_active
             FROM users WHERE email = ? AND role = 'admin'");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if (!$user) {
            $error = 'Invalid credentials.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid credentials.';
        } elseif ($user['is_active'] == 0) {
            $error = 'Account deactivated.';
        } else {
            // ✅ Admin login successful
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = 'admin';
            header('Location: dashboard.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — PetCare HMS</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <!-- Auth CSS — note ../ because we are inside admin folder -->
  <link href="../assets/css/auth.css" rel="stylesheet"/>
</head>
<body>

<!-- ═══════════════════════════════════
     FRONTEND — Admin Login Page
════════════════════════════════════ -->
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 PetCare <span>HMS</span></div>
      <p class="auth-subtitle">Admin Portal</p>
      <span class="badge mt-1"
            style="background:#134e4a; font-size:0.8rem; padding:6px 14px; border-radius:20px;">
        🔒 Restricted Access
      </span>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert-error">
      <i class="bi bi-exclamation-circle-fill me-2"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Admin Login Form -->
    <form method="POST" action="login.php">

      <!-- Email -->
      <div class="mb-3">
        <label class="auth-label">Email address</label>
        <input type="email"
               name="email"
               class="auth-input"
               placeholder="admin@petcare.com"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required/>
      </div>

      <!-- Password -->
      <div class="mb-4">
        <label class="auth-label">Password</label>
        <input type="password"
               name="password"
               class="auth-input"
               placeholder="••••••••"
               required/>
      </div>

      <div class="text-end mb-4">
        <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
      </div>

      <!-- Submit -->
      <button type="submit" class="auth-btn">
        <i class="bi bi-shield-lock me-2"></i>
        Sign in as Admin
      </button>

    </form>

    <!-- Back to main login -->
    <div class="text-center mt-4">
      <a href="../login.php" class="auth-link" style="font-size:0.9rem;">
        <i class="bi bi-arrow-left me-1"></i>
        Back to main login
      </a>
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>