<?php
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_helper.php';
include 'includes/reminder_sent_helper.php';

$active_page = 'reminders';
$success = '';
$error = '';

$vet_id = (int)($_SESSION['user_id'] ?? 0);
if ($vet_id <= 0) {
    header('Location: ../login.php');
    exit();
}

$status_filter = trim((string)($_GET['status'] ?? 'all'));
$allowed_filters = ['all', 'due_today', 'due_soon', 'overdue', 'upcoming'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

// Flash success message from redirect
if (!empty($_GET['sent'])) {
    $success = 'Reminder sent successfully.';
}

function map_reminder_type($record_type)
{
    if ($record_type === 'vaccination') {
        return 'vaccination';
    }
    if ($record_type === 'deworming') {
        return 'deworming';
    }
    return 'followup';
}

function insert_owner_notification($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $message) {
    $title = $pet_name . ' — ' . ucfirst($reminder_type) . ' reminder';
    $type  = ($reminder_type === 'vaccination' || $reminder_type === 'deworming') ? 'reminder' : 'followup';
    $stmt  = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, pet_id, title, message, type, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iisss', $owner_id, $pet_id, $title, $message, $type);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'send_now') {
        $record_type = trim((string)($_POST['record_type'] ?? ''));
        $record_id = (int)($_POST['record_id'] ?? 0);

        if (!in_array($record_type, ['vaccination', 'deworming', 'treatment'], true) || $record_id <= 0) {
            $error = 'Invalid reminder request.';
        } else {
            $row = null;

            if ($record_type === 'vaccination') {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT v.id, v.next_due_date AS due_date, v.vaccine_name AS title,
                            p.id AS pet_id, p.name AS pet_name, p.owner_id,
                            u.email AS owner_email
                     FROM vaccinations v
                     JOIN pets p ON p.id = v.pet_id
                     LEFT JOIN users u ON u.id = p.owner_id
                     WHERE v.id = ? AND v.vet_id = ? AND v.reminder_status = 'active'
                     LIMIT 1"
                );
            } elseif ($record_type === 'deworming') {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT d.id, d.next_due_date AS due_date, d.product_name AS title,
                            p.id AS pet_id, p.name AS pet_name, p.owner_id,
                            u.email AS owner_email
                     FROM dewormings d
                     JOIN pets p ON p.id = d.pet_id
                     LEFT JOIN users u ON u.id = p.owner_id
                     WHERE d.id = ? AND d.vet_id = ? AND d.reminder_status = 'active'
                     LIMIT 1"
                );
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT t.id, t.followup_date AS due_date, COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS title,
                            p.id AS pet_id, p.name AS pet_name, p.owner_id,
                            u.email AS owner_email
                     FROM treatments t
                     JOIN pets p ON p.id = t.pet_id
                     LEFT JOIN users u ON u.id = p.owner_id
                     WHERE t.id = ? AND t.vet_id = ? AND t.followup_status = 'active'
                     LIMIT 1"
                );
            }

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $record_id, $vet_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
            }

            if (!$row) {
                $error = 'Reminder source record was not found.';
            } else {
                $pet_id = (int)($row['pet_id'] ?? 0);
                $owner_id = (int)($row['owner_id'] ?? 0);
                $due_date = (string)($row['due_date'] ?? '');
                $title = (string)($row['title'] ?? 'Reminder');
                $pet_name = (string)($row['pet_name'] ?? 'Pet');

                if ($pet_id <= 0 || $owner_id <= 0) {
                    $error = 'Owner/pet link is missing for this record.';
                } else {
                    $reminder_type = map_reminder_type($record_type);
                    $message = 'Manual reminder sent by vet for ' . $pet_name . ' - ' . $title . '.';

                    if (petcura_reminders_advanced_supported($conn)) {
                        // Update the existing PENDING reminder row for this specific record.
                        // We only match record_type exactly and only if it's still pending —
                        // this prevents accidentally marking other rows as sent.
                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE reminders
                             SET status = 'sent',
                                 sent_at = NOW(),
                                 message = ?,
                                 record_type = ?,
                                 event_due_date_sent = 1
                             WHERE vet_id = ?
                               AND pet_id = ?
                               AND record_id = ?
                               AND record_type = ?
                               AND status = 'pending'
                             LIMIT 1"
                        );

                        if ($update_stmt) {
                            mysqli_stmt_bind_param(
                                $update_stmt,
                                'ssiiss',
                                $message,
                                $record_type,
                                $vet_id,
                                $pet_id,
                                $record_id,
                                $record_type
                            );
                            mysqli_stmt_execute($update_stmt);
                            $updated_rows = mysqli_stmt_affected_rows($update_stmt);
                            mysqli_stmt_close($update_stmt);

                            if ($updated_rows > 0) {
                                insert_owner_notification($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $message);
                                header('Location: reminders.php?status=' . urlencode($status_filter) . '&sent=1&scroll_to=' . $record_type . '-' . $record_id);
                                exit();
                            } else {
                                // If no auto-reminder row exists, save a new sent log safely.
                                $insert_stmt = mysqli_prepare(
                                    $conn,
                                    "INSERT INTO reminders (
                                        pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, sent_at, created_at,
                                        record_type, record_id, next_due_date,
                                        reminder_7_days, reminder_1_day, reminder_due_date, reminder_1_day_after,
                                        event_7_days_sent, event_1_day_sent, event_due_date_sent, event_1_day_after_sent
                                    ) VALUES (?, ?, ?, ?, ?, 'email', 'sent', ?, NOW(), NOW(), ?, ?, ?, NULL, NULL, NULL, NULL, 0, 0, 1, 0)"
                                );

                                if ($insert_stmt) {
                                    $reminder_date = ($due_date !== '') ? $due_date : date('Y-m-d');
                                    $next_due_dt = petcura_to_datetime($due_date, '06:00:00');
                                    mysqli_stmt_bind_param(
                                        $insert_stmt,
                                        'iiissssis',
                                        $pet_id,
                                        $owner_id,
                                        $vet_id,
                                        $reminder_type,
                                        $reminder_date,
                                        $message,
                                        $record_type,
                                        $record_id,
                                        $next_due_dt
                                    );
                                    if (mysqli_stmt_execute($insert_stmt)) {
                                        insert_owner_notification($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $message);
                                        header('Location: reminders.php?status=' . urlencode($status_filter) . '&sent=1&scroll_to=' . $record_type . '-' . $record_id);
                                        exit();
                                    } else {
                                        $error = 'Failed to save reminder log: ' . mysqli_stmt_error($insert_stmt);
                                    }
                                    mysqli_stmt_close($insert_stmt);
                                } else {
                                    $error = 'Failed to prepare reminder insert: ' . mysqli_error($conn);
                                }
                            }
                        } else {
                            $error = 'Failed to prepare reminder update: ' . mysqli_error($conn);
                        }
                    } else {
                        $insert_stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO reminders (
                                pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, sent_at, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'email', 'sent', ?, NOW(), NOW())"
                        );

                        if ($insert_stmt) {
                            $reminder_date = ($due_date !== '') ? $due_date : date('Y-m-d');
                            mysqli_stmt_bind_param(
                                $insert_stmt,
                                'iiisss',
                                $pet_id,
                                $owner_id,
                                $vet_id,
                                $reminder_type,
                                $reminder_date,
                                $message
                            );
                            if (mysqli_stmt_execute($insert_stmt)) {
                                insert_owner_notification($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $message);
                                header('Location: reminders.php?status=' . urlencode($status_filter) . '&sent=1&scroll_to=' . $record_type . '-' . $record_id);
                                exit();
                            } else {
                                $error = 'Failed to save reminder log: ' . mysqli_stmt_error($insert_stmt);
                            }
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            $error = 'Failed to prepare reminder insert: ' . mysqli_error($conn);
                        }
                    }
                }
            }
        }
    }
}

