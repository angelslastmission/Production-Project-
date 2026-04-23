<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'profile';
$owner_id = (int)($_SESSION['user_id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($first_name === '' || $last_name === '') {
        $error = 'First name and last name are required.';
    } else {
        $update_stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ? AND role = 'owner' LIMIT 1"
        );

        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, 'ssssi', $first_name, $last_name, $phone, $address, $owner_id);
            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
                $success = 'Profile updated successfully.';
            } else {
                $error = 'Unable to update profile. Please try again.';
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error = 'Unable to prepare update statement.';
        }
    }
}

$owner_profile = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'created_at' => null,
];

$profile_stmt = mysqli_prepare(
    $conn,
    "SELECT first_name, last_name, email, phone, address, created_at
     FROM users
     WHERE id = ? AND role = 'owner'
     LIMIT 1"
);

if ($profile_stmt) {
    mysqli_stmt_bind_param($profile_stmt, 'i', $owner_id);
    mysqli_stmt_execute($profile_stmt);
    $result = mysqli_stmt_get_result($profile_stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row) {
        $owner_profile = $row;
    }
    mysqli_stmt_close($profile_stmt);
}

$pets_count = 0;
$pets_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pets WHERE owner_id = ?");
if ($pets_stmt) {
    mysqli_stmt_bind_param($pets_stmt, 'i', $owner_id);
    mysqli_stmt_execute($pets_stmt);
    $pets_result = mysqli_stmt_get_result($pets_stmt);
    $pets_row = $pets_result ? mysqli_fetch_assoc($pets_result) : null;
    $pets_count = (int)($pets_row['total'] ?? 0);
    mysqli_stmt_close($pets_stmt);
}

$owner_name = trim(($owner_profile['first_name'] ?? '') . ' ' . ($owner_profile['last_name'] ?? ''));
if ($owner_name === '') {
    $owner_name = $_SESSION['user_name'] ?? 'Owner';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <section class="owner-greeting" style="margin-bottom: 20px;">
            <h1>My Profile</h1>
            <p>Manage your owner account information.</p>
        </section>

        <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="owner-panel" style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
                <div style="background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 10px; padding: 16px;">
                    <div style="font-size: 0.8rem; color: #0f766e; font-weight: 600; text-transform: uppercase;">Owner Name</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #134e4a;"><?= htmlspecialchars($owner_name) ?></div>
                </div>
                <div style="background: #ecfeff; border: 1px solid #cffafe; border-radius: 10px; padding: 16px;">
                    <div style="font-size: 0.8rem; color: #155e75; font-weight: 600; text-transform: uppercase;">Registered Pets</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #164e63;"><?= $pets_count ?></div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
                    <div style="font-size: 0.8rem; color: #334155; font-weight: 600; text-transform: uppercase;">Member Since</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #1e293b;">
                        <?= !empty($owner_profile['created_at']) ? htmlspecialchars(date('M d, Y', strtotime((string)$owner_profile['created_at']))) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="owner-panel">
            <div class="owner-panel-header" style="margin-bottom: 12px;">
                <h3 class="owner-panel-title">Profile Details</h3>
            </div>

            <form method="POST" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">First Name</label>
                        <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars((string)$owner_profile['first_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars((string)$owner_profile['last_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">Email</label>
                        <input type="email" class="form-control" readonly value="<?= htmlspecialchars((string)$owner_profile['email']) ?>">
                        <small style="color: #6b7280;">Email is used for login and cannot be changed here.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 600;">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)$owner_profile['phone']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" style="font-weight: 600;">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars((string)$owner_profile['address']) ?>">
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="dashboard.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn" style="background: #0d9488; color: #fff;">Save Changes</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
