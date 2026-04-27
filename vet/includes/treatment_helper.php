<?php
// Helper for treatment CRUD and display
function get_treatments_for_pet($conn, $pet_id, $vet_id) {
    $treatments = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, diagnosis, treatment_date, followup_date, notes FROM treatments WHERE pet_id = ? AND vet_id = ? ORDER BY treatment_date DESC, id DESC"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $pet_id, $vet_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $treatments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    return $treatments;
}
