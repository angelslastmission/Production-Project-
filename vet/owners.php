<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'owners';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$owners = [];

if ($vet_id > 0) {
    $where_search = '';

    if ($search !== '') {
        $safe_search = mysqli_real_escape_string($conn, $search);
        $where_search = " AND (u.first_name LIKE '%$safe_search%' OR u.last_name LIKE '%$safe_search%' OR u.email LIKE '%$safe_search%' OR u.phone LIKE '%$safe_search%')";
    }

    // Owners are linked by pets assigned to this vet.
    $owner_query = mysqli_query(
        $conn,
        "SELECT u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                COUNT(p.id) AS pet_count
         FROM users u
         JOIN pets p ON p.owner_id = u.id
         WHERE u.role = 'owner' AND p.vet_id = $vet_id $where_search
         GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone
         ORDER BY u.first_name ASC, u.last_name ASC"
    );

    if ($owner_query) {
        while ($row = mysqli_fetch_assoc($owner_query)) {
            $owners[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Owners - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/vet.css" rel="stylesheet"/>
</head>
<body>
<div class="vet-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="vet-main">
        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">My owners</h3>
            </div>
            <form method="GET" class="p-3 p-md-4 pt-0">
                <div class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search by name, email, or phone"
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="vet-alert-btn">SEARCH</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Owner records</h3>
            </div>
            <div class="table-responsive">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Pets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($owners)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No owners found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($owners as $owner): ?>
                            <tr>
                                <td><?= htmlspecialchars(trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($owner['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($owner['phone'] ?: 'N/A') ?></td>
                                <td><?= (int)$owner['pet_count'] ?></td>
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
