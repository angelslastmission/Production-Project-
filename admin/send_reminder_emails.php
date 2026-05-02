<?php
session_start();
include __DIR__ . '/../config.php';
include __DIR__ . '/includes/auth.php';
include 'includes/reminder_monitor_helper.php';
require_once __DIR__ . '/../includes/petcura_mailer.php';

$active_page = 'send_email_reminders';

function email_sender_column_exists(mysqli $conn, string $table, string $column): bool {
    $table_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column_safe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table_safe === '' || $column_safe === '') return false;

    $result = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM `{$table_safe}` LIKE '" . mysqli_real_escape_string($conn, $column_safe) . "'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function email_sender_ensure_schema(mysqli $conn): void {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS reminder_email_runs (
          id INT AUTO_INCREMENT PRIMARY KEY,
          started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          finished_at DATETIME NULL,
          sent_count INT NOT NULL DEFAULT 0,
          failed_count INT NOT NULL DEFAULT 0,
          skipped_count INT NOT NULL DEFAULT 0,
          INDEX idx_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!email_sender_column_exists($conn, 'reminder_email_runs', 'finished_at')) {
        mysqli_query($conn, "ALTER TABLE reminder_email_runs ADD COLUMN finished_at DATETIME NULL AFTER started_at");
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS reminder_email_logs (
          id INT AUTO_INCREMENT PRIMARY KEY,
          run_id INT NULL,
          reminder_id INT NULL,
          pet_id INT NULL,
          owner_id INT NULL,
          vet_id INT NULL,
          record_type VARCHAR(30) NULL,
          record_id INT NULL,
          event_key VARCHAR(50) NOT NULL,
          recipient_email VARCHAR(255) NOT NULL,
          subject VARCHAR(255) NOT NULL,
          message_preview TEXT NULL,
          status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
          error_message TEXT NULL,
          sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_run_id (run_id),
          INDEX idx_status (status),
          INDEX idx_sent_at (sent_at),
          INDEX idx_event_key (event_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!email_sender_column_exists($conn, 'reminder_email_logs', 'run_id')) {
        mysqli_query($conn, "ALTER TABLE reminder_email_logs ADD COLUMN run_id INT NULL AFTER id");
    }
    if (!email_sender_column_exists($conn, 'reminder_email_logs', 'record_type')) {
        mysqli_query($conn, "ALTER TABLE reminder_email_logs ADD COLUMN record_type VARCHAR(30) NULL AFTER vet_id");
    }
    if (!email_sender_column_exists($conn, 'reminder_email_logs', 'record_id')) {
        mysqli_query($conn, "ALTER TABLE reminder_email_logs ADD COLUMN record_id INT NULL AFTER record_type");
    }
}

function email_sender_start_run(mysqli $conn): int {
    mysqli_query($conn, "INSERT INTO reminder_email_runs (started_at) VALUES (NOW())");
    return (int)mysqli_insert_id($conn);
}

function email_sender_finish_run(mysqli $conn, int $runId, int $sent, int $failed, int $skipped): void {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reminder_email_runs
         SET finished_at = NOW(), sent_count = ?, failed_count = ?, skipped_count = ?
         WHERE id = ?"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iiii', $sent, $failed, $skipped, $runId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function email_sender_settings(mysqli $conn): array {
    $defaults = [
        'email_reminders' => 1,
        'auto_scheduler' => 1,
        'remind_7days' => 1,
        'remind_3days' => 1,
        'remind_due' => 1,
        'remind_overdue' => 1,
    ];

    $result = mysqli_query(
        $conn,
        "SELECT email_reminders, auto_scheduler, remind_7days, remind_3days, remind_due, remind_overdue
         FROM admin_settings
         WHERE id = 1
         LIMIT 1"
    );

    if ($result && $row = mysqli_fetch_assoc($result)) {
        foreach ($defaults as $key => $value) {
            $defaults[$key] = (int)($row[$key] ?? $value);
        }
    }

    return $defaults;
}

function email_sender_event_for_due(string $dueDate, array $settings): ?array {
    $dueTs = strtotime(date('Y-m-d', strtotime($dueDate)));
    if (!$dueTs) return null;

    $todayTs = strtotime(date('Y-m-d'));
    $daysLeft = (int)(($dueTs - $todayTs) / 86400);

    if ($daysLeft === 7 && !empty($settings['remind_7days'])) {
        return ['key' => '7_days', 'label' => '7 days before due date'];
    }

    if ($daysLeft === 3 && !empty($settings['remind_3days'])) {
        return ['key' => '3_days', 'label' => '3 days before due date'];
    }

    if ($daysLeft === 1) {
        return ['key' => '1_day', 'label' => '1 day before due date'];
    }

    // Due date and overdue are allowed when this sender is manually run.
    if ($daysLeft === 0 && !empty($settings['remind_due'])) {
        return ['key' => 'due_date', 'label' => 'on due date'];
    }

    if ($daysLeft === -1 && !empty($settings['remind_overdue'])) {
        return ['key' => '1_day_after', 'label' => 'overdue'];
    }

    return null;
}

function email_sender_type_label(string $type): string {
    if ($type === 'vaccination') return 'Vaccination';
    if ($type === 'deworming') return 'Deworming';
    if ($type === 'treatment' || $type === 'followup') return 'Treatment follow-up';
    return ucfirst($type);
}

function email_sender_owner(mysqli $conn, int $ownerId): ?array {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, email, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS name
         FROM users
         WHERE id = ? AND role = 'owner' AND is_active = 1
         LIMIT 1"
    );

    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, 'i', $ownerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function email_sender_already_sent(mysqli $conn, string $recordType, int $recordId, string $eventKey, string $recipient): bool {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id
         FROM reminder_email_logs
         WHERE status = 'sent'
           AND record_type = ?
           AND record_id = ?
           AND event_key = ?
           AND recipient_email = ?
         LIMIT 1"
    );

    if (!$stmt) return false;

    mysqli_stmt_bind_param($stmt, 'siss', $recordType, $recordId, $eventKey, $recipient);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

function email_sender_build_message(array $item, array $owner, array $event): array {
    $ownerName = trim((string)($owner['name'] ?? ''));
    $petName = trim((string)$item['pet_name']);
    $typeLabel = email_sender_type_label((string)$item['record_type']);
    $detail = trim((string)$item['title']);
    $dueDate = date('M d, Y', strtotime((string)$item['due_date']));
    $eventKey = (string)$event['key'];

    if ($eventKey === '1_day_after') {
        $subject = "PawCura overdue reminder for {$petName}";
        $mainLine = "This is an overdue reminder. {$petName}'s {$typeLabel}" . ($detail !== '' ? " ({$detail})" : '') . " was due on {$dueDate}.";
    } elseif ($eventKey === 'due_date') {
        $subject = "PawCura due today reminder for {$petName}";
        $mainLine = "{$petName}'s {$typeLabel}" . ($detail !== '' ? " ({$detail})" : '') . " is due today, {$dueDate}.";
    } else {
        $subject = "PawCura upcoming reminder for {$petName}";
        $mainLine = "{$petName}'s {$typeLabel}" . ($detail !== '' ? " ({$detail})" : '') . " is due on {$dueDate}. This reminder is sent {$event['label']}.";
    }

    $vetText = trim((string)($item['vet_name'] ?? '')) !== '' && (string)$item['vet_name'] !== 'Not assigned'
        ? 'Dr. ' . trim((string)$item['vet_name'])
        : 'your vet';

    $safeOwner = htmlspecialchars($ownerName !== '' ? $ownerName : 'Pet Owner', ENT_QUOTES, 'UTF-8');
    $safeMain = htmlspecialchars($mainLine, ENT_QUOTES, 'UTF-8');
    $safeVet = htmlspecialchars($vetText, ENT_QUOTES, 'UTF-8');

    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">'
        . '<h2 style="margin-bottom:12px;color:#0f766e;">PawCura Reminder</h2>'
        . '<p>Hello ' . $safeOwner . ',</p>'
        . '<p>' . $safeMain . '</p>'
        . '<p>Please contact ' . $safeVet . ' or your clinic if you need to reschedule.</p>'
        . '<p style="font-size:13px;color:#6b7280;">This is an automated reminder from PawCura HMS.</p>'
        . '</div>';

    $plain = "Hello " . ($ownerName !== '' ? $ownerName : 'Pet Owner') . ",\n\n" . $mainLine . "\n\nPlease contact " . $vetText . " or your clinic if you need to reschedule.\n\nThis is an automated reminder from PawCura HMS.";

    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

function email_sender_log_attempt(mysqli $conn, int $runId, array $item, array $owner, array $event, array $message, string $status, string $errorMessage = ''): void {
    $preview = substr((string)($message['plain'] ?? ''), 0, 1000);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reminder_email_logs
            (run_id, reminder_id, pet_id, owner_id, vet_id, record_type, record_id, event_key, recipient_email, subject, message_preview, status, error_message, sent_at)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) return;

    $petId = (int)$item['pet_id'];
    $ownerId = (int)$item['owner_id'];
    $vetId = (int)$item['vet_id'];
    $recordType = (string)$item['record_type'];
    $recordId = (int)$item['record_id'];
    $eventKey = (string)$event['key'];
    $recipient = (string)$owner['email'];
    $subject = (string)$message['subject'];
    $safeStatus = $status === 'failed' ? 'failed' : 'sent';

    mysqli_stmt_bind_param(
        $stmt,
        'iiiisissssss',
        $runId,
        $petId,
        $ownerId,
        $vetId,
        $recordType,
        $recordId,
        $eventKey,
        $recipient,
        $subject,
        $preview,
        $safeStatus,
        $errorMessage
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

email_sender_ensure_schema($conn);

$results = [];
$totalSent = 0;
$totalFailed = 0;
$totalSkipped = 0;
$sentTodayTotal = 0;
$failedTodayTotal = 0;
$runId = 0;
$has_run = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_sender') {
    $has_run = true;
    $settings = email_sender_settings($conn);
    $runId = email_sender_start_run($conn);

    if (empty($settings['email_reminders'])) {
        $results[] = ['type' => 'info', 'message' => 'Email reminders are disabled in admin settings.'];
    } elseif (empty($settings['auto_scheduler'])) {
        $results[] = ['type' => 'info', 'message' => 'Auto reminder scheduler is disabled in admin settings.'];
    } else {
        $items = admin_get_reminder_monitor_items($conn, null, 500);

        foreach ($items as $item) {
             if (($item['due_status_key'] ?? '') === 'completed') {
        $totalSkipped++;
        continue;
    }

            $event = email_sender_event_for_due((string)$item['due_date'], $settings);

            if (!$event) {
                $totalSkipped++;
                continue;
            }

            $owner = email_sender_owner($conn, (int)$item['owner_id']);

            if (!$owner || empty($owner['email'])) {
                $totalSkipped++;
                $results[] = [
                    'type' => 'warning',
                    'message' => 'Skipped ' . ($item['pet_name'] ?? 'pet') . ': owner email missing or owner account inactive.'
                ];
                continue;
            }

            $recordType = (string)$item['record_type'];
            $recordId = (int)$item['record_id'];
            $eventKey = (string)$event['key'];
            $recipient = (string)$owner['email'];

            if (email_sender_already_sent($conn, $recordType, $recordId, $eventKey, $recipient)) {
                $totalSkipped++;
                continue;
            }

            $message = email_sender_build_message($item, $owner, $event);

            $sendResult = petcura_send_system_email(
                $recipient,
                trim((string)$owner['name']),
                $message['subject'],
                $message['html'],
                $message['plain']
            );

            if (!empty($sendResult['success'])) {
                email_sender_log_attempt($conn, $runId, $item, $owner, $event, $message, 'sent');
                $totalSent++;
                $results[] = [
                    'type' => 'success',
                    'message' => 'Sent ' . $event['label'] . ' email to ' . $recipient . ' for ' . $item['pet_name'] . '.'
                ];
            } else {
                $errorText = (string)($sendResult['error'] ?? 'Unknown mailer error');
                email_sender_log_attempt($conn, $runId, $item, $owner, $event, $message, 'failed', $errorText);
                $totalFailed++;
                $results[] = [
                    'type' => 'danger',
                    'message' => 'Failed for ' . $recipient . ': ' . $errorText
                ];
            }
        }
    }

    email_sender_finish_run($conn, $runId, $totalSent, $totalFailed, $totalSkipped);
} else {
    $results[] = ['type' => 'info', 'message' => 'Click "Run email sender" to send reminders now.'];
}

$logCountResult = mysqli_query(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM reminder_email_logs
     WHERE DATE(sent_at) = CURDATE()
     GROUP BY status"
);

if ($logCountResult) {
    while ($logCountRow = mysqli_fetch_assoc($logCountResult)) {
        if (($logCountRow['status'] ?? '') === 'sent') {
            $sentTodayTotal = (int)($logCountRow['total'] ?? 0);
        } elseif (($logCountRow['status'] ?? '') === 'failed') {
            $failedTodayTotal = (int)($logCountRow['total'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Send Email Reminders — PawCura HMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="admin-page-title">Send email reminders</h1>
                <p class="admin-page-sub">Runs 7-day, 3-day, 1-day, due-date and overdue reminder emails.</p>
            </div>
            <div>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="run_sender" />
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-send-fill me-1"></i> Run email sender
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card mb-3">
            <h5 class="admin-card-title mb-2">Run summary</h5>
            <p class="text-muted mb-3" style="font-size:0.92rem;">
                Run ID: <strong><?= $has_run ? '#' . (int)$runId : 'Not started' ?></strong>. This sender checks the same records shown in Reminder Monitor.
            </p>

            <div class="row g-3">
                <div class="col-md-3">
                    <a href="email_reminder_logs.php?run_id=<?= (int)$runId ?>&status=sent" class="text-decoration-none text-dark">
                        <div class="p-3 border rounded h-100 bg-white">
                            <strong><?= (int)$totalSent ?></strong><br>
                            Sent in this run
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="email_reminder_logs.php?run_id=<?= (int)$runId ?>&status=failed" class="text-decoration-none text-dark">
                        <div class="p-3 border rounded h-100 bg-white">
                            <strong><?= (int)$totalFailed ?></strong><br>
                            Failed in this run
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="reminder_logs.php" class="text-decoration-none text-dark">
                        <div class="p-3 border rounded h-100 bg-white">
                            <strong><?= (int)$totalSkipped ?></strong><br>
                            Skipped in this run
                        </div>
                    </a>
                </div>

                <div class="col-md-3">
                    <a href="email_reminder_logs.php?date=today" class="text-decoration-none text-dark">
                        <div class="p-3 border rounded h-100 bg-white">
                            <strong><?= (int)$sentTodayTotal ?></strong><br>
                            Sent today total
                            <?php if ($failedTodayTotal > 0): ?>
                                <small class="d-block text-danger"><?= (int)$failedTodayTotal ?> failed today</small>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5 class="admin-card-title mb-3">Details</h5>

            <?php if (empty($results)): ?>
                <div class="alert alert-info mb-0">No new email reminders were sent in this run.</div>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <div class="alert alert-<?= htmlspecialchars($result['type']) ?> mb-2">
                        <?= htmlspecialchars($result['message']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mt-3">
                <a href="reminder_logs.php" class="btn btn-outline-dark me-2">Back to reminder monitor</a>
                <a href="email_reminder_logs.php" class="btn btn-dark">View email logs</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
