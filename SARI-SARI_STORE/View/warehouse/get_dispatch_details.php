<?php
session_start();
require_once '../../Model/database.php';

$dispatch_id = (int)($_GET['dispatch_id'] ?? 0);

$disp = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT d.*, e.full_name AS clerk_name
    FROM warehouse_dispatches d
    LEFT JOIN employees e ON d.dispatched_by = e.employee_id
    WHERE d.dispatch_id = $dispatch_id LIMIT 1
"));

if (!$disp) {
    echo "<div class='alert alert-danger'>Dispatch record not found.</div>";
    exit();
}

$items = mysqli_query($conn, "
    SELECT i.*, p.product_name, c.category_name
    FROM warehouse_dispatch_items i
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE i.dispatch_id = $dispatch_id
");
$itemList = [];
if ($items) {
    while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;
}
?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <small class="text-muted fw-semibold">Dispatch ID Code</small>
        <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($disp['dispatch_code']); ?></div>
    </div>
    <div class="col-md-6 text-md-end">
        <small class="text-muted fw-semibold">Shipment Status</small>
        <div><span class="badge bg-primary fs-6"><?= htmlspecialchars($disp['status']); ?></span></div>
    </div>
    <div class="col-md-4">
        <small class="text-muted">Source Warehouse</small>
        <div class="fw-semibold"><?= htmlspecialchars($disp['source_warehouse']); ?></div>
    </div>
    <div class="col-md-4">
        <small class="text-muted">Destination Branch</small>
        <div class="fw-semibold text-success"><?= htmlspecialchars($disp['destination_branch']); ?></div>
    </div>
    <div class="col-md-4">
        <small class="text-muted">Dispatch Date</small>
        <div class="fw-semibold"><?= date('M d, Y h:i A', strtotime($disp['dispatched_at'])); ?></div>
    </div>
    <div class="col-md-6">
        <small class="text-muted">Courier / Delivery Service</small>
        <div><?= htmlspecialchars($disp['courier_name'] ?: 'In-House Delivery'); ?></div>
    </div>
    <div class="col-md-6">
        <small class="text-muted">Driver / Vehicle</small>
        <div><?= htmlspecialchars($disp['driver_info'] ?: 'Unassigned'); ?></div>
    </div>
    <div class="col-12">
        <small class="text-muted">Notes / Special Instructions</small>
        <div class="p-2 bg-light rounded border"><?= htmlspecialchars($disp['notes'] ?: 'None'); ?></div>
    </div>
</div>

<h6 class="fw-bold text-dark mb-2"><i class="bi bi-list-check me-2 text-primary"></i>Dispatched Products Manifest</h6>
<div class="table-responsive mb-3">
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th class="text-center">Expected Dispatch Qty</th>
                <th class="text-center">Received Qty</th>
                <th class="text-center">Item Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itemList as $it): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($it['product_name']); ?></td>
                <td><span class="badge bg-secondary-subtle text-dark border"><?= htmlspecialchars($it['category_name'] ?: 'General'); ?></span></td>
                <td class="text-center fw-bold fs-6 text-primary"><?= $it['expected_qty']; ?> units</td>
                <td class="text-center fw-bold"><?= $it['received_qty']; ?> units</td>
                <td class="text-center"><span class="badge bg-info-subtle text-info border"><?= $it['item_status']; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($disp['discrepancy_reason'])): ?>
<div class="alert alert-danger mb-0">
    <div class="fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Branch Receiving Discrepancy Reason</div>
    <div><?= htmlspecialchars($disp['discrepancy_reason']); ?></div>
</div>
<?php endif; ?>
