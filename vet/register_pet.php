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
    'gender' => '',
    'dob' => '',
    'weight' => '',
    'allergies' => '',
    'is_neutered' => 0,
    'owner_email' => '',
    'status' => 'healthy'
];

// ── Handle form submission ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $form_data['pet_name'] = trim($_POST['pet_name'] ?? '');
    $form_data['species'] = trim($_POST['species'] ?? '');
    $form_data['breed'] = trim($_POST['breed'] ?? '');
    $form_data['gender'] = trim($_POST['gender'] ?? '');
    $form_data['dob'] = trim($_POST['dob'] ?? '');
    $form_data['weight'] = trim($_POST['weight'] ?? '');
    $form_data['allergies'] = trim($_POST['allergies'] ?? '');
    $form_data['is_neutered'] = isset($_POST['is_neutered']) ? 1 : 0;
    $form_data['owner_email'] = trim($_POST['owner_email'] ?? '');
    $form_data['status'] = trim($_POST['status'] ?? 'healthy');

    $allowed_status = ['healthy', 'sick', 'treatment', 'recovering'];
    $allowed_gender = ['', 'male', 'female'];

    // Validate form data
    if (empty($form_data['pet_name'])) {
        $error = 'Pet name is required';
    } elseif (empty($form_data['species'])) {
        $error = 'Species is required';
    } elseif (empty($form_data['owner_email'])) {
        $error = 'Owner email is required';
    } elseif (!filter_var($form_data['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid owner email address';
    } elseif (!in_array($form_data['status'], $allowed_status, true)) {
        $error = 'Invalid health status selected';
    } elseif (!in_array($form_data['gender'], $allowed_gender, true)) {
        $error = 'Invalid gender selected';
    } elseif ($form_data['weight'] !== '' && (!is_numeric($form_data['weight']) || (float)$form_data['weight'] <= 0)) {
        $error = 'Weight must be a valid positive number';
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
                $vet_id = (int)($_SESSION['user_id'] ?? 0);
                if ($vet_id <= 0) {
                    $error = 'Invalid session. Please login again.';
                }

                $species = mysqli_real_escape_string($conn, $form_data['species']);
                $breed = mysqli_real_escape_string($conn, $form_data['breed']);
                $gender = $form_data['gender'] !== ''
                    ? "'" . mysqli_real_escape_string($conn, $form_data['gender']) . "'"
                    : 'NULL';
                $dob = !empty($form_data['dob']) 
                    ? "'" . mysqli_real_escape_string($conn, $form_data['dob']) . "'"
                    : 'NULL';
                $weight = $form_data['weight'] !== '' ? (float)$form_data['weight'] : 'NULL';
                $allergies = $form_data['allergies'] !== ''
                    ? "'" . mysqli_real_escape_string($conn, $form_data['allergies']) . "'"
                    : 'NULL';
                $is_neutered = (int)$form_data['is_neutered'];
                $status = mysqli_real_escape_string($conn, $form_data['status']);

                if (empty($error)) {
                    $insert_query = "
                        INSERT INTO pets (vet_id, owner_id, name, species, breed, gender, dob, weight, allergies, is_neutered, status, created_at)
                        VALUES ($vet_id, $owner_id, '$pet_name', '$species', '$breed', $gender, $dob, $weight, $allergies, $is_neutered, '$status', NOW())
                    ";

                    if (mysqli_query($conn, $insert_query)) {
                        $success = 'Pet registered successfully!';
                        // Clear form data on success
                        $form_data = [
                            'pet_name' => '',
                            'species' => '',
                            'breed' => '',
                            'gender' => '',
                            'dob' => '',
                            'weight' => '',
                            'allergies' => '',
                            'is_neutered' => 0,
                            'owner_email' => '',
                            'status' => 'healthy'
                        ];
                    } else {
                        $error = 'Database error: ' . mysqli_error($conn);
                    }
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
                            <label class="form-label small text-secondary">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="" <?= $form_data['gender'] === '' ? 'selected' : '' ?>>Select gender</option>
                                <option value="male" <?= $form_data['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $form_data['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Date of birth</label>
                            <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($form_data['dob']) ?>"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" placeholder="12.50" min="0.1" step="0.01" value="<?= htmlspecialchars($form_data['weight']) ?>"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Owner email <span style="color: #dc2626;">*</span></label>
                            <input type="email" name="owner_email" class="form-control" placeholder="owner@email.com" value="<?= htmlspecialchars($form_data['owner_email']) ?>" required/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Health status</label>
                            <select name="status" class="form-select">
                                <option value="healthy" <?= $form_data['status'] === 'healthy' ? 'selected' : '' ?>>Healthy</option>
                                <option value="sick" <?= $form_data['status'] === 'sick' ? 'selected' : '' ?>>Sick</option>
                                <option value="treatment" <?= $form_data['status'] === 'treatment' ? 'selected' : '' ?>>Treatment</option>
                                <option value="recovering" <?= $form_data['status'] === 'recovering' ? 'selected' : '' ?>>Recovering</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Allergies</label>
                            <textarea name="allergies" class="form-control" rows="3" placeholder="Mention known allergies (optional)"><?= htmlspecialchars($form_data['allergies']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="is_neutered" name="is_neutered" value="1" <?= (int)$form_data['is_neutered'] === 1 ? 'checked' : '' ?> />
                                <label class="form-check-label small text-secondary" for="is_neutered">
                                    Pet is neutered/spayed
                                </label>
                            </div>
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
