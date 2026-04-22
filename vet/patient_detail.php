<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'patients';
$success = '';
$error = '';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
$pet_id = (int)($_GET['id'] ?? 0);

if ($vet_id <= 0) {
    header('Location: ../login.php');
    exit();
}

if ($pet_id <= 0) {
    header('Location: patients.php');
    exit();
}

$pet = null;
$pet_stmt = mysqli_prepare(
    $conn,
    "SELECT p.id, p.name, p.species, p.breed, p.gender, p.dob, p.weight, p.status, p.allergies, p.last_visit,
            p.vaccination_status, p.vaccination_type, p.deworming_status, p.deworming_type,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone
     FROM pets p
     LEFT JOIN users u ON p.owner_id = u.id
     WHERE p.id = ? AND p.vet_id = ?
     LIMIT 1"
);

if ($pet_stmt) {
    mysqli_stmt_bind_param($pet_stmt, 'ii', $pet_id, $vet_id);
    mysqli_stmt_execute($pet_stmt);
    $pet_result = mysqli_stmt_get_result($pet_stmt);
    $pet = $pet_result ? mysqli_fetch_assoc($pet_result) : null;
    mysqli_stmt_close($pet_stmt);
}

if (!$pet) {
    header('Location: patients.php');
    exit();
}

$vaccination_form = [
    'vaccine_name' => '',
    'date_given' => '',
    'next_due_date' => '',
    'dose_number' => '',
    'batch_number' => '',
    'notes' => ''
];

$deworming_form = [
    'product_name' => '',
    'date_given' => '',
    'next_due_date' => '',
    'dose' => '',
    'notes' => ''
];

$status_form = [
    'vaccination_status' => ($pet['vaccination_status'] ?? '') === 'vaccinated' ? 'up-to-date' : ($pet['vaccination_status'] ?? 'not-vaccinated'),
    'vaccination_type' => $pet['vaccination_type'] ?? '',
    'deworming_status' => $pet['deworming_status'] ?? 'not-dewormed',
    'deworming_type' => $pet['deworming_type'] ?? ''
];

$vaccine_catalog = [
    'Dog' => ['Rabies', 'DHPP', 'Parvovirus', 'Leptospirosis', 'Bordetella', 'Canine Influenza'],
    'Cat' => ['Rabies', 'FVRCP', 'FeLV', 'Chlamydia', 'Bordetella'],
    'Bird' => ['Polyomavirus', 'Pox', 'PBFD'],
    'Rabbit' => ['Myxomatosis', 'RHDV1', 'RHDV2'],
    'Hamster' => ['No routine vaccine']
];

$deworming_catalog = [
    'Dog' => ['Pyrantel', 'Fenbendazole', 'Praziquantel', 'Ivermectin'],
    'Cat' => ['Pyrantel', 'Fenbendazole', 'Praziquantel'],
    'Rabbit' => ['Fenbendazole', 'Pyrantel'],
    'Bird' => ['Ivermectin', 'Fenbendazole'],
    'Hamster' => ['Fenbendazole']
];

function normalize_vaccination_status($status)
{
    $status = (string)$status;
    if ($status === 'vaccinated') {
        return 'up-to-date';
    }

    $allowed = ['not-vaccinated', 'in-progress', 'up-to-date', 'overdue'];
    return in_array($status, $allowed, true) ? $status : 'not-vaccinated';
}

function vaccination_status_display($status)
{
    $normalized = normalize_vaccination_status($status);

    if ($normalized === 'up-to-date') {
        return ['label' => 'Vaccinated (Up to date)', 'class' => 'vet-pill-updated'];
    }
    if ($normalized === 'in-progress') {
        return ['label' => 'Vaccination in progress', 'class' => 'vet-pill-soon'];
    }
    if ($normalized === 'overdue') {
        return ['label' => 'Booster overdue', 'class' => 'vet-pill-overdue'];
    }

    return ['label' => 'Not vaccinated', 'class' => 'vet-pill-overdue'];
}

