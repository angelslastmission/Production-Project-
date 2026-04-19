<?php
$active_page = 'reminders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reminders - PetCura</title>

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
                <h3 class="vet-panel-title">Reminders (Frontend)</h3>
                <button type="button" class="vet-alert-btn">SEND ALL</button>
            </div>
            <div class="table-responsive">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Reminder</th>
                            <th>Due date</th>
                            <th>Owner</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Rocky</td>
                            <td>Vaccination</td>
                            <td>Apr 21</td>
                            <td>ram@email.com</td>
                            <td><span class="vet-pill vet-pill-soon">Pending</span></td>
                        </tr>
                        <tr>
                            <td>Luna</td>
                            <td>Deworming</td>
                            <td>Apr 19</td>
                            <td>sita@email.com</td>
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
