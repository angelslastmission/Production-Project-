<?php
// ═══════════════════════════════════════════
// BACKEND — Manage Owners
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'manage_owners';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $owner_id = (int)($_POST['owner_id'] ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 0);

    if ($owner_id <= 0) {
        $error = 'Invalid owner selected.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE users
             SET is_active = ?
             WHERE id = ? AND role = 'owner'");
        mysqli_stmt_bind_param($stmt, 'ii', $new_status, $owner_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = $new_status === 1
                ? 'Owner activated successfully.'
                : 'Owner deactivated successfully.';
        } else {
            $error = 'Failed to update owner status.';
        }
    }
}

$owners = mysqli_query($conn,
    "SELECT o.id, o.first_name, o.last_name, o.email, o.phone, o.address, o.is_active, o.created_at,
            (
                SELECT CONCAT(v1.first_name, ' ', v1.last_name)
                FROM pets p1
                JOIN users v1 ON p1.vet_id = v1.id AND v1.role = 'vet'
                WHERE p1.owner_id = o.id
                ORDER BY p1.created_at DESC, p1.id DESC
                LIMIT 1
            ) AS assigned_vet_name,
            (SELECT COUNT(*) FROM pets p WHERE p.owner_id = o.id) AS pet_count
     FROM users o
     WHERE o.role = 'owner'
     ORDER BY o.created_at DESC");

$owner_rows = [];
if ($owners) {
    while ($row = mysqli_fetch_assoc($owners)) {
        $owner_rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Owners — PetCare HMS</title>

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
                <h1 class="admin-page-title">Manage owners</h1>
                <p class="admin-page-sub">All registered pet owners</p>
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
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>ASSIGNED VET</th>
                            <th>PETS</th>
                            <th>JOINED</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($owner_rows)): ?>
                            <?php foreach ($owner_rows as $owner): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($owner['first_name']) ?>
                                        <?= htmlspecialchars($owner['last_name']) ?>
                                    </strong>
                                </td>
                                <td><?= htmlspecialchars($owner['email']) ?></td>
                                <td><?= htmlspecialchars($owner['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if (!empty($owner['assigned_vet_name'])): ?>
                                        Dr. <?= htmlspecialchars($owner['assigned_vet_name']) ?>
                                    <?php else: ?>
                                        Not assigned
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$owner['pet_count'] ?></td>
                                <td><?= date('M Y', strtotime($owner['created_at'])) ?></td>
                                <td>
                                    <?php if ((int)$owner['is_active'] === 1): ?>
                                        <span class="badge-sent">Active</span>
                                    <?php else: ?>
                                        <span class="badge-failed">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                            class="btn-review"
                                            data-bs-toggle="modal"
                                            data-bs-target="#ownerStatusModal<?= (int)$owner['id'] ?>">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No owners found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php foreach ($owner_rows as $owner): ?>
        <div class="modal fade" id="ownerStatusModal<?= (int)$owner['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit owner status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            <strong>
                                <?= htmlspecialchars($owner['first_name']) ?>
                                <?= htmlspecialchars($owner['last_name']) ?>
                            </strong>
                        </p>
                        <p class="text-muted mb-0">
                            Current status:
                            <?php if ((int)$owner['is_active'] === 1): ?>
                                <span class="badge-sent">Active</span>
                            <?php else: ?>
                                <span class="badge-failed">Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <?php if ((int)$owner['is_active'] === 1): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="owner_id" value="<?= (int)$owner['id'] ?>"/>
                            <input type="hidden" name="new_status" value="0"/>
                            <button type="submit" name="toggle_status" class="btn btn-danger">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="owner_id" value="<?= (int)$owner['id'] ?>"/>
                            <input type="hidden" name="new_status" value="1"/>
                            <button type="submit" name="toggle_status" class="btn btn-success">Activate</button>
                        </form>
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
