<?php
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_helper.php';

$active_page = 'treatments';
$success = '';
$error = '';
$view_mode = trim($_GET['view'] ?? 'all');
$allowed_view_modes = ['all', 'followups'];
if (!in_array($view_mode, $allowed_view_modes, true)) {
    $view_mode = 'all';
}
$quick_view_mode = $view_mode !== 'all';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    $error = 'Invalid session. Please login again.';
}

$form_data = [
    'pet_id' => '',
    'diagnosis' => '',
    'treatment' => '',
    'treatment_date' => '',
    'followup_date' => '',
    'severity' => '',
    'notes' => ''
];

$allowed_severity = ['mild', 'moderate', 'severe', 'critical'];

$pets = [];
if ($vet_id > 0) {
    $pets_result = mysqli_query(
        $conn,
        "SELECT p.id, p.name, p.species,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
                u.email AS owner_email
         FROM pets p
         LEFT JOIN users u ON p.owner_id = u.id
         WHERE p.vet_id = $vet_id
         ORDER BY p.name ASC"
    );
    if ($pets_result) {
        while ($pet = mysqli_fetch_assoc($pets_result)) {
            $pets[] = $pet;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vet_id > 0) {
    $form_data['pet_id'] = (int)($_POST['pet_id'] ?? 0);
    $form_data['diagnosis'] = trim($_POST['diagnosis'] ?? '');
    $form_data['treatment'] = trim($_POST['treatment'] ?? '');
    $form_data['treatment_date'] = trim($_POST['treatment_date'] ?? '');
    $form_data['followup_date'] = trim($_POST['followup_date'] ?? '');
    $form_data['severity'] = trim($_POST['severity'] ?? 'mild');
    $form_data['notes'] = trim($_POST['notes'] ?? '');

    if ($form_data['pet_id'] <= 0) {
        $error = 'Please select a patient.';
    } elseif ($form_data['diagnosis'] === '') {
        $error = 'Diagnosis is required.';
    } elseif ($form_data['treatment_date'] === '') {
        $error = 'Treatment date is required.';
    } elseif ($form_data['severity'] === '') {
        $error = 'Please select a priority.';
    } elseif (!in_array($form_data['severity'], $allowed_severity, true)) {
        $error = 'Invalid priority selected.';
    }

    if ($error === '') {
        $pet_check = mysqli_query(
            $conn,
            "SELECT id, owner_id, name FROM pets WHERE id = {$form_data['pet_id']} AND vet_id = $vet_id LIMIT 1"
        );

        if (!$pet_check || mysqli_num_rows($pet_check) === 0) {
            $error = 'Invalid patient selected.';
        } else {
            $pet_row = mysqli_fetch_assoc($pet_check);
        }
    }

    if ($error === '') {
        $pet_id = (int)$form_data['pet_id'];
        $diagnosis = mysqli_real_escape_string($conn, $form_data['diagnosis']);
        $treatment_text = $form_data['treatment'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['treatment']) . "'"
            : 'NULL';
        $treatment_date = mysqli_real_escape_string($conn, $form_data['treatment_date']);
        $followup_date = $form_data['followup_date'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['followup_date']) . "'"
            : 'NULL';
        $severity = mysqli_real_escape_string($conn, $form_data['severity']);
        $notes = $form_data['notes'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['notes']) . "'"
            : 'NULL';

        $insert_query = "
            INSERT INTO treatments (pet_id, vet_id, diagnosis, treatment, treatment_date, followup_date, severity, notes, created_at)
            VALUES ($pet_id, $vet_id, '$diagnosis', $treatment_text, '$treatment_date', $followup_date, '$severity', $notes, NOW())
        ";

        if (mysqli_query($conn, $insert_query)) {
            $treatment_id = (int)mysqli_insert_id($conn);

            // New same diagnosis follow-up replaces older active follow-up records.
            $complete_old_stmt = mysqli_prepare(
                $conn,
                "UPDATE treatments
                 SET followup_status = 'completed'
                 WHERE pet_id = ?
                   AND vet_id = ?
                   AND diagnosis = ?
                   AND id <> ?"
            );
            if ($complete_old_stmt) {
                mysqli_stmt_bind_param($complete_old_stmt, 'iisi', $pet_id, $vet_id, $form_data['diagnosis'], $treatment_id);
                mysqli_stmt_execute($complete_old_stmt);
                mysqli_stmt_close($complete_old_stmt);
            }

            $owner_id = (int)($pet_row['owner_id'] ?? 0);
            $followup_value = $form_data['followup_date'] !== '' ? $form_data['followup_date'] : null;

            if ($followup_value !== null && $owner_id > 0) {
                $diagnosis_text = $form_data['diagnosis'] !== '' ? $form_data['diagnosis'] : 'Follow-up check';
                $reminder_message = 'Follow-up reminder for ' . ($pet_row['name'] ?? 'your pet') . ': ' . $diagnosis_text . '.';
                petcura_upsert_auto_reminder(
                    $conn,
                    $pet_id,
                    $owner_id,
                    $vet_id,
                    'treatment',
                    $treatment_id,
                    $followup_value,
                    'email',
                    $reminder_message
                );
            }

            petcura_refresh_last_visit($conn, $pet_id, $vet_id);

            $success = 'Treatment saved. Follow-up reminder and last-visit were updated automatically.';
            $form_data = [
                'pet_id' => '',
                'diagnosis' => '',
                'treatment' => '',
                'treatment_date' => '',
                'followup_date' => '',
                'severity' => '',
                'notes' => ''
            ];
        } else {
            $error = 'Database error: ' . mysqli_error($conn);
        }
    }
}

$treatments = [];
if ($vet_id > 0) {
    $list_where = "t.vet_id = $vet_id AND t.followup_status = 'active'";
    if ($view_mode === 'followups') {
        // Show all active follow-ups, including overdue ones.
        $list_where .= " AND t.followup_date IS NOT NULL";
    }

    $treatment_query = mysqli_query(
        $conn,
        "SELECT t.id, t.diagnosis, t.treatment_date, t.followup_date, t.severity,
                p.name AS pet_name,
                CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name,
                u.email AS owner_email,
                u.phone AS owner_phone
         FROM treatments t
         JOIN pets p ON t.pet_id = p.id
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE $list_where
         ORDER BY t.treatment_date DESC, t.id DESC"
    );

    if ($treatment_query) {
        while ($row = mysqli_fetch_assoc($treatment_query)) {
            $treatments[] = $row;
        }
    }
}

$records_title = 'Treatment records';
if ($view_mode === 'followups') {
    $records_title = 'Follow-ups pending list';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Treatments - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <?php if ($success !== ''): ?>
        <div class="vet-alert mb-3">
            <div class="vet-alert-text">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <div style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 10px; padding: 13px 16px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #991b1b; font-size: 0.82rem; font-weight: 700;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$quick_view_mode): ?>
        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title"><i class="bi bi-clipboard2-pulse me-2"></i>New treatment case</h3>
            </div>
            <form method="POST" class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Patient <span style="color: #dc2626;">*</span></label>
                        <select name="pet_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int)$pet['id'] ?>" <?= (int)$form_data['pet_id'] === (int)$pet['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pet['name']) ?> (<?= htmlspecialchars($pet['species']) ?>) - Owner: <?= htmlspecialchars(trim($pet['owner_name']) !== '' ? trim($pet['owner_name']) : 'Unknown') ?><?= !empty($pet['owner_email']) ? ' [' . htmlspecialchars($pet['owner_email']) . ']' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Diagnosis <span style="color: #dc2626;">*</span></label>
                        <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($form_data['diagnosis']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Treatment date <span style="color: #dc2626;">*</span></label>
                        <input type="date" name="treatment_date" class="form-control" value="<?= htmlspecialchars($form_data['treatment_date']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Follow-up date</label>
                        <input type="date" name="followup_date" class="form-control" value="<?= htmlspecialchars($form_data['followup_date']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Priority</label>
                        <select name="severity" class="form-select">
                            <option value="" <?= $form_data['severity'] === '' ? 'selected' : '' ?>>Select priority</option>
                            <option value="mild" <?= $form_data['severity'] === 'mild' ? 'selected' : '' ?>>Low</option>
                            <option value="moderate" <?= $form_data['severity'] === 'moderate' ? 'selected' : '' ?>>Medium</option>
                            <option value="severe" <?= $form_data['severity'] === 'severe' ? 'selected' : '' ?>>High</option>
                            <option value="critical" <?= $form_data['severity'] === 'critical' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Treatment plan</label>
                        <input type="text" name="treatment" class="form-control" value="<?= htmlspecialchars($form_data['treatment']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($form_data['notes']) ?></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="vet-alert-btn">SAVE</button>
                    <button type="reset" class="patients-view-btn">Clear</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title"><i class="bi bi-arrow-counterclockwise me-2"></i><?= htmlspecialchars($records_title) ?></h3>
            </div>
            <div class="table-responsive" style="max-height:420px; overflow-y:auto;">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Case</th>
                            <th>Treatment date</th>
                            <th>Follow-up</th>
                            <th>Owner</th>
                            <th>Follow-up status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($treatments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No treatment cases yet</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($treatments as $row): ?>
                                <?php
                                // Follow-up status based on followup_date vs today
                                if (empty($row['followup_date'])) {
                                    $fu_label = 'No follow-up';
                                    $fu_class = 'vet-pill-status';
                                } else {
                                    $fu_days = (int)((strtotime($row['followup_date']) - strtotime(date('Y-m-d'))) / 86400);
                                    if ($fu_days < 0) {
                                        $fu_label = 'Overdue';
                                        $fu_class = 'vet-pill-overdue';
                                    } elseif ($fu_days === 0) {
                                        $fu_label = 'Today';
                                        $fu_class = 'vet-pill-soon';
                                    } elseif ($fu_days <= 3) {
                                        $fu_label = 'In ' . $fu_days . ' day' . ($fu_days > 1 ? 's' : '');
                                        $fu_class = 'vet-pill-soon';
                                    } else {
                                        $fu_label = 'Upcoming';
                                        $fu_class = 'vet-pill-upcoming';
                                    }
                                }
                                $o_name  = trim($row['owner_name'] ?? '');
                                $o_email = trim($row['owner_email'] ?? '');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pet_name']) ?></td>
                                    <td><?= htmlspecialchars($row['diagnosis']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['treatment_date']))) ?></td>
                                    <td><?= $row['followup_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['followup_date']))) : 'N/A' ?></td>
                                    <td>
                                        <?php if ($o_name !== ''): ?><div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($o_name) ?></div><?php endif; ?>
                                        <?php if (!empty($row['owner_phone'])): ?><div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-telephone-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($row['owner_phone']) ?></div><?php endif; ?>
                                        <?php if ($o_email !== ''): ?><div style="font-size:0.78rem;color:#6b7280;"><i class="bi bi-envelope-fill" style="font-size:0.7rem;"></i> <?= htmlspecialchars($o_email) ?></div><?php endif; ?>
                                        <?php if ($o_name === '' && $o_email === ''): ?><span style="color:#9ca3af;font-size:0.8rem;">—</span><?php endif; ?>
                                    </td>
                                    <td><span class="vet-pill <?= htmlspecialchars($fu_class) ?>"><?= htmlspecialchars($fu_label) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>