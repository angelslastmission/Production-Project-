<?php
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_helper.php';

$active_page = 'dashboard';

$success_message = '';
$error_message = '';
$vet_id = (int)($_SESSION['user_id'] ?? 0);

if ($vet_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$stats = ['pets' => 0];

$today = date('Y-m-d');

$vaccination_days = (int)($_GET['vacc_days'] ?? 7);
if (!in_array($vaccination_days, [3, 7], true)) {
    $vaccination_days = 7;
}

$vaccination_end_date = date('Y-m-d', strtotime("+{$vaccination_days} days"));
$vaccination_start_label = date('d M Y', strtotime($today));
$vaccination_end_label = date('d M Y', strtotime($vaccination_end_date));

$pets_count_stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM pets WHERE vet_id = ?');
if ($pets_count_stmt) {
    mysqli_stmt_bind_param($pets_count_stmt, 'i', $vet_id);
    mysqli_stmt_execute($pets_count_stmt);
    $pets_count_result = mysqli_stmt_get_result($pets_count_stmt);
    $pets_count_row = $pets_count_result ? mysqli_fetch_assoc($pets_count_result) : null;
    $stats['pets'] = (int)($pets_count_row['c'] ?? 0);
    mysqli_stmt_close($pets_count_stmt);
}

$due_this_week = 0;
$due_this_week_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_due
     FROM (
         SELECT next_due_date FROM vaccinations
         WHERE vet_id = ? AND reminder_status = 'active' AND next_due_date IS NOT NULL
         AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         UNION ALL
         SELECT next_due_date FROM dewormings
         WHERE vet_id = ? AND reminder_status = 'active' AND next_due_date IS NOT NULL
         AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ) combined"
);
if ($due_this_week_stmt) {
    mysqli_stmt_bind_param($due_this_week_stmt, 'ii', $vet_id, $vet_id);
    mysqli_stmt_execute($due_this_week_stmt);
    $due_this_week_result = mysqli_stmt_get_result($due_this_week_stmt);
    $due_this_week_row = $due_this_week_result ? mysqli_fetch_assoc($due_this_week_result) : null;
    $due_this_week = (int)($due_this_week_row['total_due'] ?? 0);
    mysqli_stmt_close($due_this_week_stmt);
}

$overdue_count = 0;
$overdue_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_overdue
     FROM (
         SELECT next_due_date FROM vaccinations
         WHERE vet_id = ? AND reminder_status = 'active' AND next_due_date IS NOT NULL AND next_due_date < CURDATE()
         UNION ALL
         SELECT next_due_date FROM dewormings
         WHERE vet_id = ? AND reminder_status = 'active' AND next_due_date IS NOT NULL AND next_due_date < CURDATE()
     ) combined"
);
if ($overdue_stmt) {
    mysqli_stmt_bind_param($overdue_stmt, 'ii', $vet_id, $vet_id);
    mysqli_stmt_execute($overdue_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_stmt);
    $overdue_row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null;
    $overdue_count = (int)($overdue_row['total_overdue'] ?? 0);
    mysqli_stmt_close($overdue_stmt);
}

$followups_pending = 0;
$followup_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_followups
     FROM treatments
     WHERE vet_id = ?
     AND followup_status = 'active'
     AND followup_date IS NOT NULL
     AND followup_date >= CURDATE()"
);
if ($followup_stmt) {
    mysqli_stmt_bind_param($followup_stmt, 'i', $vet_id);
    mysqli_stmt_execute($followup_stmt);
    $followup_result = mysqli_stmt_get_result($followup_stmt);
    $followup_row = $followup_result ? mysqli_fetch_assoc($followup_result) : null;
    $followups_pending = (int)($followup_row['total_followups'] ?? 0);
    mysqli_stmt_close($followup_stmt);
}