$items = [];

$vacc_query = mysqli_prepare(
    $conn,
    "SELECT v.id AS record_id, 'vaccination' AS record_type,
            v.vaccine_name AS title, v.next_due_date AS due_date,
            p.id AS pet_id, p.name AS pet_name,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.email AS owner_email
     FROM vaccinations v
     JOIN pets p ON p.id = v.pet_id
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE v.vet_id = ? AND v.reminder_status = 'active' AND v.next_due_date IS NOT NULL"
);
if ($vacc_query) {
    mysqli_stmt_bind_param($vacc_query, 'i', $vet_id);
    mysqli_stmt_execute($vacc_query);
    $result = mysqli_stmt_get_result($vacc_query);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $status = petcura_due_status($row['due_date']);
        $items[] = [
            'record_id' => (int)$row['record_id'],
            'record_type' => 'vaccination',
            'title' => (string)$row['title'],
            'due_date' => (string)$row['due_date'],
            'pet_id' => (int)$row['pet_id'],
            'pet_name' => (string)$row['pet_name'],
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => petcura_reminder_sent($conn, 'vaccination', (int)$row['record_id'])
        ];
    }
    mysqli_stmt_close($vacc_query);
}

$deworm_query = mysqli_prepare(
    $conn,
    "SELECT d.id AS record_id, 'deworming' AS record_type,
            d.product_name AS title, d.next_due_date AS due_date,
            p.id AS pet_id, p.name AS pet_name,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.email AS owner_email
     FROM dewormings d
     JOIN pets p ON p.id = d.pet_id
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE d.vet_id = ? AND d.reminder_status = 'active' AND d.next_due_date IS NOT NULL"
);
if ($deworm_query) {
    mysqli_stmt_bind_param($deworm_query, 'i', $vet_id);
    mysqli_stmt_execute($deworm_query);
    $result = mysqli_stmt_get_result($deworm_query);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $status = petcura_due_status($row['due_date']);
        $items[] = [
            'record_id' => (int)$row['record_id'],
            'record_type' => 'deworming',
            'title' => (string)$row['title'],
            'due_date' => (string)$row['due_date'],
            'pet_id' => (int)$row['pet_id'],
            'pet_name' => (string)$row['pet_name'],
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => petcura_reminder_sent($conn, 'deworming', (int)$row['record_id'])
        ];
    }
    mysqli_stmt_close($deworm_query);
}

