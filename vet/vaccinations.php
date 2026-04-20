<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'vaccinations';
$success = '';
$error = '';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    $error = 'Invalid session. Please login again.';
}

$form_data = [
    'pet_id' => '',
    'vaccine_name' => '',
    'date_given' => '',
    'next_due_date' => '',
    'dose_number' => '',
    'batch_number' => '',
    'notes' => ''
];

$vaccine_catalog = [
    'Dog' => ['Rabies', 'DHPP', 'Parvovirus', 'Leptospirosis', 'Bordetella', 'Canine Influenza'],
    'Cat' => ['Rabies', 'FVRCP', 'FeLV', 'Chlamydia', 'Bordetella'],
    'Bird' => ['Polyomavirus', 'Pox', 'PBFD'],
    'Rabbit' => ['Myxomatosis', 'RHDV1', 'RHDV2'],
    'Hamster' => ['No routine vaccine']
];

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
    $form_data['vaccine_name'] = trim($_POST['vaccine_name'] ?? '');
    $form_data['date_given'] = trim($_POST['date_given'] ?? '');
    $form_data['next_due_date'] = trim($_POST['next_due_date'] ?? '');
    $form_data['dose_number'] = trim($_POST['dose_number'] ?? '');
    $form_data['batch_number'] = trim($_POST['batch_number'] ?? '');
    $form_data['notes'] = trim($_POST['notes'] ?? '');

    if ($form_data['pet_id'] <= 0) {
        $error = 'Please select a patient.';
    } elseif ($form_data['vaccine_name'] === '') {
        $error = 'Vaccine name is required.';
    } elseif ($form_data['date_given'] === '') {
        $error = 'Date given is required.';
    }

    if ($error === '') {
        $pet_check = mysqli_query(
            $conn,
            "SELECT id, species FROM pets WHERE id = {$form_data['pet_id']} AND vet_id = $vet_id LIMIT 1"
        );

        if (!$pet_check || mysqli_num_rows($pet_check) === 0) {
            $error = 'Invalid patient selected.';
        } else {
            $pet_row = mysqli_fetch_assoc($pet_check);
            $pet_species = $pet_row['species'] ?? '';
            $allowed_vaccines = $vaccine_catalog[$pet_species] ?? [];
            if (!in_array($form_data['vaccine_name'], $allowed_vaccines, true)) {
                $error = 'Invalid vaccine selected for this species.';
            }
        }
    }

    if ($error === '') {
        $pet_id = (int)$form_data['pet_id'];
        $vaccine_name = mysqli_real_escape_string($conn, $form_data['vaccine_name']);
        $date_given = mysqli_real_escape_string($conn, $form_data['date_given']);
        $next_due_date = $form_data['next_due_date'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['next_due_date']) . "'"
            : 'NULL';
        $dose_number = $form_data['dose_number'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['dose_number']) . "'"
            : 'NULL';
        $batch_number = $form_data['batch_number'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['batch_number']) . "'"
            : 'NULL';
        $notes = $form_data['notes'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $form_data['notes']) . "'"
            : 'NULL';

        $insert_query = "
            INSERT INTO vaccinations (pet_id, vet_id, vaccine_name, date_given, next_due_date, dose_number, batch_number, notes, created_at)
            VALUES ($pet_id, $vet_id, '$vaccine_name', '$date_given', $next_due_date, $dose_number, $batch_number, $notes, NOW())
        ";

        if (mysqli_query($conn, $insert_query)) {
            $success = 'Vaccination record added successfully.';
            $form_data = [
                'pet_id' => '',
                'vaccine_name' => '',
                'date_given' => '',
                'next_due_date' => '',
                'dose_number' => '',
                'batch_number' => '',
                'notes' => ''
            ];
        } else {
            $error = 'Database error: ' . mysqli_error($conn);
        }
    }
}

$vaccinations = [];
if ($vet_id > 0) {
    $vaccination_query = mysqli_query(
        $conn,
        "SELECT v.id, v.vaccine_name, v.date_given, v.next_due_date, v.dose_number,
                p.name AS pet_name, p.species
         FROM vaccinations v
         JOIN pets p ON v.pet_id = p.id
         WHERE v.vet_id = $vet_id
         ORDER BY v.date_given DESC, v.id DESC"
    );

    if ($vaccination_query) {
        while ($row = mysqli_fetch_assoc($vaccination_query)) {
            $vaccinations[] = $row;
        }
    }
}

