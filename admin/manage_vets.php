<?php
// ═══════════════════════════════════════════
// FRONTEND ONLY — Manage Vets
// ═══════════════════════════════════════════
session_start();
include 'includes/auth.php';

$active_page = 'manage_vets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Vets — PetCare HMS</title>

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
                <h1 class="admin-page-title">Manage vets</h1>
                <p class="admin-page-sub">All approved veterinarians in the system</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">All approved vets</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>CLINIC</th>
                            <th>PHONE</th>
                            <th>PETS</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Dr. Sunita Rai</strong></td>
                            <td>sunita@care.com</td>
                            <td>Animal Care</td>
                            <td>9812345678</td>
                            <td>24</td>
                            <td>
                                <span class="badge-sent">Active</span>
                            </td>
                            <td>
                                <button type="button" class="btn-review">Edit</button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Dr. Rajan Thapa</strong></td>
                            <td>rajan@pet.com</td>
                            <td>PetWell</td>
                            <td>9845678901</td>
                            <td>18</td>
                            <td><span class="badge-sent">Active</span></td>
                            <td><button type="button" class="btn-review">Edit</button></td>
                        </tr>
                        <tr>
                            <td><strong>Dr. Mina Gurung</strong></td>
                            <td>mina@care.com</td>
                            <td>Happy Paws</td>
                            <td>9856789012</td>
                            <td>31</td>
                            <td><span class="badge-sent">Active</span></td>
                            <td><button type="button" class="btn-review">Edit</button></td>
                        </tr>
                        <tr>
                            <td><strong>Dr. Bikash KC</strong></td>
                            <td>bikash@vet.com</td>
                            <td>City Vet</td>
                            <td>9834567890</td>
                            <td>12</td>
                            <td><span class="badge-failed">Inactive</span></td>
                            <td><button type="button" class="btn-review">Edit</button></td>
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
