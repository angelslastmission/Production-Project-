<?php
$active_page = 'owners';
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
        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">My owners (Frontend)</h3>
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
                        <tr>
                            <td>Ram Sharma</td>
                            <td>ram@email.com</td>
                            <td>9800000001</td>
                            <td>2</td>
                        </tr>
                        <tr>
                            <td>Sita Karki</td>
                            <td>sita@email.com</td>
                            <td>9800000002</td>
                            <td>1</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
