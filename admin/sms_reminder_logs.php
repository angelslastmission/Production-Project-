<?php
session_start();
include __DIR__ . '/../config.php';
include __DIR__ . '/includes/auth.php';

$active_page = 'sms_reminder_logs';

function safe_text($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$run_id = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;

$where = [];
$params = [];
$types = '';
$filter_label = 'All SMS reminder logs';

if ($run_id > 0) {
    $where[] = 'l.run_id = ?';
    $params[] = $run_id;
    $types .= 'i';
    $filter_label = 'SMS logs for run #' . $run_id;
}

if ($status_filter === 'sent' || $status_filter === 'failed') {
    $where[] = 'l.status = ?';
    $params[] = $status_filter;
    $types .= 's';
    $filter_label .= ' — ' . ucfirst($status_filter);
}

if ($date_filter === 'today') {
    $where[] = 'DATE(l.sent_at) = CURDATE()';
    $filter_label = 'SMS sent today';
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$logs = [];
$sql = "SELECT l.*, p.name AS pet_name
        FROM reminder_sms_logs l
        LEFT JOIN pets p ON p.id = l.pet_id
        {$where_sql}
        ORDER BY l.sent_at DESC
        LIMIT 200";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$count_sent_today = 0;
$count_failed_today = 0;
$count_result = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM reminder_sms_logs WHERE DATE(sent_at) = CURDATE() GROUP BY status");
while ($count_result && $row = mysqli_fetch_assoc($count_result)) {
    if ($row['status'] === 'sent') $count_sent_today = (int)$row['total'];
    if ($row['status'] === 'failed') $count_failed_today = (int)$row['total'];
}

function sms_event_label($key) {
    $map = ['7_days'=>'7 days before','3_days'=>'3 days before','1_day'=>'1 day before','due_date'=>'Due date','1_day_after'=>'Overdue'];
    return $map[$key] ?? str_replace('_', ' ', (string)$key);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SMS Reminder Logs — PawCura HMS</title>
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
                <h1 class="admin-page-title">SMS reminder logs</h1>
                <p class="admin-page-sub"><?= safe_text($filter_label) ?></p>
            </div>
            <div><a href="send_sms_reminders.php" class="btn btn-dark"><i class="bi bi-phone-vibrate me-1"></i> Run SMS sender</a></div>
        </div>

        <div class="admin-card mb-3">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div><strong><?= count($logs) ?></strong> records shown <span class="text-muted ms-2">Today: <?= (int)$count_sent_today ?> sent, <?= (int)$count_failed_today ?> failed</span></div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="sms_reminder_logs.php" class="btn btn-sm <?= ($status_filter === '' && $date_filter === '' && $run_id === 0) ? 'btn-dark' : 'btn-outline-dark' ?>">All</a>
                    <a href="sms_reminder_logs.php?status=sent" class="btn btn-sm <?= $status_filter === 'sent' && $run_id === 0 ? 'btn-success' : 'btn-outline-success' ?>">Sent</a>
                    <a href="sms_reminder_logs.php?status=failed" class="btn btn-sm <?= $status_filter === 'failed' && $run_id === 0 ? 'btn-danger' : 'btn-outline-danger' ?>">Failed</a>
                    <a href="sms_reminder_logs.php?date=today" class="btn btn-sm <?= $date_filter === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>">Today</a>
                    <a href="reminder_logs.php" class="btn btn-sm btn-outline-secondary">Reminder monitor</a>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info mb-0">No SMS logs found for this filter.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Run</th><th>Date/Time</th><th>Pet</th><th>Phone</th><th>Type</th><th>Event</th><th>Status</th><th>Attempts</th><th>Message/Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['run_id'] ? '#' . (int)$log['run_id'] : '-' ?></td>
                                    <td><?= safe_text(date('M d, Y h:i A', strtotime($log['sent_at'] ?? 'now'))) ?></td>
                                    <td><?= safe_text($log['pet_name'] ?? '-') ?></td>
                                    <td><?= safe_text($log['recipient_phone'] ?? '') ?></td>
                                    <td><?= safe_text(ucfirst((string)($log['record_type'] ?? ''))) ?></td>
                                    <td><?= safe_text(sms_event_label($log['event_key'] ?? '')) ?></td>
                                    <td><?= ($log['status'] ?? '') === 'sent' ? '<span class="badge bg-success">Sent</span>' : '<span class="badge bg-danger">Failed</span>' ?></td>
                                    <td><?= (int)($log['attempts'] ?? 1) ?></td>
                                    <td style="max-width:420px;">
                                        <?php if (($log['status'] ?? '') === 'failed' && !empty($log['error_message'])): ?>
                                            <span class="text-danger"><?= safe_text($log['error_message']) ?></span>
                                        <?php else: ?>
                                            <?= safe_text($log['message_preview'] ?? '') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
