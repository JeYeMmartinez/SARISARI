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

// Handle status updates from Finance (Approve / Reject)
if (isset($_POST['action']) && $_POST['action'] === 'update_req_status') {
    $req_id = (int)$_POST['req_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);

    // Fetch existing requisition details
    $reqRes = mysqli_query($conn, "SELECT * FROM stock_requisitions WHERE requisition_id = $req_id LIMIT 1");
    $reqData = mysqli_fetch_assoc($reqRes);

    if (!$reqData) {
        echo json_encode(['status' => 'error', 'message' => 'Requisition record not found.']);
        exit;
    }

    $q = mysqli_query($conn, "UPDATE stock_requisitions SET status = '$new_status' WHERE requisition_id = $req_id");

    if ($q) {
        // If approved by Finance, apply stock update directly to inventory
        if ($new_status === 'Approved Finance') {
            $product_id = (int)$reqData['product_id'];
            $qty_added = (int)$reqData['requested_qty'];

            // 1. Check or insert into inventory table
            $invCheck = mysqli_query($conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id LIMIT 1");
            if ($invCheck && mysqli_num_rows($invCheck) > 0) {
                $invRow = mysqli_fetch_assoc($invCheck);
                $inventory_id = (int)$invRow['inventory_id'];
                mysqli_query($conn, "UPDATE inventory SET quantity = quantity + $qty_added, last_restock = NOW() WHERE inventory_id = $inventory_id");
            } else {
                mysqli_query($conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($product_id, $qty_added, 5, NOW())");
                $inventory_id = mysqli_insert_id($conn);
            }

            // 2. Ensure product status is set to Available
            mysqli_query($conn, "UPDATE products SET status = 'Available' WHERE product_id = $product_id");

            // 3. Insert into stock_movements table for audit trail
            $ref_no = 'REQ-' . str_pad($req_id, 4, '0', STR_PAD_LEFT);
            $notes = mysqli_real_escape_string($conn, 'Restock Request Approved by Finance: ' . ($reqData['reason'] ?? ''));
            mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, supplier, notes, moved_by, moved_at) VALUES ($inventory_id, 'Stock In', $qty_added, '$ref_no', 'Approved Restock', '$notes', 1, NOW())");

            // Log action
            logAction($conn, 1, 'Restock Approved', 'inventory', $inventory_id, "Finance approved Restock Request #$req_id: +$qty_added units added to product ID $product_id");
        } else {
            logAction($conn, 1, 'Update', 'stock_requisitions', $req_id, "Finance updated Requisition #$req_id status to $new_status");
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// Handle AJAX request for History Modal (Approved / Rejected)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_history_requests') {
    $status_param = $_GET['status'] ?? 'Approved Finance';
    $status_filter = ($status_param === 'Rejected') ? "r.status = 'Rejected'" : "r.status = 'Approved Finance'";

    $res = mysqli_query($conn, "
        SELECT r.*, p.product_name, p.barcode, p.selling_price, p.cost_price,
               c.category_name, i.quantity AS current_stock
        FROM stock_requisitions r
        JOIN products p ON r.product_id = p.product_id
        LEFT JOIN inventory i ON p.product_id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE $status_filter
        ORDER BY r.created_at DESC
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

// Fetch pending restocking requests ONLY for the main list
$requests_query = mysqli_query($conn, "
    SELECT r.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.description,
           c.category_name, i.quantity AS current_stock, i.minimum_stock
    FROM stock_requisitions r
    JOIN products p ON r.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE r.status = 'Pending Procurement' OR r.status = 'Procurement Processing'
    ORDER BY r.created_at DESC
");
$rows = [];
while ($r = mysqli_fetch_assoc($requests_query)) $rows[] = $r;
?>

<div class="animate__animated animate__fadeIn">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-box-seam-fill me-2" style="color:#7b2cbf;"></i>Restocking Requests</h3>
            <p class="text-muted mb-0" style="font-size:13px;">Review restocking requests submitted by inventory clerks and approve budget allocation.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-success fw-bold" onclick="showHistoryModal('Approved Finance')">
                <i class="bi bi-check-circle-fill me-1"></i>Approved Requests
            </button>
            <button class="btn btn-sm btn-outline-danger fw-bold" onclick="showHistoryModal('Rejected')">
                <i class="bi bi-x-circle-fill me-1"></i>Rejected Requests
            </button>
            <span class="badge bg-purple fs-6 px-3 py-2 text-white" style="background:#7b2cbf;"><i class="bi bi-inbox-fill me-1"></i><?= count($rows); ?> pending request(s)</span>
        </div>
    </div>

    <div class="row g-3">
        <!-- REQUISITIONS LIST -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius:12px;">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ul me-2" style="color:#7b2cbf;"></i>Pending Restock Requests</h6>
                <?php if (count($rows) === 0): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox-fill text-muted" style="font-size:40px;"></i>
                        <div class="fw-bold text-muted mt-2">No pending restocking requests.</div>
                        <small class="text-muted">Requests sent by inventory clerks will appear here.</small>
                    </div>
                <?php else: foreach ($rows as $r):
                    $estTotal = (int)$r['requested_qty'] * (float)$r['cost_price'];
                    $prioBadge = $r['priority'] == 'Urgent' ? 'bg-danger' : ($r['priority'] == 'High' ? 'bg-warning text-dark' : 'bg-secondary');
                    $statusBadge = $r['status'] == 'Approved Finance' ? 'bg-success' : ($r['status'] == 'Rejected' ? 'bg-danger' : 'bg-warning text-dark');
                ?>
                <div class="d-flex align-items-center gap-3 p-3 rounded mb-2 low-stock-item"
                     style="background:#f9fafb;border:1.5px solid #7b2cbf;cursor:pointer;"
                     onclick="selectRequest(<?= $r['requisition_id']; ?>)"
                     data-id="<?= $r['requisition_id']; ?>">
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size:13px;">#REQ-<?= str_pad($r['requisition_id'], 4, '0', STR_PAD_LEFT); ?> — <?= htmlspecialchars($r['product_name']); ?></div>
                        <div class="text-muted" style="font-size:11px;">By: <?= htmlspecialchars($r['requested_by']); ?> • <?= date('M d, Y h:i A', strtotime($r['created_at'])); ?></div>
                        <div class="mt-1 d-flex align-items-center gap-2">
                            <span class="badge <?= $prioBadge; ?>" style="font-size:9px;"><?= $r['priority']; ?></span>
                            <span class="badge <?= $statusBadge; ?>" style="font-size:9px;"><?= $r['status']; ?></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-primary" style="font-size:15px;">+<?= $r['requested_qty']; ?> units</div>
                        <div style="font-size:11px;font-weight:700;color:#7b2cbf;">₱<?= number_format($estTotal, 2); ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- DETAIL + ACTION PANEL -->
        <div class="col-lg-7">
            <!-- Placeholder -->
            <div id="lsPlaceholder" class="card border-0 shadow-sm text-center py-5 text-muted" style="border-radius:12px;">
                <i class="bi bi-cursor-fill fs-2 d-block mb-2" style="color:#7b2cbf;"></i>
                <div class="fw-semibold">Select a Request to Review</div>
                <div style="font-size:12px;">Click any submitted request on the left to evaluate cost details & approve/reject.</div>
            </div>

            <!-- Detail Panel -->
            <div id="lsDetailPanel" style="display:none;">
                <!-- Request Review -->
                <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius:12px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0" style="color:#2b1055;"><i class="bi bi-search me-2" style="color:#7b2cbf;"></i>Request & Financial Evaluation</h6>
                        <button class="btn btn-sm btn-outline-secondary" onclick="closeLSDetail()"><i class="bi bi-x"></i> Close</button>
                    </div>
                    <div id="lsDetailBody"></div>
                </div>

                <!-- Finance Actions -->
                <div class="card border-0 shadow-sm p-3" style="border-radius:12px; border-top: 4px solid #7b2cbf !important;">
                    <h6 class="fw-bold mb-2" style="color:#7b2cbf;"><i class="bi bi-check-circle-fill me-2"></i>Finance Decision & Report</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success flex-grow-1 fw-bold" onclick="updateStatus('Approved Finance')">
                            <i class="bi bi-check-lg me-1"></i>Approve Request
                        </button>
                        <button class="btn btn-danger flex-grow-1 fw-bold" onclick="updateStatus('Rejected')">
                            <i class="bi bi-x-lg me-1"></i>Reject Request
                        </button>
                        <button class="btn text-white fw-bold" style="background:#7b2cbf;" onclick="generateReport()">
                            <i class="bi bi-printer me-1"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- HISTORY REQUESTS MODAL (APPROVED / REJECTED) -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" id="historyModalHeader" style="background:#2b1055;">
                <h5 class="modal-title fw-bold" id="historyModalTitle"><i class="bi bi-clock-history me-2"></i>Request History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="historyTable">
                        <thead class="table-light">
                            <tr style="font-size:12px;text-transform:uppercase;color:#6c757d;">
                                <th>REQ #</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Requested Qty</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th>Date Requested</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" style="font-size:13px;"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REPORT PREVIEW MODAL -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:#2b1055;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i>Restocking Request Report — Finance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="reportPreview"></div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn text-white" style="background:#7b2cbf;" onclick="printReport()"><i class="bi bi-printer me-1"></i>Print Report</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedReq = null;
const allRequests = <?= json_encode(array_values($rows)); ?>;

function selectRequest(id) {
    selectedReq = allRequests.find(r => r.requisition_id == id);
    if (!selectedReq) return;
    document.getElementById('lsPlaceholder').style.display = 'none';
    document.getElementById('lsDetailPanel').style.display = 'block';

    // Highlight selected
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    document.querySelector(`.low-stock-item[data-id="${id}"]`).style.background = '#f3e8ff';

    const r = selectedReq;
    const estTotal = parseInt(r.requested_qty) * parseFloat(r.cost_price);

    document.getElementById('lsDetailBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">REQUISITION NO.</div>
                <div class="fw-bold fs-6 text-purple" style="color:#7b2cbf;">#REQ-${String(r.requisition_id).padStart(4, '0')}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">REQUESTED BY</div>
                <div class="fw-semibold">${r.requested_by}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">PRODUCT NAME</div>
                <div class="fw-bold fs-6">${r.product_name}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">CATEGORY</div>
                <div class="fw-semibold">${r.category_name || '—'}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:11px;">CURRENT STOCK</div>
                <div class="fw-bold fs-6">${r.current_stock ?? 0}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:11px;">REQUESTED QTY</div>
                <div class="fw-bold fs-5 text-primary">+${r.requested_qty} units</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:11px;">UNIT COST</div>
                <div class="fw-semibold">₱${parseFloat(r.cost_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:11px;">ESTIMATED TOTAL</div>
                <div class="fw-bold fs-5" style="color:#7b2cbf;">₱${estTotal.toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">PRIORITY</div>
                <span class="badge ${r.priority === 'Urgent' ? 'bg-danger' : (r.priority === 'High' ? 'bg-warning text-dark' : 'bg-secondary')}">${r.priority}</span>
            </div>
            <div class="col-md-6">
                <div class="text-muted" style="font-size:11px;">STATUS</div>
                <span class="badge ${r.status === 'Approved Finance' ? 'bg-success' : (r.status === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark')}">${r.status}</span>
            </div>
            ${r.reason ? `<div class="col-12"><div class="text-muted" style="font-size:11px;">REASON / CLERK NOTES</div><div class="p-2 rounded bg-light" style="font-size:12.5px;">${r.reason}</div></div>` : ''}
        </div>
    `;
}

function closeLSDetail() {
    document.getElementById('lsDetailPanel').style.display = 'none';
    document.getElementById('lsPlaceholder').style.display = 'block';
    document.querySelectorAll('.low-stock-item').forEach(el => el.style.background = '#f9fafb');
    selectedReq = null;
}
window.closeLSDetail = closeLSDetail;

function updateStatus(newStatus) {
    if (!selectedReq) return;
    const targetUrl = window.location.pathname.includes('Finance_employee') ? 'finance_restock.php' : 'Finance_employee/finance_restock.php';

    $.ajax({
        url: targetUrl,
        type: 'POST',
        data: {
            action: 'update_req_status',
            req_id: selectedReq.requisition_id,
            status: newStatus
        },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Status Updated',
                    text: 'Requisition #' + selectedReq.requisition_id + ' updated to ' + newStatus,
                    confirmButtonColor: '#7b2cbf'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to update status.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            Swal.fire('Error', 'Server request failed.', 'error');
        }
    });
}
window.updateStatus = updateStatus;

function generateReport() {
    if (!selectedReq) return;
    const r = selectedReq;
    const date = new Date(r.created_at).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
    const estTotal = parseInt(r.requested_qty) * parseFloat(r.cost_price);
    const priorityColor = r.priority === 'Urgent' ? '#dc3545' : r.priority === 'High' ? '#fd7e14' : '#ffc107';

    document.getElementById('reportPreview').innerHTML = `
        <div id="reportPrintArea" style="font-family:'Segoe UI',sans-serif;">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:22px;font-weight:800;color:#2b1055;">O-CART! SARI-SARI STORE</div>
                <div style="font-size:14px;color:#6c757d;">Finance & Accounting Portal</div>
                <div style="font-size:18px;font-weight:700;margin-top:8px;color:#7b2cbf;border-top:2px solid #7b2cbf;border-bottom:2px solid #7b2cbf;padding:6px 0;">RESTOCKING REQUEST REPORT #REQ-${String(r.requisition_id).padStart(4,'0')}</div>
                <div style="font-size:12px;color:#6c757d;">Filed Date: ${date}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div style="background:#f9fafb;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Product Name</div>
                    <div style="font-weight:700;font-size:15px;">${r.product_name}</div>
                </div>
                <div style="background:#f9fafb;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Requested By</div>
                    <div style="font-weight:600;">${r.requested_by}</div>
                </div>
                <div style="background:#f3e8ff;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Requested Quantity</div>
                    <div style="font-weight:800;font-size:20px;color:#7b2cbf;">+${r.requested_qty} units</div>
                </div>
                <div style="background:#e0f2fe;border-radius:8px;padding:12px;">
                    <div style="font-size:10px;color:#6c757d;text-transform:uppercase;font-weight:700;">Estimated Budget</div>
                    <div style="font-weight:800;font-size:20px;color:#0284c7;">₱${estTotal.toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;width:40%;">Category</td>
                    <td style="padding:8px;border:1px solid #ddd;">${r.category_name || '—'}</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Current Stock</td>
                    <td style="padding:8px;border:1px solid #ddd;">${r.current_stock ?? 0} units</td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Unit Cost Price</td>
                    <td style="padding:8px;border:1px solid #ddd;">₱${parseFloat(r.cost_price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Priority Level</td>
                    <td style="padding:8px;border:1px solid #ddd;"><span style="background:${priorityColor};color:white;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">${r.priority}</span></td>
                </tr>
                <tr style="background:#f0f4f8;">
                    <td style="padding:8px;border:1px solid #ddd;font-weight:700;">Workflow Status</td>
                    <td style="padding:8px;border:1px solid #ddd;"><strong>${r.status}</strong></td>
                </tr>
            </table>
            ${r.reason ? `<div style="background:#f3e8ff;border-left:4px solid #7b2cbf;padding:12px;border-radius:0 8px 8px 0;margin-bottom:16px;"><div style="font-size:11px;font-weight:700;color:#2b1055;text-transform:uppercase;margin-bottom:4px;">Clerk Notes / Reason</div><div style="font-size:13px;">${r.reason}</div></div>` : ''}
            <div style="text-align:center;margin-top:30px;font-size:11px;color:#6c757d;">
                This report was submitted by the inventory clerk and evaluated in the Finance Portal.
            </div>
        </div>
    `;

    const modalEl = document.getElementById('reportModal');
    document.body.appendChild(modalEl);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}
window.generateReport = generateReport;

function showHistoryModal(statusFilter) {
    const isApproved = statusFilter === 'Approved Finance';
    const modalTitle = isApproved ? 'Approved Restocking Requests' : 'Rejected Restocking Requests';
    const headerBg = isApproved ? '#198754' : '#dc3545';

    document.getElementById('historyModalTitle').innerHTML = `<i class="bi bi-clock-history me-2"></i>${modalTitle}`;
    document.getElementById('historyModalHeader').style.background = headerBg;

    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading history...</td></tr>`;

    const targetUrl = window.location.pathname.includes('Finance_employee') ? 'finance_restock.php' : 'Finance_employee/finance_restock.php';

    $.ajax({
        url: targetUrl,
        type: 'GET',
        data: {
            action: 'fetch_history_requests',
            status: statusFilter
        },
        dataType: 'json',
        success: function(rows) {
            if (!rows || rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">No ${isApproved ? 'approved' : 'rejected'} requests found.</td></tr>`;
            } else {
                let html = '';
                rows.forEach(r => {
                    const totalCost = parseInt(r.requested_qty) * parseFloat(r.cost_price);
                    const prioClass = r.priority === 'Urgent' ? 'bg-danger' : (r.priority === 'High' ? 'bg-warning text-dark' : 'bg-secondary');
                    const statusClass = isApproved ? 'bg-success' : 'bg-danger';
                    const reqDate = new Date(r.created_at).toLocaleString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });

                    html += `
                        <tr>
                            <td class="fw-bold text-purple" style="color:#7b2cbf;">#REQ-${String(r.requisition_id).padStart(4, '0')}</td>
                            <td class="fw-bold">${r.product_name}</td>
                            <td>${r.category_name || '—'}</td>
                            <td class="fw-bold text-primary">+${r.requested_qty} units</td>
                            <td>₱${parseFloat(r.cost_price).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                            <td class="fw-bold" style="color:#7b2cbf;">₱${totalCost.toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                            <td><span class="badge ${prioClass}">${r.priority}</span></td>
                            <td><span class="badge ${statusClass}">${r.status}</span></td>
                            <td>${r.requested_by || 'Inventory Staff'}</td>
                            <td class="text-muted" style="font-size:12px;">${reqDate}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }

            const modalEl = document.getElementById('historyModal');
            document.body.appendChild(modalEl);
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">Failed to load request history.</td></tr>`;
        }
    });
}
window.showHistoryModal = showHistoryModal;

function printReport() {
    const contents = document.getElementById('reportPrintArea').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>Restocking Request Report — Finance</title><style>body{font-family:'Segoe UI',sans-serif;padding:30px;}</style></head><body>${contents}</body></html>`);
    w.document.close();
    w.print();
}
window.printReport = printReport;
</script>
