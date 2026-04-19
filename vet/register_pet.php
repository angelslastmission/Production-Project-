<?php
$active_page = 'register_pet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register Pet - PetCura</title>

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
                <h3 class="vet-panel-title">Register pet (Frontend)</h3>
                <a href="patients.php" class="patients-view-btn">Back</a>
            </div>
            <div class="p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Pet name</label>
                        <input type="text" class="form-control" placeholder="Bruno"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Species</label>
                        <select class="form-select">
                            <option>Dog</option>
                            <option>Cat</option>
                            <option>Bird</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Breed</label>
                        <input type="text" class="form-control" placeholder="Labrador"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">DOB</label>
                        <input type="date" class="form-control"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Owner email</label>
                        <input type="email" class="form-control" placeholder="owner@email.com"/>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Status</label>
                        <select class="form-select">
                            <option>Healthy</option>
                            <option>Sick</option>
                            <option>Treatment</option>
                            <option>Recovering</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="button" class="vet-alert-btn">SAVE</button>
                    <button type="button" class="patients-view-btn">Clear</button>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
