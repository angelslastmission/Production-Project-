<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'notifications';
$owner_id = (int)($_SESSION['user_id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$search = trim($_GET['search'] ?? '');
$selected_pet_id = (int)($_GET['pet_id'] ?? 0);
$type_filter = trim($_GET['type'] ?? 'all');

$notifications = [];
$notif_conditions = ['r.owner_id = ?'];
$notif_params = [$owner_id];
$notif_types = 'i';

if ($search !== '') {
  $notif_conditions[] = "(p.name LIKE ? OR r.message LIKE ? OR r.reminder_type LIKE ? OR r.channel LIKE ?)";
  $search_term = '%' . $search . '%';
  $notif_params[] = $search_term;
  $notif_params[] = $search_term;
  $notif_params[] = $search_term;
  $notif_params[] = $search_term;
  $notif_types .= 'ssss';
}

if ($selected_pet_id > 0) {
  $notif_conditions[] = 'r.pet_id = ?';
  $notif_params[] = $selected_pet_id;
  $notif_types .= 'i';
}

if ($type_filter !== '' && $type_filter !== 'all') {
  $notif_conditions[] = 'r.reminder_type = ?';
  $notif_params[] = $type_filter;
  $notif_types .= 's';
}

$notif_sql = "SELECT r.id, r.pet_id, r.reminder_type, r.reminder_date, r.channel, r.status, r.message, r.created_at,
      p.name AS pet_name
   FROM reminders r
   JOIN pets p ON p.id = r.pet_id
   WHERE " . implode(' AND ', $notif_conditions) . "
   ORDER BY r.created_at DESC";

$notif_stmt = mysqli_prepare($conn, $notif_sql);
if ($notif_stmt) {
  mysqli_stmt_bind_param($notif_stmt, $notif_types, ...$notif_params);
  mysqli_stmt_execute($notif_stmt);
  $notif_result = mysqli_stmt_get_result($notif_stmt);
  while ($row = $notif_result ? mysqli_fetch_assoc($notif_result) : null) {
    $notifications[] = $row;
  }
  mysqli_stmt_close($notif_stmt);
}

$pets_for_filter = [];
$pet_filter_stmt = mysqli_prepare($conn,
  "SELECT id, name FROM pets WHERE owner_id = ? ORDER BY name ASC");
if ($pet_filter_stmt) {
  mysqli_stmt_bind_param($pet_filter_stmt, 'i', $owner_id);
  mysqli_stmt_execute($pet_filter_stmt);
  $pet_filter_result = mysqli_stmt_get_result($pet_filter_stmt);
  while ($row = $pet_filter_result ? mysqli_fetch_assoc($pet_filter_result) : null) {
    $pets_for_filter[] = $row;
  }
  mysqli_stmt_close($pet_filter_stmt);
}

function notification_channel_label($channel) {
  if ($channel === 'sms') {
    return 'SMS Sent';
  }
  if ($channel === 'both') {
    return 'SMS + Email';
  }
  return 'Email Sent';
}

function notification_title($notification) {
  $pet_name = $notification['pet_name'] ?? 'Pet';
  if ($notification['reminder_type'] === 'vaccination') {
    return $pet_name . ' — Vaccination reminder';
  }
  if ($notification['reminder_type'] === 'deworming') {
    return $pet_name . ' — Deworming reminder';
  }
  return $pet_name . ' — Follow-up reminder';
}

function notification_type_label($type) {
  if ($type === 'vaccination') {
    return 'Vaccination';
  }
  if ($type === 'deworming') {
    return 'Deworming';
  }
  return 'Follow-up';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
  <style>
    .notif-filters {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
    }
    .notif-filter-btn {
      padding: 8px 16px;
      border: 1px solid #e5e7eb;
      background: #fff;
      border-radius: 6px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s;
      font-weight: 600;
      color: #6b7280;
    }
    .notif-filter-btn:hover {
      border-color: #0d9488;
      color: #0d9488;
    }
    .notif-filter-btn.active {
      background: #0d9488;
      color: #fff;
      border-color: #0d9488;
    }
    .notif-search {
      flex: 1;
    }
    .notif-item {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 12px;
      display: flex;
      gap: 14px;
      transition: all 0.3s;
    }
    .notif-item:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .notif-icon {
      width: 48px;
      height: 48px;
      background: #f0fdfa;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0d9488;
      font-size: 1.25rem;
      flex-shrink: 0;
    }
    .notif-content {
      flex: 1;
    }
    .notif-title {
      font-weight: 700;
      color: #1f2937;
      font-size: 0.95rem;
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: start;
    }
    .notif-detail {
      color: #6b7280;
      font-size: 0.9rem;
      line-height: 1.4;
      margin-bottom: 10px;
    }
    .notif-meta {
      display: flex;
      gap: 16px;
      font-size: 0.8rem;
      color: #9ca3af;
    }
    .notif-channel {
      background: #f3f4f6;
      padding: 4px 8px;
      border-radius: 4px;
      font-weight: 500;
    }
  </style>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 style="font-size: 1.75rem; font-weight: 700; font-family: 'Sora', sans-serif; margin: 0;">Notifications</h1>
            <button style="background: #fff; border: 1px solid #e5e7eb; padding: 8px 16px; border-radius: 6px; color: #0d9488; font-weight: 600; cursor: pointer; font-size: 0.9rem;">Mark all read</button>
        </div>

        <!-- Filters -->
        <form method="GET" class="notif-filters" style="flex-wrap:wrap;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search notifications..." style="flex: 1; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; min-width: 220px;">
            <select name="pet_id" style="padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; min-width: 180px;">
                <option value="0">All pets</option>
                <?php foreach ($pets_for_filter as $pet_option): ?>
                  <option value="<?= (int)$pet_option['id'] ?>" <?= $selected_pet_id === (int)$pet_option['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pet_option['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" style="padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; min-width: 180px;">
                <option value="all">All types</option>
                <option value="vaccination" <?= $type_filter === 'vaccination' ? 'selected' : '' ?>>Vaccination</option>
                <option value="deworming" <?= $type_filter === 'deworming' ? 'selected' : '' ?>>Deworming</option>
                <option value="followup" <?= $type_filter === 'followup' ? 'selected' : '' ?>>Follow-up</option>
            </select>
            <button type="submit" class="notif-filter-btn active">Filter</button>
        </form>

        <!-- Notifications List -->
        <div style="background: #fff; border-radius: 8px; padding: 20px;">
            <?php foreach ($notifications as $notif): ?>
            <a href="pet_detail.php?id=<?= (int)$notif['pet_id'] ?>#vaccinations" class="notif-item" style="text-decoration:none; color:inherit;">
              <div class="notif-icon">
                <?php if ($notif['reminder_type'] === 'vaccination'): ?>
                  <i class="bi bi-shield-check"></i>
                <?php elseif ($notif['reminder_type'] === 'deworming'): ?>
                  <i class="bi bi-capsule"></i>
                <?php else: ?>
                  <i class="bi bi-bell-fill"></i>
                <?php endif; ?>
              </div>
                <div class="notif-content">
                    <div class="notif-title">
                  <strong><?= htmlspecialchars(notification_title($notif)) ?></strong>
                  <span style="color: #d1d5db;"><?= htmlspecialchars(date('M d, Y', strtotime($notif['created_at']))) ?></span>
                    </div>
                <div style="font-weight: 700; color: #1f2937; margin-bottom: 6px; font-size: 0.95rem;"><?= htmlspecialchars($notif['message'] ?: notification_title($notif)) ?></div>
                <div class="notif-detail">
                  <?= htmlspecialchars($notif['message'] ?: 'Reminder for ' . ($notif['pet_name'] ?? 'this pet')) ?>
                </div>
                    <div class="notif-meta">
                  <span class="notif-channel"><?= htmlspecialchars(notification_channel_label($notif['channel'])) ?></span>
                  <span class="notif-channel"><?= htmlspecialchars(notification_type_label($notif['reminder_type'])) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
