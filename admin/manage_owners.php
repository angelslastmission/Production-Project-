<?php
// ═══════════════════════════════════════════
// BACKEND — Manage Owners
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'manage_owners';

$owners = mysqli_query($conn,
    "SELECT o.id, o.first_name, o.last_name, o.email, o.phone, o.created_at,
            v.first_name AS vet_first_name, v.last_name AS vet_last_name,
            (SELECT COUNT(*) FROM pets p WHERE p.owner_id = o.id) AS pet_count
     FROM users o
     LEFT JOIN users v ON o.vet_id = v.id AND v.role = 'vet'
     WHERE o.role = 'owner'
     ORDER BY o.created_at DESC");
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
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($owners && mysqli_num_rows($owners) > 0): ?>
                            <?php while ($owner = mysqli_fetch_assoc($owners)): ?>
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
                                    <?php if (!empty($owner['vet_first_name'])): ?>
                                        Dr. <?= htmlspecialchars($owner['vet_first_name']) ?>
                                        <?= htmlspecialchars($owner['vet_last_name']) ?>
                                    <?php else: ?>
                                        Not assigned
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$owner['pet_count'] ?></td>
                                <td><?= date('M Y', strtotime($owner['created_at'])) ?></td>
                                <td>
                                    <button class="btn-review" type="button">View</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No owners found
                                </td>
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
