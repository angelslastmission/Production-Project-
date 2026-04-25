<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

$gmailUsername = trim((string)($mail_gmail_username ?? ''));
$gmailAppPassword = trim((string)($mail_gmail_app_password ?? ''));

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

function build_owner_reset_url(int $requestId, string $token): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/owner/forgot_password.php'))), '/');
  if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
  }

  return $scheme . '://' . $host . $basePath . '/owner/reset_password.php?id=' . urlencode((string)$requestId) . '&token=' . urlencode($token);
}

function send_owner_reset_email(string $toEmail, string $toName, string $resetUrl, string $gmailUsername, string $gmailAppPassword): bool {
  if ($gmailUsername === '' || $gmailAppPassword === '') {
    throw new RuntimeException('Please configure your Gmail username and app password in config.php.');
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
  $mail->addAddress($toEmail, $toName ?: 'Owner');
  $mail->isHTML(true);
  $mail->Subject = 'PetCura - Reset your password';
  $mail->Body = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
    <h2 style="margin-bottom:12px;">Reset your password</h2>
    <p>We received a request to reset your PetCura password.</p>
    <p style="margin:24px 0;">
      <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#0d9488;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block;">Reset Password</a>
    </p>
    <p style="font-size:14px;color:#475569;">If the button does not work, copy and open this link:</p>
    <p style="word-break:break-all;font-size:14px;color:#0f766e;"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0f766e;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>
    <p>If you did not request this, you can ignore this email.</p>
    <p>This link will expire in 1 hour.</p>
  </div>';
  $mail->AltBody = 'Reset your password: ' . $resetUrl;

  return $mail->send();
}

if (!ensure_owner_password_resets_table($conn)) {
  $error = 'Unable to prepare password reset storage.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

  if ($error === '' && $email === '') {
        $error = 'Email is required.';
  } elseif ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
  } elseif ($error === '') {
    $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email FROM users WHERE email = ? AND role = 'owner' AND is_active = 1 LIMIT 1");

    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $email);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      $owner = $result ? mysqli_fetch_assoc($result) : null;
      mysqli_stmt_close($stmt);

      if ($owner) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $insertStmt = mysqli_prepare($conn, "INSERT INTO owner_password_resets (owner_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
        if ($insertStmt) {
          mysqli_stmt_bind_param($insertStmt, 'isss', $owner['id'], $owner['email'], $tokenHash, $expiresAt);
          if (mysqli_stmt_execute($insertStmt)) {
            $requestId = (int)mysqli_insert_id($conn);
            $resetUrl = build_owner_reset_url($requestId, $token);

            try {
              send_owner_reset_email((string)$owner['email'], trim((string)$owner['first_name'] . ' ' . (string)$owner['last_name']), $resetUrl, $gmailUsername, $gmailAppPassword);
              $success = 'If an account exists for this email, password reset instructions have been sent.';
            } catch (Exception $e) {
              $error = 'Mailer error: ' . $e->getMessage();
            } catch (RuntimeException $e) {
              $error = $e->getMessage();
            }
          } else {
            $error = 'Could not create password reset request.';
          }
          mysqli_stmt_close($insertStmt);
        } else {
          $error = 'Could not prepare password reset request.';
        }
      } else {
        $success = 'If an account exists for this email, password reset instructions have been sent.';
      }
    } else {
      $error = 'Could not verify account.';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/auth.css" rel="stylesheet"/>
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="text-center mb-4">
      <div class="auth-logo mb-2">🐾 Pet<span>Cura</span></div>
      <p class="auth-subtitle">Reset your password</p>
    </div>

    <?php if ($success !== ''): ?>
    <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php">
      <div class="mb-3">
        <label class="auth-label">Email address</label>
        <input type="email"
               name="email"
               class="auth-input"
               placeholder="you@example.com"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required/>
      </div>

      <button type="submit" class="auth-btn">Send reset instructions</button>
    </form>

    <div class="text-center mt-3">
      <a href="../login.php" class="auth-link">Back to Sign in</a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
