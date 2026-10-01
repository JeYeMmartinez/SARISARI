<?php
require 'c:\xampp\htdocs\SARISARI\SARISARI\SARI-SARI_STORE\Model\database.php';
mysqli_query($conn, "UPDATE transfer_requests SET status = 'Denied - Sent to Procurement' WHERE status = 'Denied - Sent to Finance'");
echo 'Updated ' . mysqli_affected_rows($conn) . ' rows.';