$treat_query = mysqli_prepare(
    $conn,
    "SELECT t.id AS record_id, 'treatment' AS record_type,
            COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS title,
            t.followup_date AS due_date,
            p.id AS pet_id, p.name AS pet_name,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS owner_name,
            u.email AS owner_email
     FROM treatments t
     JOIN pets p ON p.id = t.pet_id
     LEFT JOIN users u ON u.id = p.owner_id
     WHERE t.vet_id = ? AND t.followup_status = 'active' AND t.followup_date IS NOT NULL"
);
if ($treat_query) {
    mysqli_stmt_bind_param($treat_query, 'i', $vet_id);
    mysqli_stmt_execute($treat_query);
    $result = mysqli_stmt_get_result($treat_query);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $status = petcura_due_status($row['due_date']);
        $items[] = [
            'record_id' => (int)$row['record_id'],
            'record_type' => 'treatment',
            'title' => (string)$row['title'],
            'due_date' => (string)$row['due_date'],
            'pet_id' => (int)$row['pet_id'],
            'pet_name' => (string)$row['pet_name'],
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => petcura_reminder_sent($conn, 'treatment', (int)$row['record_id'])
        ];
    }
    mysqli_stmt_close($treat_query);
}

usort($items, static function ($a, $b) {
    $a_date = strtotime((string)$a['due_date']) ?: PHP_INT_MAX;
    $b_date = strtotime((string)$b['due_date']) ?: PHP_INT_MAX;
    return $a_date <=> $b_date;
});

// Count each status (only count unsent items so numbers match what the vet still needs to action)
$counts = [
    'all'       => 0,
    'due_today' => 0,
    'due_soon'  => 0,
    'overdue'   => 0,
    'upcoming'  => 0
];

foreach ($items as $item) {
    $counts['all']++;
    if ($item['status_key'] === 'due-today') {
        $counts['due_today']++;
    } elseif ($item['status_key'] === 'due-soon') {
        $counts['due_soon']++;
    } elseif ($item['status_key'] === 'overdue') {
        $counts['overdue']++;
    } elseif ($item['status_key'] === 'upcoming') {
        $counts['upcoming']++;
    }
}

