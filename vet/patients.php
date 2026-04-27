<?php
// ═══════════════════════════════════════
// BACKEND — Vet Patients List
// ═══════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_helper.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$active_page = 'patients';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    header('Location: ../login.php');
    exit();
}

// ── Get filter parameters ────────────
$search_query = $_GET['search'] ?? '';
$filter_species = $_GET['species'] ?? '';
$filter_status = $_GET['status'] ?? '';

// ── Build pets query with filters ────
$where_conditions = [];

// Only show pets assigned to the currently logged-in vet.
$where_conditions[] = "p.vet_id = $vet_id";

if (!empty($search_query)) {
    $search = mysqli_real_escape_string($conn, $search_query);
    $where_conditions[] = "(p.name LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}

if (!empty($filter_species)) {
    $species = mysqli_real_escape_string($conn, $filter_species);
    $where_conditions[] = "p.species = '$species'";
}

if (!empty($filter_status)) {
    $status = mysqli_real_escape_string($conn, $filter_status);
    $where_conditions[] = "p.status = '$status'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ── Fetch all pets with owner info ───
$pets_query = "
    SELECT p.id, p.name, p.species, p.breed, p.dob, p.status, p.last_visit,
           u.id as owner_id, CONCAT(u.first_name, ' ', u.last_name) as owner_name,
           u.email as owner_email
    FROM pets p
    JOIN users u ON p.owner_id = u.id
    $where_clause
    ORDER BY p.name ASC
";

$pets_result = mysqli_query($conn, $pets_query);
$pets = [];

// ── Helper: Calculate age from DOB ───
function calculate_age($dob) {
    if (empty($dob) || $dob === '0000-00-00' || $dob === '1970-01-01') {
        return 'N/A';
    }
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    
    if ($age->y > 0) {
        return $age->y . ' yr' . ($age->y > 1 ? 's' : '');
    } elseif ($age->m > 0) {
        return $age->m . ' mo' . ($age->m > 1 ? 's' : '');
    } else {
        return $age->d . ' day' . ($age->d > 1 ? 's' : '');
    }
}



// ── Helper: Pick patient-page care summary record ────────────
// Priority: nearest upcoming date -> latest overdue date -> latest active record without date.
function petcura_fetch_patient_due_record($conn, $table, $name_column, $date_column, $pet_id, $vet_id) {
    $allowed_tables = ['vaccinations', 'dewormings', 'treatments'];
    $allowed_columns = ['vaccine_name', 'product_name', 'diagnosis', 'next_due_date', 'followup_date', 'date_given', 'treatment_date'];

    if (!in_array($table, $allowed_tables, true) || !in_array($name_column, $allowed_columns, true) || !in_array($date_column, $allowed_columns, true)) {
        return null;
    }

    $status_column = $table === 'treatments' ? 'followup_status' : 'reminder_status';
    $main_date_column = $table === 'treatments' ? 'treatment_date' : 'date_given';
    $name_expr = $table === 'treatments'
        ? "COALESCE(NULLIF(TRIM($name_column), ''), 'Treatment') AS record_name"
        : "$name_column AS record_name";

    // 1) First show the nearest upcoming due/follow-up date.
    $sql = "SELECT $name_expr, $main_date_column AS record_date, $date_column AS due_date
            FROM $table
            WHERE pet_id = ?
              AND vet_id = ?
              AND $status_column = 'active'
              AND $date_column IS NOT NULL
              AND $date_column <> '0000-00-00'
              AND $date_column >= CURDATE()
            ORDER BY $date_column ASC, id DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $pet_id, $vet_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            return $row;
        }
    }

    // 2) If there is no upcoming date, show the latest overdue active due/follow-up.
    $sql = "SELECT $name_expr, $main_date_column AS record_date, $date_column AS due_date
            FROM $table
            WHERE pet_id = ?
              AND vet_id = ?
              AND $status_column = 'active'
              AND $date_column IS NOT NULL
              AND $date_column <> '0000-00-00'
              AND $date_column < CURDATE()
            ORDER BY $date_column DESC, id DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $pet_id, $vet_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            return $row;
        }
    }

    // 3) If all active records have no due/follow-up date, show latest active record as No date.
    $sql = "SELECT $name_expr, $main_date_column AS record_date, $date_column AS due_date
            FROM $table
            WHERE pet_id = ?
              AND vet_id = ?
              AND $status_column = 'active'
            ORDER BY $main_date_column DESC, id DESC
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $pet_id, $vet_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        if ($row) {
            return $row;
        }
    }

    return null;
}

