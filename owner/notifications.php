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

// Mock notifications data
$notifications = [
    ['pet' => 'Bruno', 'type' => 'Vaccine Reminder', 'message' => 'Bruno — Rabies booster OVERDUE', 'detail' => 'The annual rabies booster for Bruno was due 3 days ago. Please schedule an appointment immediately to remain compliant with local regulations.', 'date' => '2023-10-12', 'time' => '09:15 AM', 'channel' => 'Email Sent'],
    ['pet' => 'Bruno', 'type' => 'Vaccine Alert', 'message' => 'Bruno — DHPP vaccine due soon', 'detail' => 'Bruno\'s DHPP (Distemper, Hepatitis, Parainfluenza and Parvovirus) combination vaccine is due in 14 days.', 'date' => '2023-10-11', 'time' => '02:30 PM', 'channel' => 'Push Notification'],
    ['pet' => 'Luna', 'type' => 'Deworming Reminder', 'message' => 'Luna — Deworming reminder', 'detail' => 'Monthly deworming tablet for Luna. Administer with food this morning.', 'date' => '2023-10-08', 'time' => '08:00 AM', 'channel' => 'Calendar Sync'],
    ['pet' => 'Bruno', 'type' => 'Visit Complete', 'message' => 'Bruno — Follow-up visit completed', 'detail' => 'The clinical records for Bruno\'s follow-up visit on Oct 09 have been uploaded to his profile. All vitals look normal.', 'date' => '2023-10-09', 'time' => '04:45 PM', 'channel' => 'Records Updated'],
    ['pet' => 'Luna', 'type' => 'Vaccine Reminder', 'message' => 'Luna — Vaccination reminder', 'detail' => 'Upcoming Bordetella vaccine reminder for Luna. This is required for her boarding reservation next month.', 'date' => '2023-10-08', 'time' => '11:15 AM', 'channel' => 'Email Sent'],
];
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
        <div class="notif-filters">
            <input type="text" placeholder="Search notifications..." style="flex: 1; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
            <select style="padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
                <option>All pets</option>
                <option>Bruno</option>
                <option>Luna</option>
            </select>
            <select style="padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
                <option>All types</option>
                <option>Vaccine Reminder</option>
                <option>Deworming Reminder</option>
                <option>Follow-up</option>
            </select>
        </div>

        <!-- Notifications List -->
        <div style="background: #fff; border-radius: 8px; padding: 20px;">
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-item">
                <div class="notif-icon"><i class="bi bi-bell-fill"></i></div>
                <div class="notif-content">
                    <div class="notif-title">
                        <strong><?= htmlspecialchars($notif['pet']) ?> — <?= htmlspecialchars($notif['type']) ?></strong>
                        <span style="color: #d1d5db;"><?= htmlspecialchars($notif['date']) ?>, <?= htmlspecialchars($notif['time']) ?></span>
                    </div>
                    <div style="font-weight: 700; color: #1f2937; margin-bottom: 6px; font-size: 0.95rem;"><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notif-detail"><?= htmlspecialchars($notif['detail']) ?></div>
                    <div class="notif-meta">
                        <span class="notif-channel"><?= htmlspecialchars($notif['channel']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
