<?php
// ═══════════════════════════════════════════
// BACKEND — Reminder Logs
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'reminder_logs';

$logs = mysqli_query($conn,
    "SELECT r.reminder_type, r.channel, r.status, r.sent_at, r.created_at,
            p.name AS pet_name,
            CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
            CONCAT(v.first_name, ' ', v.last_name) AS vet_name
     FROM reminders r
     LEFT JOIN pets p ON r.pet_id = p.id
     LEFT JOIN users o ON r.owner_id = o.id
     LEFT JOIN users v ON r.vet_id = v.id
     ORDER BY COALESCE(r.sent_at, r.created_at) DESC
     LIMIT 100");

function reminder_label($type) {
    if ($type === 'vaccination') return 'Vaccination';
    if ($type === 'deworming') return 'Deworming';
    if ($type === 'followup') return 'Follow-up';
    return ucfirst((string)$type);
}

function channel_label($channel) {
    if ($channel === 'both') return 'SMS + Email';
    if ($channel === 'sms') return 'SMS';
    if ($channel === 'email') return 'Email';
    return strtoupper((string)$channel);
}

function format_log_time($sent_at, $created_at) {
    $time = $sent_at ?: $created_at;
    if (!$time) return '-';
    return date('M j, g:ia', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reminder Logs — PetCare HMS</title>

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
                <h1 class="admin-page-title">Reminder logs</h1>
                <p class="admin-page-sub">Every SMS and email the system has sent — monitor failures here</p>
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
                            <th>PET</th>
                            <th>OWNER</th>
                            <th>VET</th>
                            <th>TYPE</th>
                            <th>CHANNEL</th>
                            <th>RESULT</th>
                            <th>SENT AT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs && mysqli_num_rows($logs) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['pet_name'] ?: 'Unknown pet') ?></strong></td>
                                <td><?= htmlspecialchars($row['owner_name'] ?: 'Unknown owner') ?></td>
                                <td><?= htmlspecialchars($row['vet_name'] ? 'Dr. ' . $row['vet_name'] : 'Not assigned') ?></td>
                                <td><?= htmlspecialchars(reminder_label($row['reminder_type'])) ?></td>
                                <td><?= htmlspecialchars(channel_label($row['channel'])) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'sent'): ?>
                                        <span class="badge-sent">Sent</span>
                                    <?php elseif ($row['status'] === 'failed'): ?>
                                        <span class="badge-failed">Failed</span>
                                    <?php else: ?>
                                        <span class="badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(format_log_time($row['sent_at'], $row['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No reminder logs found</td>
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
