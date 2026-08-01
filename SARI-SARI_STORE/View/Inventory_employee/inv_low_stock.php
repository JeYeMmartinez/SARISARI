<?php
session_start();
require_once '../../Model/database.php';

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
    SELECT i.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.description,
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

            <!-- Generate Report -->
            <div class="page-card border-warning border-2" style="border:2px solid #f59e0b!important;">
                <h6 class="fw-bold text-warning mb-1"><i class="bi bi-file-earmark-text-fill me-2"></i>Is a Report Needed?</h6>
                <p class="text-muted mb-3" style="font-size:12px;">Generate a low-stock report and send it to the Procurement team for restocking.</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:12px;">Priority</label>
                        <select class="form-select form-select-sm" id="report_priority">
                            <option value="Urgent">🔴 Urgent</option>
                            <option value="High">🟠 High</option>
                            <option value="Normal" selected>🟡 Normal</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:12px;">Additional Notes for Procurement</label>
                        <textarea class="form-control form-control-sm" id="report_notes" rows="2" placeholder="e.g. Fast-moving item, needs to be restocked ASAP..."></textarea>
                    </div>
                </div>
                <button class="btn btn-warning text-dark w-100" onclick="generateReport()">
                    <i class="bi bi-send-fill me-2"></i>Generate Low Stock Report &amp; Send to Procurement
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
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i>Low Stock Report — Procurement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="reportPreview"></div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-warning text-dark" onclick="printReport()"><i class="bi bi-printer me-1"></i>Print Report</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedProduct = null;
const allProducts = <?= json_encode(array_values($rows)); ?>;

function selectProduct(id) {
    selectedProduct = allProducts.find(p => p.inventory_id == id);
    if (!selectedProduct) return;
    document.getElementById('lsPlaceholder').style.display = 'none';
    document.getElementById('lsDetailPanel').style.display = 'block';

    // Highlight selected
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    document.querySelector(`.low-stock-item[data-id="${id}"]`).style.background = '#fef3c7';

    const p = selectedProduct;
    const isOut = parseInt(p.quantity) === 0;
    const needed = Math.max(0, parseInt(p.minimum_stock) - parseInt(p.quantity));

    document.getElementById('lsDetailBody').innerHTML = `
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
                <div class="text-muted" style="font-size:11px;">REORDER NEEDED</div>
                <div class="fw-bold text-danger">+${needed} units</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:11px;">STATUS</div>
                <span class="badge ${isOut ? 'bg-danger' : 'bg-warning text-dark'}">${isOut ? 'Out of Stock' : 'Low Stock'}</span>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">SELLING PRICE</div>
                <div class="fw-semibold">₱${parseFloat(p.selling_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">COST PRICE</div>
                <div class="fw-semibold">₱${parseFloat(p.cost_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
            ${p.description ? `<div class="col-12"><div class="text-muted" style="font-size:11px;">DESCRIPTION</div><div style="font-size:12px;">${p.description}</div></div>` : ''}
            ${p.aisle ? `<div class="col-12"><div class="text-muted" style="font-size:11px;">WAREHOUSE LOCATION / AISLE</div><div class="fw-semibold">${p.aisle}</div></div>` : ''}
        </div>
    `;
    document.getElementById('reorder_qty') && (document.getElementById('reorder_qty').value = '');
}

function closeLSDetail() {
    document.getElementById('lsDetailPanel').style.display = 'none';
    document.getElementById('lsPlaceholder').style.display = 'block';
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    selectedProduct = null;
}
window.closeLSDetail = closeLSDetail;

function generateReport() {
    if (!selectedProduct) return;
    const p = selectedProduct;
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
                <div style="font-size:18px;font-weight:700;margin-top:8px;color:#f59e0b;border-top:2px solid #f59e0b;border-bottom:2px solid #f59e0b;padding:6px 0;">LOW STOCK REPORT — FOR PROCUREMENT</div>
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
                    <div style="font-weight:800;font-size:20px;color:${isOut ? '#dc3545' : '#f59e0b'};">Qty: ${p.quantity} — Min: ${p.minimum_stock}</div>
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;width:40%;">Minimum Stock Level</td>
                    <td style="padding:8px;border:1px solid #ddd;">${p.minimum_stock} units</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Selling Price</td>
                    <td style="padding:8px;border:1px solid #ddd;">₱${parseFloat(p.selling_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Cost Price</td>
                    <td style="padding:8px;border:1px solid #ddd;">₱${parseFloat(p.cost_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Warehouse Location</td>
                    <td style="padding:8px;border:1px solid #ddd;">${p.aisle || '—'}</td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Priority Level</td>
                    <td style="padding:8px;border:1px solid #ddd;"><span style="background:${priorityColor};color:white;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">${priority}</span></td>
                </tr>
            </table>
            ${notes ? `<div style="background:#fff8e1;border-left:4px solid #f59e0b;padding:12px;border-radius:0 8px 8px 0;margin-bottom:16px;"><div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;margin-bottom:4px;">Procurement Notes</div><div style="font-size:13px;">${notes}</div></div>` : ''}
            <div style="text-align:center;margin-top:30px;font-size:11px;color:#6c757d;">
                This report was generated by the Inventory Management System and sent to the Procurement team for restocking action.
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
