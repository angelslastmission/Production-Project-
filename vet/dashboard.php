<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'dashboard';
include 'includes/dashboard_backend.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vet Dashboard - PetCura</title>

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
                <h1 class="admin-page-title">Vet dashboard</h1>
                <p class="admin-page-sub">Manage pets and health records</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <?php if ($success_message !== ''): ?>
            <div class="admin-alert-success mb-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="admin-alert-error mb-3">
                <i class="bi bi-x-circle-fill me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid mb-4">
            <div class="stat-card">
                <p class="stat-label">My Pets</p>
                <h3 class="stat-value"><?= (int)$stats['pets'] ?></h3>
            </div>
            <div class="stat-card">
                <p class="stat-label">Vaccinations</p>
                <h3 class="stat-value"><?= (int)$stats['vaccinations'] ?></h3>
            </div>
            <div class="stat-card">
                <p class="stat-label">Dewormings</p>
                <h3 class="stat-value"><?= (int)$stats['dewormings'] ?></h3>
            </div>
            <div class="stat-card">
                <p class="stat-label">Treatments</p>
                <h3 class="stat-value"><?= (int)$stats['treatments'] ?></h3>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-plus-square-fill me-2"></i>Add pet</h5>
                    </div>
                    <div class="p-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="add_pet"/>
                            <div class="col-md-6">
                                <label class="form-label">Pet name *</label>
                                <input type="text" name="name" class="form-control" required/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Owner email (optional)</label>
                                <input type="email" name="owner_email" class="form-control" placeholder="owner@email.com"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Species</label>
                                <input type="text" name="species" class="form-control"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Breed</label>
                                <input type="text" name="breed" class="form-control"/>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DOB</label>
                                <input type="date" name="dob" class="form-control"/>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" min="0" name="weight" class="form-control"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Blood group</label>
                                <input type="text" name="blood_group" class="form-control"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Microchip number</label>
                                <input type="text" name="microchip_number" class="form-control"/>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Health status</label>
                                <select name="status" class="form-select">
                                    <option value="healthy">Healthy</option>
                                    <option value="sick">Sick</option>
                                    <option value="treatment">Treatment</option>
                                    <option value="recovering">Recovering</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last visit</label>
                                <input type="date" name="last_visit" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Allergies</label>
                                <textarea name="allergies" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="col-12 form-check mt-1 ms-1">
                                <input class="form-check-input" type="checkbox" id="is_neutered" name="is_neutered">
                                <label class="form-check-label" for="is_neutered">Neutered</label>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-dark w-100" type="submit">Save pet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-bell-fill me-2"></i>Upcoming due items</h5>
                    </div>
                    <div class="p-3">
                        <?php if (count($upcoming) > 0): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Pet</th>
                                            <th>Type</th>
                                            <th>Due date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)$row['pet_name']) ?></td>
                                                <td><?= htmlspecialchars((string)$row['reminder_type']) ?></td>
                                                <td><?= htmlspecialchars(date('M j, Y', strtotime((string)$row['due_date']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No upcoming due records found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-syringe me-2"></i>Add vaccination</h5>
                    </div>
                    <div class="p-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="add_vaccination"/>
                            <div class="col-12">
                                <label class="form-label">Pet *</label>
                                <select name="pet_id" class="form-select" required>
                                    <option value="">Select pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?= (int)$pet['id'] ?>"><?= htmlspecialchars((string)$pet['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Vaccine name *</label>
                                <input type="text" name="vaccine_name" class="form-control" required/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Date given *</label>
                                <input type="date" name="date_given" class="form-control" required/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Next due</label>
                                <input type="date" name="next_due_date" class="form-control"/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Dose number</label>
                                <input type="text" name="dose_number" class="form-control"/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Batch number</label>
                                <input type="text" name="batch_number" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <textarea name="vaccination_notes" class="form-control" rows="2" placeholder="Notes"></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-dark w-100" type="submit">Save vaccination</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-capsule-pill me-2"></i>Add deworming</h5>
                    </div>
                    <div class="p-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="add_deworming"/>
                            <div class="col-12">
                                <label class="form-label">Pet *</label>
                                <select name="pet_id" class="form-select" required>
                                    <option value="">Select pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?= (int)$pet['id'] ?>"><?= htmlspecialchars((string)$pet['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Product name *</label>
                                <input type="text" name="product_name" class="form-control" required/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Date given *</label>
                                <input type="date" name="deworming_date_given" class="form-control" required/>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Next due</label>
                                <input type="date" name="deworming_next_due_date" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dose</label>
                                <input type="text" name="dose" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <textarea name="deworming_notes" class="form-control" rows="2" placeholder="Notes"></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-dark w-100" type="submit">Save deworming</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-heart-pulse-fill me-2"></i>Add treatment</h5>
                    </div>
                    <div class="p-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="add_treatment"/>
                            <div class="col-12">
                                <label class="form-label">Pet *</label>
                                <select name="pet_id" class="form-select" required>
                                    <option value="">Select pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?= (int)$pet['id'] ?>"><?= htmlspecialchars((string)$pet['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Treatment date *</label>
                                <input type="date" name="treatment_date" class="form-control" required/>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Follow-up date</label>
                                <input type="date" name="followup_date" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis</label>
                                <input type="text" name="diagnosis" class="form-control"/>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <option value="">Select</option>
                                    <option value="mild">Mild</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="severe">Severe</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <textarea name="treatment" class="form-control" rows="2" placeholder="Treatment details"></textarea>
                            </div>
                            <div class="col-12">
                                <textarea name="treatment_notes" class="form-control" rows="2" placeholder="Notes"></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-dark w-100" type="submit">Save treatment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="bi bi-list-ul me-2"></i>My pets</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PET</th>
                            <?php if ($has_pet_code): ?><th>CODE</th><?php endif; ?>
                            <th>SPECIES/BREED</th>
                            <th>OWNER</th>
                            <th>STATUS</th>
                            <th>LAST VISIT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pets) > 0): ?>
                            <?php foreach ($pets as $pet): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string)$pet['name']) ?></strong></td>
                                    <?php if ($has_pet_code): ?>
                                        <td><?= htmlspecialchars((string)($pet['pet_code'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?= htmlspecialchars(trim(((string)($pet['species'] ?? '')) . ' / ' . ((string)($pet['breed'] ?? '')))) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)($pet['owner_name'] ?: 'Not linked')) ?>
                                        <?php if (!empty($pet['owner_email'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars((string)$pet['owner_email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst((string)($pet['status'] ?? 'healthy'))) ?></td>
                                    <td><?= htmlspecialchars(!empty($pet['last_visit']) ? date('M j, Y', strtotime((string)$pet['last_visit'])) : '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $has_pet_code ? '6' : '5' ?>" class="text-center text-muted py-4">No pets added yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
