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

// Determine greeting based on time
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

$clinic_name = $_SESSION['clinic_name'] ?? 'Pet Health Clinic';
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

        <!-- Immunization Alert -->
        <section class="owner-alert">
            <div class="owner-alert-text">
                <i class="bi bi-exclamation-triangle"></i>
                <span>Bruno's rabies vaccination is overdue since 15 Oct 2023. Please schedule an appointment immediately to maintain compliance and pet safety.</span>
            </div>
            <div>
                <button type="button" class="owner-alert-btn">Schedule Now</button>
            </div>
        </section>

        <!-- My Pets Section -->
        <section class="owner-panel mb-4">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">My Pets</h3>
                <a href="#" class="owner-add-btn"><i class="bi bi-plus-circle me-2"></i>Add New Pet</a>
            </div>
            <div class="owner-pets-grid">
                <!-- Pet Card 1 -->
                <div class="owner-pet-card">
                    <div class="owner-pet-header">
                        <div class="owner-pet-avatar"><i class="bi bi-paw-fill"></i></div>
                        <div class="owner-pet-name-section">
                            <h5 class="owner-pet-name">Bruno</h5>
                            <p class="owner-pet-breed">Labrador · 3 yrs</p>
                        </div>
                        <span class="owner-pet-status owner-status-overdue">OVERDUE</span>
                    </div>
                    <div class="owner-pet-info">
                        <div class="owner-pet-info-row">
                            <span class="owner-info-label">Last Vaccine:</span>
                            <span class="owner-info-value">DHPP (Sep 2022)</span>
                        </div>
                        <div class="owner-pet-info-row">
                            <span class="owner-info-label">Next Due:</span>
                            <span class="owner-info-value">Rabies (Oct 2023)</span>
                        </div>
                    </div>
                    <a href="pet_detail.php?id=1" class="owner-pet-btn">View health records</a>
                </div>

                <!-- Pet Card 2 -->
                <div class="owner-pet-card">
                    <div class="owner-pet-header">
                        <div class="owner-pet-avatar"><i class="bi bi-paw-fill"></i></div>
                        <div class="owner-pet-name-section">
                            <h5 class="owner-pet-name">Luna</h5>
                            <p class="owner-pet-breed">Persian Cat · 2 yrs</p>
                        </div>
                        <span class="owner-pet-status owner-status-updated">UP TO DATE</span>
                    </div>
                    <div class="owner-pet-info">
                        <div class="owner-pet-info-row">
                            <span class="owner-info-label">Last Vaccine:</span>
                            <span class="owner-info-value">FVRCP (Aug 2023)</span>
                        </div>
                        <div class="owner-pet-info-row">
                            <span class="owner-info-label">Next Due:</span>
                            <span class="owner-info-value">Rabies (Aug 2024)</span>
                        </div>
                    </div>
                    <a href="pet_detail.php?id=2" class="owner-pet-btn">View health records</a>
                </div>
            </div>
        </section>

        <!-- Recent Notifications -->
        <section class="owner-panel">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">Recent Notifications</h3>
                <a href="notifications.php" class="owner-view-all">View All</a>
            </div>
            <div class="owner-notifications-list">
                <!-- Notification 1 -->
                <div class="owner-notification-item">
                    <div class="owner-notif-icon"><i class="bi bi-exclamation-circle"></i></div>
                    <div class="owner-notif-content">
                        <strong>Appointment confirmed for Bruno with Dr. Sarah Smith</strong>
                        <p>5 hours ago</p>
                    </div>
                </div>

                <!-- Notification 2 -->
                <div class="owner-notification-item">
                    <div class="owner-notif-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="owner-notif-content">
                        <strong>New lab results available: Luna's annual bloodwork</strong>
                        <p>Yesterday, 4:30 PM</p>
                    </div>
                </div>

                <!-- Notification 3 -->
                <div class="owner-notification-item">
                    <div class="owner-notif-icon"><i class="bi bi-bell"></i></div>
                    <div class="owner-notif-content">
                        <strong>Invoice #INV-9902 paid successfully</strong>
                        <p>Oct 18, 2023</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
