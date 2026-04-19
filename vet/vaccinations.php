<?php
$active_page = 'vaccinations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vaccinations - PetCura</title>

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
                <h3 class="vet-panel-title">Vaccinations (Frontend)</h3>
                <button type="button" class="vet-alert-btn">ADD</button>
            </div>
            <div class="table-responsive">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Date given</th>
                            <th>Next due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Bruno</td>
                            <td>Rabies booster</td>
                            <td>Apr 5</td>
                            <td>May 5</td>
                            <td><span class="vet-pill vet-pill-soon">Soon</span></td>
                        </tr>
                        <tr>
                            <td>Luna</td>
                            <td>DHPP combo</td>
                            <td>Apr 2</td>
                            <td>Apr 18</td>
                            <td><span class="vet-pill vet-pill-overdue">Overdue</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
