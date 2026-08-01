<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$emp_id   = $_SESSION['emp_id'] ?? 1;
$emp_name = $_SESSION['emp_name'] ?? 'Inventory Staff';

/* ── AJAX: SUBMIT TRANSFER (with shipment verification) ── */
if (isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $from_inv  = (int)$_POST['from_inventory_id'];
    $to_inv    = (int)$_POST['to_inventory_id'];
    $qty       = (int)$_POST['quantity'];
    $from_loc  = mysqli_real_escape_string($conn, trim($_POST['from_location'] ?? ''));
    $to_loc    = mysqli_real_escape_string($conn, trim($_POST['to_location'] ?? ''));
    $notes     = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $ref_no    = mysqli_real_escape_string($conn, trim($_POST['ref_no'] ?? ''));
    $verified  = (int)($_POST['shipment_verified'] ?? 0);
    $discrepancy_notes = mysqli_real_escape_string($conn, trim($_POST['discrepancy_notes'] ?? ''));

    if ($from_inv === $to_inv) { echo 'error: Source and destination cannot be the same.'; exit; }
    if ($qty <= 0)             { echo 'error: Quantity must be greater than 0.'; exit; }

    $from = mysqli_fetch_assoc(mysqli_query($conn, "SELECT i.*, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE i.inventory_id=$from_inv LIMIT 1"));
    $to   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT i.*, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE i.inventory_id=$to_inv LIMIT 1"));

    if (!$from || !$to) { echo 'error: Product not found.'; exit; }
    if ($qty > (int)$from['quantity']) { echo 'error: Not enough stock. Available: ' . $from['quantity'] . ' units.'; exit; }

    if (!$verified) {
        // Log discrepancy report without adjusting stock
        $disc = "Discrepancy Report: Transfer of {$qty} units from {$from['product_name']} → {$to['product_name']} NOT verified. Notes: {$discrepancy_notes}";
        logAction($conn, 1, 'Transfer Discrepancy', 'inventory', $from_inv, $disc . " by {$emp_name}");
        mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($from_inv, 'Transfer Discrepancy', $qty, '$ref_no', '$disc', $emp_id, NOW())");
        echo 'discrepancy';
        exit;
    }

    // Shipment verified — proceed with transfer
    $newFrom = (int)$from['quantity'] - $qty;
    $newTo   = (int)$to['quantity']   + $qty;
    mysqli_query($conn, "UPDATE inventory SET quantity=$newFrom" . ($from_loc ? ", aisle='$from_loc'" : '') . " WHERE inventory_id=$from_inv");
    mysqli_query($conn, "UPDATE inventory SET quantity=$newTo"   . ($to_loc   ? ", aisle='$to_loc'"   : '') . " WHERE inventory_id=$to_inv");
    // Create stock movement records
    mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($from_inv, 'Transfer Out', $qty, '$ref_no', '$notes', $emp_id, NOW())");
    mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($to_inv, 'Transfer In', $qty, '$ref_no', '$notes', $emp_id, NOW())");
    logAction($conn, 1, 'Transfer', 'inventory', $from_inv, "Transfer: {$qty} units from {$from['product_name']} → {$to['product_name']}. Ref: {$ref_no} by {$emp_name}");
    echo 'success';
    exit;
}

/* ── FETCH DATA ── */
$items = mysqli_query($conn, "SELECT i.*, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE p.deleted_at IS NULL ORDER BY p.product_name ASC");
$itemList = [];
while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;

