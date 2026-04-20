<?php
// ═══════════════════════════════════════
// BACKEND — Register Pet
// ═══════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'register_pet';

$error = '';
$success = '';
$form_data = [
    'pet_name' => '',
    'species' => '',
    'breed' => '',
    'dob' => '',
    'owner_email' => '',
    'status' => 'Healthy'
];

// ── Handle form submission ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $form_data['pet_name'] = trim($_POST['pet_name'] ?? '');
    $form_data['species'] = trim($_POST['species'] ?? '');
    $form_data['breed'] = trim($_POST['breed'] ?? '');
    $form_data['dob'] = trim($_POST['dob'] ?? '');
    $form_data['owner_email'] = trim($_POST['owner_email'] ?? '');
    $form_data['status'] = trim($_POST['status'] ?? 'Healthy');

    // Validate form data
    if (empty($form_data['pet_name'])) {
        $error = 'Pet name is required';
    } elseif (empty($form_data['species'])) {
        $error = 'Species is required';
    } elseif (empty($form_data['owner_email'])) {
        $error = 'Owner email is required';
    } elseif (!filter_var($form_data['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid owner email address';
    }

    if (empty($error)) {
        // ── Verify owner exists ──────────────
        $owner_email = mysqli_real_escape_string($conn, $form_data['owner_email']);
        $owner_result = mysqli_query($conn,
            "SELECT id FROM users WHERE email = '$owner_email' AND role = 'owner'");
        
        if (!$owner_result || mysqli_num_rows($owner_result) === 0) {
            $error = 'Owner with this email not found in system';
        } else {
            $owner = mysqli_fetch_assoc($owner_result);
            $owner_id = $owner['id'];

            // ── Check for duplicate pet name ─
            $pet_name = mysqli_real_escape_string($conn, $form_data['pet_name']);
            $existing_pet = mysqli_query($conn,
                "SELECT id FROM pets WHERE name = '$pet_name' AND owner_id = $owner_id");
            
            if (mysqli_num_rows($existing_pet) > 0) {
                $error = 'This pet name already exists for this owner';
            } else {
                // ── Insert pet into database ────
                $species = mysqli_real_escape_string($conn, $form_data['species']);
                $breed = mysqli_real_escape_string($conn, $form_data['breed']);
                $dob = !empty($form_data['dob']) 
                    ? "'" . mysqli_real_escape_string($conn, $form_data['dob']) . "'"
                    : 'NULL';
                $status = mysqli_real_escape_string($conn, $form_data['status']);

                $insert_query = "
                    INSERT INTO pets (owner_id, name, species, breed, dob, status, created_at, updated_at)
                    VALUES ($owner_id, '$pet_name', '$species', '$breed', $dob, '$status', NOW(), NOW())
                ";

                if (mysqli_query($conn, $insert_query)) {
                    $success = 'Pet registered successfully!';
                    // Clear form data on success
                    $form_data = [
                        'pet_name' => '',
                        'species' => '',
                        'breed' => '',
                        'dob' => '',
                        'owner_email' => '',
                        'status' => 'Healthy'
                    ];
                } else {
                    $error = 'Database error: ' . mysqli_error($conn);
                }
            }
        }
    }
}

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
                <h3 class="vet-panel-title">Register pet</h3>
                <a href="patients.php" class="patients-view-btn">Back</a>
            </div>

            <?php if (!empty($success)): ?>
            <div class="vet-alert" style="margin: 16px 18px 0;">
                <span class="vet-alert-text">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= htmlspecialchars($success) ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 10px; padding: 13px 16px; margin: 16px 18px 0;">
                <div style="display: flex; align-items: center; gap: 10px; color: #991b1b; font-size: 0.82rem; font-weight: 700;">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Pet name <span style="color: #dc2626;">*</span></label>
                            <input type="text" name="pet_name" class="form-control" placeholder="Bruno" value="<?= htmlspecialchars($form_data['pet_name']) ?>" required/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Species <span style="color: #dc2626;">*</span></label>
                            <select name="species" class="form-select" required>
                                <option value="">Select species</option>
                                <option value="Dog" <?= $form_data['species'] === 'Dog' ? 'selected' : '' ?>>Dog</option>
                                <option value="Cat" <?= $form_data['species'] === 'Cat' ? 'selected' : '' ?>>Cat</option>
                                <option value="Bird" <?= $form_data['species'] === 'Bird' ? 'selected' : '' ?>>Bird</option>
                                <option value="Rabbit" <?= $form_data['species'] === 'Rabbit' ? 'selected' : '' ?>>Rabbit</option>
                                <option value="Hamster" <?= $form_data['species'] === 'Hamster' ? 'selected' : '' ?>>Hamster</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Breed</label>
                            <input type="text" name="breed" class="form-control" placeholder="Labrador" value="<?= htmlspecialchars($form_data['breed']) ?>"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Date of birth</label>
                            <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($form_data['dob']) ?>"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Owner email <span style="color: #dc2626;">*</span></label>
                            <input type="email" name="owner_email" class="form-control" placeholder="owner@email.com" value="<?= htmlspecialchars($form_data['owner_email']) ?>" required/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Health status</label>
                            <select name="status" class="form-select">
                                <option value="Healthy" <?= $form_data['status'] === 'Healthy' ? 'selected' : '' ?>>Healthy</option>
                                <option value="Sick" <?= $form_data['status'] === 'Sick' ? 'selected' : '' ?>>Sick</option>
                                <option value="Treatment" <?= $form_data['status'] === 'Treatment' ? 'selected' : '' ?>>Treatment</option>
                                <option value="Recovering" <?= $form_data['status'] === 'Recovering' ? 'selected' : '' ?>>Recovering</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="vet-alert-btn">SAVE</button>
                        <button type="reset" class="patients-view-btn">Clear</button>
                    </div>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
