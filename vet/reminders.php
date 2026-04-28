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
$allowed_filters = ['all', 'due_today', 'due_soon', 'due_week', 'overdue', 'upcoming'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

$type_filter = trim((string)($_GET['type'] ?? 'all'));
$allowed_type_filters = ['all', 'vaccine_deworming', 'treatment'];
if (!in_array($type_filter, $allowed_type_filters, true)) {
    $type_filter = 'all';
}

function vet_reminder_query(array $extra = []): string
{
    $params = [
        'status' => $GLOBALS['status_filter'] ?? 'all',
        'type' => $GLOBALS['type_filter'] ?? 'all'
    ];
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }
    return http_build_query($params);
}

// Flash success message from redirect
if (!empty($_GET['sent'])) {
    $success = 'Owner notification sent successfully.';
}
if (!empty($_GET['already_sent'])) {
    $success = 'Owner notification was already sent for this reminder.';
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

function vet_notification_payload($pet_name, $reminder_type, $title_text): array {
    $title = $pet_name . ' — ' . ucfirst($reminder_type) . ' reminder';
    $type  = ($reminder_type === 'vaccination' || $reminder_type === 'deworming') ? 'reminder' : 'followup';
    $message = 'Manual reminder sent by vet for ' . $pet_name . ' - ' . $title_text . '.';
    return [$title, $type, $message];
}

function owner_notification_exists($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $title_text): bool {
    [$title, $type, $message] = vet_notification_payload($pet_name, $reminder_type, $title_text);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id FROM notifications
         WHERE user_id = ?
           AND pet_id = ?
           AND title = ?
           AND message = ?
           AND type = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iisss', $owner_id, $pet_id, $title, $message, $type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

function insert_owner_notification($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $title_text): bool {
    [$title, $type, $message] = vet_notification_payload($pet_name, $reminder_type, $title_text);

    if (owner_notification_exists($conn, $owner_id, $pet_id, $pet_name, $reminder_type, $title_text)) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO notifications (user_id, pet_id, title, message, type, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iisss', $owner_id, $pet_id, $title, $message, $type);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
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

                    // Vet Send Now sends ONLY a website notification.
                    // It must not send email and must not change email reminder flags.
                    $notification_added = insert_owner_notification(
                        $conn,
                        $owner_id,
                        $pet_id,
                        $pet_name,
                        $reminder_type,
                        $title
                    );

                    if ($notification_added) {
                        header('Location: reminders.php?status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '&sent=1&scroll_to=' . $record_type . '-' . $record_id);
                        exit();
                    }

                    header('Location: reminders.php?status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '&already_sent=1&scroll_to=' . $record_type . '-' . $record_id);
                    exit();
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
            p.id AS pet_id, p.name AS pet_name, p.owner_id,
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
            'owner_id' => (int)($row['owner_id'] ?? 0),
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => owner_notification_exists($conn, (int)($row['owner_id'] ?? 0), (int)$row['pet_id'], (string)$row['pet_name'], 'vaccination', (string)$row['title'])
        ];
    }
    mysqli_stmt_close($vacc_query);
}

$deworm_query = mysqli_prepare(
    $conn,
    "SELECT d.id AS record_id, 'deworming' AS record_type,
            d.product_name AS title, d.next_due_date AS due_date,
            p.id AS pet_id, p.name AS pet_name, p.owner_id,
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
            'owner_id' => (int)($row['owner_id'] ?? 0),
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => owner_notification_exists($conn, (int)($row['owner_id'] ?? 0), (int)$row['pet_id'], (string)$row['pet_name'], 'deworming', (string)$row['title'])
        ];
    }
    mysqli_stmt_close($deworm_query);
}

$treat_query = mysqli_prepare(
    $conn,
    "SELECT t.id AS record_id, 'treatment' AS record_type,
            COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS title,
            t.followup_date AS due_date,
            p.id AS pet_id, p.name AS pet_name, p.owner_id,
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
            'owner_id' => (int)($row['owner_id'] ?? 0),
            'owner_name' => trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown',
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'reminder_sent' => owner_notification_exists($conn, (int)($row['owner_id'] ?? 0), (int)$row['pet_id'], (string)$row['pet_name'], 'followup', (string)$row['title'])
        ];
    }
    mysqli_stmt_close($treat_query);
}

usort($items, static function ($a, $b) {
    $a_date = strtotime((string)$a['due_date']) ?: PHP_INT_MAX;
    $b_date = strtotime((string)$b['due_date']) ?: PHP_INT_MAX;
    return $a_date <=> $b_date;
});

// Apply type filter before counting/listing.
// This keeps dashboard clicks accurate:
// vaccination/deworming card will not show treatment follow-ups.
if ($type_filter === 'vaccine_deworming') {
    $items = array_values(array_filter($items, static function ($item) {
        return in_array($item['record_type'], ['vaccination', 'deworming'], true);
    }));
} elseif ($type_filter === 'treatment') {
    $items = array_values(array_filter($items, static function ($item) {
        return $item['record_type'] === 'treatment';
    }));
}

// Count each status after type filter.
$counts = [
    'all'       => 0,
    'due_today' => 0,
    'due_soon'  => 0,
    'due_week'  => 0,
    'overdue'   => 0,
    'upcoming'  => 0
];

foreach ($items as $item) {
    $counts['all']++;

    $due_ts = strtotime((string)$item['due_date']);
    $today_start = strtotime(date('Y-m-d'));
    $week_end = strtotime(date('Y-m-d', strtotime('+7 days')) . ' 23:59:59');
    if ($due_ts && $due_ts >= $today_start && $due_ts <= $week_end) {
        $counts['due_week']++;
    }

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
    $due_ts = strtotime((string)$item['due_date']);
    $today_start = strtotime(date('Y-m-d'));
    $week_end = strtotime(date('Y-m-d', strtotime('+7 days')) . ' 23:59:59');
    $is_due_week = $due_ts && $due_ts >= $today_start && $due_ts <= $week_end;

    if ($status_filter === 'all') {
        $filtered[] = $item;
    } elseif ($status_filter === 'due_today' && $item['status_key'] === 'due-today') {
        $filtered[] = $item;
    } elseif ($status_filter === 'due_soon' && $item['status_key'] === 'due-soon') {
        $filtered[] = $item;
    } elseif ($status_filter === 'due_week' && $is_due_week) {
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
        <section class="vet-panel">
            <div class="vet-panel-header">
                <h3 class="vet-panel-title">Reminder list</h3>
                <span class="text-muted small">
                    <?php if ($type_filter === 'vaccine_deworming'): ?>
                        Showing vaccination/deworming reminders only.
                    <?php elseif ($type_filter === 'treatment'): ?>
                        Showing treatment follow-up reminders only.
                    <?php else: ?>
                        Manual typing removed. Reminders are sent based on medical records only.
                    <?php endif; ?>
                </span>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-sm <?= $type_filter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="reminders.php?<?= htmlspecialchars(vet_reminder_query(['type' => 'all'])) ?>">All types</a>
                <a class="btn btn-sm <?= $type_filter === 'vaccine_deworming' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="reminders.php?<?= htmlspecialchars(vet_reminder_query(['type' => 'vaccine_deworming'])) ?>">Vaccination / Deworming</a>
                <a class="btn btn-sm <?= $type_filter === 'treatment' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="reminders.php?<?= htmlspecialchars(vet_reminder_query(['type' => 'treatment'])) ?>">Treatment follow-ups</a>
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
                            <td colspan="7" class="text-center py-4 text-muted">No reminders found</td>
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