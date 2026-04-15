<?php
// ═══════════════════════════════════════════
// FRONTEND ONLY — Manage Owners
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'manage_owners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Owners — PetCare HMS</title>

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
                <h1 class="admin-page-title">Manage owners</h1>
                <p class="admin-page-sub">All registered pet owners</p>
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
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>ASSIGNED VET</th>
                            <th>PETS</th>
                            <th>JOINED</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Ram Sharma</strong></td>
                            <td>ram@gmail.com</td>
                            <td>9812345678</td>
                            <td>Dr. Sunita Rai</td>
                            <td>2</td>
                            <td>Jan 2025</td>
                            <td><button class="btn-review" type="button">View</button></td>
                        </tr>
                        <tr>
                            <td><strong>Sita Karki</strong></td>
                            <td>sita@gmail.com</td>
                            <td>9845678901</td>
                            <td>Dr. Sunita Rai</td>
                            <td>1</td>
                            <td>Feb 2025</td>
                            <td><button class="btn-review" type="button">View</button></td>
                        </tr>
                        <tr>
                            <td><strong>Hari Thapa</strong></td>
                            <td>hari@email.com</td>
                            <td>9856789012</td>
                            <td>Dr. Rajan Thapa</td>
                            <td>1</td>
                            <td>Mar 2025</td>
                            <td><button class="btn-review" type="button">View</button></td>
                        </tr>
                        <tr>
                            <td><strong>Puja Tamang</strong></td>
                            <td>puja@gmail.com</td>
                            <td>9834567890</td>
                            <td>Dr. Sunita Rai</td>
                            <td>3</td>
                            <td>Jun 2025</td>
                            <td><button class="btn-review" type="button">View</button></td>
                        </tr>
                        <tr>
                            <td><strong>Bikash Shrestha</strong></td>
                            <td>bikash@gmail.com</td>
                            <td>9823456789</td>
                            <td>Dr. Mina Gurung</td>
                            <td>2</td>
                            <td>Aug 2025</td>
                            <td><button class="btn-review" type="button">View</button></td>
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
