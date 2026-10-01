<?php
// View/procurement/procurement_history.php
if (!defined('IN_APP')) exit;

$search = $_GET['search'] ?? '';
$supplier = $_GET['supplier'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate summary totals (avoid double counting PRs vs POs, just sum the ones that are successfully ordered)
$total_po_count = 0;
$total_purchase_value = 0;
$total_arrived = 0;
$total_not_arrived = 0;

$records = [];
while ($row = mysqli_fetch_assoc($historyRecords)) {
    $records[] = $row;
    if (!empty($row['order_code'])) {
        $total_po_count++;
        if ($row['po_status'] == 'Arrived') $total_arrived++;
        else $total_not_arrived++;
        
        $total_purchase_value += (float)$row['estimated_cost'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Procurement History</h4>
        <p class="text-secondary mb-0" style="font-size: 14px;">Historical records of all purchase requests and purchase orders</p>
    </div>
</div>

<!-- Summary Section -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4 text-center">
                <h6 class="text-secondary mb-2 fw-bold" style="font-size:12px; text-transform:uppercase;">Total Purchase Orders</h6>
                <h3 class="mb-0 fw-bold text-dark"><?= number_format($total_po_count) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4 text-center">
                <h6 class="text-secondary mb-2 fw-bold" style="font-size:12px; text-transform:uppercase;">Recorded Purchase Value</h6>
                <h3 class="mb-0 fw-bold text-primary">₱<?= number_format($total_purchase_value, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4 text-center">
                <h6 class="text-secondary mb-2 fw-bold" style="font-size:12px; text-transform:uppercase;">Orders Arrived</h6>
                <h3 class="mb-0 fw-bold text-success"><?= number_format($total_arrived) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
            <div class="card-body p-4 text-center">
                <h6 class="text-secondary mb-2 fw-bold" style="font-size:12px; text-transform:uppercase;">Not Yet Arrived</h6>
                <h3 class="mb-0 fw-bold text-warning"><?= number_format($total_not_arrived) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="page-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-funnel me-2"></i>Filter Records</h6>
    </div>
    <form method="GET" action="javascript:void(0)" onsubmit="applyHistoryFilters(event)" class="row g-2">
        <div class="col-md-3">
            <input type="text" id="hist_search" class="form-control form-control-sm" placeholder="Search Request/PO Code or Product..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select id="hist_supplier" class="form-select form-select-sm">
                <option value="">All Suppliers</option>
                <?php mysqli_data_seek($suppliers, 0); while($s = mysqli_fetch_assoc($suppliers)): ?>
                    <option value="<?= htmlspecialchars($s['supplier_name']) ?>" <?= $supplier == $s['supplier_name'] ? 'selected' : '' ?>><?= htmlspecialchars($s['supplier_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select id="hist_status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="Pending Procurement" <?= $status == 'Pending Procurement' ? 'selected' : '' ?>>Pending Procurement</option>
                <option value="Pending Finance Approval" <?= $status == 'Pending Finance Approval' ? 'selected' : '' ?>>Pending Finance Approval</option>
                <option value="Approved by Finance" <?= $status == 'Approved by Finance' ? 'selected' : '' ?>>Approved by Finance (No PO)</option>
                <option value="Not Arrived" <?= $status == 'Not Arrived' ? 'selected' : '' ?>>PO - Not Arrived</option>
                <option value="Arrived" <?= $status == 'Arrived' ? 'selected' : '' ?>>PO - Arrived (Completed)</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" id="hist_start" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
        </div>
        <div class="col-md-2">
            <div class="d-flex gap-2">
                <input type="date" id="hist_end" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </form>
</div>

<div class="page-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle" style="font-size: 13px;" id="historyTable">
            <thead class="table-light text-secondary">
                <tr>
                    <th class="ps-3 border-0 rounded-start" style="font-weight:600; text-transform:uppercase; font-size:11px;">Request Code</th>
                    <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">PO Number</th>
                    <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Product Details</th>
                    <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Supplier</th>
                    <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Amount</th>
                    <th class="border-0" style="font-weight:600; text-transform:uppercase; font-size:11px;">Dates</th>
                    <th class="pe-3 border-0 rounded-end text-end" style="font-weight:600; text-transform:uppercase; font-size:11px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $r): 
                        $hasPo = !empty($r['order_code']);
                        
                        $st = $hasPo ? $r['po_status'] : $r['pr_status'];
                        $badgeClass = 'bg-secondary';
                        if(strpos($st, 'Pending') !== false) $badgeClass = 'bg-warning text-dark';
                        if($st == 'Approved by Finance' || $st == 'Arrived') $badgeClass = 'bg-success';
                        if($st == 'Not Arrived') $badgeClass = 'bg-primary';
                        if($st == 'Pending Finance Approval') $badgeClass = 'bg-info text-dark';
                    ?>
                    <tr>
                        <td class="ps-3 fw-bold text-dark">
                            <?= htmlspecialchars($r['purchase_code']) ?>
                        </td>
                        <td class="fw-bold <?= $hasPo ? 'text-primary' : 'text-muted' ?>">
                            <?= $hasPo ? htmlspecialchars($r['order_code']) : '<span style="font-size:11px;">No PO Generated</span>' ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($r['product_name']) ?></div>
                            <div class="text-muted" style="font-size:11px;">Qty: <?= $r['requested_qty'] ?></div>
                        </td>
                        <td>
                            <div class="text-dark"><?= htmlspecialchars($r['supplier_name']) ?></div>
                        </td>
                        <td>
                            <?= ($r['estimated_cost'] > 0) ? '₱' . number_format($r['estimated_cost'], 2) : '<span class="text-muted">TBD</span>' ?>
                        </td>
                        <td>
                            <div style="font-size:11px;">
                                <span class="text-muted">Req:</span> <?= date('M d, Y', strtotime($r['pr_date'])) ?><br>
                                <?php if ($hasPo): ?>
                                    <span class="text-muted">Ord:</span> <?= date('M d, Y', strtotime($r['po_date'])) ?>
                                    <?php if ($r['po_status'] == 'Arrived' && $r['arrived_at']): ?>
                                        <br><span class="text-success">Arr:</span> <?= date('M d, Y', strtotime($r['arrived_at'])) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="pe-3 text-end">
                            <span class="badge <?= $badgeClass ?> fw-normal rounded-pill" style="font-size:11px; letter-spacing:0.3px;">
                                <?= htmlspecialchars($st) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            No procurement records found matching your filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyHistoryFilters(e) {
    e.preventDefault();
    const search = $('#hist_search').val();
    const supplier = $('#hist_supplier').val();
    const status = $('#hist_status').val();
    const start = $('#hist_start').val();
    const end = $('#hist_end').val();
    
    let url = 'procurement/procurement_history.php?route=procurement_history';
    if(search) url += '&search=' + encodeURIComponent(search);
    if(supplier) url += '&supplier=' + encodeURIComponent(supplier);
    if(status) url += '&status=' + encodeURIComponent(status);
    if(start) url += '&start_date=' + encodeURIComponent(start);
    if(end) url += '&end_date=' + encodeURIComponent(end);
    
    // We update the content using loadPage to the same route with GET params
    // Wait, loadPage only accepts simple URL and doesn't handle passing query params cleanly if it uses ?route= inside.
    // The standard loadPage in this project uses: `url` and pushes to `content`.
    // Let's pass the params to loadPage.
    
    $('#content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
    $.get('../router?route=procurement_history', {
        search: search,
        supplier: supplier,
        status: status,
        start_date: start,
        end_date: end
    }, function(data) {
        $('#content').html(data);
    }).fail(function(xhr) {
        $('#content').html('<div class="alert alert-danger">Failed to load history records.</div>');
    });
}
</script>
