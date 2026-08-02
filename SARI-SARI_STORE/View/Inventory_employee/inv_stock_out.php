<?php
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$emp_id   = $_SESSION['emp_id'] ?? 1;
$emp_name = $_SESSION['emp_name'] ?? 'Inventory Staff';

/* ── AJAX: SUBMIT STOCK OUT ── */
if (isset($_POST['action']) && $_POST['action'] === 'stock_out') {
    $inventory_id = (int)$_POST['inventory_id'];
    $qty          = (int)$_POST['quantity'];
    $reason       = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
    $ref_no       = mysqli_real_escape_string($conn, trim($_POST['ref_no'] ?? ''));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

    if ($qty <= 0) { echo 'error: Quantity must be greater than 0.'; exit; }

    $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT i.*, p.product_name, p.product_id FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE i.inventory_id=$inventory_id LIMIT 1"));
    if (!$inv) { echo 'error: Inventory item not found.'; exit; }
    if ($qty > (int)$inv['quantity']) { echo 'error: Not enough stock. Available: ' . $inv['quantity'] . ' units.'; exit; }

    // Handle evidence file upload
    $evidence_file = '';
    if (!empty($_FILES['evidence']['name'])) {
        $uploadDir = '../../uploads/stock_evidence/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext       = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg','jpeg','png','pdf','webp'];
        if (!in_array($ext, $allowed)) { echo 'error: Invalid file type. Allowed: jpg, png, pdf.'; exit; }
        $fileName  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['evidence']['name']);
        move_uploaded_file($_FILES['evidence']['tmp_name'], $uploadDir . $fileName);
        $evidence_file = mysqli_real_escape_string($conn, $fileName);
    }

    $newQty = (int)$inv['quantity'] - $qty;
    $ok = mysqli_query($conn, "UPDATE inventory SET quantity = $newQty WHERE inventory_id = $inventory_id");
    if ($ok) {
        mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, reason, notes, evidence_file, moved_by, moved_at)
            VALUES ($inventory_id, 'Stock Out', $qty, '$ref_no', '$reason', '$notes', '$evidence_file', $emp_id, NOW())");
        if ($newQty == 0) mysqli_query($conn, "UPDATE products SET status='Unavailable' WHERE product_id={$inv['product_id']}");
        logAction($conn, 1, 'Stock Out', 'inventory', $inventory_id, "Stock Out: -{$qty} units for {$inv['product_name']} by {$emp_name}. Reason: {$reason}");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit;
}

/* ── FETCH DATA ── */
$items = mysqli_query($conn, "SELECT i.*, p.product_name, p.barcode FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE p.deleted_at IS NULL ORDER BY p.product_name ASC");
$itemList = [];
while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;

// Recent Stock Out records
$movements = mysqli_query($conn, "
    SELECT sm.*, p.product_name, e.full_name AS emp_name
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN employees e ON sm.moved_by = e.employee_id
    WHERE sm.type = 'Stock Out'
    ORDER BY sm.moved_at DESC LIMIT 200
");
$records = [];
if ($movements) while ($m = mysqli_fetch_assoc($movements)) $records[] = $m;
?>

<style>
.detail-panel { display:none; }
.detail-panel.show { display:block; }
.row-clickable { cursor:pointer; }
.row-clickable:hover { background:#f0f7ff !important; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-box-arrow-up-right me-2 text-danger"></i>Stock Out</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">Display stock-out records. Select a row to view details or add a new record.</p>
    </div>
    <button class="btn btn-danger" onclick="openStockOutModal()">
        <i class="bi bi-plus-lg me-1"></i> Add New Stock Out Record
    </button>
</div>

<div class="row g-3">
    <!-- TABLE -->
    <div class="col-lg-7">
        <div class="page-card">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-danger"></i>Stock Out Records</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="stockOutTable">
                    <thead class="table-danger">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) > 0): $si = 1;
                        foreach ($records as $m): ?>
                        <tr class="row-clickable" onclick="showSODetail(<?= $m['movement_id']; ?>)" data-id="<?= $m['movement_id']; ?>">
                            <td class="text-muted fw-semibold"><?= $si++; ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($m['moved_at'])); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($m['product_name']); ?></td>
                            <td><span class="badge bg-danger">-<?= $m['quantity']; ?></span></td>
                            <td><span class="badge bg-secondary-subtle text-dark border" style="font-size:10px;"><?= htmlspecialchars($m['reason'] ?? '—'); ?></span></td>
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
        <div id="soDetailPanel" class="page-card" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2 text-danger"></i>Stock-Out Details</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="closeSODetail()"><i class="bi bi-x"></i> Close</button>
            </div>
            <div id="soDetailBody">
                <div class="text-center text-muted py-4"><i class="bi bi-cursor-fill me-2"></i>Click a row to view details</div>
            </div>
        </div>
        <div id="soPlaceholder" class="page-card text-center py-5 text-muted">
            <i class="bi bi-hand-index-thumb fs-2 d-block mb-2 text-secondary"></i>
            <div class="fw-semibold">Select a Stock Out Row</div>
            <div style="font-size:12px;">Click any row to view its details</div>
        </div>
    </div>
