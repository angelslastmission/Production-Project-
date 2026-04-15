<?php
// ═══════════════════════════════════════════
// FRONTEND ONLY — Reminder Logs
// ═══════════════════════════════════════════
session_start();
include 'includes/auth.php';

$active_page = 'reminder_logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reminder Logs — PetCare HMS</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>

<div class="admin-wrapper">

    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">

        <div class="admin-topbar">
            <div>
                <h1 class="admin-page-title">Reminder logs</h1>
                <p class="admin-page-sub">Every SMS and email the system has sent — monitor failures here</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PET</th>
                            <th>OWNER</th>
                            <th>VET</th>
                            <th>TYPE</th>
                            <th>CHANNEL</th>
                            <th>RESULT</th>
                            <th>SENT AT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Bruno</strong></td>
                            <td>Ram Sharma</td>
                            <td>Dr. Sunita</td>
                            <td>Rabies vaccine</td>
                            <td>SMS + Email</td>
                            <td><span class="badge-sent">Sent</span></td>
                            <td>Apr 3, 9:00am</td>
                        </tr>
                        <tr>
                            <td><strong>Mimi</strong></td>
                            <td>Sita Karki</td>
                            <td>Dr. Sunita</td>
                            <td>Deworming</td>
                            <td>Email</td>
                            <td><span class="badge-sent">Sent</span></td>
                            <td>Apr 3, 9:01am</td>
                        </tr>
                        <tr>
                            <td><strong>Luna</strong></td>
                            <td>Puja Tamang</td>
                            <td>Dr. Sunita</td>
                            <td>Follow-up</td>
                            <td>SMS</td>
                            <td><span class="badge-failed">Failed</span></td>
                            <td>Apr 3, 9:01am</td>
                        </tr>
                        <tr>
                            <td><strong>Rocky</strong></td>
                            <td>Hari Thapa</td>
                            <td>Dr. Rajan</td>
                            <td>DHPP vaccine</td>
                            <td>Both</td>
                            <td><span class="badge-sent">Sent</span></td>
                            <td>Apr 2, 9:00am</td>
                        </tr>
                        <tr>
                            <td><strong>Tiger</strong></td>
                            <td>Bikash KC</td>
                            <td>Dr. Mina</td>
                            <td>Deworming</td>
                            <td>Email</td>
                            <td><span class="badge-sent">Sent</span></td>
                            <td>Apr 1, 9:00am</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
