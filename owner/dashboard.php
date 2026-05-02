<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'dashboard';
$owner_id = (int)($_SESSION['user_id'] ?? 0);
$owner_name = $_SESSION['user_name'] ?? 'Owner';

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

// Ensure greeting uses the app's local timezone.
$app_timezone = $_SESSION['user_timezone'] ?? 'Asia/Kathmandu';
if (!in_array($app_timezone, timezone_identifiers_list(), true)) {
    $app_timezone = 'Asia/Kathmandu';
}
date_default_timezone_set($app_timezone);

// Determine greeting based on time
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

$clinic_name = $_SESSION['clinic_name'] ?? 'Pet Health Clinic';

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

// ── Fetch owner's pets ───────────────
$pets = [];
$pets_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.name, p.species, p.breed, p.dob, p.status,
            CONCAT(COALESCE(v.first_name, ''), ' ', COALESCE(v.last_name, '')) AS vet_name,
            v.clinic_name
     FROM pets p
     LEFT JOIN users v ON v.id = p.vet_id AND v.role = 'vet'
     WHERE p.owner_id = ?
     ORDER BY p.name ASC");
if ($pets_stmt) {
    mysqli_stmt_bind_param($pets_stmt, 'i', $owner_id);
    mysqli_stmt_execute($pets_stmt);
    $pets_result = mysqli_stmt_get_result($pets_stmt);
    
    while ($pet = mysqli_fetch_assoc($pets_result)) {
        // Get most recent vaccination for this pet
        $vax_stmt = mysqli_prepare($conn,
            "SELECT vaccine_name, next_due_date FROM vaccinations WHERE pet_id = ? ORDER BY date_given DESC LIMIT 1");
        if ($vax_stmt) {
            mysqli_stmt_bind_param($vax_stmt, 'i', $pet['id']);
            mysqli_stmt_execute($vax_stmt);
            $vax_result = mysqli_stmt_get_result($vax_stmt);
            $pet['last_vaccine'] = mysqli_fetch_assoc($vax_result);
            mysqli_stmt_close($vax_stmt);
        }
        
        $pets[] = $pet;
    }
    mysqli_stmt_close($pets_stmt);
}

$unvaccinated_pet_count = 0;
foreach ($pets as $pet_item) {
    if (empty($pet_item['last_vaccine'])) {
        $unvaccinated_pet_count++;
    }
}

// ── Fetch reminder alerts (vaccination, deworming, treatment follow-up) ──────────
$overdue_alerts = [];
$upcoming_alerts = [];

function owner_alert_type_label($type) {
    if ($type === 'vaccination') return 'vaccination';
    if ($type === 'deworming') return 'deworming';
    if ($type === 'treatment') return 'treatment follow-up';
    return 'reminder';
}

function owner_add_alert(&$bucket, $row, $type, $title_key, $date_key) {
    $bucket[] = [
        'type' => $type,
        'pet_id' => (int)($row['pet_id'] ?? 0),
        'pet_name' => (string)($row['pet_name'] ?? 'Pet'),
        'title' => (string)($row[$title_key] ?? ''),
        'due_date' => (string)($row[$date_key] ?? '')
    ];
}