$movements = mysqli_query($conn, "
    SELECT sm.*, p.product_name, e.full_name AS emp_name
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN employees e ON sm.moved_by = e.employee_id
    WHERE sm.type IN ('Transfer In','Transfer Out','Transfer Discrepancy')
    ORDER BY sm.moved_at DESC LIMIT 200
");
$records = [];
if ($movements) while ($m = mysqli_fetch_assoc($movements)) $records[] = $m;
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-arrow-left-right me-2 text-info"></i>Stock Transfer</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">Display transfer records. Select a row to view details or initiate a new transfer.</p>
    </div>
    <button class="btn btn-info text-white" onclick="openTransferModal()">
        <i class="bi bi-arrow-left-right me-1"></i> New Transfer
    </button>
</div>

<div class="row g-3">
    <!-- TABLE -->
    <div class="col-lg-7">
        <div class="page-card">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-info"></i>Stock Transfer Records</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="transferTable">
                    <thead style="background:#cff4fc;">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Ref No.</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): $si = 1;
                        foreach ($records as $m):
                            $isIn   = $m['type'] === 'Transfer In';
                            $isDisc = $m['type'] === 'Transfer Discrepancy';
                            if ($isIn)        $tb = '<span class="badge bg-success">Transfer In</span>';
                            elseif ($isDisc)  $tb = '<span class="badge bg-danger">Discrepancy</span>';
                            else              $tb = '<span class="badge bg-secondary">Transfer Out</span>';
                        ?>
                        <tr style="cursor:pointer;" onclick="showTRDetail(<?= $m['movement_id']; ?>)" data-id="<?= $m['movement_id']; ?>">
                            <td class="text-muted fw-semibold"><?= $si++; ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($m['moved_at'])); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($m['product_name']); ?></td>
                            <td><?= $tb; ?></td>
                            <td class="fw-bold"><?= $m['quantity']; ?></td>
                            <td class="font-monospace text-muted" style="font-size:11px;"><?= htmlspecialchars($m['reference_no'] ?? '—'); ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($m['emp_name'] ?? 'Staff'); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DETAIL PANEL -->
    <div class="col-lg-5">
        <div id="trDetailPanel" class="page-card" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2 text-info"></i>Transfer Details</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="closeTRDetail()"><i class="bi bi-x"></i> Close</button>
            </div>
            <div id="trDetailBody"></div>
        </div>
        <div id="trPlaceholder" class="page-card text-center py-5 text-muted">
            <i class="bi bi-hand-index-thumb fs-2 d-block mb-2 text-secondary"></i>
            <div class="fw-semibold">Select a Transfer Row</div>
            <div style="font-size:12px;">Click any row to view its details</div>
        </div>
    </div>
</div>

<!-- TRANSFER MODAL with Shipment Verification -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>New Stock Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="transferForm">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">From (Source) <span class="text-danger">*</span></label>
                            <select class="form-select" name="from_inventory_id" required onchange="showFromQty(this)">
                                <option value="">-- Select Source --</option>
                                <?php foreach ($itemList as $it): ?>
                                <option value="<?= $it['inventory_id']; ?>" data-qty="<?= $it['quantity']; ?>">
                                    <?= htmlspecialchars($it['product_name']); ?> (<?= $it['quantity']; ?> units)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="tr_from_qty" class="text-muted" style="font-size:11px;"></small>
                        </div>
                        <div class="col-md-2 d-flex align-items-center justify-content-center pt-3">
                            <i class="bi bi-arrow-right fs-3 text-info"></i>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">To (Destination) <span class="text-danger">*</span></label>
                            <select class="form-select" name="to_inventory_id" required>
                                <option value="">-- Select Destination --</option>
                                <?php foreach ($itemList as $it): ?>
                                <option value="<?= $it['inventory_id']; ?>">
                                    <?= htmlspecialchars($it['product_name']); ?> (<?= $it['quantity']; ?> units)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Qty to Transfer <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity" min="1" required placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">From Location</label>
                            <input type="text" class="form-control" name="from_location" placeholder="e.g. Aisle A">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">To Location</label>
                            <input type="text" class="form-control" name="to_location" placeholder="e.g. Aisle B">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reference No.</label>
                            <input type="text" class="form-control" name="ref_no" placeholder="e.g. TRF-001">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Reason for transfer..."></textarea>
                        </div>
                        <!-- SHIPMENT VERIFICATION -->
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                <div class="fw-bold mb-2"><i class="bi bi-shield-check me-2"></i>Shipment Verification</div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="shipment_verified" id="sv_yes" value="1" checked onchange="toggleDiscrepancy(false)">
                                    <label class="form-check-label fw-semibold text-success" for="sv_yes"><i class="bi bi-check-circle me-1"></i>Shipment Verified — Proceed with Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="shipment_verified" id="sv_no" value="0" onchange="toggleDiscrepancy(true)">
                                    <label class="form-check-label fw-semibold text-danger" for="sv_no"><i class="bi bi-exclamation-triangle me-1"></i>Shipment NOT Verified — Submit Discrepancy Report</label>
                                </div>
                                <div id="discrepancyBox" style="display:none;" class="mt-2">
                                    <label class="form-label fw-semibold text-danger">Discrepancy Notes <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="discrepancy_notes" rows="2" placeholder="Describe the shipment discrepancy..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-check-lg me-1"></i>Confirm Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const trRecords = <?= json_encode($records); ?>;

