<?php
require 'c:\xampp\htdocs\SARISARI\SARISARI\SARI-SARI_STORE\Model\database.php';
$q = mysqli_query($conn, "SHOW COLUMNS FROM supplier_orders");
$cols = [];
while($r = mysqli_fetch_assoc($q)) {
    $cols[] = $r['Field'];
}
echo implode(', ', $cols);
