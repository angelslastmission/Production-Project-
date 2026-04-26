<?php
// ═══════════════════════════════════════
// BACKEND — Vet Registration
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

function ensure_vet_reapplication_columns(mysqli $conn): void {
  $needed = [
    'vet_registration_attempts' => "ALTER TABLE users ADD COLUMN vet_registration_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0",
    'vet_reapply_after' => "ALTER TABLE users ADD COLUMN vet_reapply_after DATETIME NULL DEFAULT NULL",
  ];

  foreach ($needed as $column => $sql) {
    $columnEscaped = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '{$columnEscaped}'");
    $exists = $check && mysqli_num_rows($check) > 0;
    if ($check) {
      mysqli_free_result($check);
    }

    if (!$exists) {
      mysqli_query($conn, $sql);
    }
  }
}

ensure_vet_reapplication_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $clinic_name    = trim($_POST['clinic_name'] ?? '');
    $clinic_address = trim($_POST['clinic_address'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm_pass   = $_POST['confirm_password'] ?? '';
    $confirm_docs   = isset($_POST['confirm_docs']);

    // ── Validation ──────────────────────
    if (empty($first_name) || empty($last_name) ||
        empty($email) || empty($phone) ||
        empty($clinic_name) || empty($clinic_address) ||
        empty($password) || empty($confirm_pass)) {
        $error = 'Please fill in all fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';

    } elseif ($password !== $confirm_pass) {
        $error = 'Passwords do not match.';

    } elseif (!$confirm_docs) {
        $error = 'Please confirm your documents are genuine.';

    } elseif (empty($_FILES['license_doc']['name']) ||
              empty($_FILES['citizenship_doc']['name'])) {
        $error = 'Please upload both required documents.';

    } else {
        // Check if this email already has an account and apply vet re-registration policy.
        $stmt = mysqli_prepare($conn,
          "SELECT id, role, status,
              COALESCE(vet_registration_attempts, 0) AS vet_registration_attempts,
              vet_reapply_after
           FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $existingResult = mysqli_stmt_get_result($stmt);
        $existingUser = $existingResult ? mysqli_fetch_assoc($existingResult) : null;
        mysqli_stmt_close($stmt);

        $allowVetReapply = false;

        if ($existingUser) {
          if ($existingUser['role'] !== 'vet') {
            $error = 'An account with this email already exists.';
          } elseif ($existingUser['status'] === 'approved' || $existingUser['status'] === 'pending') {
            $error = 'A veterinarian account with this email already exists.';
          } elseif ($existingUser['status'] === 'rejected') {
            $attempts = (int)$existingUser['vet_registration_attempts'];
            $reapplyAfter = $existingUser['vet_reapply_after'] ? strtotime((string)$existingUser['vet_reapply_after']) : null;

            if ($attempts >= 3 && $reapplyAfter !== null && $reapplyAfter > time()) {
              $error = 'Application limit reached. You can apply again after ' . date('M j, Y g:i A', $reapplyAfter) . '.';
            } else {
              $allowVetReapply = true;
            }
          } else {
            $error = 'This email cannot be used for a new registration right now.';
          }
        } else {
          $allowVetReapply = false;
        }

        if ($error === '') {
            // ── Handle file uploads ──────────
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Production-Project-/uploads/';
            $allowed    = ['jpg', 'jpeg', 'png'];
            $max_size   = 5 * 1024 * 1024; // 5MB

            // License document
            $license_ext  = strtolower(pathinfo(
                $_FILES['license_doc']['name'],
                PATHINFO_EXTENSION));
            $license_name = 'license_' . time() . '_' . uniqid() . '.' . $license_ext;
            $license_path = $upload_dir . 'license/' . $license_name;

            // Citizenship document
            $citizen_ext  = strtolower(pathinfo(
                $_FILES['citizenship_doc']['name'],
                PATHINFO_EXTENSION));
            $citizen_name = 'citizenship_' . time() . '_' . uniqid() . '.' . $citizen_ext;
            $citizen_path = $upload_dir . 'citizenship/' . $citizen_name;
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Production-Project-/uploads/';

// Create folders if they don't exist
if (!is_dir($upload_dir . 'licenses/')) {
    mkdir($upload_dir . 'licenses/', 0755, true);
}
if (!is_dir($upload_dir . 'citizenships/')) {
    mkdir($upload_dir . 'citizenships/', 0755, true);
}

            // Validate file types
            if (!in_array($license_ext, $allowed) ||
                !in_array($citizen_ext, $allowed)) {
                $error = 'JPG, JPEG, PNG files allowed.';

            } elseif ($_FILES['license_doc']['size'] > $max_size ||
                      $_FILES['citizenship_doc']['size'] > $max_size) {
                $error = 'File size must be under 5MB.';

            } else {
                // Move uploaded files
                $license_uploaded = move_uploaded_file(
                    $_FILES['license_doc']['tmp_name'],
                    $license_path);
                $citizen_uploaded = move_uploaded_file(
                    $_FILES['citizenship_doc']['tmp_name'],
                    $citizen_path);

                if (!$license_uploaded || !$citizen_uploaded) {
                    $error = 'File upload failed. Please try again.';
                } else {
                    // ── Save to database ─────────
                    $hashed_password = password_hash(
                        $password, PASSWORD_DEFAULT);

                    if ($existingUser && $allowVetReapply) {
                      $currentAttempts = (int)$existingUser['vet_registration_attempts'];
                      $currentReapplyAfter = $existingUser['vet_reapply_after'] ? strtotime((string)$existingUser['vet_reapply_after']) : null;
                      $resetAttempts = ($currentAttempts >= 3 && $currentReapplyAfter !== null && $currentReapplyAfter <= time());
                      $newAttempts = $resetAttempts ? 0 : $currentAttempts;

                      $stmt = mysqli_prepare($conn,
                        "UPDATE users
                         SET first_name = ?,
                           last_name = ?,
                           password = ?,
                           phone = ?,
                           clinic_name = ?,
                           clinic_address = ?,
                           license_doc = ?,
                           citizenship_doc = ?,
                           status = 'pending',
                           is_active = 1,
                           rejection_reason = NULL,
                           vet_registration_attempts = ?,
                           vet_reapply_after = NULL
                         WHERE id = ? AND role = 'vet' LIMIT 1");

                      mysqli_stmt_bind_param(
                        $stmt,
                        'ssssssssii',
                        $first_name,
                        $last_name,
                        $hashed_password,
                        $phone,
                        $clinic_name,
                        $clinic_address,
                        $license_name,
                        $citizen_name,
                        $newAttempts,
                        $existingUser['id']
                      );
                    } else {
                      $stmt = mysqli_prepare($conn,
                        "INSERT INTO users (
                          first_name, last_name, email, password,
                          role, phone, clinic_name, clinic_address,
                          license_doc, citizenship_doc,
                          status, is_active, vet_registration_attempts, vet_reapply_after
                        ) VALUES (?, ?, ?, ?, 'vet', ?, ?, ?, ?, ?, 'pending', 1, 0, NULL)");

                      mysqli_stmt_bind_param($stmt, 'sssssssss',
                        $first_name, $last_name, $email,
                        $hashed_password, $phone,
                        $clinic_name, $clinic_address,
                        $license_name, $citizen_name);
                    }

                    if ($stmt && mysqli_stmt_execute($stmt)) {
                      $success = 'Registration submitted! Wait for approval. It will be reviewed within 24 hours.';
                    } else {
                      $error = 'Registration failed. Please try again.';
                    }
                    if ($stmt) {
                      mysqli_stmt_close($stmt);
                    }
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
  <title>Veterinarian Registration — PetCura</title>

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

<!-- ═══════════════════════════════════════
     FRONTEND — Vet Registration Page
     Based on wireframe: vet_01_register.png
════════════════════════════════════════ -->
<div class="auth-wrapper">
  <div class="auth-card" style="max-width:600px;">

    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 PetCura</div>
      <h5 style="font-family:'Sora',sans-serif; font-weight:700; color:#111827;">
        Veterinarian Registration
      </h5>
      <p class="auth-subtitle">
        Submit your details and documents. Wait for approval. It will be reviewed within 24 hours.
      </p>
    </div>

    <!-- Success Message -->
    <?php if ($success): ?>
    <div class="alert-success">
      <i class="bi bi-check-circle-fill me-2"></i>
      <?= htmlspecialchars($success) ?>
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
    <form method="POST"
          action="vet_register.php"
          enctype="multipart/form-data">

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
        <small class="text-muted">Use a real inbox email. Admin approval is still required before login.</small>
      </div>

      <!-- Phone + Clinic Name -->
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
          <label class="auth-label">Clinic name</label>
          <input type="text"
                 name="clinic_name"
                 class="auth-input"
                 value="<?= htmlspecialchars($_POST['clinic_name'] ?? '') ?>"
                 required/>
        </div>
      </div>

      <!-- Clinic Address -->
      <div class="mb-3">
        <label class="auth-label">Clinic address</label>
        <input type="text"
               name="clinic_address"
               class="auth-input"
               value="<?= htmlspecialchars($_POST['clinic_address'] ?? '') ?>"
               required/>
      </div>

      <!-- Password Row -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">Password</label>
          <input type="password"
                 name="password"
                 class="auth-input"
                 required/>
        </div>
        <div class="col-6">
          <label class="auth-label">Confirm password</label>
          <input type="password"
                 name="confirm_password"
                 class="auth-input"
                 required/>
        </div>
      </div>

      <!-- File Uploads -->
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="auth-label">
            Veterinary license / certificate
          </label>
          <div class="upload-box">
            <input type="file"
                   name="license_doc"
                   id="license_doc"
                   class="upload-input"
                   accept=".pdf,.jpg,.jpeg,.png"
                   required/>
            <label for="license_doc" class="upload-label">
              <i class="bi bi-cloud-upload fs-4 mb-1"></i>
              <span>Click to upload or drag file here</span>
              <small>PDF, JPG, PNG — max 5MB</small>
            </label>
          </div>
          <div class="upload-filename" id="license_name"></div>
        </div>
        <div class="col-6">
          <label class="auth-label">
            Citizenship / national ID
          </label>
          <div class="upload-box">
            <input type="file"
                   name="citizenship_doc"
                   id="citizenship_doc"
                   class="upload-input"
                   accept=".pdf,.jpg,.jpeg,.png"
                   required/>
            <label for="citizenship_doc" class="upload-label">
              <i class="bi bi-cloud-upload fs-4 mb-1"></i>
              <span>Click to upload or drag file here</span>
              <small>PDF, JPG, PNG — max 5MB</small>
            </label>
          </div>
          <div class="upload-filename" id="citizenship_name"></div>
        </div>
      </div>

      <!-- Confirmation Checkbox -->
      <div class="mb-4">
        <label class="checkbox-label">
          <input type="checkbox"
                 name="confirm_docs"
                 required/>
          <span>
            I confirm all submitted documents are genuine and valid
          </span>
        </label>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="auth-btn">
        Submit registration for approval
      </button>

    </form>
    <?php endif; ?>

    <!-- Sign in link -->
    <div class="text-center mt-4">
      <a href="../login.php" class="auth-link" style="font-size:0.9rem;">
        Already approved? Sign in here
      </a>
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show filename after file selected
document.getElementById('license_doc')
    .addEventListener('change', function() {
    document.getElementById('license_name').textContent =
        this.files[0] ? '📄 ' + this.files[0].name : '';
});

document.getElementById('citizenship_doc')
    .addEventListener('change', function() {
    document.getElementById('citizenship_name').textContent =
        this.files[0] ? '📄 ' + this.files[0].name : '';
});
</script>

</body>
</html>