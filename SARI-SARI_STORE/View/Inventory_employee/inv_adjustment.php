<?php
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$emp_id   = $_SESSION['emp_id'] ?? 1;
$emp_name = $_SESSION['emp_name'] ?? 'Inventory Staff';

/* ── AJAX: SUBMIT ADJUSTMENT ── */
if (isset($_POST['action']) && $_POST['action'] === 'adjust') {
    $inventory_id = (int)$_POST['inventory_id'];
    $new_qty      = (int)$_POST['new_qty'];
    $reason       = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

    if ($new_qty < 0) { echo 'error: Quantity cannot be negative.'; exit; }

    $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT i.*, p.product_name, p.product_id FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE i.inventory_id=$inventory_id LIMIT 1"));
    if (!$inv) { echo 'error: Item not found.'; exit; }

    $old_qty = (int)$inv['quantity'];
    $diff    = $new_qty - $old_qty;
    $type    = $diff >= 0 ? 'Adjustment (+)' : 'Adjustment (-)';

    $ok = mysqli_query($conn, "UPDATE inventory SET quantity = $new_qty WHERE inventory_id = $inventory_id");
    if ($ok) {
        mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reason, notes, moved_by, moved_at) VALUES ($inventory_id, '$type', " . abs($diff) . ", '$reason', '$notes', $emp_id, NOW())");
        if ($new_qty == 0) mysqli_query($conn, "UPDATE products SET status='Unavailable' WHERE product_id={$inv['product_id']}");
        elseif ($old_qty == 0) mysqli_query($conn, "UPDATE products SET status='Available' WHERE product_id={$inv['product_id']}");
        logAction($conn, 1, 'Adjustment', 'inventory', $inventory_id, "Stock Adjusted: {$inv['product_name']} from {$old_qty} → {$new_qty} (diff: " . ($diff >= 0 ? '+' : '') . "{$diff}). Reason: {$reason}");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit;
}

/* ── FETCH DATA ── */
$items = mysqli_query($conn, "SELECT i.*, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE p.deleted_at IS NULL ORDER BY p.product_name ASC");
$itemList = [];
while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-sliders me-2 text-warning"></i>Stock Adjustment</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">Manually correct stock quantities after physical count or discrepancy.</p>
    </div>
    <button class="btn btn-warning text-dark" onclick="openAdjustModal()">
        <i class="bi bi-pencil-square me-1"></i> New Adjustment
    </button>
</div>

