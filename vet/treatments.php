<?php
$active_page = 'treatments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Treatments - PetCura</title>

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
                <h3 class="vet-panel-title">Treatments (Frontend)</h3>
                <button type="button" class="vet-alert-btn">NEW CASE</button>
            </div>
            <div class="table-responsive">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Case</th>
                            <th>Treatment date</th>
                            <th>Follow-up</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tiger</td>
                            <td>Post-surgery check</td>
                            <td>Apr 12</td>
                            <td>Apr 20</td>
                            <td><span class="vet-pill vet-pill-overdue">Urgent</span></td>
                        </tr>
                        <tr>
                            <td>Mimi</td>
                            <td>Skin infection</td>
                            <td>Apr 10</td>
                            <td>Apr 23</td>
                            <td><span class="vet-pill vet-pill-soon">Soon</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
