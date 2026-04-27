<?php
session_start();
include '../config.php';
include 'includes/auth.php';
include 'includes/reminder_monitor_helper.php';

$active_page = 'reminder_logs';

$status_filter = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'overdue', 'due-today', 'due-soon', 'upcoming', 'sent', 'pending', 'not_sent'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}

$items = admin_get_reminder_monitor_items($conn, null, 500);
$filtered_items = [];

foreach ($items as $item) {
    if ($status_filter === 'all') {
        $filtered_items[] = $item;
    } elseif ($status_filter === 'sent' && $item['reminder_status'] === 'sent') {
        $filtered_items[] = $item;
    } elseif ($status_filter === 'pending' && $item['reminder_status'] === 'pending') {
        $filtered_items[] = $item;
    } elseif ($status_filter === 'not_sent' && $item['reminder_status'] === 'not_sent') {
        $filtered_items[] = $item;
    } elseif ($status_filter === $item['due_status_key']) {
        $filtered_items[] = $item;
    }
}

$counts = [
    'all' => count($items),
    'overdue' => 0,
    'due-today' => 0,
    'due-soon' => 0,
    'upcoming' => 0,
    'sent' => 0,
    'pending' => 0,
    'not_sent' => 0
];

foreach ($items as $item) {
    if (isset($counts[$item['due_status_key']])) {
        $counts[$item['due_status_key']]++;
    }
    if ($item['reminder_status'] === 'sent') {
        $counts['sent']++;
    } elseif ($item['reminder_status'] === 'pending') {
        $counts['pending']++;
    } elseif ($item['reminder_status'] === 'not_sent') {
        $counts['not_sent']++;
    }
}

function admin_filter_link($key, $label, $count, $current) {
    $active = $current === $key ? 'btn-review' : 'btn btn-sm btn-outline-secondary';
    return '<a class="' . $active . '" href="reminder_logs.php?status=' . urlencode($key) . '">' . htmlspecialchars($label) . ' (' . (int)$count . ')</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reminder Monitor — PetCare HMS</title>

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
                <h1 class="admin-page-title">Reminder monitor</h1>
                <p class="admin-page-sub">Read-only overview of vaccination, deworming and treatment follow-up reminders</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <div class="admin-card mb-3">
            <div class="d-flex flex-wrap gap-2">
                <?= admin_filter_link('all', 'All', $counts['all'], $status_filter) ?>
                <?= admin_filter_link('overdue', 'Overdue', $counts['overdue'], $status_filter) ?>
                <?= admin_filter_link('due-today', 'Due today', $counts['due-today'], $status_filter) ?>
                <?= admin_filter_link('due-soon', 'Due soon', $counts['due-soon'], $status_filter) ?>
                <?= admin_filter_link('upcoming', 'Upcoming', $counts['upcoming'], $status_filter) ?>
                <?= admin_filter_link('sent', 'Sent', $counts['sent'], $status_filter) ?>
                <?= admin_filter_link('pending', 'Pending', $counts['pending'], $status_filter) ?>
                <?= admin_filter_link('not_sent', 'Not sent', $counts['not_sent'], $status_filter) ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">All reminder records</h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PET</th>
                            <th>OWNER</th>
                            <th>VET</th>
                            <th>TYPE</th>
                            <th>DETAIL</th>
                            <th>DUE DATE</th>
                            <th>DUE STATUS</th>
                            <th>REMINDER STATUS</th>
                            <th>CHANNEL</th>
                            <th>LAST SENT / CREATED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($filtered_items) > 0): ?>
                            <?php foreach ($filtered_items as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['pet_name'] ?: 'Unknown pet') ?></strong></td>
                                <td><?= htmlspecialchars($row['owner_name']) ?></td>
                                <td><?= htmlspecialchars($row['vet_name'] !== 'Not assigned' ? 'Dr. ' . $row['vet_name'] : 'Not assigned') ?></td>
                                <td><?= htmlspecialchars(admin_reminder_type_label($row['record_type'])) ?></td>
                                <td><?= htmlspecialchars($row['title'] ?: '-') ?></td>
                                <td><?= htmlspecialchars(admin_format_date($row['due_date'])) ?></td>
                                <td><span class="<?= htmlspecialchars($row['due_status_class']) ?>"><?= htmlspecialchars($row['due_status_label']) ?></span></td>
                                <td><?= admin_reminder_status_badge($row['reminder_status']) ?></td>
                                <td><?= htmlspecialchars(admin_channel_label($row['reminder_channel'])) ?></td>
                                <td>
                                    <?php
                                        $time = $row['reminder_sent_at'] ?: $row['reminder_created_at'];
                                        echo htmlspecialchars(admin_format_datetime($time));
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No reminder records found</td>
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