function toggleDiscrepancy(show) {
    document.getElementById('discrepancyBox').style.display = show ? 'block' : 'none';
}

function showFromQty(sel) {
    const qty = sel.options[sel.selectedIndex]?.getAttribute('data-qty');
    document.getElementById('tr_from_qty').textContent = qty !== null ? 'Available: ' + qty + ' units' : '';
}

function showTRDetail(id) {
    const rec = trRecords.find(r => r.movement_id == id);
    if (!rec) return;
    document.getElementById('trPlaceholder').style.display = 'none';
    document.getElementById('trDetailPanel').style.display = 'block';
    document.querySelectorAll('#transferTable tbody tr').forEach(r => r.classList.remove('table-info'));
    document.querySelector(`tr[data-id="${id}"]`)?.classList.add('table-info');

    const isDisc = rec.type === 'Transfer Discrepancy';
    const isIn   = rec.type === 'Transfer In';
    const badge  = isDisc ? '<span class="badge bg-danger">Discrepancy Report</span>' : (isIn ? '<span class="badge bg-success">Transfer In</span>' : '<span class="badge bg-secondary">Transfer Out</span>');

    document.getElementById('trDetailBody').innerHTML = `
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="text-muted" style="font-size:11px;">DATE</div><div class="fw-semibold">${rec.moved_at}</div></div>
            <div class="col-6"><div class="text-muted" style="font-size:11px;">RECORDED BY</div><div class="fw-semibold">${rec.emp_name || 'Staff'}</div></div>
        </div>
        <div class="mb-2"><div class="text-muted" style="font-size:11px;">PRODUCT</div><div class="fw-bold fs-6">${rec.product_name}</div></div>
        <div class="row g-2 mb-2">
            <div class="col-4"><div class="text-muted" style="font-size:11px;">TYPE</div>${badge}</div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">QUANTITY</div><div class="fw-bold fs-5">${rec.quantity}</div></div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">REF NO.</div><div class="font-monospace" style="font-size:12px;">${rec.reference_no || '—'}</div></div>
        </div>
        <div><div class="text-muted" style="font-size:11px;">NOTES</div><div style="font-size:13px;">${rec.notes || '—'}</div></div>
        ${isDisc ? `<div class="alert alert-danger mt-3 mb-0 py-2"><i class="bi bi-exclamation-triangle me-2"></i><strong>Discrepancy was reported.</strong> Stock was NOT adjusted.</div>` : ''}
    `;
}

function closeTRDetail() {
    document.getElementById('trDetailPanel').style.display = 'none';
    document.getElementById('trPlaceholder').style.display = 'block';
    document.querySelectorAll('#transferTable tbody tr').forEach(r => r.classList.remove('table-info'));
}
window.closeTRDetail = closeTRDetail;

function openTransferModal() {
    const modalEl = document.getElementById('transferModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.openTransferModal = openTransferModal;

$('#transferForm').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'transfer');
    $.ajax({
        url: 'inv_transfer.php', type: 'POST', data: fd,
        processData: false, contentType: false,
        success: function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Transfer Completed!', text:'Stock movement records have been created.', showConfirmButton:false, timer:1800 })
                    .then(() => { $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); loadPage('inv_transfer.php'); });
            } else if (res === 'discrepancy') {
                Swal.fire({ icon:'warning', title:'Discrepancy Reported', text:'The transfer was NOT processed. A discrepancy report has been submitted.', confirmButtonColor:'#dc3545' })
                    .then(() => { $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); loadPage('inv_transfer.php'); });
            } else { Swal.fire('Error', res.replace('error: ',''), 'error'); }
        },
        error: () => Swal.fire('Error', 'Server error.', 'error')
    });
});
</script>
