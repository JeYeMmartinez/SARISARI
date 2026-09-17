<?php
require 'Model/database.php';
$q = mysqli_query($conn, "SELECT product_id, product_name FROM products");
$rows = [];
while ($row = mysqli_fetch_assoc($q)) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
