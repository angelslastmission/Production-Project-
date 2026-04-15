<?php
// ═══════════════════════════════════════
// BACKEND — Admin Dashboard
// ═══════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'dashboard';

// ── Stat counts ──────────────────────
// Total approved vets
$total_vets = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users 
    WHERE role = 'vet' AND status = 'approved' AND is_active = 1"))['count'];

// Total pet owners
$total_owners = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users 
     WHERE role = 'owner'"))['count'];

// Total pets
$total_pets = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM pets"))['count'];

// Pending vet approvals
$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM users 
     WHERE role = 'vet' AND status = 'pending'"))['count'];

// ── Pending vet approvals list ────────
$pending_vets = mysqli_query($conn,
    "SELECT id, first_name, last_name, email, 
            clinic_name, phone, created_at
     FROM users 
     WHERE role = 'vet' AND status = 'pending'
     ORDER BY created_at DESC");

// ── Today's reminder log ──────────────
$today_reminders = mysqli_query($conn,
    "SELECT r.*, 
            p.name as pet_name,
            CONCAT(u.first_name,' ',u.last_name) as owner_name,
            r.reminder_type,
            r.channel,
            r.status
     FROM reminders r
     JOIN pets p ON r.pet_id = p.id
     JOIN users u ON r.owner_id = u.id
     WHERE DATE(r.created_at) = CURDATE()
     ORDER BY r.created_at DESC
     LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — PetCare HMS</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <!-- Admin CSS -->
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>

<div class="admin-wrapper">

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main">

        <!-- Top bar -->
        <div class="admin-topbar">
            <h1 class="admin-page-title">Admin Dashboard</h1>
            <p class="admin-page-sub">System overview — PetCare HMS</p>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <!-- Alert if pending approvals -->
        <?php if ($pending_count > 0): ?>
        <div class="admin-alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= $pending_count ?> new vet registration<?= $pending_count > 1 ? 's are' : ' is' ?> 
            pending approval — 
            <a href="vet_registrations.php">review documents now</a>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-number"><?= $total_vets ?></div>
                <div class="stat-label">Total vets</div>
                <div class="stat-sub text-success">Active</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $total_owners ?></div>
                <div class="stat-label">Pet owners</div>
                <div class="stat-sub text-success">Registered</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $total_pets ?></div>
                <div class="stat-label">Total pets</div>
                <div class="stat-sub text-muted">All clinics</div>
            </div>

            <div class="stat-card stat-card-warning">
                <div class="stat-number text-warning"><?= $pending_count ?></div>
                <div class="stat-label">Pending approvals</div>
                <div class="stat-sub text-warning">Needs action</div>
            </div>

        </div>

        <!-- Pending Vet Approvals Table -->
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
                            <th>DOCS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($pending_vets) > 0): ?>
                            <?php while ($vet = mysqli_fetch_assoc($pending_vets)): ?>
                            <tr>
                                <td>
                                    <strong>
                                        Dr. <?= htmlspecialchars($vet['first_name']) ?>
                                        <?= htmlspecialchars($vet['last_name']) ?>
                                    </strong>
                                </td>
                                <td><?= htmlspecialchars($vet['clinic_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($vet['created_at'])) ?></td>
                                <td>
                                    <span class="badge-docs">Docs uploaded</span>
                                </td>
                                <td>
                                    <a href="vet_registrations.php?id=<?= $vet['id'] ?>"
                                       class="btn-review">
                                        Review & Approve
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No pending approvals 🎉
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Today's Reminder Log -->
        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h5 class="admin-card-title">Today's reminder log</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PET</th>
                            <th>OWNER</th>
                            <th>TYPE</th>
                            <th>CHANNEL</th>
                            <th>RESULT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($today_reminders) > 0): ?>
                            <?php while ($log = mysqli_fetch_assoc($today_reminders)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($log['pet_name']) ?></strong></td>
                                <td><?= htmlspecialchars($log['owner_name']) ?></td>
                                <td><?= ucfirst($log['reminder_type']) ?></td>
                                <td><?= strtoupper($log['channel']) ?></td>
                                <td>
                                    <?php if ($log['status'] === 'sent'): ?>
                                        <span class="badge-sent">Sent</span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="badge-failed">Failed</span>
                                    <?php else: ?>
                                        <span class="badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No reminders sent today
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /admin-main -->

</div><!-- /admin-wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>