// Latest vaccination record per vaccine
$overdue_vax_stmt = mysqli_prepare($conn,
    "SELECT p.id AS pet_id, p.name AS pet_name, v.vaccine_name, v.next_due_date, v.date_given,
            CASE WHEN v.date_given >= v.next_due_date THEN 1 ELSE 0 END AS is_revaccinated
     FROM vaccinations v
     JOIN pets p ON p.id = v.pet_id
     WHERE p.owner_id = ? AND v.next_due_date IS NOT NULL AND v.next_due_date < CURDATE()
       AND v.id = (SELECT id FROM vaccinations v2
                   WHERE v2.pet_id = v.pet_id AND v2.vaccine_name = v.vaccine_name
                   ORDER BY v2.date_given DESC LIMIT 1)
     ORDER BY v.next_due_date ASC");
if ($overdue_vax_stmt) {
    mysqli_stmt_bind_param($overdue_vax_stmt, 'i', $owner_id);
    mysqli_stmt_execute($overdue_vax_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_vax_stmt);

    while ($row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null) {
        if (empty($row['is_revaccinated'])) {
            owner_add_alert($overdue_alerts, $row, 'vaccination', 'vaccine_name', 'next_due_date');
        }
    }

    mysqli_stmt_close($overdue_vax_stmt);
}

$overdue_deworm_stmt = mysqli_prepare($conn,
    "SELECT p.id AS pet_id, p.name AS pet_name, d.product_name, d.next_due_date, d.date_given,
            CASE WHEN d.date_given >= d.next_due_date THEN 1 ELSE 0 END AS is_redone
     FROM dewormings d
     JOIN pets p ON p.id = d.pet_id
     WHERE p.owner_id = ? AND d.next_due_date IS NOT NULL AND d.next_due_date < CURDATE()
       AND d.id = (SELECT id FROM dewormings d2
                   WHERE d2.pet_id = d.pet_id AND d2.product_name = d.product_name
                   ORDER BY d2.date_given DESC LIMIT 1)
     ORDER BY d.next_due_date ASC");
if ($overdue_deworm_stmt) {
    mysqli_stmt_bind_param($overdue_deworm_stmt, 'i', $owner_id);
    mysqli_stmt_execute($overdue_deworm_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_deworm_stmt);

    while ($row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null) {
        if (empty($row['is_redone'])) {
            owner_add_alert($overdue_alerts, $row, 'deworming', 'product_name', 'next_due_date');
        }
    }

    mysqli_stmt_close($overdue_deworm_stmt);
}

$overdue_treatment_stmt = mysqli_prepare($conn,
    "SELECT p.id AS pet_id, p.name AS pet_name,
            COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS diagnosis,
            t.followup_date
     FROM treatments t
     JOIN pets p ON p.id = t.pet_id
     WHERE p.owner_id = ? AND t.followup_status = 'active'
       AND t.followup_date IS NOT NULL AND t.followup_date < CURDATE()
     ORDER BY t.followup_date ASC");
if ($overdue_treatment_stmt) {
    mysqli_stmt_bind_param($overdue_treatment_stmt, 'i', $owner_id);
    mysqli_stmt_execute($overdue_treatment_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_treatment_stmt);

    while ($row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null) {
        owner_add_alert($overdue_alerts, $row, 'treatment', 'diagnosis', 'followup_date');
    }

    mysqli_stmt_close($overdue_treatment_stmt);
}

if (empty($overdue_alerts)) {
    $upcoming_vax_stmt = mysqli_prepare($conn,
        "SELECT p.id AS pet_id, p.name AS pet_name, v.vaccine_name, v.next_due_date
         FROM vaccinations v
         JOIN pets p ON p.id = v.pet_id
         WHERE p.owner_id = ? AND v.next_due_date IS NOT NULL
           AND v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           AND v.id = (SELECT id FROM vaccinations v2
                       WHERE v2.pet_id = v.pet_id AND v2.vaccine_name = v.vaccine_name
                       ORDER BY v2.date_given DESC LIMIT 1)
         ORDER BY v.next_due_date ASC");
    if ($upcoming_vax_stmt) {
        mysqli_stmt_bind_param($upcoming_vax_stmt, 'i', $owner_id);
        mysqli_stmt_execute($upcoming_vax_stmt);
        $upcoming_result = mysqli_stmt_get_result($upcoming_vax_stmt);

        while ($row = $upcoming_result ? mysqli_fetch_assoc($upcoming_result) : null) {
            owner_add_alert($upcoming_alerts, $row, 'vaccination', 'vaccine_name', 'next_due_date');
        }

        mysqli_stmt_close($upcoming_vax_stmt);
    }

    $upcoming_deworm_stmt = mysqli_prepare($conn,
        "SELECT p.id AS pet_id, p.name AS pet_name, d.product_name, d.next_due_date
         FROM dewormings d
         JOIN pets p ON p.id = d.pet_id
         WHERE p.owner_id = ? AND d.next_due_date IS NOT NULL
           AND d.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           AND d.id = (SELECT id FROM dewormings d2
                       WHERE d2.pet_id = d.pet_id AND d2.product_name = d.product_name
                       ORDER BY d2.date_given DESC LIMIT 1)
         ORDER BY d.next_due_date ASC");
    if ($upcoming_deworm_stmt) {
        mysqli_stmt_bind_param($upcoming_deworm_stmt, 'i', $owner_id);
        mysqli_stmt_execute($upcoming_deworm_stmt);
        $upcoming_result = mysqli_stmt_get_result($upcoming_deworm_stmt);

        while ($row = $upcoming_result ? mysqli_fetch_assoc($upcoming_result) : null) {
            owner_add_alert($upcoming_alerts, $row, 'deworming', 'product_name', 'next_due_date');
        }

        mysqli_stmt_close($upcoming_deworm_stmt);
    }

    $upcoming_treatment_stmt = mysqli_prepare($conn,
        "SELECT p.id AS pet_id, p.name AS pet_name,
                COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS diagnosis,
                t.followup_date
         FROM treatments t
         JOIN pets p ON p.id = t.pet_id
         WHERE p.owner_id = ? AND t.followup_status = 'active'
           AND t.followup_date IS NOT NULL
           AND t.followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY t.followup_date ASC");
    if ($upcoming_treatment_stmt) {
        mysqli_stmt_bind_param($upcoming_treatment_stmt, 'i', $owner_id);
        mysqli_stmt_execute($upcoming_treatment_stmt);
        $upcoming_result = mysqli_stmt_get_result($upcoming_treatment_stmt);

        while ($row = $upcoming_result ? mysqli_fetch_assoc($upcoming_result) : null) {
            owner_add_alert($upcoming_alerts, $row, 'treatment', 'diagnosis', 'followup_date');
        }

        mysqli_stmt_close($upcoming_treatment_stmt);
    }
}

if (!empty($overdue_alerts)) {
    usort($overdue_alerts, function ($a, $b) {
        return strcmp($a['due_date'], $b['due_date']);
    });
} elseif (!empty($upcoming_alerts)) {
    usort($upcoming_alerts, function ($a, $b) {
        return strcmp($a['due_date'], $b['due_date']);
    });
}

// ── Fetch recent notifications/reminders (limit 3) ───
$notifications = [];
$notif_stmt = mysqli_prepare($conn,
    "SELECT r.id, r.pet_id, r.reminder_type, r.status, r.created_at, p.name as pet_name
     FROM reminders r
     JOIN pets p ON r.pet_id = p.id
     WHERE r.owner_id = ? AND p.owner_id = ?
     ORDER BY r.created_at DESC LIMIT 3");
if ($notif_stmt) {
    mysqli_stmt_bind_param($notif_stmt, 'ii', $owner_id, $owner_id);
    mysqli_stmt_execute($notif_stmt);
    $notif_result = mysqli_stmt_get_result($notif_stmt);
    
    while ($notif = mysqli_fetch_assoc($notif_result)) {
        $notifications[] = $notif;
    }
    mysqli_stmt_close($notif_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <section class="owner-greeting">
            <h1><?= $greeting ?>, <?= htmlspecialchars($owner_name) ?></h1>
            <p><?= htmlspecialchars(date('l, j F Y')) ?> | <?= htmlspecialchars($clinic_name) ?></p>
        </section>

        <?php if (!empty($overdue_alerts)): ?>
            <?php $alert_count = 0; ?>
            <?php foreach ($overdue_alerts as $overdue_alert): ?>
            <?php if ($alert_count >= 3) break; ?>
            <?php
                $type_label = owner_alert_type_label($overdue_alert['type']);
                $detail = trim((string)($overdue_alert['title'] ?? ''));
                $detail_text = $detail !== '' ? ' (' . htmlspecialchars($detail) . ')' : '';
                $type_colors = [
                    'vaccination' => ['bg' => '#fee2e2', 'border' => '#dc2626', 'icon' => '#dc2626'],
                    'deworming' => ['bg' => '#dbeafe', 'border' => '#2563eb', 'icon' => '#2563eb'],
                    'treatment' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'icon' => '#16a34a']
                ];
                $palette = $type_colors[$overdue_alert['type']] ?? ['bg' => '#fee2e2', 'border' => '#dc2626', 'icon' => '#dc2626'];
            ?>
            <section class="owner-alert" style="background:<?= htmlspecialchars($palette['bg']) ?>;border-left:4px solid <?= htmlspecialchars($palette['border']) ?>;">
                <div class="owner-alert-text">
                    <i class="bi bi-exclamation-triangle" style="color:<?= htmlspecialchars($palette['icon']) ?>;"></i>
                    <span style="line-height:1.4;"><?= htmlspecialchars($overdue_alert['pet_name']) ?>'s <?= htmlspecialchars($type_label) ?><?= $detail_text ?> is overdue since <?= htmlspecialchars(date('d M Y', strtotime($overdue_alert['due_date']))) ?>. Please schedule an appointment immediately.</span>
                </div>
            </section>
            <?php $alert_count++; ?>
            <?php endforeach; ?>
            <?php if (count($overdue_alerts) > 3): ?>
            <div style="text-align:center; margin-bottom:24px; padding:12px;">
                <a href="notifications.php" style="color:#0d9488; font-weight:600; text-decoration:none;">View all <?= count($overdue_alerts) ?> alerts →</a>
            </div>
            <?php endif; ?>
        <?php elseif (!empty($upcoming_alerts)): ?>
            <?php $upcoming_count = 0; ?>
            <?php foreach ($upcoming_alerts as $upcoming_alert): ?>
            <?php if ($upcoming_count >= 3) break; ?>
            <?php
                $type_label = owner_alert_type_label($upcoming_alert['type']);
                $detail = trim((string)($upcoming_alert['title'] ?? ''));
                $detail_text = $detail !== '' ? ' (' . htmlspecialchars($detail) . ')' : '';
                $type_colors = [
                    'vaccination' => ['bg' => '#fee2e2', 'border' => '#dc2626', 'icon' => '#dc2626'],
                    'deworming' => ['bg' => '#dbeafe', 'border' => '#2563eb', 'icon' => '#2563eb'],
                    'treatment' => ['bg' => '#dcfce7', 'border' => '#16a34a', 'icon' => '#16a34a']
                ];
                $palette = $type_colors[$upcoming_alert['type']] ?? ['bg' => '#fef3c7', 'border' => '#f59e0b', 'icon' => '#f59e0b'];
            ?>
            <section class="owner-alert" style="background:<?= htmlspecialchars($palette['bg']) ?>;border-left:4px solid <?= htmlspecialchars($palette['border']) ?>;">
                <div class="owner-alert-text">
                    <i class="bi bi-info-circle" style="color:<?= htmlspecialchars($palette['icon']) ?>;"></i>
                    <span style="line-height:1.4;"><?= htmlspecialchars($upcoming_alert['pet_name']) ?>'s <?= htmlspecialchars($type_label) ?><?= $detail_text ?> is coming up on <?= htmlspecialchars(date('d M Y', strtotime($upcoming_alert['due_date']))) ?>. Keep an eye on this date.</span>
                </div>
            </section>
            <?php $upcoming_count++; ?>
            <?php endforeach; ?>
            <?php if (count($upcoming_alerts) > 3): ?>
            <div style="text-align:center; margin-bottom:24px; padding:12px;">
                <a href="notifications.php" style="color:#0d9488; font-weight:600; text-decoration:none;">View all <?= count($upcoming_alerts) ?> upcoming →</a>
            </div>
            <?php endif; ?>
        <?php elseif ($unvaccinated_pet_count > 0): ?>
            <section class="owner-alert" style="background:#ecfeff;border-left:4px solid #0891b2;">
                <div class="owner-alert-text">
                    <i class="bi bi-info-circle" style="color:#0891b2;"></i>
                    <span style="line-height:1.4;"><?= (int)$unvaccinated_pet_count ?> pet(s) are registered but have no vaccination records yet. Please contact your veterinarian for initial vaccination entry.</span>
                </div>
            </section>
        <?php endif; ?>

        <!-- My Pets Section -->
        <section class="owner-panel mb-4" id="pets">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">My Pets</h3>
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
                    $next_due_text = 'No vaccination scheduled';
                    
                    if ($pet['last_vaccine']) {
                        $next_due = new DateTime($pet['last_vaccine']['next_due_date']);
                        $today = new DateTime();
                        
                        if ($today > $next_due) {
                            $next_due_text = $pet['last_vaccine']['vaccine_name'] . ' (' . date('M Y', strtotime($pet['last_vaccine']['next_due_date'])) . ')';
                        } else {
                            $next_due_text = $pet['last_vaccine']['vaccine_name'] . ' (' . date('M Y', strtotime($pet['last_vaccine']['next_due_date'])) . ')';
                        }
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
                        </div>
                        <div class="owner-pet-info">
                            <div class="owner-pet-info-row">
                                <span class="owner-info-label">Vet:</span>
                                <span class="owner-info-value">
                                    <?= htmlspecialchars(trim((string)($pet['vet_name'] ?? '')) !== '' ? 'Dr. ' . trim((string)$pet['vet_name']) : 'Not assigned') ?>
                                </span>
                            </div>
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

        <!-- Recent Notifications -->
        <section class="owner-panel">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">Recent Notifications</h3>
                <a href="notifications.php" class="owner-view-all">View All</a>
            </div>
            <div class="owner-notifications-list">
                <?php if (empty($notifications)): ?>
                <div style="text-align: center; padding: 30px 20px; color: #999;">
                    <i class="bi bi-bell-slash" style="font-size: 1.5rem; display: block; margin-bottom: 10px;"></i>
                    <p>No notifications yet</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <a href="pet_detail.php?id=<?= (int)$notif['pet_id'] ?>#vaccinations" class="owner-notification-item" style="text-decoration:none; color:inherit;">
                        <div class="owner-notif-icon">
                            <?php if ($notif['reminder_type'] === 'vaccination'): ?>
                                <i class="bi bi-shield-check"></i>
                            <?php elseif ($notif['reminder_type'] === 'deworming'): ?>
                                <i class="bi bi-pill"></i>
                            <?php else: ?>
                                <i class="bi bi-bell"></i>
                            <?php endif; ?>
                        </div>
                        <div class="owner-notif-content">
                            <strong>
                                <?php if ($notif['reminder_type'] === 'vaccination'): ?>
                                    Vaccination reminder for <?= htmlspecialchars($notif['pet_name']) ?>
                                <?php elseif ($notif['reminder_type'] === 'deworming'): ?>
                                    Deworming reminder for <?= htmlspecialchars($notif['pet_name']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($notif['pet_name']) ?> checkup reminder
                                <?php endif; ?>
                            </strong>
                            <p><?= htmlspecialchars(date('M d, Y', strtotime($notif['created_at']))) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
