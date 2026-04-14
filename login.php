<?php
// ═══════════════════════════════════════════
// BACKEND — Login Logic
// ═══════════════════════════════════════════
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php'); exit();
    } elseif ($_SESSION['user_role'] === 'vet') {
        header('Location: vet/dashboard.php'); exit();
    } else {
        header('Location: owner/dashboard.php'); exit();
    }
}

include 'config.php';

$error         = '';
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'owner';

// Make sure selected role is only vet or owner (not admin)
if (!in_array($selected_role, ['vet', 'owner'])) {
    $selected_role = 'owner';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Only allow vet or owner from this page
    if (!in_array($role, ['vet', 'owner'])) {
        $error = 'Invalid role selected.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Find user by email and role
        $stmt = mysqli_prepare($conn,
            "SELECT id, first_name, last_name, password, role, status, is_active
             FROM users WHERE email = ? AND role = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $email, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if (!$user) {
            $error = 'No account found with that email and role.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Incorrect password. Please try again.';
        } elseif ($user['is_active'] == 0) {
            $error = 'Your account has been deactivated. Contact admin.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is pending approval. Please wait for admin review.';
        } elseif ($user['status'] === 'rejected') {
            $error = 'Your registration was rejected. Contact admin for details.';
        } else {
            // ✅ Login successful
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_email'] = $email;

            // Redirect based on role
            if ($user['role'] === 'vet') {
                header('Location: vet/dashboard.php');
            } else {
                header('Location: owner/dashboard.php');
            }
            exit();
        }
    }

    // Keep selected role after error
    if (in_array($role, ['vet', 'owner'])) {
        $selected_role = $role;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — PetCura</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet"/>
  <link href="assets/css/auth.css" rel="stylesheet"/>
</head>
<body>

<!-- ═══════════════════════════════════════════
     FRONTEND — Login Page
     Based on wireframe: owner_02_login.png
     Admin tab REMOVED — admin uses secret page
════════════════════════════════════════════ -->
<div class="auth-wrapper">
  <div class="auth-card">

    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 Pet<span>Cura</span></div>
      <p class="auth-subtitle">Sign in to your account</p>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert-error">
      <i class="bi bi-exclamation-circle-fill me-2"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="login.php">

      <!-- Role Tabs — Vet and Owner only -->
      <p class="auth-label mb-2">Sign in as</p>
      <div class="role-tabs mb-4">

        <button type="button"
                class="role-tab <?= $selected_role === 'vet' ? 'active' : '' ?>"
                onclick="selectRole('vet', this)">
          <i class="bi bi-hospital me-1"></i>
          Veterinarian
        </button>

        <button type="button"
                class="role-tab <?= $selected_role === 'owner' ? 'active' : '' ?>"
                onclick="selectRole('owner', this)">
          <i class="bi bi-person-heart me-1"></i>
          Pet Owner
        </button>

      </div>

      <!-- Hidden role input -->
      <input type="hidden" name="role" id="role_input"
             value="<?= htmlspecialchars($selected_role) ?>">

      <!-- Email -->
      <div class="mb-3">
        <label class="auth-label">Email address</label>
        <input type="email"
               name="email"
               class="auth-input"
               placeholder="ram@gmail.com"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required/>
      </div>

      <!-- Password -->
      <div class="mb-2">
        <label class="auth-label">Password</label>
        <input type="password"
               name="password"
               class="auth-input"
               placeholder="••••••••"
               required/>
      </div>

      <!-- Forgot password -->
      <div class="text-end mb-4">
        <a href="#" class="forgot-link">Forgot password?</a>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="auth-btn">
        Sign in
      </button>

    </form>

    <!-- Register Links -->
    <!-- Register Links — changes based on selected role -->
<div class="text-center mt-4">

  <!-- Shows when Owner tab is active -->
  <p id="owner_register_link"
     style="color:#6b7280; font-size:0.9rem; margin-bottom:0;
            display:<?= $selected_role === 'owner' ? 'block' : 'none' ?>">
    Don't have an account?
    <a href="register/owner_register.php" class="auth-link">
      Register as pet owner
    </a>
  </p>

  <!-- Shows when Vet tab is active -->
  <p id="vet_register_link"
     style="color:#6b7280; font-size:0.9rem; margin-bottom:0;
            display:<?= $selected_role === 'vet' ? 'block' : 'none' ?>">
    Don't have an account?
    <a href="register/vet_register.php" class="auth-link">
      Register as veterinarian
    </a>
  </p>

</div>

    <!-- Admin Note — matches wireframe -->
    <div class="auth-divider mt-3">
      Admin accounts are managed internally — contact system admin
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Role tab switcher
function selectRole(role, element) {
    // Update hidden input
    document.getElementById('role_input').value = role;

    // Update tab styles
    document.querySelectorAll('.role-tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    element.classList.add('active');

    // Show/hide register links based on role
    if (role === 'owner') {
        document.getElementById('owner_register_link').style.display = 'block';
        document.getElementById('vet_register_link').style.display   = 'none';
    } else {
        document.getElementById('owner_register_link').style.display = 'none';
        document.getElementById('vet_register_link').style.display   = 'block';
    }
}
</script>

</body>
</html>