<?php
session_start();
require_once '../../Model/database.php';

// Quick stats
$total_skus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status != 'Unavailable'"))['total'] ?? 0;

$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.quantity <= i.minimum_stock AND i.quantity > 0 AND p.status != 'Unavailable'
"))['total'] ?? 0;

$out_of_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.quantity = 0 OR p.status = 'Unavailable'
"))['total'] ?? 0;

$total_inventory_val = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(i.quantity * p.cost_price) AS total_val 
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE p.status != 'Unavailable'
"))['total_val'] ?? 0;

// Recent inventory logs
$recent_logs = mysqli_query($conn, "
    SELECT log_id, action, description, created_at 
    FROM audit_logs 
    WHERE table_name IN ('inventory', 'products') 
    ORDER BY created_at DESC 
    LIMIT 6
");

// Low stock items preview
$low_stock_items = mysqli_query($conn, "
    SELECT p.product_name, p.barcode, i.quantity, i.minimum_stock, i.aisle
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.quantity <= i.minimum_stock
    ORDER BY i.quantity ASC
    LIMIT 5
");
?>
<style>
.inv-banner {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    border-radius: 16px;
    padding: 26px 30px;
    margin-bottom: 24px;
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.18);
}
.stat-card-inv {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    height: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.stat-icon-inv {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: white; flex-shrink: 0;
}
.inv-card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
    height: 100%;
}
.action-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    text-decoration: none;
    color: #212529;
    transition: all 0.2s;
}
.action-card:hover {
    background: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.06);
    color: #0d6efd;
}
</style>

<div class="animate__animated animate__fadeIn">

    <!-- WELCOME BANNER -->
    <div class="inv-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="fw-bold mb-1"><i class="bi bi-boxes me-2"></i>Inventory & Stock Operations</h3>
                <p class="mb-0 opacity-90" style="font-size:14px;">Monitor warehouse stock levels, update shelf aisle locations, log restocks, and trigger purchase requisitions.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-light text-primary px-3 py-2 fs-6 fw-bold">
                    <i class="bi bi-clock-history me-1"></i> Live Inventory Sync
                </span>
            </div>
        </div>
    </div>

    <!-- STAT METRICS -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card-inv border-start border-primary border-4">
                <div>
                    <div class="text-muted" style="font-size:12px; font-weight:600;">Total Active SKUs</div>
                    <div style="font-size:26px; font-weight:800;" class="text-primary mt-1"><?= number_format($total_skus); ?></div>
                </div>
                <div class="stat-icon-inv bg-primary"><i class="bi bi-box-seam-fill"></i></div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card-inv border-start border-warning border-4">
                <div>
                    <div class="text-muted" style="font-size:12px; font-weight:600;">Low Stock Items</div>
                    <div style="font-size:26px; font-weight:800;" class="text-warning mt-1"><?= number_format($low_stock_count); ?></div>
                </div>
                <div class="stat-icon-inv bg-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card-inv border-start border-danger border-4">
                <div>
                    <div class="text-muted" style="font-size:12px; font-weight:600;">Out of Stock</div>
                    <div style="font-size:26px; font-weight:800;" class="text-danger mt-1"><?= number_format($out_of_stock_count); ?></div>
                </div>
                <div class="stat-icon-inv bg-danger"><i class="bi bi-slash-circle-fill"></i></div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card-inv border-start border-success border-4">
                <div>
                    <div class="text-muted" style="font-size:12px; font-weight:600;">Total Cost Valuation</div>
                    <div style="font-size:22px; font-weight:800;" class="text-success mt-1">₱<?= number_format($total_inventory_val, 2); ?></div>
                </div>
                <div class="stat-icon-inv bg-success"><i class="bi bi-currency-dollar"></i></div>
            </div>
        </div>
    </div>

    <!-- CONTENT GRID -->
    <div class="row g-4">

        <!-- LOW STOCK WARNINGS TABLE -->
        <div class="col-lg-7">
            <div class="inv-card">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-bell-fill text-warning me-2"></i>Low Stock Warnings
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadPage('inv_records.php')">
                        View All Stock &rarr;
                    </button>
                </div>

                <?php if(mysqli_num_rows($low_stock_items) == 0){ ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 36px;"></i>
                        <p class="mt-2 mb-0" style="font-size: 13px;">All items are comfortably stocked!</p>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Aisle</th>
                                    <th class="text-center">Current Stock</th>
                                    <th class="text-center">Min Threshold</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($item = mysqli_fetch_assoc($low_stock_items)){ ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['product_name']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['barcode'] ?? 'No Barcode'); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['aisle'] ?? 'Unassigned'); ?></span></td>
                                    <td class="text-center fw-bold <?= $item['quantity'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                        <?= $item['quantity']; ?>
                                    </td>
                                    <td class="text-center text-muted"><?= $item['minimum_stock']; ?></td>
                                    <td class="text-end">
                                        <?php if($item['quantity'] == 0){ ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php } else { ?>
                                            <span class="badge bg-warning text-dark">Low Stock</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- QUICK ACTIONS & LOGS -->
        <div class="col-lg-5">
            <div class="inv-card mb-4">
                <h5 class="fw-bold mb-3 pb-2 border-bottom text-dark">
                    <i class="bi bi-lightning-charge-fill text-primary me-2"></i>Quick Actions
                </h5>
                <div class="d-flex flex-column gap-2">
                    <a href="#" onclick="loadPage('inv_records.php')" class="action-card">
                        <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                            <i class="bi bi-plus-slash-minus fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Manage Stock & Restock</div>
                            <small class="text-muted">Adjust quantities, assign aisles & update thresholds</small>
                        </div>
                    </a>

                    <a href="#" onclick="loadPage('inv_requisitions.php')" class="action-card">
                        <div class="bg-warning text-dark rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                            <i class="bi bi-file-earmark-plus-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Create Purchase Requisition</div>
                            <small class="text-muted">Submit procurement request for out-of-stock items</small>
                        </div>
                    </a>

                    <a href="#" onclick="loadPage('inv_logs.php')" class="action-card">
                        <div class="bg-info text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                            <i class="bi bi-journal-text fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold">View Inventory Audit Logs</div>
                            <small class="text-muted">Review history of stock movements & adjustments</small>
                        </div>
                    </a>
                </div>
            </div>

            <!-- RECENT AUDIT LOGS -->
            <div class="inv-card">
                <h5 class="fw-bold mb-3 pb-2 border-bottom text-dark">
                    <i class="bi bi-history text-secondary me-2"></i>Recent Activity Logs
                </h5>
                <div class="list-group list-group-flush">
                    <?php if(mysqli_num_rows($recent_logs) == 0){ ?>
                        <p class="text-muted text-center py-2 style='font-size:12px;'">No recent activity logs recorded.</p>
                    <?php } else { while($log = mysqli_fetch_assoc($recent_logs)){ ?>
                        <div class="list-group-item px-0 border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-primary border" style="font-size:10px;"><?= htmlspecialchars($log['action']); ?></span>
                                <small class="text-muted" style="font-size:11px;"><?= date('M d, g:i A', strtotime($log['created_at'])); ?></small>
                            </div>
                            <div class="text-dark mt-1" style="font-size:12px; font-weight:500;">
                                <?= htmlspecialchars($log['description']); ?>
                            </div>
                        </div>
                    <?php } } ?>
                </div>
            </div>
        </div>

    </div>

</div>
