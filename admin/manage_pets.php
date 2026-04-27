<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'manage_pets';

// Fetch all pets with owner and vet info
$pets = mysqli_query($conn,
    "SELECT p.id, p.name, p.species, p.breed, p.gender, p.status, p.created_at,
            CONCAT(COALESCE(o.first_name,''), ' ', COALESCE(o.last_name,'')) AS owner_name,
            o.email AS owner_email, o.phone AS owner_phone,
            CONCAT(COALESCE(v.first_name,''), ' ', COALESCE(v.last_name,'')) AS vet_name,
            v.clinic_name
     FROM pets p
     LEFT JOIN users o ON o.id = p.owner_id
     LEFT JOIN users v ON v.id = p.vet_id
     ORDER BY p.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Pets — PetCura Admin</title>
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
            <h1 class="admin-page-title">Manage Pets</h1>
            <p class="admin-page-sub">All registered pets in the system</p>
        </div>

        <div class="admin-card mt-3">
            <div class="admin-card-header">
                <h5 class="admin-card-title">All Pets</h5>
            </div>
            <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Species / Breed</th>
                            <th>Status</th>
                            <th>Owner</th>
                            <th>Vet / Clinic</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pets && mysqli_num_rows($pets) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($pets)): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                                    <div style="font-size:0.78rem;color:#9ca3af;"><?= htmlspecialchars(ucfirst($p['gender'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['species'] ?? '—') ?>
                                    <?php if (!empty($p['breed'])): ?>
                                    <div style="font-size:0.78rem;color:#9ca3af;"><?= htmlspecialchars($p['breed']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $sc = $p['status'] === 'healthy' ? 'badge-sent' : 'badge-pending'; ?>
                                    <span class="<?= $sc ?>"><?= htmlspecialchars(ucfirst($p['status'] ?? 'N/A')) ?></span>
                                </td>
                                <td>
                                    <?php $o = trim($p['owner_name'] ?? ''); ?>
                                    <div style="font-weight:600;font-size:0.85rem;"><?= $o !== '' ? htmlspecialchars($o) : '—' ?></div>
                                    <?php if (!empty($p['owner_phone'])): ?>
                                    <div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-telephone-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($p['owner_phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($p['owner_email'])): ?>
                                    <div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-envelope-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($p['owner_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $v = trim($p['vet_name'] ?? ''); ?>
                                    <div style="font-weight:600;font-size:0.85rem;"><?= $v !== '' ? 'Dr. ' . htmlspecialchars($v) : '—' ?></div>
                                    <?php if (!empty($p['clinic_name'])): ?>
                                    <div style="font-size:0.78rem;color:#9ca3af;"><?= htmlspecialchars($p['clinic_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.82rem;color:#9ca3af;"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No pets registered yet</td></tr>
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