</div>

<!-- STOCK OUT MODAL -->
<div class="modal fade" id="stockOutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right me-2"></i>Add New Stock Out Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="stockOutForm" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                            <select class="form-select" name="inventory_id" required onchange="updateAvailable(this)">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($itemList as $it): ?>
                                <option value="<?= $it['inventory_id']; ?>" data-qty="<?= $it['quantity']; ?>" data-barcode="<?= htmlspecialchars($it['barcode'] ?? ''); ?>">
                                    <?= htmlspecialchars($it['product_name']); ?> (<?= $it['quantity']; ?> available)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="so_avail" class="text-muted" style="font-size:11px;"></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Qty to Remove <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity" min="1" required placeholder="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <select class="form-select" name="reason" required>
                                <option value="">-- Reason --</option>
                                <option>Sold / Issued</option>
                                <option>Expired</option>
                                <option>Damaged / Spoiled</option>
                                <option>Lost / Stolen</option>
                                <option>Internal Use</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Barcode</label>
                            <input type="text" class="form-control bg-light" id="so_barcode" name="ref_no" readonly placeholder="Auto-filled product barcode">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes / Remarks</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Optional details..."></textarea>
                        </div>
                        <!-- ATTACH EVIDENCE / PROOF -->
                        <div class="col-12">
                            <label class="form-label fw-semibold"><i class="bi bi-paperclip me-1 text-danger"></i>Attach Evidence / Proof</label>
                            <input type="file" class="form-control" name="evidence" id="so_evidence" accept=".jpg,.jpeg,.png,.pdf,.webp" onchange="previewEvidence(this)">
                            <div class="text-muted mt-1" style="font-size:11px;"><i class="bi bi-info-circle me-1"></i>Attach a photo or document as proof (damaged goods, delivery note, etc.). Accepted: JPG, PNG, PDF</div>
                            <!-- Live Evidence Preview Container -->
                            <div id="evidencePreviewContainer" class="mt-2 text-center p-2 rounded border bg-light" style="display:none;">
                                <div class="text-muted fw-semibold mb-1" style="font-size:11px;"><i class="bi bi-eye me-1"></i>Evidence File Preview</div>
                                <div id="evidencePreviewContent"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg me-1"></i>Confirm Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// All records data for detail view
const soRecords = <?= json_encode($records); ?>;

function previewEvidence(input) {
    const container = document.getElementById('evidencePreviewContainer');
    const content = document.getElementById('evidencePreviewContent');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        const reader = new FileReader();

        reader.onload = function(e) {
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                content.innerHTML = `<img src="${e.target.result}" style="max-height:180px;max-width:100%;border-radius:6px;border:1px solid #ddd;" class="shadow-sm">`;
            } else if (ext === 'pdf') {
                content.innerHTML = `<div class="p-3 bg-white rounded border"><i class="bi bi-file-earmark-pdf text-danger fs-3 d-block"></i><span class="fw-semibold small">${file.name}</span> <span class="badge bg-secondary">PDF Document</span></div>`;
            } else {
                content.innerHTML = `<div class="p-2 bg-white rounded border"><i class="bi bi-file-earmark text-primary fs-3 d-block"></i><span class="fw-semibold small">${file.name}</span></div>`;
            }
            container.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        container.style.display = 'none';
        content.innerHTML = '';
    }
}
window.previewEvidence = previewEvidence;

