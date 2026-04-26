<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'pet_records';
$owner_id = (int)($_SESSION['user_id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

function calculate_age($dob) {
    if (empty($dob) || $dob === '0000-00-00' || $dob === '1970-01-01') {
        return 'N/A';
    }

    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate);

    if ($age->y > 0) {
        return $age->y . ' yr' . ($age->y > 1 ? 's' : '');
    }
    if ($age->m > 0) {
        return $age->m . ' mo' . ($age->m > 1 ? 's' : '');
    }

    return $age->d . ' day' . ($age->d > 1 ? 's' : '');
}

$pets = [];
$pets_stmt = mysqli_prepare($conn,
    "SELECT id, name, species, breed, dob, status FROM pets WHERE owner_id = ? ORDER BY name ASC");
if ($pets_stmt) {
    mysqli_stmt_bind_param($pets_stmt, 'i', $owner_id);
    mysqli_stmt_execute($pets_stmt);
    $pets_result = mysqli_stmt_get_result($pets_stmt);

    while ($pet = mysqli_fetch_assoc($pets_result)) {
        $pet['last_vaccine'] = null;

        $vax_stmt = mysqli_prepare($conn,
            "SELECT vaccine_name, next_due_date FROM vaccinations WHERE pet_id = ? ORDER BY date_given DESC LIMIT 1");
        if ($vax_stmt) {
            mysqli_stmt_bind_param($vax_stmt, 'i', $pet['id']);
            mysqli_stmt_execute($vax_stmt);
            $vax_result = mysqli_stmt_get_result($vax_stmt);
            $pet['last_vaccine'] = $vax_result ? mysqli_fetch_assoc($vax_result) : null;
            mysqli_stmt_close($vax_stmt);
        }

        $pets[] = $pet;
    }

    mysqli_stmt_close($pets_stmt);
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
        <section class="owner-panel mb-4">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">Pet Records</h3>
            </div>

            <div class="owner-pets-grid">
                <?php if (empty($pets)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #999;">
                    <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                    <p>No pets registered yet. Contact your veterinarian to register your pets.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($pets as $pet): ?>
                    <?php
                    $vax_label = 'Not vaccinated';
                    $vax_class = 'owner-status-overdue';
                    $next_due_text = 'No vaccination scheduled';

                    if (!empty($pet['last_vaccine']) && !empty($pet['last_vaccine']['next_due_date'])) {
                        $next_due = new DateTime($pet['last_vaccine']['next_due_date']);
                        $today = new DateTime();

                        if ($today > $next_due) {
                            $vax_label = 'OVERDUE';
                            $vax_class = 'owner-status-overdue';
                        } else {
                            $vax_label = 'UP TO DATE';
                            $vax_class = 'owner-status-updated';
                        }

                        $next_due_text = $pet['last_vaccine']['vaccine_name'] . ' (' . date('M Y', strtotime($pet['last_vaccine']['next_due_date'])) . ')';
                    }

                    $age = calculate_age($pet['dob']);
                    ?>
                    <div class="owner-pet-card">
                        <div class="owner-pet-header">
                            <div class="owner-pet-avatar"><i class="bi bi-paw-fill"></i></div>
                            <div class="owner-pet-name-section">
                                <h5 class="owner-pet-name"><?= htmlspecialchars($pet['name']) ?></h5>
                                <p class="owner-pet-breed"><?= htmlspecialchars($pet['species']) ?> · <?= htmlspecialchars($age) ?></p>
                            </div>
                            <span class="owner-pet-status <?= $vax_class ?>"><?= $vax_label ?></span>
                        </div>
                        <div class="owner-pet-info">
                            <div class="owner-pet-info-row">
                                <span class="owner-info-label">Breed:</span>
                                <span class="owner-info-value"><?= htmlspecialchars($pet['breed'] ?: 'N/A') ?></span>
                            </div>
                            <div class="owner-pet-info-row">
                                <span class="owner-info-label">Next Due:</span>
                                <span class="owner-info-value"><?= htmlspecialchars($next_due_text) ?></span>
                            </div>
                        </div>
                        <a href="pet_detail.php?id=<?= (int)$pet['id'] ?>" class="owner-pet-btn">View health records</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
