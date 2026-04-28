<?php

if (!function_exists('petcura_reminder_column_exists')) {
    function petcura_reminder_column_exists($conn, $column)
    {
        static $cache = [];

        if (isset($cache[$column])) {
            return $cache[$column];
        }

        $column_safe = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM reminders LIKE '" . $column_safe . "'");
        $exists = $result && mysqli_num_rows($result) > 0;
        $cache[$column] = $exists;

        return $exists;
    }
}

if (!function_exists('petcura_ensure_reminder_email_columns')) {
    function petcura_ensure_reminder_email_columns($conn)
    {
        $columns = [
            'reminder_3_days' => "ALTER TABLE reminders ADD COLUMN reminder_3_days DATETIME DEFAULT NULL AFTER reminder_7_days",
            'event_3_days_sent' => "ALTER TABLE reminders ADD COLUMN event_3_days_sent TINYINT(1) DEFAULT 0 AFTER event_7_days_sent"
        ];

        foreach ($columns as $column => $alter_sql) {
            if (!petcura_reminder_column_exists($conn, $column)) {
                mysqli_query($conn, $alter_sql);
            }
        }

        return petcura_reminder_column_exists($conn, 'reminder_3_days')
            && petcura_reminder_column_exists($conn, 'event_3_days_sent');
    }
}

if (!function_exists('petcura_reminders_advanced_supported')) {
    function petcura_reminders_advanced_supported($conn)
    {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }

        $required = [
            'record_type',
            'record_id',
            'next_due_date',
            'reminder_7_days',
            'reminder_3_days',
            'reminder_1_day',
            'reminder_due_date',
            'reminder_1_day_after',
            'event_7_days_sent',
            'event_3_days_sent',
            'event_1_day_sent',
            'event_due_date_sent',
            'event_1_day_after_sent'
        ];

        foreach ($required as $column) {
            if (!petcura_reminder_column_exists($conn, $column)) {
                $supported = false;
                return $supported;
            }
        }

        $supported = true;
        return $supported;
    }
}

if (!function_exists('petcura_to_datetime')) {
    function petcura_to_datetime($date, $time = '06:00:00')
    {
        if ($date === null || $date === '') {
            return null;
        }

        $timestamp = strtotime((string)$date);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp) . ' ' . $time;
    }
}

if (!function_exists('petcura_due_status')) {
    function petcura_due_status($next_due_date)
    {
        if ($next_due_date === null || $next_due_date === '' || $next_due_date === '0000-00-00') {
            return [
                'key' => 'no-date',
                'label' => 'No next date',
                'class' => 'vet-pill-status'
            ];
        }

        $today = strtotime(date('Y-m-d'));
        $due = strtotime(date('Y-m-d', strtotime((string)$next_due_date)));

        if ($due === false) {
            return [
                'key' => 'no-date',
                'label' => 'No next date',
                'class' => 'vet-pill-status'
            ];
        }

        $days = (int)(($due - $today) / 86400);

        if ($days < 0) {
            return [
                'key' => 'overdue',
                'label' => 'Overdue',
                'class' => 'vet-pill-overdue'
            ];
        }

        if ($days === 0) {
            return [
                'key' => 'due-today',
                'label' => 'Due today',
                'class' => 'vet-pill-soon'
            ];
        }

        if ($days <= 7) {
            $label = $days === 1 ? 'Due in 1 day' : "Due in {$days} days";
            return [
                'key' => 'due-soon',
                'label' => $label,
                'class' => 'vet-pill-soon'
            ];
        }

        return [
            'key' => 'upcoming',
            'label' => 'Coming soon',
            'class' => 'vet-pill-updated'
        ];
    }
}

