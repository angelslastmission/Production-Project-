<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'dashboard';
include 'includes/dashboard_backend.php';

$today = date('Y-m-d');

$due_this_week_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_due
     FROM (
        SELECT next_due_date AS due_date
        FROM vaccinations
        WHERE vet_id = ? AND next_due_date IS NOT NULL

        UNION ALL

        SELECT next_due_date AS due_date
        FROM dewormings
        WHERE vet_id = ? AND next_due_date IS NOT NULL
     ) x
     WHERE x.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);

$due_this_week = 0;
if ($due_this_week_stmt) {
    mysqli_stmt_bind_param($due_this_week_stmt, 'ii', $vet_id, $vet_id);
    mysqli_stmt_execute($due_this_week_stmt);
    $due_this_week_result = mysqli_stmt_get_result($due_this_week_stmt);
    $due_this_week_row = $due_this_week_result ? mysqli_fetch_assoc($due_this_week_result) : null;
    $due_this_week = (int)($due_this_week_row['total_due'] ?? 0);
    mysqli_stmt_close($due_this_week_stmt);
}

$overdue_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_overdue
     FROM (
        SELECT next_due_date AS due_date
        FROM vaccinations
        WHERE vet_id = ? AND next_due_date IS NOT NULL

        UNION ALL

        SELECT next_due_date AS due_date
        FROM dewormings
        WHERE vet_id = ? AND next_due_date IS NOT NULL
     ) x
     WHERE x.due_date < CURDATE()"
);

$overdue_count = 0;
if ($overdue_stmt) {
    mysqli_stmt_bind_param($overdue_stmt, 'ii', $vet_id, $vet_id);
    mysqli_stmt_execute($overdue_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_stmt);
    $overdue_row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null;
    $overdue_count = (int)($overdue_row['total_overdue'] ?? 0);
    mysqli_stmt_close($overdue_stmt);
}

$followup_stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total_followups
     FROM treatments
     WHERE vet_id = ? AND followup_date IS NOT NULL AND followup_date >= CURDATE()"
);

$followups_pending = 0;
if ($followup_stmt) {
    mysqli_stmt_bind_param($followup_stmt, 'i', $vet_id);
    mysqli_stmt_execute($followup_stmt);
    $followup_result = mysqli_stmt_get_result($followup_stmt);
    $followup_row = $followup_result ? mysqli_fetch_assoc($followup_result) : null;
    $followups_pending = (int)($followup_row['total_followups'] ?? 0);
    mysqli_stmt_close($followup_stmt);
}

$vaccinations_panel = [];
$vaccinations_panel_stmt = mysqli_prepare(
    $conn,
    "SELECT pet_name, treatment_name, due_date
     FROM (
        SELECT p.name AS pet_name, v.vaccine_name AS treatment_name, v.next_due_date AS due_date
        FROM vaccinations v
        JOIN pets p ON p.id = v.pet_id
        WHERE v.vet_id = ? AND v.next_due_date IS NOT NULL

        UNION ALL

        SELECT p.name AS pet_name, d.product_name AS treatment_name, d.next_due_date AS due_date
        FROM dewormings d
        JOIN pets p ON p.id = d.pet_id
        WHERE d.vet_id = ? AND d.next_due_date IS NOT NULL
     ) x
     ORDER BY due_date ASC
     LIMIT 6"
);

if ($vaccinations_panel_stmt) {
    mysqli_stmt_bind_param($vaccinations_panel_stmt, 'ii', $vet_id, $vet_id);
    mysqli_stmt_execute($vaccinations_panel_stmt);
    $vaccinations_panel_result = mysqli_stmt_get_result($vaccinations_panel_stmt);
    while ($vaccinations_panel_result && $row = mysqli_fetch_assoc($vaccinations_panel_result)) {
        $vaccinations_panel[] = $row;
    }
    mysqli_stmt_close($vaccinations_panel_stmt);
}

$followups_panel = [];
$followups_panel_stmt = mysqli_prepare(
    $conn,
    "SELECT p.name AS pet_name,
            COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Post-treatment check') AS case_name,
            t.followup_date
     FROM treatments t
     JOIN pets p ON p.id = t.pet_id
     WHERE t.vet_id = ? AND t.followup_date IS NOT NULL
     ORDER BY t.followup_date ASC
     LIMIT 6"
);

