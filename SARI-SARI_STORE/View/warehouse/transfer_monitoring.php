<?php
session_start();
require_once '../../Model/database.php';

/*=========================================================
    FETCH ALL DISPATCHES FOR MONITORING
==========================================================*/
$dispatches = mysqli_query($conn, "
    SELECT d.*, e.full_name AS clerk_name,
           (SELECT COUNT(*) FROM warehouse_dispatch_items i WHERE i.dispatch_id = d.dispatch_id) AS item_count
    FROM warehouse_dispatches d
    LEFT JOIN employees e ON d.dispatched_by = e.employee_id
    ORDER BY d.dispatched_at DESC
");
$dispatchList = [];
if ($dispatches) {
    while ($r = mysqli_fetch_assoc($dispatches)) $dispatchList[] = $r;
}

// Summary Metrics
$totalMonitored = count($dispatchList);
$inTransitCount = 0;
$deliveredCount = 0;
$discrepancyCount = 0;

foreach ($dispatchList as $d) {
    $st = trim($d['status'] ?? '');
    if (empty($st)) {
        $st = !empty($d['received_at']) ? 'Received' : 'Pending';
    }
    if ($st === 'In Transit') $inTransitCount++;
    if (in_array($st, ['Delivered', 'Received'])) $deliveredCount++;
    if (in_array($st, ['Partially Received', 'Rejected'])) $discrepancyCount++;
}
?>

<style>
.wh-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05); height: 100%;
}
.badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 600; }
.badge-transit { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
.badge-deliv   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-reject  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600; }
.badge-partial { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 600; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f4c81;">
            <i class="bi bi-activity me-2 text-primary"></i>Stock Transport Monitoring (Warehouse Side)
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">Monitor active shipments, track branch receiving statuses, and inspect discrepancy feedback across all store branches.</p>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Total Monitored Shipments</small>
            <h3 class="fw-bold mb-0 text-dark"><?= $totalMonitored; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">In Transit</small>
            <h3 class="fw-bold mb-0 text-info"><?= $inTransitCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Successfully Received</small>
            <h3 class="fw-bold mb-0 text-success"><?= $deliveredCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Discrepancies / Rejected</small>
            <h3 class="fw-bold mb-0 text-danger"><?= $discrepancyCount; ?></h3>
        </div>
    </div>
</div>

<!-- MONITORING TABLE -->
<div class="wh-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-eye-fill me-2 text-primary"></i>Branch Transfer Monitoring Audit Log</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="monitoringTable">
            <thead class="table-light" style="font-size:12px;text-transform:uppercase;">
                <tr>
                    <th>Dispatch ID</th>
                    <th>Destination Branch</th>
                    <th>Total Items</th>
                    <th>Date Sent</th>
                    <th>Current Status</th>
                    <th>Receiving Date</th>
                    <th class="text-center">Audit Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatchList as $d): ?>
                <tr>
                    <td class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($d['dispatch_code']); ?></td>
                    <td><span class="badge bg-secondary-subtle text-dark border fw-semibold"><?= htmlspecialchars($d['destination_branch']); ?></span></td>
                    <td class="fw-bold"><?= $d['item_count']; ?> Products</td>
                    <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($d['dispatched_at'])); ?></td>
                    <td>
                        <?php 
                        $st = trim($d['status'] ?? '');
                        if (empty($st)) {
                            $st = !empty($d['received_at']) ? 'Received' : 'Pending';
                        }
                        $bClass = 'bg-warning text-dark';
                        if ($st === 'In Transit')        $bClass = 'bg-info text-white';
                        if (in_array($st, ['Delivered', 'Received'])) $bClass = 'bg-success text-white';
                        if ($st === 'Partially Received') $bClass = 'bg-warning text-dark';
                        if ($st === 'Rejected')          $bClass = 'bg-danger text-white';
                        ?>
                        <span class="badge <?= $bClass; ?> px-2 py-1"><?= htmlspecialchars($st); ?></span>
                    </td>
                    <td style="font-size:12px;"><?= $d['received_at'] ? date('M d, Y h:i A', strtotime($d['received_at'])) : '<span class="text-muted">— Pending —</span>'; ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewMonitoringAudit(<?= $d['dispatch_id']; ?>)">
                            <i class="bi bi-search me-1"></i>Inspect Audit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- AUDIT MODAL -->
<div class="modal fade" id="auditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Transfer Monitoring Audit Trail</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="auditModalBody" style="padding:24px;">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function viewMonitoringAudit(dispatchId) {
    $.get('warehouse/get_dispatch_details.php?dispatch_id=' + dispatchId, function(html) {
        $('#auditModalBody').html(html);
        new bootstrap.Modal(document.getElementById('auditModal')).show();
    });
}
</script>
