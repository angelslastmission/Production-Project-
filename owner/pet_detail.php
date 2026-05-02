<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'pet_records';
$owner_id = (int)($_SESSION['user_id'] ?? 0);
$pet_id = (int)($_GET['id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

if ($pet_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

$pet = null;
$pet_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.name, p.species, p.breed, p.gender, p.dob, p.weight, p.status,
            p.allergies, p.last_visit, p.created_at, p.vet_id,
            CONCAT(v.first_name, ' ', v.last_name) AS vet_name,
            v.clinic_name
     FROM pets p
     LEFT JOIN users v ON v.id = p.vet_id AND v.role = 'vet'
     WHERE p.id = ? AND p.owner_id = ?");
if ($pet_stmt) {
    mysqli_stmt_bind_param($pet_stmt, 'ii', $pet_id, $owner_id);
    mysqli_stmt_execute($pet_stmt);
    $pet_result = mysqli_stmt_get_result($pet_stmt);
    $pet = $pet_result ? mysqli_fetch_assoc($pet_result) : null;
    mysqli_stmt_close($pet_stmt);
}

if (!$pet) {
    header('Location: dashboard.php');
    exit();
}

$vaccinations = [];
$vacc_stmt = mysqli_prepare($conn,
    "SELECT vaccine_name, date_given, next_due_date, dose_number, batch_number, notes
     FROM vaccinations
     WHERE pet_id = ?
     ORDER BY date_given DESC");
if ($vacc_stmt) {
    mysqli_stmt_bind_param($vacc_stmt, 'i', $pet_id);
    mysqli_stmt_execute($vacc_stmt);
    $vacc_result = mysqli_stmt_get_result($vacc_stmt);
    while ($row = $vacc_result ? mysqli_fetch_assoc($vacc_result) : null) {
        $vaccinations[] = $row;
    }
    mysqli_stmt_close($vacc_stmt);
}

$dewormings = [];
$deworm_stmt = mysqli_prepare($conn,
    "SELECT product_name, date_given, next_due_date, dose, notes
     FROM dewormings
     WHERE pet_id = ?
     ORDER BY date_given DESC");
if ($deworm_stmt) {
    mysqli_stmt_bind_param($deworm_stmt, 'i', $pet_id);
    mysqli_stmt_execute($deworm_stmt);
    $deworm_result = mysqli_stmt_get_result($deworm_stmt);
    while ($row = $deworm_result ? mysqli_fetch_assoc($deworm_result) : null) {
        $dewormings[] = $row;
    }
    mysqli_stmt_close($deworm_stmt);
}

$treatments = [];
$treat_stmt = mysqli_prepare($conn,
    "SELECT diagnosis, treatment, treatment_date, followup_date, severity, notes
     FROM treatments
     WHERE pet_id = ?
     ORDER BY treatment_date DESC");
if ($treat_stmt) {
    mysqli_stmt_bind_param($treat_stmt, 'i', $pet_id);
    mysqli_stmt_execute($treat_stmt);
    $treat_result = mysqli_stmt_get_result($treat_stmt);
    while ($row = $treat_result ? mysqli_fetch_assoc($treat_result) : null) {
        $treatments[] = $row;
    }
    mysqli_stmt_close($treat_stmt);
}

$last_visit_candidates = [];

$raw_last_visit = (string)($pet['last_visit'] ?? '');
if ($raw_last_visit !== '' && $raw_last_visit !== '0000-00-00' && $raw_last_visit !== '1970-01-01') {
    $last_visit_ts = strtotime($raw_last_visit);
    if ($last_visit_ts !== false && $last_visit_ts > 0) {
        $last_visit_candidates[] = $last_visit_ts;
    }
}

if (!empty($vaccinations[0]['date_given'])) {
    $vacc_ts = strtotime((string)$vaccinations[0]['date_given']);
    if ($vacc_ts !== false && $vacc_ts > 0) {
        $last_visit_candidates[] = $vacc_ts;
    }
}

if (!empty($dewormings[0]['date_given'])) {
    $deworm_ts = strtotime((string)$dewormings[0]['date_given']);
    if ($deworm_ts !== false && $deworm_ts > 0) {
        $last_visit_candidates[] = $deworm_ts;
    }
}

if (!empty($treatments[0]['treatment_date'])) {
    $treat_ts = strtotime((string)$treatments[0]['treatment_date']);
    if ($treat_ts !== false && $treat_ts > 0) {
        $last_visit_candidates[] = $treat_ts;
    }
}

$raw_registered_date = (string)($pet['created_at'] ?? '');
if ($raw_registered_date !== '' && $raw_registered_date !== '0000-00-00' && $raw_registered_date !== '1970-01-01') {
    $registered_ts = strtotime($raw_registered_date);
    if ($registered_ts !== false && $registered_ts > 0) {
        $last_visit_candidates[] = $registered_ts;
    }
}

$last_visit_display = 'No visit yet';
if (!empty($last_visit_candidates)) {
    $last_visit_display = date('M d, Y', max($last_visit_candidates));
}

$latest_vacc = [];
foreach ($vaccinations as $vac) {
    $key = strtolower(trim((string)($vac['vaccine_name'] ?? '')));
    if ($key === '') {
        continue;
    }
    $ts = strtotime((string)($vac['date_given'] ?? ''));
    if ($ts === false) {
        continue;
    }
    if (!isset($latest_vacc[$key]) || $ts > $latest_vacc[$key]) {
        $latest_vacc[$key] = $ts;
    }
}

$latest_deworm = [];
foreach ($dewormings as $dew) {
    $key = strtolower(trim((string)($dew['product_name'] ?? '')));
    if ($key === '') {
        continue;
    }
    $ts = strtotime((string)($dew['date_given'] ?? ''));
    if ($ts === false) {
        continue;
    }
    if (!isset($latest_deworm[$key]) || $ts > $latest_deworm[$key]) {
        $latest_deworm[$key] = $ts;
    }
}

$latest_treatment = [];
foreach ($treatments as $treat) {
    $key = strtolower(trim((string)($treat['diagnosis'] ?? 'follow-up')));
    if ($key === '') {
        $key = 'follow-up';
    }
    $ts = strtotime((string)($treat['treatment_date'] ?? ''));
    if ($ts === false) {
        continue;
    }
    if (!isset($latest_treatment[$key]) || $ts > $latest_treatment[$key]) {
        $latest_treatment[$key] = $ts;
    }
}

// Compute next upcoming vaccination date (earliest future next_due_date)
$next_vacc_display = 'None scheduled';
$next_vacc_ts = null;
foreach ($vaccinations as $v) {
    $key = strtolower(trim((string)($v['vaccine_name'] ?? '')));
    $date_ts = strtotime((string)($v['date_given'] ?? ''));
    if ($key !== '' && isset($latest_vacc[$key]) && $date_ts !== false && $date_ts < $latest_vacc[$key]) {
        continue;
    }
    $nd = (string)($v['next_due_date'] ?? '');
    if ($nd === '' || $nd === '0000-00-00') continue;
    $ts = strtotime($nd);
    if ($ts !== false && $ts > 0 && ($next_vacc_ts === null || $ts < $next_vacc_ts)) {
        $next_vacc_ts = $ts;
    }
}
if ($next_vacc_ts !== null) {
    $days_vacc = (int)(($next_vacc_ts - strtotime(date('Y-m-d'))) / 86400);
    if ($days_vacc < 0) {
        $next_vacc_display = '<span style="color:#dc2626;font-weight:700;">Overdue</span> (' . date('M d, Y', $next_vacc_ts) . ')';
    } elseif ($days_vacc === 0) {
        $next_vacc_display = '<span style="color:#d97706;font-weight:700;">Due today</span>';
    } elseif ($days_vacc <= 7) {
        $next_vacc_display = '<span style="color:#d97706;font-weight:700;">Due in ' . $days_vacc . ' day' . ($days_vacc === 1 ? '' : 's') . '</span> (' . date('M d, Y', $next_vacc_ts) . ')';
    } else {
        $next_vacc_display = date('M d, Y', $next_vacc_ts);
    }
}

// Compute next upcoming deworming date
$next_dew_display = 'None scheduled';
$next_dew_ts = null;
foreach ($dewormings as $d) {
    $key = strtolower(trim((string)($d['product_name'] ?? '')));
    $date_ts = strtotime((string)($d['date_given'] ?? ''));
    if ($key !== '' && isset($latest_deworm[$key]) && $date_ts !== false && $date_ts < $latest_deworm[$key]) {
        continue;
    }
    $nd = (string)($d['next_due_date'] ?? '');
    if ($nd === '' || $nd === '0000-00-00') continue;
    $ts = strtotime($nd);
    if ($ts !== false && $ts > 0 && ($next_dew_ts === null || $ts < $next_dew_ts)) {
        $next_dew_ts = $ts;
    }
}
if ($next_dew_ts !== null) {
    $days_dew = (int)(($next_dew_ts - strtotime(date('Y-m-d'))) / 86400);
    if ($days_dew < 0) {
        $next_dew_display = '<span style="color:#dc2626;font-weight:700;">Overdue</span> (' . date('M d, Y', $next_dew_ts) . ')';
    } elseif ($days_dew === 0) {
        $next_dew_display = '<span style="color:#d97706;font-weight:700;">Due today</span>';
    } elseif ($days_dew <= 7) {
        $next_dew_display = '<span style="color:#d97706;font-weight:700;">Due in ' . $days_dew . ' day' . ($days_dew === 1 ? '' : 's') . '</span> (' . date('M d, Y', $next_dew_ts) . ')';
    } else {
        $next_dew_display = date('M d, Y', $next_dew_ts);
    }
}

// Compute next upcoming followup/treatment date
$next_treat_display = 'None scheduled';
$next_treat_ts = null;
foreach ($treatments as $t) {
    $key = strtolower(trim((string)($t['diagnosis'] ?? 'follow-up')));
    if ($key === '') {
        $key = 'follow-up';
    }
    $date_ts = strtotime((string)($t['treatment_date'] ?? ''));
    if (isset($latest_treatment[$key]) && $date_ts !== false && $date_ts < $latest_treatment[$key]) {
        continue;
    }
    $nd = (string)($t['followup_date'] ?? '');
    if ($nd === '' || $nd === '0000-00-00') continue;
    $ts = strtotime($nd);
    if ($ts !== false && $ts > 0 && ($next_treat_ts === null || $ts < $next_treat_ts)) {
        $next_treat_ts = $ts;
    }
}
if ($next_treat_ts !== null) {
    $days_treat = (int)(($next_treat_ts - strtotime(date('Y-m-d'))) / 86400);
    if ($days_treat < 0) {
        $next_treat_display = '<span style="color:#dc2626;font-weight:700;">Overdue</span> (' . date('M d, Y', $next_treat_ts) . ')';
    } elseif ($days_treat === 0) {
        $next_treat_display = '<span style="color:#d97706;font-weight:700;">Due today</span>';
    } elseif ($days_treat <= 7) {
        $next_treat_display = '<span style="color:#d97706;font-weight:700;">Due in ' . $days_treat . ' day' . ($days_treat === 1 ? '' : 's') . '</span> (' . date('M d, Y', $next_treat_ts) . ')';
    } else {
        $next_treat_display = date('M d, Y', $next_treat_ts);
    }
}

function owner_due_status_html($due_date, $has_newer)
{
    if ($has_newer) {
        return '<span style="color:#16a34a;font-weight:700;">Completed</span>';
    }

    if ($due_date === null || $due_date === '' || $due_date === '0000-00-00') {
        return '<span style="color:#9ca3af;">No next date</span>';
    }

    $due_ts = strtotime($due_date);
    if ($due_ts === false) {
        return '<span style="color:#9ca3af;">No next date</span>';
    }

    $days = (int)(($due_ts - strtotime(date('Y-m-d'))) / 86400);

    if ($days < 0) {
        return '<span style="color:#dc2626;font-weight:700;">Overdue</span> (' . date('M d, Y', $due_ts) . ')';
    }
    if ($days === 0) {
        return '<span style="color:#d97706;font-weight:700;">Due today</span>';
    }
    if ($days <= 3) {
        return '<span style="color:#d97706;font-weight:700;">Due in 3 days</span> (' . date('M d, Y', $due_ts) . ')';
    }
    if ($days <= 7) {
        return '<span style="color:#0d9488;font-weight:700;">Due in 7 days</span> (' . date('M d, Y', $due_ts) . ')';
    }

    return date('M d, Y', $due_ts);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pet Records - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <section class="owner-panel mb-3">
            <div class="owner-panel-header">
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 700; font-family: 'Sora', sans-serif; margin: 0;">
                        <?= htmlspecialchars($pet['name']) ?>
                        <span style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 4px; font-size: 0.75rem; margin-left: 12px; font-weight: 700;">
                            <?= htmlspecialchars($pet['status']) ?>
                        </span>
                    </h2>
                    <p style="color: #6b7280; font-size: 0.95rem; margin-top: 6px;">
                        <?= htmlspecialchars($pet['breed']) ?> · <?= htmlspecialchars($pet['gender']) ?> · <?= htmlspecialchars($pet['species']) ?>
                    </p>
                </div>
                <a href="dashboard.php" style="color: #0d9488; text-decoration: none; font-weight: 600;">Back</a>
            </div>

            <div style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Weight</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($pet['weight']) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Vet</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #1f2937;">
                        <?= htmlspecialchars(trim((string)($pet['vet_name'] ?? '')) !== '' ? 'Dr. ' . trim((string)$pet['vet_name']) : 'Not assigned') ?>
                    </div>
                    <?php if (!empty($pet['clinic_name'])): ?>
                        <div style="font-size: 0.85rem; color: #6b7280;">
                            <?= htmlspecialchars($pet['clinic_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Allergies</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($pet['allergies']) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Last Visit</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($last_visit_display) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Next Vaccination</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #1f2937;"><?= $next_vacc_display ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Next Deworming</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #1f2937;"><?= $next_dew_display ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Next Follow-up</div>
                    <div style="font-size: 1rem; font-weight: 700; color: #1f2937;"><?= $next_treat_display ?></div>
                </div>
            </div>
        </section>

        <!-- Tabs Navigation -->
        <section class="owner-panel" style="margin-bottom: 24px;">
            <div style="display: flex; gap: 24px; border-bottom: 1px solid #e5e7eb; margin: -24px -24px 0 -24px; padding: 0 24px;">
                <button type="button" onclick="switchTab('vaccinations', this)" class="tab-btn active" style="padding: 16px 0; color: #0d9488; border-bottom: 3px solid #0d9488; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Vaccinations</button>
                <button type="button" onclick="switchTab('deworming', this)" class="tab-btn" style="padding: 16px 0; color: #6b7280; border-bottom: 3px solid transparent; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Deworming</button>
                <button type="button" onclick="switchTab('treatments', this)" class="tab-btn" style="padding: 16px 0; color: #6b7280; border-bottom: 3px solid transparent; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Treatments</button>
            </div>

            <!-- Vaccinations Table -->
            <div id="vaccinations-content" class="tab-content" style="margin-top: 20px; display: block;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Vaccine</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date Given</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Next Due</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Dose</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vaccinations)): ?>
                            <tr>
                                <td colspan="5" style="padding: 24px 0; text-align: center; color: #9ca3af;">No vaccination records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($vaccinations as $vac): ?>
                            <?php
                                $vac_key = strtolower(trim((string)($vac['vaccine_name'] ?? '')));
                                $vac_date_ts = strtotime((string)($vac['date_given'] ?? ''));
                                $vac_has_newer = $vac_key !== '' && isset($latest_vacc[$vac_key]) && $vac_date_ts !== false && $vac_date_ts < $latest_vacc[$vac_key];
                                $vac_status_html = owner_due_status_html($vac['next_due_date'] ?? '', $vac_has_newer);
                            ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($vac['vaccine_name']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($vac['date_given'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= !empty($vac['next_due_date']) && $vac['next_due_date'] !== '0000-00-00' ? date('M d, Y', strtotime($vac['next_due_date'])) : '—' ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($vac['dose_number']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= $vac_status_html ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Deworming Table -->
            <div id="deworming-content" class="tab-content" style="margin-top: 20px; display: none;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Product</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date Given</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Next Due</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Dose</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dewormings)): ?>
                            <tr>
                                <td colspan="5" style="padding: 24px 0; text-align: center; color: #9ca3af;">No deworming records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($dewormings as $dew): ?>
                            <?php
                                $dew_key = strtolower(trim((string)($dew['product_name'] ?? '')));
                                $dew_date_ts = strtotime((string)($dew['date_given'] ?? ''));
                                $dew_has_newer = $dew_key !== '' && isset($latest_deworm[$dew_key]) && $dew_date_ts !== false && $dew_date_ts < $latest_deworm[$dew_key];
                                $dew_status_html = owner_due_status_html($dew['next_due_date'] ?? '', $dew_has_newer);
                            ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($dew['product_name']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($dew['date_given'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= !empty($dew['next_due_date']) && $dew['next_due_date'] !== '0000-00-00' ? date('M d, Y', strtotime($dew['next_due_date'])) : '—' ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($dew['dose']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= $dew_status_html ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Treatments Table -->
            <div id="treatments-content" class="tab-content" style="margin-top: 20px; display: none;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Diagnosis</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Treatment</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Follow-up Date</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Status</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($treatments)): ?>
                            <tr>
                                <td colspan="6" style="padding: 24px 0; text-align: center; color: #9ca3af;">No treatment records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($treatments as $treat): ?>
                            <?php
                                $sev = strtolower((string)($treat['severity'] ?? ''));
                                $sev_bg    = $sev === 'critical' ? '#fee2e2' : ($sev === 'severe' ? '#fef3c7' : ($sev === 'moderate' ? '#fef9c3' : '#dcfce7'));
                                $sev_color = $sev === 'critical' ? '#991b1b' : ($sev === 'severe' ? '#92400e' : ($sev === 'moderate' ? '#713f12' : '#166534'));
                                $diag_key = strtolower(trim((string)($treat['diagnosis'] ?? 'follow-up')));
                                if ($diag_key === '') {
                                    $diag_key = 'follow-up';
                                }
                                $treat_date_ts = strtotime((string)($treat['treatment_date'] ?? ''));
                                $treat_has_newer = isset($latest_treatment[$diag_key]) && $treat_date_ts !== false && $treat_date_ts < $latest_treatment[$diag_key];
                                $treat_status_html = owner_due_status_html($treat['followup_date'] ?? '', $treat_has_newer);
                            ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($treat['diagnosis']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($treat['treatment'] ?? '—') ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($treat['treatment_date'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;">
                                    <?= !empty($treat['followup_date']) && $treat['followup_date'] !== '0000-00-00'
                                        ? date('M d, Y', strtotime($treat['followup_date']))
                                        : '<span style="color:#9ca3af;">—</span>' ?>
                                </td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= $treat_status_html ?></td>
                                <td style="padding: 14px 0;">
                                    <span style="background:<?= $sev_bg ?>; color:<?= $sev_color ?>; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                                        <?= htmlspecialchars(ucfirst($treat['severity'] ?? '')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <script>
        function switchTab(tabName, activeButton) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.style.display = 'none');
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.style.color = '#6b7280';
                btn.style.borderBottom = '3px solid transparent';
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById(tabName + '-content');
            if (selectedContent) {
                selectedContent.style.display = 'block';
            }
            
            // Highlight active button
            if (activeButton) {
                activeButton.style.color = '#0d9488';
                activeButton.style.borderBottom = '3px solid #0d9488';
                activeButton.classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            switchTab('vaccinations', document.querySelector('.tab-btn.active'));
        });
        </script>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>