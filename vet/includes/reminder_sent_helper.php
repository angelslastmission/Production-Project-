<?php
// Helper to check if a reminder has been sent for a record
function petcura_reminder_sent($conn, $record_type, $record_id) {
    $record_id = (int)$record_id;

    // Check all possible ways this record could be stored:
    // 1. Exact record_type match
    // 2. treatment stored as 'followup' (legacy)
    // 3. record_type stored as empty string '' (old bug in send code)
    $types_to_check = array_unique([$record_type, '']);
    if ($record_type === 'treatment') {
        $types_to_check[] = 'followup';
    }

    foreach ($types_to_check as $type) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM reminders WHERE record_type = ? AND record_id = ? AND status = 'sent' LIMIT 1"
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