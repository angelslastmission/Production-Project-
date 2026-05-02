<?php
session_start();
include __DIR__ . '/../config.php';
include __DIR__ . '/includes/auth.php';
include __DIR__ . '/includes/reminder_monitor_helper.php';
require_once __DIR__ . '/../includes/petcura_sms.php';

$active_page = 'send_sms_reminders';

function sms_ensure_schema(mysqli $conn): void {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS reminder_sms_logs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      run_id INT NULL, pet_id INT NULL, owner_id INT NULL, vet_id INT NULL,
      record_type VARCHAR(30) NULL, record_id INT NULL, event_key VARCHAR(50) NOT NULL,
      recipient_phone VARCHAR(30) NOT NULL, message_preview TEXT NULL,
      status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
      error_message TEXT NULL, attempts INT NOT NULL DEFAULT 1,
      last_attempt_at DATETIME NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_status (status), INDEX idx_sent_at (sent_at),
      INDEX idx_event_key (event_key), INDEX idx_record (record_type, record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    if (function_exists('admin_column_exists') && !admin_column_exists($conn, 'reminder_sms_logs', 'attempts')) {
        mysqli_query($conn, "ALTER TABLE reminder_sms_logs ADD attempts INT NOT NULL DEFAULT 1 AFTER error_message");
    }
    if (function_exists('admin_column_exists') && !admin_column_exists($conn, 'reminder_sms_logs', 'last_attempt_at')) {
        mysqli_query($conn, "ALTER TABLE reminder_sms_logs ADD last_attempt_at DATETIME NULL AFTER attempts");
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS reminder_sms_runs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      finished_at DATETIME NULL,
      sent_count INT NOT NULL DEFAULT 0,
      failed_count INT NOT NULL DEFAULT 0,
      skipped_count INT NOT NULL DEFAULT 0,
      INDEX idx_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function sms_start_run(mysqli $conn): int {
    mysqli_query($conn, "INSERT INTO reminder_sms_runs (started_at) VALUES (NOW())");
    return (int)mysqli_insert_id($conn);
}

function sms_finish_run(mysqli $conn, int $runId, int $sent, int $failed, int $skipped): void {
    $stmt = mysqli_prepare($conn, "UPDATE reminder_sms_runs SET finished_at = NOW(), sent_count = ?, failed_count = ?, skipped_count = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iiii', $sent, $failed, $skipped, $runId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function sms_event_for_due(string $dueDate): ?array {
    $dueTs = strtotime(date('Y-m-d', strtotime($dueDate)));
    if (!$dueTs) return null;
    $daysLeft = (int)(($dueTs - strtotime(date('Y-m-d'))) / 86400);

    if ($daysLeft === 7) return ['key' => '7_days', 'label' => '7 days before due date'];
    if ($daysLeft === 3) return ['key' => '3_days', 'label' => '3 days before due date'];
    if ($daysLeft === 1) return ['key' => '1_day', 'label' => '1 day before due date'];
    if ($daysLeft === 0) return ['key' => 'due_date', 'label' => 'on due date'];
    if ($daysLeft === -1) return ['key' => '1_day_after', 'label' => '1 day after due date'];

    return null;
}

function sms_format_phone(string $phone): string {
    $phone = trim(str_replace([' ', '-', '(', ')'], '', $phone));
    if ($phone === '') return '';
    if (strpos($phone, '+') === 0) return $phone;
    if (preg_match('/^0?9[0-9]{9}$/', $phone)) {
        return '+977' . ltrim($phone, '0');
    }
    return $phone;
}

function sms_owner(mysqli $conn, int $ownerId): ?array {
    $stmt = mysqli_prepare($conn, "SELECT id, phone, CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) AS name FROM users WHERE id = ? AND role = 'owner' AND is_active = 1 LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $ownerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function sms_already_sent(mysqli $conn, string $recordType, int $recordId, string $eventKey, string $phone): bool {
    // Skip only if this exact reminder was already sent successfully.
    // Failed logs are allowed to retry in the next run.
    $stmt = mysqli_prepare($conn, "SELECT id FROM reminder_sms_logs WHERE record_type = ? AND record_id = ? AND event_key = ? AND recipient_phone = ? AND status = 'sent' LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'siss', $recordType, $recordId, $eventKey, $phone);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (bool)$exists;
}

function sms_existing_failed_log(mysqli $conn, string $recordType, int $recordId, string $eventKey, string $phone): ?array {
    $stmt = mysqli_prepare($conn, "SELECT id, attempts FROM reminder_sms_logs WHERE record_type = ? AND record_id = ? AND event_key = ? AND recipient_phone = ? AND status = 'failed' ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'siss', $recordType, $recordId, $eventKey, $phone);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function sms_type_label(string $type): string {
    if ($type === 'vaccination') return 'Vaccination';
    if ($type === 'deworming') return 'Deworming';
    if ($type === 'treatment') return 'Treatment follow-up';
    return ucfirst($type);
}

function sms_build_message(array $item, array $event): string {
    $pet = trim((string)$item['pet_name']);
    $type = sms_type_label((string)$item['record_type']);
    $title = trim((string)$item['title']);
    $due = date('M d, Y', strtotime((string)$item['due_date']));
    $extra = $title !== '' ? " ({$title})" : '';

    if ($event['key'] === '1_day_after') {
        return "PawCura: Overdue reminder. {$pet}'s {$type}{$extra} was due on {$due}. Please contact your vet.";
    }
    if ($event['key'] === 'due_date') {
        return "PawCura: {$pet}'s {$type}{$extra} is due today ({$due}). Please contact your vet.";
    }
    return "PawCura: {$pet}'s {$type}{$extra} is due on {$due}. Reminder: {$event['label']}.";
}

function sms_log(mysqli $conn, int $runId, array $item, string $phone, array $event, string $message, string $status, string $error = ''): void {
    $petId = (int)$item['pet_id'];
    $ownerId = (int)$item['owner_id'];
    $vetId = (int)$item['vet_id'];
    $recordType = (string)$item['record_type'];
    $recordId = (int)$item['record_id'];
    $eventKey = (string)$event['key'];
    $preview = substr($message, 0, 1000);
    $safeStatus = $status === 'failed' ? 'failed' : 'sent';

    $existingFailed = sms_existing_failed_log($conn, $recordType, $recordId, $eventKey, $phone);

    if ($existingFailed) {
        $logId = (int)$existingFailed['id'];
        $attempts = max(1, (int)$existingFailed['attempts']) + 1;

        $stmt = mysqli_prepare($conn, "UPDATE reminder_sms_logs
            SET run_id = ?, pet_id = ?, owner_id = ?, vet_id = ?, message_preview = ?,
                status = ?, error_message = ?, attempts = ?, last_attempt_at = NOW(), sent_at = NOW()
            WHERE id = ?");
        if (!$stmt) return;
        mysqli_stmt_bind_param($stmt, 'iiiisssii', $runId, $petId, $ownerId, $vetId, $preview, $safeStatus, $error, $attempts, $logId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO reminder_sms_logs
      (run_id, pet_id, owner_id, vet_id, record_type, record_id, event_key, recipient_phone, message_preview, status, error_message, attempts, last_attempt_at, sent_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
    if (!$stmt) return;

    mysqli_stmt_bind_param($stmt, 'iiiisisssss', $runId, $petId, $ownerId, $vetId, $recordType, $recordId, $eventKey, $phone, $preview, $safeStatus, $error);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

sms_ensure_schema($conn);

$results = [];
$totalSent = 0;
$totalFailed = 0;
$totalSkipped = 0;
$runId = 0;
$has_run = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_sender') {
    $has_run = true;
    $runId = sms_start_run($conn);
    $items = admin_get_reminder_monitor_items($conn, null, 500);

    // Safety limit to prevent sending too many SMS in one run.
    // Each SMS attempt also waits 2 seconds to reduce API rate limit errors.
    $maxSmsPerRun = 5;

    foreach ($items as $item) {
        if (($item['due_status_key'] ?? '') === 'completed') {
    $totalSkipped++;
    continue;
}
        $event = sms_event_for_due((string)$item['due_date']);
        if (!$event) { $totalSkipped++; continue; }

        $owner = sms_owner($conn, (int)$item['owner_id']);
        if (!$owner || empty($owner['phone'])) {
            $totalSkipped++;
            $results[] = ['type' => 'warning', 'message' => 'Skipped ' . ($item['pet_name'] ?? 'pet') . ': owner phone missing.'];
            continue;
        }

        $phone = sms_format_phone((string)$owner['phone']);
        if ($phone === '' || strpos($phone, '+') !== 0) {
            $totalSkipped++;
            $results[] = ['type' => 'warning', 'message' => 'Skipped ' . ($item['pet_name'] ?? 'pet') . ': invalid phone number.'];
            continue;
        }

        $recordType = (string)$item['record_type'];
        $recordId = (int)$item['record_id'];
        $eventKey = (string)$event['key'];

        if (sms_already_sent($conn, $recordType, $recordId, $eventKey, $phone)) {
            $totalSkipped++;
            continue;
        }

        if (($totalSent + $totalFailed) >= $maxSmsPerRun) {
            $totalSkipped++;
            $results[] = [
                'type' => 'info',
                'message' => 'SMS safety limit reached. Remaining eligible reminders will be retried in the next run.'
            ];
            continue;
        }

        $message = sms_build_message($item, $event);
        $variables = [
            'name' => trim((string)($owner['name'] ?? 'Pet Owner')),
            'pet_name' => trim((string)$item['pet_name']),
            'treatment' => sms_type_label((string)$item['record_type']) .
                (trim((string)$item['title']) !== '' ? ' (' . trim((string)$item['title']) . ')' : ''),
            'reminder_status' => $event['key'] === '1_day_after' ? 'overdue' : $event['label'],
            'date' => date('M d, Y', strtotime((string)$item['due_date']))
        ];

        $sendResult = send_sms_template($phone, $variables);

        if (!empty($sendResult['success'])) {
            sms_log($conn, $runId, $item, $phone, $event, $message, 'sent');
            $totalSent++;
            $results[] = ['type' => 'success', 'message' => 'Sent SMS to ' . $phone . ' for ' . $item['pet_name'] . '.'];
        }  else {
            $error = (string)($sendResult['error'] ?? 'Unknown SMS error');

            sms_log($conn, $runId, $item, $phone, $event, $message, 'failed', $error);
            $totalFailed++;

            $results[] = [
                'type' => 'danger',
                'message' => 'Failed SMS to ' . $phone . ': ' . $error
            ];

            if (
                stripos($error, 'HTTP 429') !== false ||
                stripos($error, 'daily messages limit') !== false ||
                stripos($error, 'too many requests') !== false
            ) {
                $results[] = [
                    'type' => 'warning',
                    'message' => 'SMS API rate limit reached. Please wait and run the sender again later.'
                ];
                sleep(2);
                break;
            }
        }

        // Wait between SMS attempts to avoid Nest SMS rate limit / Too many requests error.
        sleep(2);
    }

    sms_finish_run($conn, $runId, $totalSent, $totalFailed, $totalSkipped);
} else {
    $results[] = ['type' => 'info', 'message' => 'Click "Run SMS sender" to send reminders now.'];
}

$sentToday = 0;
$failedToday = 0;
$countResult = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM reminder_sms_logs WHERE DATE(sent_at) = CURDATE() GROUP BY status");
while ($countResult && $row = mysqli_fetch_assoc($countResult)) {
    if ($row['status'] === 'sent') $sentToday = (int)$row['total'];
    if ($row['status'] === 'failed') $failedToday = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Send SMS Reminders — PawCura HMS</title>
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
                <h1 class="admin-page-title">Send SMS reminders</h1>
                <p class="admin-page-sub">Runs SMS reminders using the same due-date rules as email reminders. Safety limit: 5 SMS attempts per run with 2 seconds gap between attempts.</p>
            </div>
            <div>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="run_sender" />
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-phone-fill me-1"></i> Run SMS sender
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card mb-3">
            <h5 class="admin-card-title mb-2">SMS run summary</h5>
            <p class="text-muted mb-3">Run ID: <strong><?= $has_run ? '#' . (int)$runId : 'Not started' ?></strong>. SMS delivery depends on the Nest SMS API response.</p>
            <div class="row g-3">
                <div class="col-md-3"><a href="sms_reminder_logs.php?run_id=<?= (int)$runId ?>&status=sent" class="text-decoration-none text-dark"><div class="p-3 border rounded h-100 bg-white"><strong><?= (int)$totalSent ?></strong><br>Sent in this run</div></a></div>
                <div class="col-md-3"><a href="sms_reminder_logs.php?run_id=<?= (int)$runId ?>&status=failed" class="text-decoration-none text-dark"><div class="p-3 border rounded h-100 bg-white"><strong><?= (int)$totalFailed ?></strong><br>Failed in this run</div></a></div>
                <div class="col-md-3"><a href="reminder_logs.php" class="text-decoration-none text-dark"><div class="p-3 border rounded h-100 bg-white"><strong><?= (int)$totalSkipped ?></strong><br>Skipped in this run</div></a></div>
                <div class="col-md-3"><a href="sms_reminder_logs.php?date=today" class="text-decoration-none text-dark"><div class="p-3 border rounded h-100 bg-white"><strong><?= (int)$sentToday ?></strong><br>Sent today total<?php if ($failedToday > 0): ?><small class="d-block text-danger"><?= (int)$failedToday ?> failed today</small><?php endif; ?></div></a></div>
            </div>
        </div>

        <div class="admin-card">
            <h5 class="admin-card-title mb-3">Details</h5>
            <?php if (empty($results)): ?>
                <div class="alert alert-info mb-0">No new SMS reminders were sent in this run.</div>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <div class="alert alert-<?= htmlspecialchars($result['type']) ?> mb-2"><?= htmlspecialchars($result['message']) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="mt-3">
                <a href="reminder_logs.php" class="btn btn-outline-dark me-2">Back to reminder monitor</a>
                <a href="sms_reminder_logs.php" class="btn btn-dark">View SMS logs</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
