<?php
// Helper to check if a reminder has been sent for a specific source record.
function petcura_reminder_sent($conn, $record_type, $record_id) {
    $record_id = (int)$record_id;
    if ($record_id <= 0) {
        return false;
    }

    $types_to_check = [$record_type];

    // Old treatment reminders may be stored as followup, so check that legacy name too.
    if ($record_type === 'treatment') {
        $types_to_check[] = 'followup';
    }

    $types_to_check = array_unique(array_filter($types_to_check));

    foreach ($types_to_check as $type) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM reminders
             WHERE record_type = ?
               AND record_id = ?
               AND status = 'sent'
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $type, $record_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($row) {
                return true;
            }
        }
    }

    return false;
}
