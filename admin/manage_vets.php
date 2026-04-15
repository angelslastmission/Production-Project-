<?php
// ═══════════════════════════════════════════
// BACKEND — Manage Vets
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'manage_vets';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $vet_id = (int)($_POST['vet_id'] ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 0);

    $stmt = mysqli_prepare($conn,
        "UPDATE users
         SET is_active = ?
         WHERE id = ? AND role = 'vet' AND status = 'approved'");
    mysqli_stmt_bind_param($stmt, 'ii', $new_status, $vet_id);

    if (mysqli_stmt_execute($stmt)) {
        $success = $new_status === 1
            ? 'Vet activated successfully.'
            : 'Vet deactivated successfully.';
    } else {
        $error = 'Failed to update vet status.';
    }
}

$vets = mysqli_query($conn,
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.clinic_name, u.is_active,
            (SELECT COUNT(*) FROM pets p WHERE p.vet_id = u.id) AS pet_count
     FROM users u
     WHERE u.role = 'vet' AND u.status = 'approved'
     ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Vets — PetCare HMS</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>

<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="admin-page-title">Manage vets</h1>
                <p class="admin-page-sub">All approved veterinarians in the system</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="admin-alert-success mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="admin-alert-error mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">All approved vets</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>CLINIC</th>
                            <th>PHONE</th>
                            <th>PETS</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($vets && mysqli_num_rows($vets) > 0): ?>
                        <?php while ($vet = mysqli_fetch_assoc($vets)): ?>
                        <tr>
                            <td><strong>Dr. <?= htmlspecialchars($vet['first_name']) ?> <?= htmlspecialchars($vet['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars($vet['email']) ?></td>
                            <td><?= htmlspecialchars($vet['clinic_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($vet['phone'] ?: '-') ?></td>
                            <td><?= (int)$vet['pet_count'] ?></td>
                            <td>
                                <?php if ((int)$vet['is_active'] === 1): ?>
                                    <span class="badge-sent">Active</span>
                                <?php else: ?>
                                    <span class="badge-failed">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button"
                                        class="btn-review"
                                        data-bs-toggle="modal"
                                        data-bs-target="#vetActionModal<?= (int)$vet['id'] ?>">
                                    Edit
                                </button>
                            </td>
                        </tr>

                        <!-- Action Modal -->
                        <div class="modal fade"
                             id="vetActionModal<?= (int)$vet['id'] ?>"
                             tabindex="-1"
                             aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            Edit vet status
                                        </h5>
                                        <button type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2">
                                            <strong>Dr. <?= htmlspecialchars($vet['first_name']) ?> <?= htmlspecialchars($vet['last_name']) ?></strong>
                                        </p>
                                        <p class="text-muted mb-0">
                                            Current status:
                                            <?php if ((int)$vet['is_active'] === 1): ?>
                                                <span class="badge-sent">Active</span>
                                            <?php else: ?>
                                                <span class="badge-failed">Inactive</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button"
                                                class="btn btn-light"
                                                data-bs-dismiss="modal">
                                            Cancel
                                        </button>

                                        <?php if ((int)$vet['is_active'] === 1): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="vet_id" value="<?= (int)$vet['id'] ?>"/>
                                            <input type="hidden" name="new_status" value="0"/>
                                            <button type="submit"
                                                    name="toggle_status"
                                                    class="btn btn-danger">
                                                Deactivate
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="vet_id" value="<?= (int)$vet['id'] ?>"/>
                                            <input type="hidden" name="new_status" value="1"/>
                                            <button type="submit"
                                                    name="toggle_status"
                                                    class="btn btn-success">
                                                Activate
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No approved vets found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