$pet['vaccination_status'] = normalize_vaccination_status($pet['vaccination_status'] ?? 'not-vaccinated');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['form_action'] ?? '');

    if ($action === 'update_statuses') {
        $status_form['vaccination_status'] = trim($_POST['vaccination_status'] ?? 'not-vaccinated');
        $status_form['vaccination_type'] = trim($_POST['vaccination_type'] ?? '');
        $status_form['deworming_status'] = trim($_POST['deworming_status'] ?? 'not-dewormed');
        $status_form['deworming_type'] = trim($_POST['deworming_type'] ?? '');

        $allowed_vaccination_status = ['not-vaccinated', 'in-progress', 'up-to-date', 'overdue'];
        $allowed_deworming_status = ['dewormed', 'not-dewormed'];

        if (!in_array($status_form['vaccination_status'], $allowed_vaccination_status, true)) {
            $error = 'Invalid vaccination status selected.';
        } elseif (!in_array($status_form['deworming_status'], $allowed_deworming_status, true)) {
            $error = 'Invalid deworming status selected.';
        }

        if ($error === '') {
            if ($status_form['vaccination_status'] === 'not-vaccinated') {
                $status_form['vaccination_type'] = '';
            }
            if ($status_form['deworming_status'] === 'not-dewormed') {
                $status_form['deworming_type'] = '';
            }

            $vaccination_status = mysqli_real_escape_string($conn, $status_form['vaccination_status']);
            $vaccination_type = $status_form['vaccination_type'] !== '' ? "'" . mysqli_real_escape_string($conn, $status_form['vaccination_type']) . "'" : 'NULL';
            $deworming_status = mysqli_real_escape_string($conn, $status_form['deworming_status']);
            $deworming_type = $status_form['deworming_type'] !== '' ? "'" . mysqli_real_escape_string($conn, $status_form['deworming_type']) . "'" : 'NULL';

            $update_status_query = "
                UPDATE pets
                SET vaccination_status = '$vaccination_status',
                    vaccination_type = $vaccination_type,
                    deworming_status = '$deworming_status',
                    deworming_type = $deworming_type
                WHERE id = $pet_id AND vet_id = $vet_id
            ";

            if (mysqli_query($conn, $update_status_query)) {
                $success = 'Patient status updated successfully.';
                $pet['vaccination_status'] = $status_form['vaccination_status'];
                $pet['vaccination_type'] = $status_form['vaccination_type'];
                $pet['deworming_status'] = $status_form['deworming_status'];
                $pet['deworming_type'] = $status_form['deworming_type'];
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
        }
    }

    if ($action === 'add_vaccination') {
        $vaccination_form['vaccine_name'] = trim($_POST['vaccine_name'] ?? '');
        $vaccination_form['date_given'] = trim($_POST['date_given'] ?? '');
        $vaccination_form['next_due_date'] = trim($_POST['next_due_date'] ?? '');
        $vaccination_form['dose_number'] = trim($_POST['dose_number'] ?? '');
        $vaccination_form['batch_number'] = trim($_POST['batch_number'] ?? '');
        $vaccination_form['notes'] = trim($_POST['notes'] ?? '');

        if ($vaccination_form['vaccine_name'] === '') {
            $error = 'Vaccine name is required.';
        } elseif ($vaccination_form['date_given'] === '') {
            $error = 'Date given is required.';
        } else {
            $allowed_vaccines = $vaccine_catalog[$pet['species']] ?? [];
            if (!in_array($vaccination_form['vaccine_name'], $allowed_vaccines, true)) {
                $error = 'Invalid vaccine selected for this species.';
            }
        }

        if ($error === '') {
            $vac_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO vaccinations (pet_id, vet_id, vaccine_name, date_given, next_due_date, dose_number, batch_number, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if ($vac_stmt) {
                $next_due = $vaccination_form['next_due_date'] !== '' ? $vaccination_form['next_due_date'] : null;
                $dose_num = $vaccination_form['dose_number'] !== '' ? $vaccination_form['dose_number'] : null;
                $batch_num = $vaccination_form['batch_number'] !== '' ? $vaccination_form['batch_number'] : null;
                $notes_val = $vaccination_form['notes'] !== '' ? $vaccination_form['notes'] : null;

                mysqli_stmt_bind_param(
                    $vac_stmt,
                    'iissssss',
                    $pet_id,
                    $vet_id,
                    $vaccination_form['vaccine_name'],
                    $vaccination_form['date_given'],
                    $next_due,
                    $dose_num,
                    $batch_num,
                    $notes_val
                );

                if (mysqli_stmt_execute($vac_stmt)) {
                    $new_vaccination_status = 'up-to-date';
                    if ($next_due !== null) {
                        $today = date('Y-m-d');
                        $new_vaccination_status = ($next_due < $today) ? 'overdue' : 'in-progress';
                    }

                    $status_update_stmt = mysqli_prepare(
                        $conn,
                        "UPDATE pets
                         SET vaccination_status = ?, vaccination_type = ?
                         WHERE id = ? AND vet_id = ?"
                    );

                    if ($status_update_stmt) {
                        $latest_vaccine = $vaccination_form['vaccine_name'];
                        mysqli_stmt_bind_param($status_update_stmt, 'ssii', $new_vaccination_status, $latest_vaccine, $pet_id, $vet_id);
                        mysqli_stmt_execute($status_update_stmt);
                        mysqli_stmt_close($status_update_stmt);

                        $pet['vaccination_status'] = $new_vaccination_status;
                        $pet['vaccination_type'] = $latest_vaccine;
                    }

                    $success = 'Vaccination record saved. Vaccination status updated automatically.';
                    $vaccination_form = ['vaccine_name' => '', 'date_given' => '', 'next_due_date' => '', 'dose_number' => '', 'batch_number' => '', 'notes' => ''];
                } else {
                    $error = 'Database error: ' . mysqli_stmt_error($vac_stmt);
                }
                mysqli_stmt_close($vac_stmt);
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
        }
    }

    if ($action === 'add_deworming') {
        $deworming_form['product_name'] = trim($_POST['product_name'] ?? '');
        $deworming_form['date_given'] = trim($_POST['date_given'] ?? '');
        $deworming_form['next_due_date'] = trim($_POST['next_due_date'] ?? '');
        $deworming_form['dose'] = trim($_POST['dose'] ?? '');
        $deworming_form['notes'] = trim($_POST['notes'] ?? '');

        if ($deworming_form['product_name'] === '') {
            $error = 'Deworming type is required.';
        } elseif ($deworming_form['date_given'] === '') {
            $error = 'Date given is required.';
        }

        if ($error === '') {
            $dew_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO dewormings (pet_id, vet_id, product_name, date_given, next_due_date, dose, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if ($dew_stmt) {
                $next_due = $deworming_form['next_due_date'] !== '' ? $deworming_form['next_due_date'] : null;
                $dose_val = $deworming_form['dose'] !== '' ? $deworming_form['dose'] : null;
                $notes_val = $deworming_form['notes'] !== '' ? $deworming_form['notes'] : null;

                mysqli_stmt_bind_param(
                $dew_stmt,
                'iisssss',
                    $pet_id,
                    $vet_id,
                    $deworming_form['product_name'],
                    $deworming_form['date_given'],
                    $next_due,
                    $dose_val,
                    $notes_val
                );

                if (mysqli_stmt_execute($dew_stmt)) {
                    $success = 'Deworming record saved.';
                    $deworming_form = ['product_name' => '', 'date_given' => '', 'next_due_date' => '', 'dose' => '', 'notes' => ''];
                } else {
                    $error = 'Database error: ' . mysqli_stmt_error($dew_stmt);
                }
                mysqli_stmt_close($dew_stmt);
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}

$vaccinations = [];
$vaccinations_stmt = mysqli_prepare(
    $conn,
    "SELECT vaccine_name, date_given, next_due_date, dose_number, batch_number, notes
     FROM vaccinations
     WHERE pet_id = ? AND vet_id = ?
     ORDER BY date_given DESC, id DESC"
);

if ($vaccinations_stmt) {
    mysqli_stmt_bind_param($vaccinations_stmt, 'ii', $pet_id, $vet_id);
    mysqli_stmt_execute($vaccinations_stmt);
    $vaccinations_result = mysqli_stmt_get_result($vaccinations_stmt);
    while ($vaccinations_result && $row = mysqli_fetch_assoc($vaccinations_result)) {
        $vaccinations[] = $row;
    }
    mysqli_stmt_close($vaccinations_stmt);
}

$dewormings = [];
$dewormings_stmt = mysqli_prepare(
    $conn,
    "SELECT product_name, date_given, next_due_date, dose, notes
     FROM dewormings
     WHERE pet_id = ? AND vet_id = ?
     ORDER BY date_given DESC, id DESC"
);

if ($dewormings_stmt) {
    mysqli_stmt_bind_param($dewormings_stmt, 'ii', $pet_id, $vet_id);
    mysqli_stmt_execute($dewormings_stmt);
    $dewormings_result = mysqli_stmt_get_result($dewormings_stmt);
    while ($dewormings_result && $row = mysqli_fetch_assoc($dewormings_result)) {
        $dewormings[] = $row;
    }
    mysqli_stmt_close($dewormings_stmt);
}

$vaccinated = count($vaccinations) > 0;
$dewormed = count($dewormings) > 0;

$vaccination_display = vaccination_status_display($pet['vaccination_status'] ?? 'not-vaccinated');

$deworming_display = $pet['deworming_status'] === 'dewormed'
    ? ['label' => 'Dewormed', 'class' => 'vet-pill-updated']
    : ['label' => 'Not dewormed', 'class' => 'vet-pill-soon'];

function get_age_label($dob)
{
    if (!$dob || $dob === '0000-00-00') {
        return 'N/A';
    }

    $birth = new DateTime($dob);
    $today = new DateTime();
    $diff = $today->diff($birth);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
    }

    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
    }

    return $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
}

$pet_age = get_age_label($pet['dob']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patient Details - PetCura</title>

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
            <div class="vet-alert-text"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
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

        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Patient profile</h3>
                <a href="patients.php" class="patients-view-btn">Back</a>
            </div>
            <div class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-4"><strong>Name:</strong> <?= htmlspecialchars($pet['name']) ?></div>
                    <div class="col-md-4"><strong>Species:</strong> <?= htmlspecialchars($pet['species'] ?? 'N/A') ?></div>
                    <div class="col-md-4"><strong>Breed:</strong> <?= htmlspecialchars($pet['breed'] ?? 'N/A') ?></div>
                    <div class="col-md-4"><strong>Age:</strong> <?= htmlspecialchars($pet_age) ?></div>
                    <div class="col-md-4"><strong>Health:</strong> <?= htmlspecialchars($pet['status'] ?? 'N/A') ?></div>
                    <div class="col-md-4"><strong>Last visit:</strong> <?= !empty($pet['last_visit']) ? htmlspecialchars(date('M d, Y', strtotime($pet['last_visit']))) : 'N/A' ?></div>
                    <div class="col-md-6"><strong>Owner:</strong> <?= htmlspecialchars(trim((string)$pet['owner_name']) !== '' ? trim((string)$pet['owner_name']) : 'Unknown') ?></div>
                    <div class="col-md-6"><strong>Owner phone/email:</strong> <?= htmlspecialchars(trim((string)$pet['owner_phone']) !== '' ? trim((string)$pet['owner_phone']) : 'N/A') ?> / <?= htmlspecialchars(trim((string)$pet['owner_email']) !== '' ? trim((string)$pet['owner_email']) : 'N/A') ?></div>
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <span class="vet-pill <?= $vaccination_display['class'] ?>"><?= htmlspecialchars($vaccination_display['label']) ?></span>
                    <span class="vet-pill <?= $deworming_display['class'] ?>"><?= htmlspecialchars($deworming_display['label']) ?></span>
                </div>
            </div>
        </section>

        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Vaccination and deworming status</h3>
            </div>
            <form method="POST" class="p-3 p-md-4">
                <input type="hidden" name="form_action" value="update_statuses">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Vaccination status</label>
                        <select name="vaccination_status" class="form-select">
                            <option value="not-vaccinated" <?= ($pet['vaccination_status'] ?? '') === 'not-vaccinated' ? 'selected' : '' ?>>Not vaccinated</option>
                            <option value="in-progress" <?= ($pet['vaccination_status'] ?? '') === 'in-progress' ? 'selected' : '' ?>>Vaccination in progress</option>
                            <option value="up-to-date" <?= ($pet['vaccination_status'] ?? '') === 'up-to-date' ? 'selected' : '' ?>>Vaccinated (Up to date)</option>
                            <option value="overdue" <?= ($pet['vaccination_status'] ?? '') === 'overdue' ? 'selected' : '' ?>>Booster overdue</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Vaccination type</label>
                        <input type="text" name="vaccination_type" class="form-control" value="<?= htmlspecialchars($pet['vaccination_type'] ?? '') ?>" placeholder="Rabies, DHPP, etc.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Deworming status</label>
                        <select name="deworming_status" class="form-select">
                            <option value="dewormed" <?= ($pet['deworming_status'] ?? '') === 'dewormed' ? 'selected' : '' ?>>Dewormed</option>
                            <option value="not-dewormed" <?= ($pet['deworming_status'] ?? '') !== 'dewormed' ? 'selected' : '' ?>>Not dewormed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-secondary">Deworming type</label>
                        <input type="text" name="deworming_type" class="form-control" value="<?= htmlspecialchars($pet['deworming_type'] ?? '') ?>" placeholder="Pyrantel, Fenbendazole, etc.">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="vet-alert-btn">SAVE</button>
                    <button type="reset" class="patients-view-btn">Clear</button>
                </div>
            </form>
        </section>

        <section class="vet-data-grid">
            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title"><i class="bi bi-shield-plus me-2"></i>Add vaccination</h3>
                </div>
                <form method="POST" class="p-3 p-md-4">
                    <input type="hidden" name="form_action" value="add_vaccination">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-secondary">Vaccine name <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="vaccine_name" class="form-control" list="vaccine_list" value="<?= htmlspecialchars($vaccination_form['vaccine_name']) ?>" required>
                            <datalist id="vaccine_list">
                                <?php foreach (($vaccine_catalog[$pet['species']] ?? []) as $vaccine): ?>
                                <option value="<?= htmlspecialchars($vaccine) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Date given <span style="color: #dc2626;">*</span></label>
                            <input type="date" name="date_given" class="form-control" value="<?= htmlspecialchars($vaccination_form['date_given']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Next due date</label>
                            <input type="date" name="next_due_date" class="form-control" value="<?= htmlspecialchars($vaccination_form['next_due_date']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Dose number</label>
                            <input type="text" name="dose_number" class="form-control" value="<?= htmlspecialchars($vaccination_form['dose_number']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Batch number</label>
                            <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($vaccination_form['batch_number']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($vaccination_form['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="vet-alert-btn">SAVE</button>
                        <button type="reset" class="patients-view-btn">Clear</button>
                    </div>
                </form>
            </article>

            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title"><i class="bi bi-droplet me-2"></i>Add deworming</h3>
                </div>
                <form method="POST" class="p-3 p-md-4">
                    <input type="hidden" name="form_action" value="add_deworming">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-secondary">Deworming type <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="product_name" class="form-control" list="deworming_list" value="<?= htmlspecialchars($deworming_form['product_name']) ?>" required>
                            <datalist id="deworming_list">
                                <?php foreach (($deworming_catalog[$pet['species']] ?? ['Pyrantel', 'Fenbendazole', 'Praziquantel']) as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Date given <span style="color: #dc2626;">*</span></label>
                            <input type="date" name="date_given" class="form-control" value="<?= htmlspecialchars($deworming_form['date_given']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Next due date</label>
                            <input type="date" name="next_due_date" class="form-control" value="<?= htmlspecialchars($deworming_form['next_due_date']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Dose</label>
                            <input type="text" name="dose" class="form-control" value="<?= htmlspecialchars($deworming_form['dose']) ?>">
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($deworming_form['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="vet-alert-btn">SAVE</button>
                        <button type="reset" class="patients-view-btn">Clear</button>
                    </div>
                </form>
            </article>
        </section>

        <section class="vet-data-grid mt-3">
            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title"><i class="bi bi-shield-check me-2"></i>Vaccination records</h3>
                </div>
                <div class="table-responsive">
                    <table class="vet-panel-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Date given</th>
                                <th>Next due</th>
                                <th>Dose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vaccinations)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No vaccination records yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($vaccinations as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['vaccine_name']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['date_given']))) ?></td>
                                    <td><?= !empty($row['next_due_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['next_due_date']))) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($row['dose_number'] ?? 'N/A') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="vet-panel">
                <div class="vet-panel-header">
                    <h3 class="vet-panel-title"><i class="bi bi-droplet me-2"></i>Deworming records</h3>
                </div>
                <div class="table-responsive">
                    <table class="vet-panel-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Date given</th>
                                <th>Next due</th>
                                <th>Dose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dewormings)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No deworming records yet</td></tr>
                            <?php else: ?>
                                <?php foreach ($dewormings as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['date_given']))) ?></td>
                                    <td><?= !empty($row['next_due_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['next_due_date']))) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($row['dose'] ?? 'N/A') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>