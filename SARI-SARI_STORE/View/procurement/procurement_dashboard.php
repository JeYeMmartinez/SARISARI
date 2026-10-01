<?php
// View/procurement/procurement_dashboard.php
if (!defined('IN_APP')) exit;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Procurement Dashboard</h4>
        <p class="text-secondary mb-0" style="font-size: 14px;">Overview of purchasing activities and request statuses</p>
    </div>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-search fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="text-secondary mb-0 fw-bold" style="font-size:12px; text-transform:uppercase;">Pending Sourcing</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $metrics['pending_sourcing'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="text-secondary mb-0 fw-bold" style="font-size:12px; text-transform:uppercase;">Pending Finance</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $metrics['pending_finance'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="text-secondary mb-0 fw-bold" style="font-size:12px; text-transform:uppercase;">Ready for PO</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $metrics['ready_po'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="text-secondary mb-0 fw-bold" style="font-size:12px; text-transform:uppercase;">Active POs</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $metrics['active_po'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2">
        <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #fff, #f8f9fa);">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="text-secondary mb-0 fw-bold" style="font-size:12px; text-transform:uppercase;">Completed</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $metrics['completed'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Section -->
<div class="row g-4">
    <!-- Recent Purchase Requests -->
    <div class="col-md-6">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-text me-2"></i>Recent Purchase Requests</h6>
                <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_requests.php', this)" class="btn btn-sm btn-light rounded-pill px-3" style="font-size: 12px;">View All</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="ps-3 border-0 rounded-start" style="font-weight:600; text-transform:uppercase; font-size:11px;">Request Code</th>
                            <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Product</th>
                            <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Cost</th>
                            <th class="pe-3 border-0 rounded-end text-end" style="font-weight:600; text-transform:uppercase; font-size:11px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recentPRs) > 0): ?>
                            <?php while($pr = mysqli_fetch_assoc($recentPRs)): 
                                $badgeClass = 'bg-secondary';
                                if($pr['status'] == 'Pending Procurement') $badgeClass = 'bg-warning text-dark';
                                if($pr['status'] == 'Pending Finance Approval') $badgeClass = 'bg-info text-dark';
                                if($pr['status'] == 'Approved by Finance') $badgeClass = 'bg-success';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_requests.php', this)" class="text-decoration-none">
                                        <?= htmlspecialchars($pr['purchase_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($pr['product_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px;">Qty: <?= $pr['requested_qty'] ?></div>
                                </td>
                                <td><?= ($pr['estimated_cost'] > 0) ? '₱' . number_format($pr['estimated_cost'], 2) : '<span class="text-muted">TBD</span>' ?></td>
                                <td class="pe-3 text-end">
                                    <span class="badge <?= $badgeClass ?> fw-normal rounded-pill" style="font-size:11px; letter-spacing:0.3px;">
                                        <?= htmlspecialchars($pr['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No recent purchase requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Purchase Orders -->
    <div class="col-md-6">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt me-2"></i>Recent Purchase Orders</h6>
                <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_orders.php', this)" class="btn btn-sm btn-light rounded-pill px-3" style="font-size: 12px;">View All</a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="ps-3 border-0 rounded-start" style="font-weight:600; text-transform:uppercase; font-size:11px;">Order Code</th>
                            <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Supplier</th>
                            <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Cost</th>
                            <th class="pe-3 border-0 rounded-end text-end" style="font-weight:600; text-transform:uppercase; font-size:11px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recentPOs) > 0): ?>
                            <?php while($po = mysqli_fetch_assoc($recentPOs)): 
                                $poBadge = ($po['status'] == 'Arrived') ? 'bg-success' : 'bg-primary';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_orders.php', this)" class="text-decoration-none">
                                        <?= htmlspecialchars($po['order_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="text-dark"><?= htmlspecialchars($po['supplier_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px;"><?= date('M d, Y', strtotime($po['created_at'])) ?></div>
                                </td>
                                <td><?= '₱' . number_format($po['estimated_cost'] ?? 0, 2) ?></td>
                                <td class="pe-3 text-end">
                                    <span class="badge <?= $poBadge ?> fw-normal rounded-pill" style="font-size:11px; letter-spacing:0.3px;">
                                        <?= htmlspecialchars($po['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No recent purchase orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