if ($followups_panel_stmt) {
    mysqli_stmt_bind_param($followups_panel_stmt, 'i', $vet_id);
    mysqli_stmt_execute($followups_panel_stmt);
    $followups_panel_result = mysqli_stmt_get_result($followups_panel_stmt);
    while ($followups_panel_result && $row = mysqli_fetch_assoc($followups_panel_result)) {
        $followups_panel[] = $row;
    }
    mysqli_stmt_close($followups_panel_stmt);
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
            <h1>Good morning, <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
            <p><?= htmlspecialchars(date('l, j F Y')) ?> | <?= htmlspecialchars($_SESSION['clinic_name'] ?? 'Animal Care Clinic') ?></p>
        </section>

        <?php if ($success_message !== ''): ?>
            <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <section class="vet-alert">
            <div class="vet-alert-text">
                <i class="bi bi-exclamation-triangle"></i>
                <span>! <?= (int)$overdue_count ?> vaccinations are overdue - send reminders to owners now.</span>
            </div>
            <div>
                <button type="button" class="vet-alert-btn">SEND ALL</button>
            </div>
        </section>

        <section class="vet-stats">
            <article class="vet-stat-card">
                <div class="vet-stat-icon"><i class="bi bi-person-lines-fill"></i></div>
                <div class="vet-stat-number"><?= (int)$stats['pets'] ?></div>
                <div class="vet-stat-label">Total patients</div>
            </article>
            <article class="vet-stat-card">
                <div class="vet-stat-icon"><i class="bi bi-calendar3"></i></div>
                <div class="vet-stat-number"><?= (int)$due_this_week ?></div>
                <div class="vet-stat-label">Due this week (Action needed)</div>
            </article>
            <article class="vet-stat-card">
                <div class="vet-stat-icon"><i class="bi bi-exclamation-lg"></i></div>
                <div class="vet-stat-number"><?= (int)$overdue_count ?></div>
                <div class="vet-stat-label">Overdue vaccines (Urgent)</div>
            </article>
            <article class="vet-stat-card">
                <div class="vet-stat-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
                <div class="vet-stat-number"><?= (int)$followups_pending ?></div>
                <div class="vet-stat-label">Follow-ups pending (Review)</div>
            </article>
        </section>

        <section class="vet-data-grid">
            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title">Upcoming vaccinations - next 7 days</h3>
                    <i class="bi bi-three-dots text-muted"></i>
                </div>
                <div class="table-responsive">
                    <table class="vet-panel-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Treatment</th>
                                <th>Scheduled</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vaccinations_panel) > 0): ?>
                                <?php foreach ($vaccinations_panel as $row): ?>
                                    <?php
                                    $due_date = (string)$row['due_date'];
                                    $status_class = 'vet-pill-upcoming';
                                    $status_text = 'UPCOMING';
                                    if ($due_date < $today) {
                                        $status_class = 'vet-pill-overdue';
                                        $status_text = 'OVERDUE';
                                    } elseif ($due_date <= date('Y-m-d', strtotime('+3 days'))) {
                                        $status_class = 'vet-pill-soon';
                                        $status_text = 'SOON';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="vet-patient-cell">
                                                <span class="vet-avatar"><?= htmlspecialchars(strtoupper(substr((string)$row['pet_name'], 0, 1))) ?></span>
                                                <span><?= htmlspecialchars((string)$row['pet_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$row['treatment_name']) ?></td>
                                        <td><?= htmlspecialchars(date('M j', strtotime($due_date))) ?></td>
                                        <td><span class="vet-pill <?= $status_class ?>"><?= $status_text ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-muted">No upcoming vaccinations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title">Follow-ups needed</h3>
                    <i class="bi bi-three-dots text-muted"></i>
                </div>
                <div class="table-responsive">
                    <table class="vet-panel-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Case</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($followups_panel) > 0): ?>
                                <?php foreach ($followups_panel as $row): ?>
                                    <?php
                                    $followup_date = (string)$row['followup_date'];
                                    $priority_class = 'vet-pill-scheduled';
                                    $priority_text = 'SCHEDULED';
                                    if ($followup_date < $today) {
                                        $priority_class = 'vet-pill-overdue';
                                        $priority_text = 'OVERDUE';
                                    } elseif ($followup_date <= date('Y-m-d', strtotime('+3 days'))) {
                                        $priority_class = 'vet-pill-soon';
                                        $priority_text = 'SOON';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="vet-patient-cell">
                                                <span class="vet-avatar"><?= htmlspecialchars(strtoupper(substr((string)$row['pet_name'], 0, 1))) ?></span>
                                                <span><?= htmlspecialchars((string)$row['pet_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$row['case_name']) ?></td>
                                        <td><span class="vet-pill <?= $priority_class ?>"><?= $priority_text ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-muted">No follow-ups found.</td>
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