function get_due_status($next_due_date)
{
    if (!$next_due_date || $next_due_date === '0000-00-00') {
        return ['label' => 'No due date', 'class' => 'vet-pill-status'];
    }

    $today = new DateTime(date('Y-m-d'));
    $due = new DateTime($next_due_date);

    if ($due < $today) {
        return ['label' => 'Overdue', 'class' => 'vet-pill-overdue'];
    }

    $days_left = (int)$today->diff($due)->days;
    if ($days_left <= 14) {
        return ['label' => 'Due soon', 'class' => 'vet-pill-soon'];
    }

    return ['label' => 'Up to date', 'class' => 'vet-pill-updated'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vaccinations - PetCura</title>

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

        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Add vaccination</h3>
            </div>
            <form method="POST" class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Patient <span style="color: #dc2626;">*</span></label>
                        <select id="pet_id" name="pet_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach ($pets as $pet): ?>
                            <option
                                value="<?= (int)$pet['id'] ?>"
                                data-species="<?= htmlspecialchars($pet['species']) ?>"
                                <?= (int)$form_data['pet_id'] === (int)$pet['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($pet['name']) ?> (<?= htmlspecialchars($pet['species']) ?>) - Owner: <?= htmlspecialchars(trim($pet['owner_name']) !== '' ? trim($pet['owner_name']) : 'Unknown') ?><?= !empty($pet['owner_email']) ? ' [' . htmlspecialchars($pet['owner_email']) . ']' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Vaccine name <span style="color: #dc2626;">*</span></label>
                        <select id="vaccine_name" name="vaccine_name" class="form-select" required>
                            <option value="">Select vaccine</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Date given <span style="color: #dc2626;">*</span></label>
                        <input type="date" name="date_given" class="form-control" value="<?= htmlspecialchars($form_data['date_given']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Next due date</label>
                        <input type="date" name="next_due_date" class="form-control" value="<?= htmlspecialchars($form_data['next_due_date']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Dose number</label>
                        <input type="text" name="dose_number" class="form-control" value="<?= htmlspecialchars($form_data['dose_number']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Batch number</label>
                        <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($form_data['batch_number']) ?>">
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

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Vaccination records</h3>
            </div>
            <div class="table-responsive">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Date given</th>
                            <th>Next due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vaccinations)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No vaccination records yet</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($vaccinations as $row): ?>
                                <?php $status = get_due_status($row['next_due_date']); ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pet_name']) ?></td>
                                    <td><?= htmlspecialchars($row['vaccine_name']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['date_given']))) ?></td>
                                    <td>
                                        <?= $row['next_due_date'] ? htmlspecialchars(date('M d, Y', strtotime($row['next_due_date']))) : 'N/A' ?>
                                    </td>
                                    <td>
                                        <span class="vet-pill <?= htmlspecialchars($status['class']) ?>">
                                            <?= htmlspecialchars($status['label']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script>
const vaccineCatalog = <?= json_encode($vaccine_catalog, JSON_UNESCAPED_UNICODE) ?>;
const selectedVaccine = <?= json_encode($form_data['vaccine_name'], JSON_UNESCAPED_UNICODE) ?>;
const petSelect = document.getElementById('pet_id');
const vaccineSelect = document.getElementById('vaccine_name');

function populateVaccineOptions() {
    if (!petSelect || !vaccineSelect) {
        return;
    }

    const selectedPet = petSelect.options[petSelect.selectedIndex];
    const species = selectedPet ? selectedPet.getAttribute('data-species') : '';
    const vaccines = species && vaccineCatalog[species] ? vaccineCatalog[species] : [];

    vaccineSelect.innerHTML = '<option value="">Select vaccine</option>';

    vaccines.forEach((vaccine) => {
        const option = document.createElement('option');
        option.value = vaccine;
        option.textContent = vaccine;
        if (vaccine === selectedVaccine) {
            option.selected = true;
        }
        vaccineSelect.appendChild(option);
    });
}

if (petSelect && vaccineSelect) {
    petSelect.addEventListener('change', function() {
        vaccineSelect.dataset.prev = vaccineSelect.value;
        populateVaccineOptions();
    });
    populateVaccineOptions();
}
</script>
</body>
</html>