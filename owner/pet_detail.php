<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'pet_records';
$owner_id = (int)($_SESSION['user_id'] ?? 0);
$pet_id = (int)($_GET['id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

if ($pet_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

$pet = null;
$pet_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.name, p.species, p.breed, p.gender, p.dob, p.weight, p.status,
            p.allergies, p.last_visit, p.vet_id,
            CONCAT(v.first_name, ' ', v.last_name) AS vet_name,
            v.clinic_name
     FROM pets p
     LEFT JOIN users v ON v.id = p.vet_id AND v.role = 'vet'
     WHERE p.id = ? AND p.owner_id = ?");
if ($pet_stmt) {
    mysqli_stmt_bind_param($pet_stmt, 'ii', $pet_id, $owner_id);
    mysqli_stmt_execute($pet_stmt);
    $pet_result = mysqli_stmt_get_result($pet_stmt);
    $pet = $pet_result ? mysqli_fetch_assoc($pet_result) : null;
    mysqli_stmt_close($pet_stmt);
}

if (!$pet) {
    header('Location: dashboard.php');
    exit();
}

$vaccinations = [];
$vacc_stmt = mysqli_prepare($conn,
    "SELECT vaccine_name, date_given, next_due_date, dose_number, batch_number, notes
     FROM vaccinations
     WHERE pet_id = ?
     ORDER BY date_given DESC");
if ($vacc_stmt) {
    mysqli_stmt_bind_param($vacc_stmt, 'i', $pet_id);
    mysqli_stmt_execute($vacc_stmt);
    $vacc_result = mysqli_stmt_get_result($vacc_stmt);
    while ($row = $vacc_result ? mysqli_fetch_assoc($vacc_result) : null) {
        $vaccinations[] = $row;
    }
    mysqli_stmt_close($vacc_stmt);
}

$dewormings = [];
$deworm_stmt = mysqli_prepare($conn,
    "SELECT product_name, date_given, next_due_date, dose, notes
     FROM dewormings
     WHERE pet_id = ?
     ORDER BY date_given DESC");
if ($deworm_stmt) {
    mysqli_stmt_bind_param($deworm_stmt, 'i', $pet_id);
    mysqli_stmt_execute($deworm_stmt);
    $deworm_result = mysqli_stmt_get_result($deworm_stmt);
    while ($row = $deworm_result ? mysqli_fetch_assoc($deworm_result) : null) {
        $dewormings[] = $row;
    }
    mysqli_stmt_close($deworm_stmt);
}

$treatments = [];
$treat_stmt = mysqli_prepare($conn,
    "SELECT diagnosis, treatment, treatment_date, followup_date, severity, notes
     FROM treatments
     WHERE pet_id = ?
     ORDER BY treatment_date DESC");
if ($treat_stmt) {
    mysqli_stmt_bind_param($treat_stmt, 'i', $pet_id);
    mysqli_stmt_execute($treat_stmt);
    $treat_result = mysqli_stmt_get_result($treat_stmt);
    while ($row = $treat_result ? mysqli_fetch_assoc($treat_result) : null) {
        $treatments[] = $row;
    }
    mysqli_stmt_close($treat_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pet Records - PetCura</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <section class="owner-panel mb-3">
            <div class="owner-panel-header">
                <div>
                    <h2 style="font-size: 1.75rem; font-weight: 700; font-family: 'Sora', sans-serif; margin: 0;">
                        <?= htmlspecialchars($pet['name']) ?>
                        <span style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 4px; font-size: 0.75rem; margin-left: 12px; font-weight: 700;">
                            <?= htmlspecialchars($pet['status']) ?>
                        </span>
                    </h2>
                    <p style="color: #6b7280; font-size: 0.95rem; margin-top: 6px;">
                        <?= htmlspecialchars($pet['breed']) ?> · <?= htmlspecialchars($pet['gender']) ?> · <?= htmlspecialchars($pet['species']) ?>
                    </p>
                </div>
                <a href="dashboard.php" style="color: #0d9488; text-decoration: none; font-weight: 600;">Back</a>
            </div>

            <div style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Weight</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($pet['weight']) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Allergies</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($pet['allergies']) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">Last Visit</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?= date('M d, Y', strtotime($pet['last_visit'])) ?></div>
                </div>
            </div>
        </section>

        <!-- Tabs Navigation -->
        <section class="owner-panel" style="margin-bottom: 24px;">
            <div style="display: flex; gap: 24px; border-bottom: 1px solid #e5e7eb; margin: -24px -24px 0 -24px; padding: 0 24px;">
                <button type="button" onclick="switchTab('vaccinations', this)" class="tab-btn active" style="padding: 16px 0; color: #0d9488; border-bottom: 3px solid #0d9488; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Vaccinations</button>
                <button type="button" onclick="switchTab('deworming', this)" class="tab-btn" style="padding: 16px 0; color: #6b7280; border-bottom: 3px solid transparent; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Deworming</button>
                <button type="button" onclick="switchTab('treatments', this)" class="tab-btn" style="padding: 16px 0; color: #6b7280; border-bottom: 3px solid transparent; border: none; background: none; text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer;">Treatments</button>
            </div>

            <!-- Vaccinations Table -->
            <div id="vaccinations-content" class="tab-content" style="margin-top: 20px; display: block;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Vaccine</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date Given</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Next Due</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Dose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vaccinations)): ?>
                            <tr>
                                <td colspan="4" style="padding: 24px 0; text-align: center; color: #9ca3af;">No vaccination records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($vaccinations as $vac): ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($vac['vaccine_name']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($vac['date_given'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($vac['next_due_date'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($vac['dose_number']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Deworming Table -->
            <div id="deworming-content" class="tab-content" style="margin-top: 20px; display: none;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Product</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date Given</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Next Due</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Dose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dewormings)): ?>
                            <tr>
                                <td colspan="4" style="padding: 24px 0; text-align: center; color: #9ca3af;">No deworming records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($dewormings as $dew): ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($dew['product_name']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($dew['date_given'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($dew['next_due_date'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($dew['dose']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Treatments Table -->
            <div id="treatments-content" class="tab-content" style="margin-top: 20px; display: none;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Diagnosis</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Treatment</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Date</th>
                                <th style="text-align: left; padding: 12px 0; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.75rem;">Severity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($treatments)): ?>
                            <tr>
                                <td colspan="4" style="padding: 24px 0; text-align: center; color: #9ca3af;">No treatment records found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($treatments as $treat): ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($treat['diagnosis']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= htmlspecialchars($treat['treatment']) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;"><?= date('M d, Y', strtotime($treat['treatment_date'])) ?></td>
                                <td style="padding: 14px 0; color: #1f2937;">
                                    <span style="background: <?= $treat['severity'] === 'Critical' ? '#fee2e2' : ($treat['severity'] === 'High' ? '#fef3c7' : '#dcfce7') ?>; color: <?= $treat['severity'] === 'Critical' ? '#991b1b' : ($treat['severity'] === 'High' ? '#92400e' : '#166534') ?>; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                                        <?= htmlspecialchars($treat['severity']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <script>
        function switchTab(tabName, activeButton) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.style.display = 'none');
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                btn.style.color = '#6b7280';
                btn.style.borderBottom = '3px solid transparent';
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById(tabName + '-content');
            if (selectedContent) {
                selectedContent.style.display = 'block';
            }
            
            // Highlight active button
            if (activeButton) {
                activeButton.style.color = '#0d9488';
                activeButton.style.borderBottom = '3px solid #0d9488';
                activeButton.classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            switchTab('vaccinations', document.querySelector('.tab-btn.active'));
        });
        </script>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