$reminders_panel = [];
$reminders_panel_stmt = mysqli_prepare(
    $conn,
    "SELECT
        combined.pet_name,
        combined.reminder_type,
        combined.detail_text,
        combined.due_date,
        combined.owner_name,
        combined.owner_email,
        combined.owner_phone,
        combined.type_order
     FROM (
         SELECT
            p.name AS pet_name,
            'Vaccination' AS reminder_type,
            v.vaccine_name AS detail_text,
            v.next_due_date AS due_date,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone,
            1 AS type_order
         FROM vaccinations v
         JOIN pets p ON p.id = v.pet_id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE v.vet_id = ?
         AND v.reminder_status = 'active'
         AND v.next_due_date IS NOT NULL
         AND v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)

         UNION ALL

         SELECT
            p.name AS pet_name,
            'Deworming' AS reminder_type,
            d.product_name AS detail_text,
            d.next_due_date AS due_date,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone,
            2 AS type_order
         FROM dewormings d
         JOIN pets p ON p.id = d.pet_id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE d.vet_id = ?
         AND d.reminder_status = 'active'
         AND d.next_due_date IS NOT NULL
         AND d.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)

         UNION ALL

         SELECT
            p.name AS pet_name,
            'Follow-up' AS reminder_type,
            COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Post-treatment check') AS detail_text,
            t.followup_date AS due_date,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone,
            3 AS type_order
         FROM treatments t
         JOIN pets p ON p.id = t.pet_id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE t.vet_id = ?
         AND t.followup_status = 'active'
         AND t.followup_date IS NOT NULL
         AND t.followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
     ) combined
     ORDER BY combined.due_date ASC, combined.type_order ASC, combined.pet_name ASC
     LIMIT 50"
);
if ($reminders_panel_stmt) {
    mysqli_stmt_bind_param(
        $reminders_panel_stmt,
        'iiiiii',
        $vet_id,
        $vaccination_days,
        $vet_id,
        $vaccination_days,
        $vet_id,
        $vaccination_days
    );
    mysqli_stmt_execute($reminders_panel_stmt);
    $reminders_panel_result = mysqli_stmt_get_result($reminders_panel_stmt);
    while ($reminders_panel_result && $row = mysqli_fetch_assoc($reminders_panel_result)) {
        $reminders_panel[] = $row;
    }
    mysqli_stmt_close($reminders_panel_stmt);
}

