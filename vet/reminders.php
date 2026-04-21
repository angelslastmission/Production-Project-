<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'reminders';
$success = '';
$error = '';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    $error = 'Invalid session. Please login again.';
}

$form_data = [
    'pet_id' => '',
    'reminder_type' => '',
    'reminder_date' => '',
    'channel' => 'email',
    'message' => ''
];

$pets = [];
if ($vet_id > 0) {
    $pets_result = mysqli_query(
        $conn,
        "SELECT p.id, p.name, p.species,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
                u.email AS owner_email
         FROM pets p
         LEFT JOIN users u ON p.owner_id = u.id
         WHERE p.vet_id = $vet_id
         ORDER BY p.name ASC"
    );

    if ($pets_result) {
        while ($pet = mysqli_fetch_assoc($pets_result)) {
            $pets[] = $pet;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vet_id > 0) {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_all') {
        $pending_result = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_pending FROM reminders WHERE vet_id = $vet_id AND status = 'pending'"
        );

        $pending_row = $pending_result ? mysqli_fetch_assoc($pending_result) : null;
        $pending_count = (int)($pending_row['total_pending'] ?? 0);

        if ($pending_count <= 0) {
            $error = 'There are no pending reminders to send.';
        } else {
            $update_query = mysqli_query(
                $conn,
                "UPDATE reminders SET status = 'sent', sent_at = NOW() WHERE vet_id = $vet_id AND status = 'pending'"
            );

            if ($update_query) {
                $success = 'All pending reminders marked as sent.';
            } else {
                $error = 'Database error: ' . mysqli_error($conn);
            }
        }
    } else {
        $form_data['pet_id'] = (int)($_POST['pet_id'] ?? 0);
        $form_data['reminder_type'] = trim($_POST['reminder_type'] ?? '');
        $form_data['reminder_date'] = trim($_POST['reminder_date'] ?? '');
        $form_data['channel'] = trim($_POST['channel'] ?? 'email');
        $form_data['message'] = trim($_POST['message'] ?? '');

        if ($form_data['pet_id'] <= 0) {
            $error = 'Please select a patient.';
        } elseif ($form_data['reminder_type'] === '') {
            $error = 'Please select reminder type.';
        } elseif ($form_data['reminder_date'] === '') {
            $error = 'Reminder date is required.';
        }

        if ($error === '') {
            $pet_check = mysqli_query(
                $conn,
                "SELECT p.id, p.owner_id, u.email AS owner_email
                 FROM pets p
                 LEFT JOIN users u ON p.owner_id = u.id
                 WHERE p.id = {$form_data['pet_id']} AND p.vet_id = $vet_id LIMIT 1"
            );

            if (!$pet_check || mysqli_num_rows($pet_check) === 0) {
                $error = 'Invalid patient selected.';
            } else {
                $pet_row = mysqli_fetch_assoc($pet_check);
                $owner_id = (int)($pet_row['owner_id'] ?? 0);

                if ($owner_id <= 0) {
                    $error = 'Selected patient does not have an owner linked.';
                } else {
                    $pet_id = (int)$form_data['pet_id'];
                    $reminder_type = mysqli_real_escape_string($conn, $form_data['reminder_type']);
                    $reminder_date = mysqli_real_escape_string($conn, $form_data['reminder_date']);
                    $channel = mysqli_real_escape_string($conn, $form_data['channel']);
                    $message = $form_data['message'] !== ''
                        ? "'" . mysqli_real_escape_string($conn, $form_data['message']) . "'"
                        : 'NULL';

                    $insert_query = "
                        INSERT INTO reminders (pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, created_at)
                        VALUES ($pet_id, $owner_id, $vet_id, '$reminder_type', '$reminder_date', '$channel', 'pending', $message, NOW())
                    ";

                    if (mysqli_query($conn, $insert_query)) {
                        $success = 'Reminder added successfully.';
                        $form_data = [
                            'pet_id' => '',
                            'reminder_type' => '',
                            'reminder_date' => '',
                            'channel' => 'email',
                            'message' => ''
                        ];
                    } else {
                        $error = 'Database error: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

$reminders = [];
if ($vet_id > 0) {
    $reminder_query = mysqli_query(
        $conn,
        "SELECT r.id, r.reminder_type, r.reminder_date, r.channel, r.status, r.message,
                p.name AS pet_name,
                u.email AS owner_email
         FROM reminders r
         JOIN pets p ON r.pet_id = p.id
         LEFT JOIN users u ON r.owner_id = u.id
         WHERE r.vet_id = $vet_id
         ORDER BY r.reminder_date DESC, r.id DESC"
    );

    if ($reminder_query) {
        while ($row = mysqli_fetch_assoc($reminder_query)) {
            $reminders[] = $row;
        }
    }
}

$today = new DateTime(date('Y-m-d'));
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
        <?php if ($success !== ''): ?>
        <div class="vet-alert mb-3">
            <div class="vet-alert-text">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <div style="background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 10px; padding: 13px 16px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #991b1b; font-size: 0.82rem; font-weight: 700;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        </div>
        <?php endif; ?>

        <section class="vet-panel mb-3">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">New reminder</h3>
                <form method="POST" class="m-0">
                    <input type="hidden" name="action" value="send_all">
                    <button type="submit" class="vet-alert-btn">SEND ALL</button>
                </form>
            </div>

            <form method="POST" class="p-3 p-md-4">
                <input type="hidden" name="action" value="add_reminder">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Patient <span style="color: #dc2626;">*</span></label>
                        <select name="pet_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int)$pet['id'] ?>" <?= (int)$form_data['pet_id'] === (int)$pet['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pet['name']) ?> (<?= htmlspecialchars($pet['species']) ?>) - Owner: <?= htmlspecialchars(trim($pet['owner_name']) !== '' ? trim($pet['owner_name']) : 'Unknown') ?><?= !empty($pet['owner_email']) ? ' [' . htmlspecialchars($pet['owner_email']) . ']' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Reminder type <span style="color: #dc2626;">*</span></label>
                        <select name="reminder_type" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="vaccination" <?= $form_data['reminder_type'] === 'vaccination' ? 'selected' : '' ?>>Vaccination</option>
                            <option value="deworming" <?= $form_data['reminder_type'] === 'deworming' ? 'selected' : '' ?>>Deworming</option>
                            <option value="followup" <?= $form_data['reminder_type'] === 'followup' ? 'selected' : '' ?>>Follow-up</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Reminder date <span style="color: #dc2626;">*</span></label>
                        <input type="date" name="reminder_date" class="form-control" value="<?= htmlspecialchars($form_data['reminder_date']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Channel</label>
                        <select name="channel" class="form-select">
                            <option value="email" <?= $form_data['channel'] === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="sms" <?= $form_data['channel'] === 'sms' ? 'selected' : '' ?>>SMS</option>
                            <option value="both" <?= $form_data['channel'] === 'both' ? 'selected' : '' ?>>Both</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Message</label>
                        <textarea name="message" class="form-control" rows="3"><?= htmlspecialchars($form_data['message']) ?></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="vet-alert-btn">SAVE</button>
                    <button type="reset" class="patients-view-btn">Clear</button>
                </div>
            </form>
        </section>

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Reminder records</h3>
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
                        <?php if (empty($reminders)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No reminders yet</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($reminders as $row): ?>
                                <?php
                                $status_label = 'Pending';
                                $status_class = 'vet-pill-soon';

                                if ($row['status'] === 'sent') {
                                    $status_label = 'Sent';
                                    $status_class = 'vet-pill-updated';
                                } else {
                                    $due_date = new DateTime($row['reminder_date']);
                                    if ($due_date < $today) {
                                        $status_label = 'Overdue';
                                        $status_class = 'vet-pill-overdue';
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pet_name']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($row['reminder_type'])) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['reminder_date']))) ?></td>
                                    <td><?= htmlspecialchars($row['owner_email'] ?? 'Unknown') ?></td>
                                    <td><span class="vet-pill <?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars($status_label) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
