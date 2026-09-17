<?php
require 'Model/database.php';
$q = mysqli_query($conn, "SELECT * FROM transfer_requests");
if (!$q) {
    echo "Error: " . mysqli_error($conn);
    exit;
}
while ($row = mysqli_fetch_assoc($q)) {
    print_r($row);
}
echo "Done.";
