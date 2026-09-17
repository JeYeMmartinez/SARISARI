<?php
require 'Model/database.php';
$q = mysqli_query($conn, "SELECT * FROM transfer_requests ORDER BY request_id DESC LIMIT 5");
$rows = [];
while ($row = mysqli_fetch_assoc($q)) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