if (!function_exists('petcura_refresh_last_visit')) {
    function petcura_refresh_last_visit($conn, $pet_id, $vet_id)
    {
        $pet_id = (int)$pet_id;
        $vet_id = (int)$vet_id;

        $sql = "
            SELECT MAX(last_date) AS last_visit
            FROM (
                SELECT date_given AS last_date
                FROM vaccinations
                WHERE pet_id = ? AND vet_id = ?

                UNION ALL

                SELECT date_given AS last_date
                FROM dewormings
                WHERE pet_id = ? AND vet_id = ?

                UNION ALL

                SELECT treatment_date AS last_date
                FROM treatments
                WHERE pet_id = ? AND vet_id = ?

                UNION ALL

                SELECT DATE(created_at) AS last_date
                FROM pets
                WHERE id = ? AND vet_id = ?
            ) latest_dates
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }

        mysqli_stmt_bind_param($stmt, 'iiiiiiii', $pet_id, $vet_id, $pet_id, $vet_id, $pet_id, $vet_id, $pet_id, $vet_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        $last_visit = (string)($row['last_visit'] ?? '');
        if ($last_visit === '' || $last_visit === '0000-00-00') {
            return;
        }

        $update_stmt = mysqli_prepare($conn, "UPDATE pets SET last_visit = ? WHERE id = ? AND vet_id = ?");
        if (!$update_stmt) {
            return;
        }

        mysqli_stmt_bind_param($update_stmt, 'sii', $last_visit, $pet_id, $vet_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

if (!function_exists('petcura_upsert_auto_reminder')) {
    function petcura_upsert_auto_reminder($conn, $pet_id, $owner_id, $vet_id, $record_type, $record_id, $next_due_date, $channel = 'email', $message = null)
    {
        $pet_id = (int)$pet_id;
        $owner_id = (int)$owner_id;
        $vet_id = (int)$vet_id;
        $record_id = (int)$record_id;

        $due_date_only = '';
        if ($next_due_date !== null && $next_due_date !== '') {
            $due_date_only = date('Y-m-d', strtotime((string)$next_due_date));
        }

        if ($due_date_only === '' || $due_date_only === '1970-01-01') {
            return true;
        }

        $legacy_type = $record_type === 'treatment' ? 'followup' : $record_type;
        if (!in_array($legacy_type, ['vaccination', 'deworming', 'followup'], true)) {
            $legacy_type = 'followup';
        }

        $safe_channel = in_array($channel, ['email', 'sms', 'both'], true) ? $channel : 'email';

        $default_message = ucfirst($legacy_type) . ' reminder for your pet. Due date: ' . date('M d, Y', strtotime($due_date_only));
        $safe_message = $message !== null && trim((string)$message) !== '' ? trim((string)$message) : $default_message;

        petcura_ensure_reminder_email_columns($conn);

        if (petcura_reminders_advanced_supported($conn)) {
            $delete_stmt = mysqli_prepare(
                $conn,
                "DELETE FROM reminders
                 WHERE pet_id = ? AND vet_id = ? AND record_type = ? AND record_id = ?"
            );

            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, 'iisi', $pet_id, $vet_id, $record_type, $record_id);
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
            }

            $due_dt = petcura_to_datetime($due_date_only, '06:00:00');
            $r7 = petcura_to_datetime(date('Y-m-d', strtotime($due_date_only . ' -7 days')), '06:00:00');
            $r3 = petcura_to_datetime(date('Y-m-d', strtotime($due_date_only . ' -3 days')), '06:00:00');
            $r1 = petcura_to_datetime(date('Y-m-d', strtotime($due_date_only . ' -1 day')), '06:00:00');
            $r_after = petcura_to_datetime(date('Y-m-d', strtotime($due_date_only . ' +1 day')), '06:00:00');

            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO reminders (
                    pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, sent_at, created_at,
                    record_type, record_id, next_due_date,
                    reminder_7_days, reminder_3_days, reminder_1_day, reminder_due_date, reminder_1_day_after,
                    event_7_days_sent, event_3_days_sent, event_1_day_sent, event_due_date_sent, event_1_day_after_sent
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NULL, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0)"
            );

            if (!$insert_stmt) {
                return false;
            }

            mysqli_stmt_bind_param(
                $insert_stmt,
                'iiisssssissssss',
                $pet_id,
                $owner_id,
                $vet_id,
                $legacy_type,
                $due_date_only,
                $safe_channel,
                $safe_message,
                $record_type,
                $record_id,
                $due_dt,
                $r7,
                $r3,
                $r1,
                $due_dt,
                $r_after
            );

            $ok = mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            return $ok;
        }

        $insert_legacy_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO reminders (
                pet_id, owner_id, vet_id, reminder_type, reminder_date, channel, status, message, sent_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NULL, NOW())"
        );

        if (!$insert_legacy_stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $insert_legacy_stmt,
            'iiissss',
            $pet_id,
            $owner_id,
            $vet_id,
            $legacy_type,
            $due_date_only,
            $safe_channel,
            $safe_message
        );

        $ok = mysqli_stmt_execute($insert_legacy_stmt);
        mysqli_stmt_close($insert_legacy_stmt);
        return $ok;
    }
}