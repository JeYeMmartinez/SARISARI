<?php
function logAction($conn, $user_id, $action, $table, $record_id, $description){
    $description = mysqli_real_escape_string($conn, $description);
    $table       = mysqli_real_escape_string($conn, $table);
    mysqli_query($conn,"
        INSERT INTO audit_logs (user_id, action, table_name, record_id, description)
        VALUES ($user_id, '$action', '$table', $record_id, '$description')
    ");
}
?>