<?php
function logAction($conn, $user_id, $action, $table, $record_id, $description){
    // Skip logging if user_id is invalid — prevents FK constraint crash
    if(empty($user_id) || (int)$user_id <= 0) return;

    try {
        $description = mysqli_real_escape_string($conn, $description);
        $table       = mysqli_real_escape_string($conn, $table);
        $action      = mysqli_real_escape_string($conn, $action);
        $user_id     = (int)$user_id;
        $record_id   = (int)$record_id;
        mysqli_query($conn,"
            INSERT INTO audit_logs (user_id, action, table_name, record_id, description)
            VALUES ($user_id, '$action', '$table', $record_id, '$description')
        ");
    } catch(Throwable $e){
        // Silently skip — logging should never crash the app
    }
}
?>