function updateAvailable(sel) {
    const selectedOption = sel.options[sel.selectedIndex];
    const qty = selectedOption?.getAttribute('data-qty');
    const barcode = selectedOption?.getAttribute('data-barcode');

    document.getElementById('so_avail').textContent = qty ? 'Available: ' + qty + ' units' : '';
    document.getElementById('so_barcode').value = barcode || '';
}

function showSODetail(id) {
    const rec = soRecords.find(r => r.movement_id == id);
    if (!rec) return;
    document.getElementById('soPlaceholder').style.display = 'none';
    document.getElementById('soDetailPanel').style.display = 'block';

    // Highlight active row
    document.querySelectorAll('#stockOutTable tbody tr').forEach(r => r.classList.remove('table-warning'));
    document.querySelector(`tr[data-id="${id}"]`)?.classList.add('table-warning');

    let evidenceHtml = '';
    if (rec.evidence_file) {
        const ext = rec.evidence_file.split('.').pop().toLowerCase();
        const url = '../../uploads/stock_evidence/' + rec.evidence_file;
        if (['jpg','jpeg','png','webp'].includes(ext)) {
            evidenceHtml = `<a href="${url}" target="_blank"><img src="${url}" style="max-width:100%;border-radius:8px;margin-top:6px;" alt="Evidence"></a>`;
        } else {
            evidenceHtml = `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-secondary mt-1"><i class="bi bi-file-earmark-pdf me-1"></i>View Evidence File</a>`;
        }
    } else {
        evidenceHtml = '<span class="text-muted">No evidence attached.</span>';
    }

    document.getElementById('soDetailBody').innerHTML = `
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="text-muted" style="font-size:11px;">DATE</div><div class="fw-semibold">${rec.moved_at}</div></div>
            <div class="col-6"><div class="text-muted" style="font-size:11px;">RECORDED BY</div><div class="fw-semibold">${rec.emp_name || 'Staff'}</div></div>
        </div>
        <div class="mb-2"><div class="text-muted" style="font-size:11px;">PRODUCT</div><div class="fw-bold fs-6">${rec.product_name}</div></div>
        <div class="row g-2 mb-2">
            <div class="col-4"><div class="text-muted" style="font-size:11px;">QTY REMOVED</div><div class="fw-bold text-danger fs-5">-${rec.quantity}</div></div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">REASON</div><div class="fw-semibold">${rec.reason || '—'}</div></div>
            <div class="col-4"><div class="text-muted" style="font-size:11px;">REF NO.</div><div class="font-monospace" style="font-size:12px;">${rec.reference_no || '—'}</div></div>
        </div>
        <div class="mb-3"><div class="text-muted" style="font-size:11px;">NOTES</div><div style="font-size:13px;">${rec.notes || '—'}</div></div>
        <hr>
        <div class="mb-1"><div class="text-muted fw-semibold" style="font-size:11px;"><i class="bi bi-paperclip me-1"></i>EVIDENCE / PROOF</div>${evidenceHtml}</div>
    `;
}

function closeSODetail() {
    document.getElementById('soDetailPanel').style.display = 'none';
    document.getElementById('soPlaceholder').style.display = 'block';
    document.querySelectorAll('#stockOutTable tbody tr').forEach(r => r.classList.remove('table-warning'));
}
window.closeSODetail = closeSODetail;

function openStockOutModal() {
    const modalEl = document.getElementById('stockOutModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.openStockOutModal = openStockOutModal;

$('#stockOutForm').on('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'stock_out');
    const targetUrl = window.location.pathname.includes('Inventory_employee') ? 'inv_stock_out.php' : 'Inventory_employee/inv_stock_out.php';

    $.ajax({
        url: targetUrl, type: 'POST', data: fd,
        processData: false, contentType: false,
        success: function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Stock Out Recorded!', showConfirmButton:false, timer:1500 })
                    .then(() => {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        const pagePath = window.location.pathname.includes('Inventory_employee') ? 'inv_stock_out.php' : 'Inventory_employee/inv_stock_out.php';
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
