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
    die("Manifest not found.");
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Manifest — <?= htmlspecialchars($disp['dispatch_code']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 30px; background: #fff; color: #1e293b; }
        .manifest-card { border: 2px solid #0f172a; padding: 30px; border-radius: 12px; }
        .header-title { font-size: 24px; font-weight: 800; color: #0f172a; text-transform: uppercase; }
        .code-box { font-size: 20px; font-family: monospace; font-weight: bold; background: #f1f5f9; padding: 8px 16px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .manifest-card { border: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print text-end mb-3">
    <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="bi bi-printer me-2"></i>Print Manifest</button>
</div>

<div class="manifest-card">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <div class="header-title">O-CART! WAREHOUSE LOGISTICS</div>
            <div class="text-muted fw-bold">OFFICIAL DELIVERY MANIFEST / DISPATCH SLIP</div>
        </div>
        <div class="text-end">
            <div class="code-box"><?= htmlspecialchars($disp['dispatch_code']); ?></div>
            <div class="text-muted mt-1" style="font-size:12px;">Date: <?= date('M d, Y h:i A', strtotime($disp['dispatched_at'])); ?></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="p-3 bg-light rounded border">
                <div class="fw-bold text-uppercase text-secondary" style="font-size:11px;">ORIGIN WAREHOUSE</div>
                <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($disp['source_warehouse']); ?></div>
                <div class="text-muted" style="font-size:12px;">Dispatched by: <?= htmlspecialchars($disp['clerk_name'] ?: 'Warehouse Staff'); ?></div>
            </div>
        </div>
        <div class="col-6">
            <div class="p-3 bg-light rounded border">
                <div class="fw-bold text-uppercase text-secondary" style="font-size:11px;">DESTINATION BRANCH</div>
                <div class="fw-bold fs-5 text-success"><?= htmlspecialchars($disp['destination_branch']); ?></div>
                <div class="text-muted" style="font-size:12px;">Courier: <?= htmlspecialchars($disp['courier_name'] ?: 'In-House Logistics'); ?></div>
            </div>
        </div>
        <div class="col-12">
            <div class="p-2 border rounded">
                <strong>Driver / Vehicle Info:</strong> <?= htmlspecialchars($disp['driver_info'] ?: 'N/A'); ?> | 
                <strong>Notes:</strong> <?= htmlspecialchars($disp['notes'] ?: 'None'); ?>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-uppercase mb-2">Itemized Cargo List (<?= count($itemList); ?> Items)</h6>
    <table class="table table-bordered align-middle mb-4">
        <thead class="table-dark">
            <tr>
                <th style="width:50px;">#</th>
                <th>Product Description</th>
                <th>Category</th>
                <th class="text-center" style="width:140px;">Dispatched Qty</th>
                <th class="text-center" style="width:140px;">Checkmark</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 1; foreach ($itemList as $it): ?>
            <tr>
                <td><?= $n++; ?></td>
                <td class="fw-bold"><?= htmlspecialchars($it['product_name']); ?></td>
                <td><?= htmlspecialchars($it['category_name'] ?: 'General'); ?></td>
                <td class="text-center fw-bold fs-5"><?= $it['expected_qty']; ?> units</td>
                <td class="text-center"><div style="border:1px solid #94a3b8; height:24px; width:24px; margin:0 auto; border-radius:4px;"></div></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-4 border-top">
        <div class="col-6 text-center">
            <div style="border-bottom:1.5px solid #000; width:80%; margin:0 auto 5px;"></div>
            <div class="fw-bold" style="font-size:13px;">Dispatched By (Warehouse Supervisor Signature)</div>
        </div>
        <div class="col-6 text-center">
            <div style="border-bottom:1.5px solid #000; width:80%; margin:0 auto 5px;"></div>
            <div class="fw-bold" style="font-size:13px;">Received By (Receiving Branch Clerk Signature)</div>
        </div>
    </div>
</div>

</body>
</html>
