<?php
require_once '../../Model/database.php';

$records = mysqli_query($conn, "
    SELECT i.*, p.product_name, p.barcode, p.selling_price, p.cost_price,
           p.status AS product_status, c.category_name
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.product_name ASC
");
$rows = [];
while ($r = mysqli_fetch_assoc($records)) $rows[] = $r;

$totalItems   = count($rows);
$lowStock     = array_filter($rows, fn($r) => $r['quantity'] <= $r['minimum_stock']);
$outOfStock   = array_filter($rows, fn($r) => $r['quantity'] == 0);
$totalUnits   = array_sum(array_column($rows, 'quantity'));
?>
<style>
.stat-card  { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
.stat-icon  { width:46px; height:46px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:21px; color:#fff; flex-shrink:0; }
.stat-label { font-size:11px; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.stat-val   { font-size:26px; font-weight:800; line-height:1.2; margin-top:4px; }
.badge-ok   { background:#d1fae5; color:#065f46; }
.badge-low  { background:#fef3c7; color:#92400e; }
.badge-out  { background:#fee2e2; color:#991b1b; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;">Inventory Records</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">View all product stock levels and warehouse details.</p>
    </div>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div><div class="stat-label">Total Products</div><div class="stat-val"><?= $totalItems; ?></div></div>
            <div class="stat-icon" style="background:#0d6efd;"><i class="bi bi-boxes"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div><div class="stat-label">Total Units</div><div class="stat-val"><?= number_format($totalUnits); ?></div></div>
            <div class="stat-icon" style="background:#198754;"><i class="bi bi-stack"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-start border-warning border-4">
            <div><div class="stat-label">Low Stock</div><div class="stat-val text-warning"><?= count($lowStock); ?></div></div>
            <div class="stat-icon" style="background:#f59e0b;"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-start border-danger border-4">
            <div><div class="stat-label">Out of Stock</div><div class="stat-val text-danger"><?= count($outOfStock); ?></div></div>
            <div class="stat-icon" style="background:#dc3545;"><i class="bi bi-x-circle-fill"></i></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="page-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="recordsTable">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Barcode</th>
                    <th>Current Qty</th>
                    <th>Min Stock</th>
                    <th>Max Stock</th>
                    <th>Aisle / Location</th>
                    <th>Last Restock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r):
                    $qty = (int)$r['quantity'];
                    $min = (int)$r['minimum_stock'];
                    if ($qty == 0)       { $badge = '<span class="badge" style="background:#fee2e2;color:#991b1b;">Out of Stock</span>'; }
                    elseif ($qty <= $min){ $badge = '<span class="badge" style="background:#fef3c7;color:#92400e;">Low Stock</span>'; }
                    else                 { $badge = '<span class="badge" style="background:#d1fae5;color:#065f46;">Sufficient</span>'; }
                ?>
                <tr>
                    <td class="fw-semibold text-muted"><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['product_name']); ?></div>
                        <small class="text-muted">₱<?= number_format($r['selling_price'], 2); ?> / ₱<?= number_format($r['cost_price'], 2); ?> cost</small>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-dark border"><?= htmlspecialchars($r['category_name'] ?? '—'); ?></span></td>
                    <td class="font-monospace text-muted" style="font-size:12px;"><?= htmlspecialchars($r['barcode'] ?? '—'); ?></td>
                    <td class="fw-bold text-dark fs-6"><?= number_format($qty); ?></td>
                    <td class="text-muted"><?= number_format($min); ?></td>
                    <td class="text-muted"><?= $r['maximum_Stock'] ? number_format($r['maximum_Stock']) : '—'; ?></td>
                    <td><?= $r['aisle'] ? '<span class="badge bg-light text-dark border">' . htmlspecialchars($r['aisle']) . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= $r['last_restock'] ? date('M d, Y h:i A', strtotime($r['last_restock'])) : '—'; ?></td>
                    <td><?= $badge; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