<!-- RECENT TABLE -->
<div class="page-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-2 text-warning"></i>Recent Adjustments</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="adjustTable">
            <thead style="background:#fef3c7;">
                <tr>
                    <th style="width:60px;">#</th>
                    <th>Product</th>
                    <th style="width:180px;">Type</th>
                    <th style="width:120px;" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $movements = mysqli_query($conn, "
                    SELECT sm.*, p.product_name, e.full_name AS emp_name
                    FROM stock_movements sm
                    JOIN inventory i ON sm.inventory_id = i.inventory_id
                    JOIN products p ON i.product_id = p.product_id
                    LEFT JOIN employees e ON sm.moved_by = e.employee_id
                    WHERE sm.type LIKE 'Adjustment%'
                    ORDER BY sm.moved_at DESC LIMIT 100
                ");
                $recordsList = [];
                $si = 1;
                if ($movements && mysqli_num_rows($movements) > 0):
                    while ($m = mysqli_fetch_assoc($movements)):
                        $recordsList[] = $m;
                    ?>
                    <tr>
                        <td class="text-muted fw-semibold"><?= $si++; ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($m['product_name']); ?></td>
                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($m['type']); ?></span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="viewAdjustmentDetail(<?= $m['movement_id']; ?>)">
                                <i class="bi bi-eye-fill me-1"></i>View
                            </button>
                        </td>
                    </tr>
                    <?php endwhile;
                endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW DETAIL MODAL -->
<div class="modal fade" id="viewDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Stock Adjustment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewDetailBody"></div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ADJUST MODAL -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adjustForm">
                <div class="modal-body p-4">
                    <div class="alert alert-info small"><i class="bi bi-info-circle me-2"></i>Enter the <strong>corrected / actual quantity</strong> after physical count. The system will calculate the difference automatically.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                            <select class="form-select" name="inventory_id" required onchange="showCurrent(this)">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($itemList as $it): ?>
                                <option value="<?= $it['inventory_id']; ?>" data-qty="<?= $it['quantity']; ?>">
                                    <?= htmlspecialchars($it['product_name']); ?> (<?= $it['quantity']; ?> on record)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="adj_current" class="text-muted"></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Actual Qty <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="new_qty" min="0" required placeholder="Enter correct count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <select class="form-select" name="reason" required>
                                <option value="">-- Reason --</option>
                                <option>Physical Count Correction</option>
                                <option>System Error Correction</option>
                                <option>Damage Write-off</option>
                                <option>Expiry Write-off</option>
                                <option>Found Stock</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes / Remarks</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Explain the reason for adjustment..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-check-lg me-1"></i>Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const adjRecords = <?= json_encode($recordsList); ?>;

function viewAdjustmentDetail(id) {
    const rec = adjRecords.find(r => r.movement_id == id);
    if (!rec) return;

    const isPos = rec.type.indexOf('+') !== -1;
    const badge = isPos ? `<span class="badge bg-success">+${rec.quantity} units</span>` : `<span class="badge bg-danger">-${rec.quantity} units</span>`;
    const formattedDate = new Date(rec.moved_at).toLocaleString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });

    document.getElementById('viewDetailBody').innerHTML = `
        <div class="row g-3">
            <div class="col-12">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Product Name</div>
                <div class="fw-bold fs-5 text-dark">${rec.product_name}</div>
            </div>
            <div class="col-6">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Adjustment Type</div>
                <div><span class="badge bg-warning text-dark fs-6">${rec.type}</span></div>
            </div>
            <div class="col-6">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Quantity Change</div>
                <div class="fs-6 fw-bold">${badge}</div>
            </div>
            <div class="col-6">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Reason</div>
                <div class="fw-semibold text-dark">${rec.reason || '—'}</div>
            </div>
            <div class="col-6">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Adjusted By</div>
                <div class="fw-semibold text-dark">${rec.emp_name || 'Staff'}</div>
            </div>
            <div class="col-12">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Date & Time</div>
                <div class="fw-semibold text-secondary">${formattedDate}</div>
            </div>
            <div class="col-12">
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;">Notes / Remarks</div>
                <div class="p-2 rounded bg-light border text-dark" style="font-size:13px;">${rec.notes || 'No remarks provided.'}</div>
            </div>
        </div>
    `;

    const modalEl = document.getElementById('viewDetailModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.viewAdjustmentDetail = viewAdjustmentDetail;

function showCurrent(sel) {
    const qty = sel.options[sel.selectedIndex]?.getAttribute('data-qty');
    document.getElementById('adj_current').textContent = qty !== null ? 'Current system qty: ' + qty : '';
}
function openAdjustModal() {
    const modalEl = document.getElementById('adjustModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.openAdjustModal = openAdjustModal;

$('#adjustForm').on('submit', function(e) {
    e.preventDefault();
    const fd = $(this).serialize() + '&action=adjust';
    const targetUrl = window.location.pathname.includes('Inventory_employee') ? 'inv_adjustment.php' : 'Inventory_employee/inv_adjustment.php';

    $.ajax({
        url: targetUrl, type: 'POST', data: fd,
        success: function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Adjustment Applied!', showConfirmButton:false, timer:1500 })
                    .then(() => {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        const pagePath = window.location.pathname.includes('Inventory_employee') ? 'inv_adjustment.php' : 'Inventory_employee/inv_adjustment.php';
                        if (typeof loadPage === 'function') {
                            loadPage(pagePath);
                        } else {
                            location.reload();
                        }
                    });
            } else { Swal.fire('Error', res.replace('error: ',''), 'error'); }
        },
        error: (xhr, status, error) => {
            console.error(xhr.responseText);
            Swal.fire('Error', 'Server error: ' + (error || status), 'error');
        }
    });
});
</script>
