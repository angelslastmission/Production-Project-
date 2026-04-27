<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$owner_id = (int)($_SESSION['user_id'] ?? 0);
$pet_id = (int)($_GET['pet_id'] ?? 0);
$alert_type = $_GET['type'] ?? 'followup';
$success = '';
$error = '';

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$allowed_types = ['overdue', 'followup'];
if (!in_array($alert_type, $allowed_types, true)) {
    $alert_type = 'followup';
}

$pet = null;
$pet_stmt = mysqli_prepare($conn,
    "SELECT p.id, p.name, p.vet_id, CONCAT(v.first_name, ' ', v.last_name) AS vet_name
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requested_date = trim($_POST['appointment_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($requested_date === '') {
        $error = 'Please choose a date for the appointment request.';
    } else {
        $reminder_type = $alert_type === 'followup' ? 'followup' : 'vaccination';
        $channel = 'email';
        $message = sprintf(
            'Appointment request for %s on %s.%s',
            $pet['name'],
            date('d M Y', strtotime($requested_date)),
            $notes !== '' ? ' Notes: ' . $notes : ''
        );

        $insert_stmt = mysqli_prepare($conn,
            "INSERT INTO reminders (pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");

        if ($insert_stmt) {
            mysqli_stmt_bind_param(
                $insert_stmt,
                'iiissss',
                $pet['id'],
                $owner_id,
                $pet['vet_id'],
                $reminder_type,
                $requested_date,
                $channel,
                $message
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                header('Location: notifications.php?scheduled=1');
                exit();
            }

            $error = 'Could not save your schedule request. Please try again.';
            mysqli_stmt_close($insert_stmt);
        } else {
            $error = 'Could not prepare the schedule request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Schedule Appointment - PetCura</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
</head>
<body>
<div class="owner-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="owner-main">
        <section class="owner-panel" style="max-width: 820px;">
            <div class="owner-panel-header">
                <h3 class="owner-panel-title">Schedule appointment request</h3>
                <a href="dashboard.php" class="owner-view-all">Back to dashboard</a>
            </div>

            <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                <p style="margin: 0 0 8px; color: #6b7280; font-size: 0.9rem;">Pet</p>
                <h2 style="font-family: 'Sora', sans-serif; font-weight: 700; margin: 0; color: #111827;"> <?= htmlspecialchars($pet['name']) ?> </h2>
                <p style="margin: 8px 0 0; color: #6b7280;">Vet: <?= htmlspecialchars($pet['vet_name'] ?: 'Assigned vet') ?></p>
            </div>

            <div style="padding: 24px;">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Appointment date</label>
                        <input type="date" name="appointment_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Optional message for the vet"></textarea>
                    </div>
                    <button type="submit" class="owner-alert-btn" style="border:0;">Send request</button>
                </form>
            </div>
        </section>
    </main>
</div>
</body>
</html>