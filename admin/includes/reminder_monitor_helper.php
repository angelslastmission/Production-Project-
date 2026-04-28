<?php

if (!function_exists('admin_column_exists')) {
    function admin_column_exists($conn, $table, $column)
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $table_safe = mysqli_real_escape_string($conn, $table);
        $column_safe = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$table_safe}` LIKE '{$column_safe}'");
        $cache[$key] = $result && mysqli_num_rows($result) > 0;
        return $cache[$key];
    }
}

if (!function_exists('admin_reminders_advanced_supported')) {
    function admin_reminders_advanced_supported($conn)
    {
        return admin_column_exists($conn, 'reminders', 'record_type')
            && admin_column_exists($conn, 'reminders', 'record_id')
            && admin_column_exists($conn, 'reminders', 'next_due_date');
    }
}

if (!function_exists('admin_due_status')) {
    function admin_due_status($due_date)
    {
        if ($due_date === null || $due_date === '' || $due_date === '0000-00-00') {
            return [
                'key' => 'no-date',
                'label' => 'No due date',
                'class' => 'badge-pending'
            ];
        }

        $today = strtotime(date('Y-m-d'));
        $due = strtotime(date('Y-m-d', strtotime((string)$due_date)));

        if ($due === false) {
            return [
                'key' => 'no-date',
                'label' => 'No due date',
                'class' => 'badge-pending'
            ];
        }

        $days = (int)(($due - $today) / 86400);

        if ($days < 0) {
            return [
                'key' => 'overdue',
                'label' => 'Overdue',
                'class' => 'badge-failed'
            ];
        }

        if ($days === 0) {
            return [
                'key' => 'due-today',
                'label' => 'Due today',
                'class' => 'badge-pending'
            ];
        }

        if ($days <= 7) {
            return [
                'key' => 'due-soon',
                'label' => $days === 1 ? 'Due in 1 day' : 'Due in ' . $days . ' days',
                'class' => 'badge-pending'
            ];
        }

        return [
            'key' => 'upcoming',
            'label' => 'Upcoming',
            'class' => 'badge-sent'
        ];
    }
}

if (!function_exists('admin_reminder_type_label')) {
    function admin_reminder_type_label($type)
    {
        if ($type === 'vaccination') return 'Vaccination';
        if ($type === 'deworming') return 'Deworming';
        if ($type === 'treatment') return 'Treatment follow-up';
        if ($type === 'followup') return 'Treatment follow-up';
        return ucfirst((string)$type);
    }
}

if (!function_exists('admin_channel_label')) {
    function admin_channel_label($channel)
    {
        if ($channel === null || $channel === '') return '-';
        if ($channel === 'both') return 'SMS + Email';
        if ($channel === 'sms') return 'SMS';
        if ($channel === 'email') return 'Email';
        return strtoupper((string)$channel);
    }
}

if (!function_exists('admin_format_date')) {
    function admin_format_date($date)
    {
        if (!$date || $date === '0000-00-00') return '-';
        $time = strtotime((string)$date);
        return $time ? date('M d, Y', $time) : '-';
    }
}

if (!function_exists('admin_format_datetime')) {
    function admin_format_datetime($date)
    {
        if (!$date || $date === '0000-00-00 00:00:00') return '-';
        $time = strtotime((string)$date);
        return $time ? date('M d, Y g:ia', $time) : '-';
    }
}

if (!function_exists('admin_latest_reminder_info')) {
    function admin_latest_reminder_info($conn, $item)
    {
        $default = [
            'status' => 'not_sent',
            'channel' => '',
            'sent_at' => '',
            'created_at' => '',
            'message' => ''
        ];

        $record_type = (string)($item['record_type'] ?? '');
        $record_id = (int)($item['record_id'] ?? 0);

        /*
         * For the admin monitor, email logs are the source of truth.
         * Old vet "Send now" notifications may have marked reminders.status = sent
         * without actually sending an email, so do not trust reminders.status alone.
         */
        if ($record_id > 0 && admin_column_exists($conn, 'reminder_email_logs', 'record_type')) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT status, sent_at, message_preview, error_message
                 FROM reminder_email_logs
                 WHERE record_type = ?
                   AND record_id = ?
                 ORDER BY sent_at DESC, id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $record_type, $record_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);

                if ($row) {
                    return [
                        'status' => $row['status'] === 'failed' ? 'failed' : 'sent',
                        'channel' => 'email',
                        'sent_at' => $row['sent_at'] ?? '',
                        'created_at' => $row['sent_at'] ?? '',
                        'message' => ($row['status'] === 'failed' ? ($row['error_message'] ?? '') : ($row['message_preview'] ?? ''))
                    ];
                }
            }
        }

        // If no email log exists yet, show not_sent. Keep created_at only for reference.
        if (admin_reminders_advanced_supported($conn) && $record_id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT created_at, message
                 FROM reminders
                 WHERE record_type = ? AND record_id = ?
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $record_type, $record_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
                if ($row) {
                    $default['created_at'] = $row['created_at'] ?? '';
                    $default['message'] = $row['message'] ?? '';
                }
            }
        }

        return $default;
    }
}
if (!function_exists('admin_get_reminder_monitor_items')) {
    function admin_get_reminder_monitor_items($conn, $days = null, $limit = 300)
    {
        $items = [];
        $days_sql = '';
        if ($days !== null) {
            $days = (int)$days;
            if ($days < 1) $days = 7;
            $days_sql = " AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$days} DAY)";
        }

        $limit = (int)$limit;
        if ($limit < 1) $limit = 300;

        $sql = "
            SELECT * FROM (
                SELECT v.id AS record_id,
                       'vaccination' AS record_type,
                       v.vaccine_name AS title,
                       v.next_due_date AS due_date,
                       p.id AS pet_id,
                       p.name AS pet_name,
                       p.owner_id AS owner_id,
                       v.vet_id AS vet_id,
                       CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) AS owner_name,
                       CONCAT(COALESCE(vet.first_name, ''), ' ', COALESCE(vet.last_name, '')) AS vet_name
                FROM vaccinations v
                JOIN pets p ON p.id = v.pet_id
                LEFT JOIN users o ON o.id = p.owner_id
                LEFT JOIN users vet ON vet.id = v.vet_id
                WHERE v.next_due_date IS NOT NULL
                  AND v.reminder_status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM vaccinations v2 WHERE v2.pet_id = v.pet_id AND v2.vet_id = v.vet_id AND v2.vaccine_name = v.vaccine_name AND v2.id > v.id)

                UNION ALL

                SELECT d.id AS record_id,
                       'deworming' AS record_type,
                       d.product_name AS title,
                       d.next_due_date AS due_date,
                       p.id AS pet_id,
                       p.name AS pet_name,
                       p.owner_id AS owner_id,
                       d.vet_id AS vet_id,
                       CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) AS owner_name,
                       CONCAT(COALESCE(vet.first_name, ''), ' ', COALESCE(vet.last_name, '')) AS vet_name
                FROM dewormings d
                JOIN pets p ON p.id = d.pet_id
                LEFT JOIN users o ON o.id = p.owner_id
                LEFT JOIN users vet ON vet.id = d.vet_id
                WHERE d.next_due_date IS NOT NULL
                  AND d.reminder_status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM dewormings d2 WHERE d2.pet_id = d.pet_id AND d2.vet_id = d.vet_id AND d2.product_name = d.product_name AND d2.id > d.id)

                UNION ALL

                SELECT t.id AS record_id,
                       'treatment' AS record_type,
                       COALESCE(NULLIF(TRIM(t.diagnosis), ''), 'Follow-up') AS title,
                       t.followup_date AS due_date,
                       p.id AS pet_id,
                       p.name AS pet_name,
                       p.owner_id AS owner_id,
                       t.vet_id AS vet_id,
                       CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) AS owner_name,
                       CONCAT(COALESCE(vet.first_name, ''), ' ', COALESCE(vet.last_name, '')) AS vet_name
                FROM treatments t
                JOIN pets p ON p.id = t.pet_id
                LEFT JOIN users o ON o.id = p.owner_id
                LEFT JOIN users vet ON vet.id = t.vet_id
                WHERE t.followup_date IS NOT NULL
                  AND t.followup_status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM treatments t2 WHERE t2.pet_id = t.pet_id AND t2.vet_id = t.vet_id AND COALESCE(t2.diagnosis, '') = COALESCE(t.diagnosis, '') AND t2.id > t.id)
            ) reminder_items
            WHERE due_date IS NOT NULL {$days_sql}
            ORDER BY due_date ASC
            LIMIT {$limit}
        ";

        $result = mysqli_query($conn, $sql);
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $status = admin_due_status($row['due_date']);
            $reminder = admin_latest_reminder_info($conn, $row);
            $row['owner_name'] = trim((string)$row['owner_name']) !== '' ? trim((string)$row['owner_name']) : 'Unknown owner';
            $row['vet_name'] = trim((string)$row['vet_name']) !== '' ? trim((string)$row['vet_name']) : 'Not assigned';
            $row['due_status_key'] = $status['key'];
            $row['due_status_label'] = $status['label'];
            $row['due_status_class'] = $status['class'];
            $row['reminder_status'] = $reminder['status'];
            $row['reminder_channel'] = $reminder['channel'];
            $row['reminder_sent_at'] = $reminder['sent_at'];
            $row['reminder_created_at'] = $reminder['created_at'];
            $row['reminder_message'] = $reminder['message'];
            $items[] = $row;
        }

        return $items;
    }
}

if (!function_exists('admin_reminder_status_badge')) {
    function admin_reminder_status_badge($status)
    {
        if ($status === 'sent') return '<span class="badge-sent">Sent</span>';
        if ($status === 'failed') return '<span class="badge-failed">Failed</span>';
        if ($status === 'pending') return '<span class="badge-pending">Pending</span>';
        return '<span class="badge-pending">Not sent</span>';
    }
}
