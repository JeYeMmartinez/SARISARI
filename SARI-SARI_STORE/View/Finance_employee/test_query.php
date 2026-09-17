<?php
$db_path = __DIR__ . '/../../Model/database.php';
require_once($db_path);
$pid = 1; // Or any ID
$q = mysqli_query($conn, "
    SELECT fa.*, pr.purchase_code, pr.requested_qty, pr.supplier_name, pr.estimated_cost, pr.requested_by, p.product_name 
    FROM finance_approvals fa 
    JOIN stock_purchase_requests pr ON fa.related_id = pr.purchase_id
    JOIN products p ON pr.product_id = p.product_id
    WHERE fa.document_type = 'Stock Purchase' AND fa.related_id = $pid LIMIT 1
");
if (!$q) {
    echo "SQL Error: " . mysqli_error($conn);
} else {
    echo "Success!";
}
?>
