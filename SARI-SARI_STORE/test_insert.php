<?php
require 'Model/database.php';

// Simulate sending restock request
$_POST['action'] = 'send_restock_request';
$_POST['product_id'] = 1; // Coke 1.5L
$_POST['requested_qty'] = 5;
$_POST['priority'] = 'Normal';
$_POST['notes'] = 'Test from script';

ob_start();
include 'View/Inventory_employee/inv_low_stock.php';
$response = ob_get_clean();

echo "Response from inv_low_stock.php:\n";
echo $response . "\n\n";

$q = mysqli_query($conn, "SELECT * FROM transfer_requests ORDER BY request_id DESC LIMIT 1");
$row = mysqli_fetch_assoc($q);
echo "Latest row in DB:\n";
echo json_encode($row, JSON_PRETTY_PRINT);
