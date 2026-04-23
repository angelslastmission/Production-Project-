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
    "SELECT id, name, species, breed, dob, status FROM pets WHERE owner_id = ? ORDER BY name ASC");
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

// ── Fetch vaccination alerts ──────────
$overdue_alerts = [];
$upcoming_alerts = [];

// Get LATEST vaccination record for each vaccine per pet
// Only show overdue if the latest record's date_given is BEFORE the due date (not re-vaccinated yet)
$overdue_stmt = mysqli_prepare($conn,
    "SELECT p.id AS pet_id, p.name AS pet_name, v.vaccine_name, v.next_due_date, v.date_given,
            CASE WHEN v.date_given >= v.next_due_date THEN 1 ELSE 0 END AS is_revaccinated
     FROM vaccinations v
     JOIN pets p ON p.id = v.pet_id
     WHERE p.owner_id = ? AND v.next_due_date IS NOT NULL AND v.next_due_date < CURDATE()
       AND v.id = (SELECT id FROM vaccinations v2 
                   WHERE v2.pet_id = v.pet_id AND v2.vaccine_name = v.vaccine_name 
                   ORDER BY v2.date_given DESC LIMIT 1)
     ORDER BY v.next_due_date ASC");
if ($overdue_stmt) {
    mysqli_stmt_bind_param($overdue_stmt, 'i', $owner_id);
    mysqli_stmt_execute($overdue_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_stmt);

    while ($row = $overdue_result ? mysqli_fetch_assoc($overdue_result) : null) {
        if (!$row['is_revaccinated']) {
            $overdue_alerts[] = $row;
        }
    }

    mysqli_stmt_close($overdue_stmt);
}

if (empty($overdue_alerts)) {
    $upcoming_stmt = mysqli_prepare($conn,
        "SELECT p.id AS pet_id, p.name AS pet_name, v.vaccine_name, v.next_due_date
         FROM vaccinations v
         JOIN pets p ON p.id = v.pet_id
         WHERE p.owner_id = ? AND v.next_due_date IS NOT NULL
           AND v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY v.next_due_date ASC");
    if ($upcoming_stmt) {
        mysqli_stmt_bind_param($upcoming_stmt, 'i', $owner_id);
        mysqli_stmt_execute($upcoming_stmt);
        $upcoming_result = mysqli_stmt_get_result($upcoming_stmt);

        while ($row = $upcoming_result ? mysqli_fetch_assoc($upcoming_result) : null) {
            $upcoming_alerts[] = $row;
        }

        mysqli_stmt_close($upcoming_stmt);
    }
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
        <div class="owner-search-row">
            <input type="text" class="owner-search" placeholder="Search pets or records..."/>
        </div>

        <section class="owner-greeting">
            <h1><?= $greeting ?>, <?= htmlspecialchars($owner_name) ?></h1>
            <p><?= htmlspecialchars(date('l, j F Y')) ?> | <?= htmlspecialchars($clinic_name) ?></p>
        </section>

        <?php if (!empty($overdue_alerts)): ?>
            <?php $alert_count = 0; ?>
            <?php foreach ($overdue_alerts as $overdue_alert): ?>
            <?php if ($alert_count >= 3) break; ?>
            <section class="owner-alert" style="background:#fee2e2;border-left:4px solid #dc2626;">
                <div class="owner-alert-text">
                    <i class="bi bi-exclamation-triangle" style="color:#dc2626;"></i>
                    <span style="line-height:1.4;"><?= htmlspecialchars($overdue_alert['pet_name']) ?>'s <?= htmlspecialchars($overdue_alert['vaccine_name']) ?> vaccination is overdue since <?= htmlspecialchars(date('d M Y', strtotime($overdue_alert['next_due_date']))) ?>. Please schedule an appointment immediately.</span>
                </div>
                <div>
                    <a href="schedule.php?pet_id=<?= (int)$overdue_alert['pet_id'] ?>&type=overdue" class="owner-alert-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                        Schedule Now
                    </a>
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
            <section class="owner-alert" style="background:#fef3c7;border-left:4px solid #f59e0b;">
                <div class="owner-alert-text">
                    <i class="bi bi-info-circle" style="color:#f59e0b;"></i>
                    <span style="line-height:1.4;"><?= htmlspecialchars($upcoming_alert['pet_name']) ?>'s <?= htmlspecialchars($upcoming_alert['vaccine_name']) ?> vaccination is coming up on <?= htmlspecialchars(date('d M Y', strtotime($upcoming_alert['next_due_date']))) ?>. Keep an eye on this date.</span>
                </div>
            </section>
            <?php $upcoming_count++; ?>
            <?php endforeach; ?>
            <?php if (count($upcoming_alerts) > 3): ?>
            <div style="text-align:center; margin-bottom:24px; padding:12px;">
                <a href="notifications.php" style="color:#0d9488; font-weight:600; text-decoration:none;">View all <?= count($upcoming_alerts) ?> upcoming →</a>
            </div>
            <?php endif; ?>
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
                    // Determine vaccination status
                    $vax_status = 'not-vaccinated';
                    $vax_label = 'Not vaccinated';
                    $vax_class = 'owner-status-overdue';
                    $next_due_text = 'No vaccination scheduled';
                    
                    if ($pet['last_vaccine']) {
                        $next_due = new DateTime($pet['last_vaccine']['next_due_date']);
                        $today = new DateTime();
                        
                        if ($today > $next_due) {
                            $vax_status = 'overdue';
                            $vax_label = 'OVERDUE';
                            $vax_class = 'owner-status-overdue';
                            $next_due_text = $pet['last_vaccine']['vaccine_name'] . ' (' . date('M Y', strtotime($pet['last_vaccine']['next_due_date'])) . ')';
                        } else {
                            $vax_status = 'up-to-date';
                            $vax_label = 'UP TO DATE';
                            $vax_class = 'owner-status-updated';
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