// ── Build pets array with processed data
while ($pet = mysqli_fetch_assoc($pets_result)) {
    $pet_id = (int)$pet['id'];

    // Patient page care summary must show:
    // 1) nearest upcoming date, 2) latest overdue date, 3) no date if there is no due/follow-up date.
    $vacc_row = petcura_fetch_patient_due_record($conn, 'vaccinations', 'vaccine_name', 'next_due_date', $pet_id, $vet_id);
    $latest_vaccination_name = $vacc_row ? (string)($vacc_row['record_name'] ?? '') : '';
    $latest_vaccination_due = $vacc_row ? (string)($vacc_row['due_date'] ?? '') : '';

    $deworm_row = petcura_fetch_patient_due_record($conn, 'dewormings', 'product_name', 'next_due_date', $pet_id, $vet_id);
    $latest_deworming_name = $deworm_row ? (string)($deworm_row['record_name'] ?? '') : '';
    $latest_deworming_due = $deworm_row ? (string)($deworm_row['due_date'] ?? '') : '';

    $vacc_status = petcura_due_status($latest_vaccination_due);
    if ($vacc_status['key'] === 'no-date') {
        $vacc_status = ['label' => 'No due date', 'class' => 'vet-pill-status'];
    }

    $deworm_status = petcura_due_status($latest_deworming_due);
    if ($deworm_status['key'] === 'no-date') {
        $deworm_status = ['label' => 'No due date', 'class' => 'vet-pill-status'];
    }

    $treat_row = petcura_fetch_patient_due_record($conn, 'treatments', 'diagnosis', 'followup_date', $pet_id, $vet_id);
    $latest_treatment_title = $treat_row ? (string)($treat_row['record_name'] ?? '') : '';
    $latest_treatment_date = $treat_row ? (string)($treat_row['record_date'] ?? '') : '';
    $latest_followup_due = $treat_row ? (string)($treat_row['due_date'] ?? '') : '';

    $treatment_status = petcura_due_status($latest_followup_due);
    if ($treatment_status['key'] === 'no-date') {
        $treatment_status = ['label' => 'No follow-up', 'class' => 'vet-pill-status'];
    }

    $pet['vaccination_display'] = $vacc_status;
    $pet['deworming_display'] = $deworm_status;
    $pet['treatment_display'] = $treatment_status;
    $pet['latest_vaccination_name'] = $latest_vaccination_name;
    $pet['latest_vaccination_due'] = $latest_vaccination_due;
    $pet['latest_deworming_name'] = $latest_deworming_name;
    $pet['latest_deworming_due'] = $latest_deworming_due;
    $pet['latest_treatment_title'] = $latest_treatment_title;
    $pet['latest_treatment_date'] = $latest_treatment_date;
    $pet['latest_followup_due'] = $latest_followup_due;
    $pet['age'] = calculate_age($pet['dob']);
    $pets[] = $pet;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Patients - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <section class="patients-header">
            <h1 class="patients-title">My patients</h1>
            <p class="patients-sub"><?= count($pets) ?> total patients in your clinic</p>
        </section>

        <form class="patients-toolbar" method="GET">
            <input type="text" class="patients-search" name="search" placeholder="Search pet or owner..." value="<?= htmlspecialchars($search_query) ?>"/>

            <select class="patients-select" name="species" onchange="this.form.submit()">
                <option value="">All species</option>
                <option value="Dog" <?= $filter_species === 'Dog' ? 'selected' : '' ?>>Dog</option>
                <option value="Cat" <?= $filter_species === 'Cat' ? 'selected' : '' ?>>Cat</option>
                <option value="Bird" <?= $filter_species === 'Bird' ? 'selected' : '' ?>>Bird</option>
                <option value="Rabbit" <?= $filter_species === 'Rabbit' ? 'selected' : '' ?>>Rabbit</option>
                <option value="Hamster" <?= $filter_species === 'Hamster' ? 'selected' : '' ?>>Hamster</option>
            </select>

            <select class="patients-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="Healthy" <?= $filter_status === 'Healthy' ? 'selected' : '' ?>>Healthy</option>
                <option value="Sick" <?= $filter_status === 'Sick' ? 'selected' : '' ?>>Sick</option>
                <option value="Treatment" <?= $filter_status === 'Treatment' ? 'selected' : '' ?>>Treatment</option>
                <option value="Recovering" <?= $filter_status === 'Recovering' ? 'selected' : '' ?>>Recovering</option>
            </select>

            <a href="register_pet.php" class="patients-add-btn">
                <i class="bi bi-plus-lg"></i>
                <span>Register new pet</span>
            </a>
        </form>

        <section class="patients-table-wrap">
            <div class="table-responsive">
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th>Pet name</th>
                            <th>Species</th>
                            <th>Breed</th>
                            <th>Age</th>
                            <th>Last visit</th>
                            <th>Owner</th>
                            <th>Care summary</th>
                            <th>Health</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pets)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-2"></i>
                                No patients found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($pets as $pet): ?>
                            <tr>
                                <td class="patients-pet-name"><?= htmlspecialchars($pet['name']) ?></td>
                                <td><?= htmlspecialchars($pet['species']) ?></td>
                                <td><?= htmlspecialchars($pet['breed'] ?? 'N/A') ?></td>
                                <td><?= $pet['age'] ?></td>
                                <td><?= !empty($pet['last_visit']) ? htmlspecialchars(date('M d, Y', strtotime($pet['last_visit']))) : 'N/A' ?></td>
                                <td>
                                    <div class="small">
                                        <strong><?= htmlspecialchars($pet['owner_name']) ?></strong>
                                        <br/>
                                        <span class="text-muted"><?= htmlspecialchars($pet['owner_email']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="care-summary-flex">
                                        <div class="care-summary-item">
                                            <span class="care-summary-label">Vaccine</span>
                                            <span class="vet-pill <?= $pet['vaccination_display']['class'] ?>"><?= htmlspecialchars($pet['vaccination_display']['label']) ?></span>
                                            <span class="care-summary-detail">
                                                <?= htmlspecialchars($pet['latest_vaccination_name'] !== '' ? $pet['latest_vaccination_name'] : 'No record') ?>
                                                <?php if (!empty($pet['latest_vaccination_due'])): ?>
                                                    • <?= htmlspecialchars(date('M d, Y', strtotime($pet['latest_vaccination_due']))) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="care-summary-divider"></div>
                                        <div class="care-summary-item">
                                            <span class="care-summary-label">Deworm</span>
                                            <span class="vet-pill <?= $pet['deworming_display']['class'] ?>"><?= htmlspecialchars($pet['deworming_display']['label']) ?></span>
                                            <span class="care-summary-detail">
                                                <?= htmlspecialchars($pet['latest_deworming_name'] !== '' ? $pet['latest_deworming_name'] : 'No record') ?>
                                                <?php if (!empty($pet['latest_deworming_due'])): ?>
                                                    • <?= htmlspecialchars(date('M d, Y', strtotime($pet['latest_deworming_due']))) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="care-summary-divider"></div>
                                        <div class="care-summary-item">
                                            <span class="care-summary-label">Treatment</span>
                                            <span class="vet-pill <?= $pet['treatment_display']['class'] ?>"><?= htmlspecialchars($pet['treatment_display']['label']) ?></span>
                                            <span class="care-summary-detail">
                                                <?= htmlspecialchars($pet['latest_treatment_title'] !== '' ? $pet['latest_treatment_title'] : 'No treatment record') ?>
                                                <?php if (!empty($pet['latest_treatment_date'])): ?>
                                                    • Last: <?= htmlspecialchars(date('M d, Y', strtotime($pet['latest_treatment_date']))) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($pet['latest_followup_due'])): ?>
                                                    • Follow-up: <?= htmlspecialchars(date('M d, Y', strtotime($pet['latest_followup_due']))) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="vet-pill vet-health-<?= strtolower(str_replace(' ', '-', $pet['status'])) ?>">
                                        <?= htmlspecialchars($pet['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="patient_detail.php?id=<?= $pet['id'] ?>" class="patients-view-btn">View</a>
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
</body>
</html>