// Build filtered list for the table (always show all items including sent)
$filtered = [];
foreach ($items as $item) {
    if ($status_filter === 'all') {
        $filtered[] = $item;
    } elseif ($status_filter === 'due_today' && $item['status_key'] === 'due-today') {
        $filtered[] = $item;
    } elseif ($status_filter === 'due_soon' && $item['status_key'] === 'due-soon') {
        $filtered[] = $item;
    } elseif ($status_filter === 'overdue' && $item['status_key'] === 'overdue') {
        $filtered[] = $item;
    } elseif ($status_filter === 'upcoming' && $item['status_key'] === 'upcoming') {
        $filtered[] = $item;
    }
}
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

        <section class="vet-stats" style="margin-bottom: 16px;">
            <a href="reminders.php?status=all" class="vet-stat-card vet-stat-link" aria-label="Show all reminders">
                <div class="vet-stat-icon"><i class="bi bi-list-task"></i></div>
                <div class="vet-stat-number"><?= (int)$counts['all'] ?></div>
                <div class="vet-stat-label">All reminders</div>
            </a>
            <a href="reminders.php?status=due_today" class="vet-stat-card vet-stat-link" aria-label="Show due today reminders">
                <div class="vet-stat-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="vet-stat-number"><?= (int)$counts['due_today'] ?></div>
                <div class="vet-stat-label">Due today</div>
            </a>
            <a href="reminders.php?status=due_soon" class="vet-stat-card vet-stat-link" aria-label="Show due soon reminders">
                <div class="vet-stat-icon"><i class="bi bi-alarm"></i></div>
                <div class="vet-stat-number"><?= (int)$counts['due_soon'] ?></div>
                <div class="vet-stat-label">Due soon</div>
            </a>
            <a href="reminders.php?status=overdue" class="vet-stat-card vet-stat-link" aria-label="Show overdue reminders">
                <div class="vet-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="vet-stat-number"><?= (int)$counts['overdue'] ?></div>
                <div class="vet-stat-label">Overdue</div>
            </a>
        </section>

        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Auto reminder queue (status-based)</h3>
                <span class="text-muted small">Manual typing removed. Reminders are sent based on medical records only.</span>
            </div>
            <div class="table-responsive vet-reminder-scroll">
                <table class="vet-panel-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Due date</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No reminders in this status filter</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($filtered as $row): ?>
                                <tr id="row-<?= htmlspecialchars($row['record_type']) ?>-<?= (int)$row['record_id'] ?>">
                                    <td>
                                        <a href="patient_detail.php?id=<?= (int)$row['pet_id'] ?>" style="text-decoration:none; color: inherit;">
                                            <?= htmlspecialchars($row['pet_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst($row['record_type'])) ?></td>
                                    <td><?= htmlspecialchars($row['title']) ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['owner_name']) ?>
                                        <?php if ($row['owner_email'] !== ''): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($row['owner_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="vet-pill <?= htmlspecialchars($row['status_class']) ?>">
                                            <?= htmlspecialchars($row['status_label']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($row['reminder_sent']) && $row['reminder_sent']): ?>
                                            <span class="vet-pill vet-pill-status">Sent</span>
                                        <?php else: ?>
                                            <form method="POST" class="m-0">
                                                <input type="hidden" name="action" value="send_now">
                                                <input type="hidden" name="record_type" value="<?= htmlspecialchars($row['record_type']) ?>">
                                                <input type="hidden" name="record_id" value="<?= (int)$row['record_id'] ?>">
                                                <button type="submit" class="vet-alert-btn" style="padding:6px 12px; font-size:12px;">
                                                    Send now
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll to the row that was just sent, and highlight it briefly
var scrollTo = '<?= htmlspecialchars($_GET['scroll_to'] ?? '') ?>';
if (scrollTo) {
    var row = document.getElementById('row-' + scrollTo);
    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.style.transition = 'background 0.3s';
        row.style.background = '#f0fdfa';
        setTimeout(function() { row.style.background = ''; }, 2500);
    }
}
</script>
</body>
</html>