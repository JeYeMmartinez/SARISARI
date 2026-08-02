<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

// Ensure stock_requisitions table exists dynamically
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS stock_requisitions (
        requisition_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        requested_qty INT(11) NOT NULL,
        priority ENUM('Normal', 'High', 'Urgent') DEFAULT 'Normal',
        reason TEXT DEFAULT NULL,
        status ENUM('Pending Procurement', 'Procurement Processing', 'Approved Finance', 'Received Warehouse', 'Rejected') DEFAULT 'Pending Procurement',
        requested_by VARCHAR(100) DEFAULT 'Inventory Staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ── AJAX: SEND RESTOCK REQUEST TO FINANCE ── */
if (isset($_POST['action']) && $_POST['action'] === 'send_restock_request') {
    $product_id    = (int)$_POST['product_id'];
    $requested_qty = (int)$_POST['requested_qty'];
    $priority      = mysqli_real_escape_string($conn, $_POST['priority']);
    $notes         = mysqli_real_escape_string($conn, trim($_POST['notes']));
    $requested_by  = mysqli_real_escape_string($conn, $_SESSION['emp_name'] ?? 'Inventory Staff');

    if ($requested_qty <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Requested quantity must be greater than 0.']);
        exit;
    }

    $q = mysqli_query($conn, "
        INSERT INTO stock_requisitions (product_id, requested_qty, priority, reason, status, requested_by)
        VALUES ($product_id, $requested_qty, '$priority', '$notes', 'Pending Procurement', '$requested_by')
    ");

    if ($q) {
        $req_id = mysqli_insert_id($conn);
        logAction($conn, 1, 'Create', 'stock_requisitions', $req_id, "Sent Restock Request #$req_id to Finance for product ID $product_id ($requested_qty units)");
        echo json_encode(['status' => 'success', 'req_id' => $req_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

/* ── AJAX: GENERATE LOW STOCK REPORT ── */
if (isset($_GET['action']) && $_GET['action'] === 'generate_report') {
    $product_id = (int)($_GET['product_id'] ?? 0);
    $inv = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT i.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.description,
               c.category_name
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE i.inventory_id = $product_id AND p.deleted_at IS NULL
        LIMIT 1
    "));
    header('Content-Type: application/json');
    echo json_encode($inv ?: null);
    exit;
}

// Fetch all low/out-of-stock items
$items = mysqli_query($conn, "
    SELECT i.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.units_per_box, p.cost_per_box, p.description,
           c.category_name,
           CASE WHEN i.quantity = 0 THEN 'Out of Stock' ELSE 'Low Stock' END AS stock_status
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NULL AND i.quantity <= i.minimum_stock
    ORDER BY i.quantity ASC
");
$rows = [];
while ($r = mysqli_fetch_assoc($items)) $rows[] = $r;

$outOfStock = array_filter($rows, fn($r) => (int)$r['quantity'] === 0);
$lowStock   = array_filter($rows, fn($r) => (int)$r['quantity'] > 0);
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Low Stock Alert</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">View low-stock products. Select a product to review details and generate a procurement report.</p>
    </div>
    <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-bell-fill me-1"></i><?= count($rows); ?> alert(s)</span>
</div>

<div class="row g-3">
    <!-- PRODUCT LIST -->
    <div class="col-lg-5">
        <div class="page-card">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ul me-2 text-warning"></i>Low Stock Products</h6>
            <?php if (count($rows) === 0): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:40px;"></i>
                    <div class="fw-bold text-success mt-2">All Stock Levels OK!</div>
                </div>
            <?php else: foreach ($rows as $r):
                $isOut = (int)$r['quantity'] === 0;
                $pct = $r['minimum_stock'] > 0 ? min(100, round(($r['quantity'] / $r['minimum_stock']) * 100)) : 0;
                $barColor = $isOut ? '#dc3545' : '#f59e0b';
            ?>
            <div class="d-flex align-items-center gap-3 p-3 rounded mb-2 low-stock-item"
                 style="background:#f9fafb;border:1.5px solid <?= $isOut ? '#dc3545' : '#f59e0b'; ?>;cursor:pointer;"
                 onclick="selectProduct(<?= $r['inventory_id']; ?>)"
                 data-id="<?= $r['inventory_id']; ?>">
                <div class="flex-grow-1">
                    <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($r['product_name']); ?></div>
                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($r['category_name'] ?? '—'); ?></div>
                    <div class="progress mt-1" style="height:4px;">
                        <div class="progress-bar" style="width:<?= $pct; ?>%;background:<?= $barColor; ?>;"></div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold" style="font-size:18px;color:<?= $barColor; ?>;"><?= $r['quantity']; ?></div>
                    <div style="font-size:10px;color:#6c757d;">/ <?= $r['minimum_stock']; ?> min</div>
                    <?php if ($isOut): ?>
                        <span class="badge bg-danger" style="font-size:9px;">OUT</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark" style="font-size:9px;">LOW</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- DETAIL + REPORT PANEL -->
    <div class="col-lg-7">
        <!-- Placeholder -->
        <div id="lsPlaceholder" class="page-card text-center py-5 text-muted">
            <i class="bi bi-cursor-fill fs-2 d-block mb-2 text-warning"></i>
            <div class="fw-semibold">Select a Product</div>
            <div style="font-size:12px;">Click any product on the left to review its details</div>
        </div>

        <!-- Detail Panel -->
        <div id="lsDetailPanel" style="display:none;">
            <!-- Product Review -->
            <div class="page-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-search me-2 text-warning"></i>Product Review</h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="closeLSDetail()"><i class="bi bi-x"></i> Close</button>
                </div>
                <div id="lsDetailBody"></div>
            </div>

                <!-- Generate & Preview Report Card -->
                <div class="page-card border-warning border-2" style="border:2px solid #f59e0b!important;">
                    <h6 class="fw-bold text-warning mb-1"><i class="bi bi-box-seam-fill me-2"></i>Restock Box Order Configuration</h6>
                    <p class="text-muted mb-3" style="font-size:12px;">Choose how many boxes to order based on box packaging info.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px;">Boxes to Order (Max 5)</label>
                            <input type="number" class="form-control form-control-sm" id="report_requested_boxes" min="1" max="5" value="1" oninput="updateBoxCalculation()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px;">Units per Box</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="report_units_per_box" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px;">Cost per Box</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="report_cost_per_box" readonly>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted" style="font-size:10px;text-transform:uppercase;font-weight:700;">Total Pieces Generated</div>
                                <div class="fw-bold text-primary fs-6" id="calc_total_pieces">0 pcs</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 rounded bg-light border">
                                <div class="text-muted" style="font-size:10px;text-transform:uppercase;font-weight:700;">Est. Cost per Box Order</div>
                                <div class="fw-bold text-purple fs-6" style="color:#7b2cbf;" id="calc_total_cost">₱0.00</div>
                            </div>
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label fw-semibold" style="font-size:12px;">Priority</label>
                            <select class="form-select form-select-sm" id="report_priority">
                                <option value="Urgent">🔴 Urgent</option>
                                <option value="High">🟠 High</option>
                                <option value="Normal" selected>🟡 Normal</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:12px;">Additional Notes for Finance</label>
                            <textarea class="form-control form-control-sm" id="report_notes" rows="2" placeholder="e.g. Fast-moving item, order extra boxes ASAP..."></textarea>
                        </div>
                    </div>
                    <button class="btn btn-warning text-dark w-100 fw-bold" onclick="generateReport()">
                        <i class="bi bi-eye-fill me-2"></i>Preview Low Stock Report &amp; Send to Finance
                    </button>
                </div>
        </div>
    </div>
</div>

<!-- REPORT PREVIEW MODAL -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i>Low Stock Report — Finance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="reportPreview"></div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-outline-dark" onclick="printReport()"><i class="bi bi-printer me-1"></i>Print Report</button>
                <button class="btn btn-warning text-dark fw-bold" onclick="sendRestockRequest()"><i class="bi bi-send-fill me-1"></i>Send Request to Finance</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedProduct = null;
const allProducts = <?= json_encode(array_values($rows)); ?>;

function updateBoxCalculation() {
    if (!selectedProduct) return;
    const boxInput = document.getElementById('report_requested_boxes');
    let boxes = parseInt(boxInput.value) || 0;

    if (boxes > 5) {
        boxes = 5;
        boxInput.value = 5;
    }

    const unitsPerBox = parseInt(selectedProduct.units_per_box) || 1;
    const costPerBox = parseFloat(selectedProduct.cost_per_box) || (parseFloat(selectedProduct.cost_price) * unitsPerBox);

    const totalPieces = boxes * unitsPerBox;
    const totalCost = boxes * costPerBox;

    const piecesEl = document.getElementById('calc_total_pieces');
    const costEl = document.getElementById('calc_total_cost');

    if (piecesEl) piecesEl.innerText = `${totalPieces} pcs (${boxes} box${boxes !== 1 ? 'es' : ''})`;
    if (costEl) costEl.innerText = `₱${totalCost.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
}
window.updateBoxCalculation = updateBoxCalculation;

function selectProduct(id) {
    selectedProduct = allProducts.find(p => p.inventory_id == id);
    if (!selectedProduct) return;
    const placeholder = document.getElementById('lsPlaceholder');
    const panel = document.getElementById('lsDetailPanel');
    if (placeholder) placeholder.style.display = 'none';
    if (panel) panel.style.display = 'block';

    // Highlight selected
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    const selectedEl = document.querySelector(`.low-stock-item[data-id="${id}"]`);
    if (selectedEl) selectedEl.style.background = '#fef3c7';

    const p = selectedProduct;
    const isOut = parseInt(p.quantity) === 0;
    const neededPieces = Math.max(0, parseInt(p.minimum_stock) - parseInt(p.quantity));
    const unitsPerBox = parseInt(p.units_per_box) || 1;
    const suggestedBoxes = Math.min(5, Math.max(1, Math.ceil(neededPieces / unitsPerBox)));
    const costPerBox = parseFloat(p.cost_per_box) || (parseFloat(p.cost_price) * unitsPerBox);

    const detailBody = document.getElementById('lsDetailBody');
    if (detailBody) {
        detailBody.innerHTML = `
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:11px;">PRODUCT NAME</div>
                    <div class="fw-bold fs-6">${p.product_name}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:11px;">CATEGORY</div>
                    <div class="fw-semibold">${p.category_name || '—'}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:11px;">CURRENT QTY</div>
                    <div class="fw-bold fs-5" style="color:${isOut ? '#dc3545' : '#f59e0b'};">${p.quantity}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:11px;">MINIMUM STOCK</div>
                    <div class="fw-bold">${p.minimum_stock}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:11px;">UNITS PER BOX</div>
                    <div class="fw-bold text-primary">${unitsPerBox} pcs/box</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:11px;">COST PER BOX</div>
                    <div class="fw-bold text-success">₱${costPerBox.toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:11px;">SELLING PRICE PER PIECE</div>
                    <div class="fw-semibold">₱${parseFloat(p.selling_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:11px;">COST PER PIECE</div>
                    <div class="fw-semibold">₱${parseFloat(p.cost_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
                </div>
                ${p.description ? `<div class="col-12"><div class="text-muted" style="font-size:11px;">DESCRIPTION</div><div style="font-size:12px;">${p.description}</div></div>` : ''}
            </div>
        `;
    }

    document.getElementById('report_units_per_box').value = `${unitsPerBox} pcs/box`;
    document.getElementById('report_cost_per_box').value = `₱${costPerBox.toLocaleString('en-PH', {minimumFractionDigits:2})}`;
    document.getElementById('report_requested_boxes').value = suggestedBoxes;

    updateBoxCalculation();
}
window.selectProduct = selectProduct;

function closeLSDetail() {
    document.getElementById('lsDetailPanel').style.display = 'none';
    document.getElementById('lsPlaceholder').style.display = 'block';
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    selectedProduct = null;
}
window.closeLSDetail = closeLSDetail;

function sendRestockRequest() {
    if (!selectedProduct) return;
    const boxInput = document.getElementById('report_requested_boxes');
    let boxes = parseInt(boxInput.value) || 0;

    if (boxes <= 0) {
        Swal.fire('Invalid Boxes', 'Please enter at least 1 box to order.', 'warning');
        return;
    }
    if (boxes > 5) {
        Swal.fire('Box Limit Reached', 'Maximum quantity of boxes to order is 5 boxes.', 'warning');
        boxInput.value = 5;
        return;
    }

    const unitsPerBox = parseInt(selectedProduct.units_per_box) || 1;
    const totalPieces = boxes * unitsPerBox;
    const priority = document.getElementById('report_priority').value;
    const notes = document.getElementById('report_notes').value;

    const targetUrl = window.location.pathname.includes('Inventory_employee') ? 'inv_low_stock.php' : 'Inventory_employee/inv_low_stock.php';

    const formattedNotes = `[Box Order: ${boxes} box(es) x ${unitsPerBox} pcs/box = ${totalPieces} pcs] ${notes}`;

    $.ajax({
        url: targetUrl,
        type: 'POST',
        data: {
            action: 'send_restock_request',
            product_id: selectedProduct.product_id,
            requested_qty: totalPieces,
            priority: priority,
            notes: formattedNotes
        },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                const modalEl = document.getElementById('reportModal');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Request Sent to Finance!',
                    text: 'Restocking request #' + res.req_id + ' for ' + boxes + ' box(es) (' + totalPieces + ' pcs) submitted for Finance approval.',
                    confirmButtonColor: '#0f4c81'
                });
                document.getElementById('report_notes').value = '';
            } else {
                Swal.fire('Error', res.message || 'Failed to submit request.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            Swal.fire('Error', 'Server request failed: ' + (error || status), 'error');
        }
    });
}
window.sendRestockRequest = sendRestockRequest;

function generateReport() {
    if (!selectedProduct) return;
    const p = selectedProduct;
    const boxes = parseInt(document.getElementById('report_requested_boxes').value) || 0;
    const unitsPerBox = parseInt(p.units_per_box) || 1;
    const totalPieces = boxes * unitsPerBox;
    const costPerBox = parseFloat(p.cost_per_box) || (parseFloat(p.cost_price) * unitsPerBox);
    const totalCost = boxes * costPerBox;
    const priority = document.getElementById('report_priority').value;
    const notes = document.getElementById('report_notes').value;
    const date = new Date().toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
    const isOut = parseInt(p.quantity) === 0;

    const priorityColor = priority === 'Urgent' ? '#dc3545' : priority === 'High' ? '#fd7e14' : '#ffc107';

    document.getElementById('reportPreview').innerHTML = `
        <div id="reportPrintArea" style="font-family:'Segoe UI',sans-serif;">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:22px;font-weight:800;color:#0f4c81;">O-CART! SARI-SARI STORE</div>
                <div style="font-size:14px;color:#6c757d;">Inventory Management System</div>
                <div style="font-size:18px;font-weight:700;margin-top:8px;color:#f59e0b;border-top:2px solid #f59e0b;border-bottom:2px solid #f59e0b;padding:6px 0;">BOX RESTOCKING REPORT — FOR FINANCE</div>
                <div style="font-size:12px;color:#6c757d;">Generated: ${date}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="background:#f9fafb;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Product</div>
                    <div style="font-weight:700;font-size:15px;">${p.product_name}</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Category</div>
                    <div style="font-weight:600;">${p.category_name || '—'}</div>
                </div>
                <div style="background:${isOut ? '#fee2e2' : '#fef3c7'};border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Current Stock</div>
                    <div style="font-weight:800;font-size:18px;color:${isOut ? '#dc3545' : '#f59e0b'};">Qty: ${p.quantity} — Min: ${p.minimum_stock}</div>
                </div>
                <div style="background:#e0f2fe;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Requested Box Order</div>
                    <div style="font-weight:800;font-size:18px;color:#0284c7;">${boxes} box(es) (${totalPieces} pcs)</div>
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;width:40%;">Packaging Specification</td>
                    <td style="padding:8px;border:1px solid #ddd;">${unitsPerBox} pcs per box</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Cost per Box</td>
                    <td style="padding:8px;border:1px solid #ddd;">₱${costPerBox.toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Estimated Total Budget</td>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;color:#7b2cbf;">₱${totalCost.toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Cost per Piece</td>
                    <td style="padding:8px;border:1px solid #ddd;">₱${parseFloat(p.cost_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Priority Level</td>
                    <td style="padding:8px;border:1px solid #ddd;"><span style="background:${priorityColor};color:white;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">${priority}</span></td>
                </tr>
            </table>
            ${notes ? `<div style="background:#fff8e1;border-left:4px solid #f59e0b;padding:12px;border-radius:0 8px 8px 0;margin-bottom:16px;"><div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:4px;">Notes for Finance</div><div style="font-size:13px;">${notes}</div></div>` : ''}
            <div style="text-align:center;margin-top:30px;font-size:11px;color:#6c757d;">
                This box restocking report was generated by the Inventory Management System and will be sent to the Finance team for approval upon confirmation.
            </div>
        </div>
    `;

    const modalEl = document.getElementById('reportModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.generateReport = generateReport;

function printReport() {
    const contents = document.getElementById('reportPrintArea').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>Low Stock Report</title><style>body{font-family:'Segoe UI',sans-serif;padding:30px;}</style></head><body>${contents}</body></html>`);
    w.document.close();
    w.print();
}
window.printReport = printReport;
</script>
