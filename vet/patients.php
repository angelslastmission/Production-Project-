<?php
// ═══════════════════════════════════════
// BACKEND — Vet Patients List
// ═══════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'patients';

// ── Get filter parameters ────────────
$search_query = $_GET['search'] ?? '';
$filter_species = $_GET['species'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_vaccine = $_GET['vaccine_status'] ?? '';

// ── Build pets query with filters ────
$where_conditions = [];

// Filter by clinic (assuming pets have clinic_id or vet_id relationship)
// For now, we'll assume all vets in a clinic see all pets
// Adjust based on your actual schema

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
    SELECT p.id, p.name, p.species, p.breed, p.dob, p.status,
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

// ── Helper: Get vaccine status ───────
function get_vaccine_status($conn, $pet_id) {
    $vax_query = "
        SELECT MAX(next_due_date) as latest_due
        FROM vaccinations
        WHERE pet_id = $pet_id
        ORDER BY next_due_date DESC
        LIMIT 1
    ";
    
    $result = mysqli_fetch_assoc(mysqli_query($conn, $vax_query));
    
    if (!$result || empty($result['latest_due']) || $result['latest_due'] === '0000-00-00') {
        return ['status' => 'not-recorded', 'label' => 'Not recorded', 'class' => 'vet-pill-status'];
    }
    
    $due_date = new DateTime($result['latest_due']);
    $today = new DateTime();
    $interval = $today->diff($due_date);
    
    if ($today > $due_date) {
        return ['status' => 'overdue', 'label' => 'Overdue', 'class' => 'vet-pill-overdue'];
    } elseif ($interval->days <= 14) {
        return ['status' => 'soon', 'label' => 'Due soon', 'class' => 'vet-pill-soon'];
    } else {
        return ['status' => 'updated', 'label' => 'Up to date', 'class' => 'vet-pill-updated'];
    }
}

// ── Build pets array with processed data
while ($pet = mysqli_fetch_assoc($pets_result)) {
    $vax_status = get_vaccine_status($conn, $pet['id']);
    $pet['vaccine_status'] = $vax_status;
    $pet['age'] = calculate_age($pet['dob']);
    $pets[] = $pet;
}

// ── Apply vaccine status filter ──────
if (!empty($filter_vaccine)) {
    $pets = array_filter($pets, function($pet) use ($filter_vaccine) {
        return $pet['vaccine_status']['status'] === $filter_vaccine;
    });
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

            <select class="patients-select" name="vaccine_status" onchange="this.form.submit()">
                <option value="">All vaccine statuses</option>
                <option value="overdue" <?= $filter_vaccine === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="soon" <?= $filter_vaccine === 'soon' ? 'selected' : '' ?>>Due soon</option>
                <option value="updated" <?= $filter_vaccine === 'updated' ? 'selected' : '' ?>>Up to date</option>
                <option value="not-recorded" <?= $filter_vaccine === 'not-recorded' ? 'selected' : '' ?>>Not recorded</option>
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
                            <th>Owner</th>
                            <th>Vaccine status</th>
                            <th>Health</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pets)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
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
                                <td>
                                    <div class="small">
                                        <strong><?= htmlspecialchars($pet['owner_name']) ?></strong>
                                        <br/>
                                        <span class="text-muted"><?= htmlspecialchars($pet['owner_email']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="vet-pill <?= $pet['vaccine_status']['class'] ?>">
                                        <?= $pet['vaccine_status']['label'] ?>
                                    </span>
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
