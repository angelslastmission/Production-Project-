<?php
$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings - PetCura</title>

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
                <h3 class="vet-panel-title">Settings (Frontend)</h3>
                <button type="button" class="vet-alert-btn">SAVE</button>
            </div>
            <div class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Display name</label>
                        <input type="text" class="form-control" placeholder="Dr. Sunita"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Clinic name</label>
                        <input type="text" class="form-control" placeholder="Animal Care Clinic"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Reminder window</label>
                        <select class="form-select">
                            <option>3 days before</option>
                            <option>7 days before</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Email notifications</label>
                        <select class="form-select">
                            <option>Enabled</option>
                            <option>Disabled</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
