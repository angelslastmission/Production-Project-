<?php
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_monitor_helper.php';

$active_page = 'dashboard';

$total_vets = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users WHERE role = 'vet' AND status = 'approved' AND is_active = 1"))['count'];

$total_owners = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users WHERE role = 'owner'"))['count'];

$total_pets = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM pets"))['count'];

$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users WHERE role = 'vet' AND status = 'pending'"))['count'];

$pending_vets = mysqli_query($conn,
    "SELECT id, first_name, last_name, email, clinic_name, phone, created_at,
            COALESCE(vet_registration_attempts, 0) AS vet_registration_attempts
     FROM users
     WHERE role = 'vet' AND status = 'pending'
     ORDER BY created_at DESC");

$dashboard_reminders = admin_get_reminder_monitor_items($conn, 7, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — PetCare HMS</title>

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
            <h1 class="admin-page-title">Admin Dashboard</h1>
            <p class="admin-page-sub">System overview — PetCura HMS</p>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <?php if ($pending_count > 0): ?>
        <div class="admin-alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= $pending_count ?> new vet registration<?= $pending_count > 1 ? 's are' : ' is' ?> pending approval —
            <a href="vet_registrations.php?status=pending">review documents now</a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <a class="stat-card stat-link-card" href="manage_vets.php" aria-label="Open manage vets">
                <div class="stat-number"><?= $total_vets ?></div>
                <div class="stat-label">Total vets</div>
                <div class="stat-sub text-success">Active</div>
            </a>

            <a class="stat-card stat-link-card" href="manage_owners.php" aria-label="Open manage pet owners">
                <div class="stat-number"><?= $total_owners ?></div>
                <div class="stat-label">Pet owners</div>
                <div class="stat-sub text-success">Registered</div>
            </a>

            <a class="stat-card stat-link-card" href="manage_pets.php" aria-label="Open manage pets">
                <div class="stat-number"><?= $total_pets ?></div>
                <div class="stat-label">Total pets</div>
                <div class="stat-sub text-muted">Registered</div>
            </a>

            <a class="stat-card stat-card-warning stat-link-card" href="vet_registrations.php?status=pending" aria-label="Open pending vet approvals">
                <div class="stat-number text-warning"><?= $pending_count ?></div>
                <div class="stat-label">Pending approvals</div>
                <div class="stat-sub text-warning">Review now</div>
            </a>
        </div>

        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h5 class="admin-card-title">Pending vet approvals</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>CLINIC</th>
                            <th>SUBMITTED</th>
                            <th>ATTEMPTS</th>
                            <th>DOCS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($pending_vets) > 0): ?>
                            <?php while ($vet = mysqli_fetch_assoc($pending_vets)): ?>
                            <tr>
                                <td><strong>Dr. <?= htmlspecialchars($vet['first_name']) ?> <?= htmlspecialchars($vet['last_name']) ?></strong></td>
                                <td><?= htmlspecialchars($vet['clinic_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($vet['created_at'])) ?></td>
                                <td><span class="badge-docs"><?= (int)$vet['vet_registration_attempts'] ?>/3</span></td>
                                <td><span class="badge-docs">Docs uploaded</span></td>
                                <td><a href="vet_registrations.php?id=<?= $vet['id'] ?>" class="btn-review">Review & Approve</a></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No pending approvals 🎉</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card mt-4">
            <div class="admin-card-header d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="admin-card-title mb-1">Upcoming reminders - next 7 days</h5>
                    <p class="admin-page-sub mb-0">Read-only monitor for vaccination, deworming and treatment follow-ups</p>
                </div>
                <a href="reminder_logs.php" class="btn-review">View all</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PET</th>
                            <th>OWNER</th>
                            <th>VET</th>
                            <th>TYPE</th>
                            <th>DETAIL</th>
                            <th>DUE DATE</th>
                            <th>DUE STATUS</th>
                            <th>REMINDER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($dashboard_reminders) > 0): ?>
                            <?php foreach ($dashboard_reminders as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['pet_name'] ?: 'Unknown pet') ?></strong></td>
                                <td><?= htmlspecialchars($item['owner_name']) ?></td>
                                <td><?= htmlspecialchars($item['vet_name'] !== 'Not assigned' ? 'Dr. ' . $item['vet_name'] : 'Not assigned') ?></td>
                                <td><?= htmlspecialchars(admin_reminder_type_label($item['record_type'])) ?></td>
                                <td><?= htmlspecialchars($item['title'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(admin_format_date($item['due_date'])) ?></td>
                                <td><span class="<?= htmlspecialchars($item['due_status_class']) ?>"><?= htmlspecialchars($item['due_status_label']) ?></span></td>
                                <td><?= admin_reminder_status_badge($item['reminder_status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No reminders due in the next 7 days</td>
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
