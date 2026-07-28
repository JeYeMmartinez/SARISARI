<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$emp_id   = $_SESSION['emp_id'] ?? 1;
$emp_name = $_SESSION['emp_name'] ?? 'Inventory Staff';

/* ── AJAX: SUBMIT STOCK IN ── */
if (isset($_POST['action']) && $_POST['action'] === 'stock_in') {
    $inventory_id = (int)$_POST['inventory_id'];
    $qty          = (int)$_POST['quantity'];
    $supplier     = mysqli_real_escape_string($conn, trim($_POST['supplier'] ?? ''));
    $ref_no       = mysqli_real_escape_string($conn, trim($_POST['ref_no'] ?? ''));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $date_in      = mysqli_real_escape_string($conn, $_POST['date_in'] ?? date('Y-m-d'));

    if ($qty <= 0) { echo 'error: Quantity must be greater than 0.'; exit; }

    $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT i.*, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE i.inventory_id=$inventory_id LIMIT 1"));
    if (!$inv) { echo 'error: Inventory item not found.'; exit; }

    $ok = mysqli_query($conn, "UPDATE inventory SET quantity = quantity + $qty, last_restock = NOW() WHERE inventory_id = $inventory_id");
    if ($ok) {
        mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, supplier, notes, moved_by, moved_at) VALUES ($inventory_id, 'Stock In', $qty, '$ref_no', '$supplier', '$notes', $emp_id, NOW())");
        mysqli_query($conn, "UPDATE products SET status='Available' WHERE product_id={$inv['product_id']}");
        logAction($conn, 1, 'Stock In', 'inventory', $inventory_id, "Stock In: +{$qty} units for {$inv['product_name']} by {$emp_name}");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit;
}

/* ── FETCH RECORDS ── */
$items = mysqli_query($conn, "SELECT i.*, p.product_name, p.barcode FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE p.deleted_at IS NULL ORDER BY p.product_name ASC");
$itemList = [];
while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;

$movements = mysqli_query($conn, "
    SELECT sm.*, p.product_name, e.full_name AS emp_name
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN employees e ON sm.moved_by = e.employee_id
    WHERE sm.type = 'Stock In'
    ORDER BY sm.moved_at DESC LIMIT 200
");
$records = [];
if ($movements) while ($m = mysqli_fetch_assoc($movements)) $records[] = $m;
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-box-arrow-in-down-right me-2 text-success"></i>Stock In</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">View stock-in records. Click a row to view details or record a new stock-in.</p>
    </div>
    <button class="btn btn-success" onclick="openStockInModal()">
        <i class="bi bi-plus-lg me-1"></i> Record Stock In
    </button>
</div>

<div class="row g-3">
    <!-- STOCK IN TABLE -->
    <div class="col-lg-7">
        <div class="page-card">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-success"></i>Stock In Records</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="stockInTable">
                    <thead class="table-success">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty Added</th>
                            <th>Supplier</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): $si = 1;
                        foreach ($records as $m): ?>
                        <tr style="cursor:pointer;" onclick="showSIDetail(<?= $m['movement_id']; ?>)" data-id="<?= $m['movement_id']; ?>">
                            <td class="text-muted fw-semibold"><?= $si++; ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($m['moved_at'])); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($m['product_name']); ?></td>
                            <td><span class="badge bg-success">+<?= $m['quantity']; ?></span></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($m['supplier'] ?? '—'); ?></td>
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
        <div id="siDetailPanel" class="page-card" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2 text-success"></i>Stock-In Details</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="closeSIDetail()"><i class="bi bi-x"></i> Close</button>
            </div>
            <div id="siDetailBody"></div>
        </div>
        <div id="siPlaceholder" class="page-card text-center py-5 text-muted">
            <i class="bi bi-hand-index-thumb fs-2 d-block mb-2 text-secondary"></i>
            <div class="fw-semibold">Select a Stock In Row</div>
            <div style="font-size:12px;">Click any row to view its details</div>
        </div>
    </div>
</div>

<!-- STOCK IN MODAL -->
<div class="modal fade" id="stockInModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down-right me-2"></i>Record Stock In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockInForm">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                            <select class="form-select" name="inventory_id" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($itemList as $it): ?>
                                <option value="<?= $it['inventory_id']; ?>">
                                    <?= htmlspecialchars($it['product_name']); ?> (Current: <?= $it['quantity']; ?> units)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Qty to Add <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity" min="1" required placeholder="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date Received</label>
                            <input type="date" class="form-control" name="date_in" value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Supplier / Vendor</label>
                            <input type="text" class="form-control" name="supplier" placeholder="e.g. SM Trading Co.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reference / PO No.</label>
                            <input type="text" class="form-control" name="ref_no" placeholder="e.g. PO-2024-001">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes / Remarks</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Optional remarks..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirm Stock In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const siRecords = <?= json_encode($records); ?>;

function showSIDetail(id) {
    const rec = siRecords.find(r => r.movement_id == id);
    if (!rec) return;
    document.getElementById('siPlaceholder').style.display = 'none';
    document.getElementById('siDetailPanel').style.display = 'block';
    document.querySelectorAll('#stockInTable tbody tr').forEach(r => r.classList.remove('table-success'));
    document.querySelector(`tr[data-id="${id}"]`)?.classList.add('table-success');
    document.getElementById('siDetailBody').innerHTML = `
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="text-muted" style="font-size:11px;">DATE RECEIVED</div><div class="fw-semibold">${rec.moved_at}</div></div>
            <div class="col-6"><div class="text-muted" style="font-size:11px;">RECORDED BY</div><div class="fw-semibold">${rec.emp_name || 'Staff'}</div></div>
        </div>
        <div class="mb-2"><div class="text-muted" style="font-size:11px;">PRODUCT</div><div class="fw-bold fs-6">${rec.product_name}</div></div>
        <div class="row g-2 mb-2">
            <div class="col-4"><div class="text-muted" style="font-size:11px;">QTY ADDED</div><div class="fw-bold text-success fs-5">+${rec.quantity}</div></div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">SUPPLIER</div><div class="fw-semibold" style="font-size:13px;">${rec.supplier || '—'}</div></div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">REF NO.</div><div class="font-monospace" style="font-size:12px;">${rec.reference_no || '—'}</div></div>
        </div>
        <div><div class="text-muted" style="font-size:11px;">NOTES</div><div style="font-size:13px;">${rec.notes || '—'}</div></div>
    `;
}

function closeSIDetail() {
    document.getElementById('siDetailPanel').style.display = 'none';
    document.getElementById('siPlaceholder').style.display = 'block';
    document.querySelectorAll('#stockInTable tbody tr').forEach(r => r.classList.remove('table-success'));
}
window.closeSIDetail = closeSIDetail;

function openStockInModal() {
    const modalEl = document.getElementById('stockInModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.openStockInModal = openStockInModal;

$('#stockInForm').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'stock_in');
    $.ajax({
        url: 'inv_stock_in.php', type: 'POST', data: fd,
        processData: false, contentType: false,
        success: function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Stock In Recorded!', showConfirmButton:false, timer:1500 })
                    .then(() => { $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); loadPage('inv_stock_in.php'); });
            } else { Swal.fire('Error', res, 'error'); }
        },
        error: () => Swal.fire('Error', 'Server error.', 'error')
    });
});
</script>