$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vet Dashboard - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <div class="vet-search-row">
            <input type="text" class="vet-search" placeholder="Search patients or records..."/>
        </div>

        <section class="vet-greeting">
            <h1><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Vet') ?></h1>
            <p><?= htmlspecialchars(date('l, j F Y')) ?> | <?= htmlspecialchars($_SESSION['clinic_name'] ?? 'Animal Care Clinic') ?></p>
        </section>

        <?php if ($success_message !== ''): ?>
            <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($overdue_count > 0): ?>
        <section class="vet-alert">
            <div class="vet-alert-text">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?= (int)$overdue_count ?> vaccination/deworming<?= $overdue_count > 1 ? 's are' : ' is' ?> overdue - send reminders to owners now.</span>
            </div>
            <div>
                <a href="reminders.php?status=overdue" class="vet-alert-btn text-decoration-none">VIEW REMINDERS</a>
            </div>
        </section>
        <?php endif; ?>

        <section class="vet-stats">
            <a href="patients.php" class="vet-stat-card vet-stat-link">
                <div class="vet-stat-icon"><i class="bi bi-person-lines-fill"></i></div>
                <div class="vet-stat-number"><?= (int)$stats['pets'] ?></div>
                <div class="vet-stat-label">Total patients</div>
            </a>

            <a href="reminders.php?status=due_soon" class="vet-stat-card vet-stat-link">
                <div class="vet-stat-icon"><i class="bi bi-shield-check"></i></div>
                <div class="vet-stat-number"><?= (int)$due_this_week ?></div>
                <div class="vet-stat-label">Vaccination / deworming due this week</div>
            </a>

            <a href="reminders.php?status=overdue" class="vet-stat-card vet-stat-link">
                <div class="vet-stat-icon"><i class="bi bi-shield-exclamation"></i></div>
                <div class="vet-stat-number"><?= (int)$overdue_count ?></div>
                <div class="vet-stat-label">Vaccination / deworming overdue</div>
            </a>

            <a href="treatments.php?view=followups" class="vet-stat-card vet-stat-link">
                <div class="vet-stat-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                <div class="vet-stat-number"><?= (int)$followups_pending ?></div>
                <div class="vet-stat-label">Follow-ups pending</div>
            </a>
        </section>

        <section class="vet-data-grid vet-dashboard-panels">
            <article class="vet-panel vet-dashboard-wide-panel" id="reminder-panel">
                <div class="vet-panel-header">
                    <div>
                        <h3 class="vet-panel-title mb-1">
                            Upcoming reminders - next <?= (int)$vaccination_days ?> days
                        </h3>
                        <small class="text-muted">
                            Vaccinations, deworming and treatment follow-ups ·
                            <?= htmlspecialchars($vaccination_start_label) ?> to <?= htmlspecialchars($vaccination_end_label) ?>
                        </small>
                    </div>

                    <form method="GET" action="dashboard.php#reminder-panel" class="m-0">
                        <select name="vacc_days" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="7" <?= $vaccination_days === 7 ? 'selected' : '' ?>>Next 7 days</option>
                            <option value="3" <?= $vaccination_days === 3 ? 'selected' : '' ?>>Next 3 days</option>
                        </select>
                    </form>
                </div>

                <div class="table-responsive vet-dashboard-table-wrap">
                    <table class="vet-panel-table vet-dashboard-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Reminder type</th>
                                <th>Details</th>
                                <th>Due / follow-up date</th>
                                <th>Owner</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reminders_panel) > 0): ?>
                                <?php foreach ($reminders_panel as $row): ?>
                                    <?php
                                    $due_date = (string)$row['due_date'];
                                    $due_status = petcura_due_status($due_date);
                                    $status_class = $due_status['class'];
                                    $status_text = $due_status['label'];
                                    $owner_name  = trim((string)($row['owner_name'] ?? ''));
                                    $owner_email = trim((string)($row['owner_email'] ?? ''));
                                    $owner_phone = trim((string)($row['owner_phone'] ?? ''));
                                    $reminder_type = (string)($row['reminder_type'] ?? 'Reminder');

                                    if ($reminder_type === 'Deworming') {
                                        $type_icon = 'bi-capsule';
                                        $type_style = 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;';
                                    } elseif ($reminder_type === 'Follow-up') {
                                        $type_icon = 'bi-arrow-counterclockwise';
                                        $type_style = 'background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;';
                                    } else {
                                        $type_icon = 'bi-shield-check';
                                        $type_style = 'background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="vet-patient-cell">
                                                <span class="vet-avatar"><?= htmlspecialchars(strtoupper(substr((string)$row['pet_name'], 0, 1))) ?></span>
                                                <span><?= htmlspecialchars((string)$row['pet_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size:0.75rem;font-weight:600;padding:4px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;<?= $type_style ?>">
                                                <i class="bi <?= htmlspecialchars($type_icon) ?>" style="font-size:0.78rem;"></i>
                                                <?= htmlspecialchars($reminder_type) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars((string)$row['detail_text']) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($due_date))) ?></td>
                                        <td>
                                            <?php if ($owner_name !== ''): ?>
                                                <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($owner_name) ?></div>
                                            <?php endif; ?>
                                            <?php if ($owner_phone !== ''): ?>
                                                <div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-telephone-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($owner_phone) ?></div>
                                            <?php endif; ?>
                                            <?php if ($owner_email !== ''): ?>
                                                <div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-envelope-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($owner_email) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="vet-pill <?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars($status_text) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-muted">
                                        No vaccination, deworming or follow-up reminders found for next <?= (int)$vaccination_days ?> days.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
