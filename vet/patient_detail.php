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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['form_action'] ?? '');

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
            $vaccine_name = mysqli_real_escape_string($conn, $vaccination_form['vaccine_name']);
            $date_given = mysqli_real_escape_string($conn, $vaccination_form['date_given']);
            $next_due_date = $vaccination_form['next_due_date'] !== '' ? "'" . mysqli_real_escape_string($conn, $vaccination_form['next_due_date']) . "'" : 'NULL';
            $dose_number = $vaccination_form['dose_number'] !== '' ? "'" . mysqli_real_escape_string($conn, $vaccination_form['dose_number']) . "'" : 'NULL';
            $batch_number = $vaccination_form['batch_number'] !== '' ? "'" . mysqli_real_escape_string($conn, $vaccination_form['batch_number']) . "'" : 'NULL';
            $notes = $vaccination_form['notes'] !== '' ? "'" . mysqli_real_escape_string($conn, $vaccination_form['notes']) . "'" : 'NULL';

            $insert = "
                INSERT INTO vaccinations (pet_id, vet_id, vaccine_name, date_given, next_due_date, dose_number, batch_number, notes, created_at)
                VALUES ($pet_id, $vet_id, '$vaccine_name', '$date_given', $next_due_date, $dose_number, $batch_number, $notes, NOW())
            ";

            if (mysqli_query($conn, $insert)) {
                $success = 'Vaccination record saved.';
                $vaccination_form = ['vaccine_name' => '', 'date_given' => '', 'next_due_date' => '', 'dose_number' => '', 'batch_number' => '', 'notes' => ''];
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
            $product_name = mysqli_real_escape_string($conn, $deworming_form['product_name']);
            $date_given = mysqli_real_escape_string($conn, $deworming_form['date_given']);
            $next_due_date = $deworming_form['next_due_date'] !== '' ? "'" . mysqli_real_escape_string($conn, $deworming_form['next_due_date']) . "'" : 'NULL';
            $dose = $deworming_form['dose'] !== '' ? "'" . mysqli_real_escape_string($conn, $deworming_form['dose']) . "'" : 'NULL';
            $notes = $deworming_form['notes'] !== '' ? "'" . mysqli_real_escape_string($conn, $deworming_form['notes']) . "'" : 'NULL';

            $insert = "
                INSERT INTO dewormings (pet_id, vet_id, product_name, date_given, next_due_date, dose, notes, created_at)
                VALUES ($pet_id, $vet_id, '$product_name', '$date_given', $next_due_date, $dose, $notes, NOW())
            ";

            if (mysqli_query($conn, $insert)) {
                $success = 'Deworming record saved.';
                $deworming_form = ['product_name' => '', 'date_given' => '', 'next_due_date' => '', 'dose' => '', 'notes' => ''];
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
                    <span class="vet-pill <?= $vaccinated ? 'vet-pill-updated' : 'vet-pill-overdue' ?>"><?= $vaccinated ? 'Vaccinated' : 'Not vaccinated' ?></span>
                    <span class="vet-pill <?= $dewormed ? 'vet-pill-updated' : 'vet-pill-soon' ?>"><?= $dewormed ? 'Dewormed' : 'No deworming record' ?></span>
                </div>
            </div>
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