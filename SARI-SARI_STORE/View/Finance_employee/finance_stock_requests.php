<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

// Auto-create tables
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS stock_purchase_requests (
        purchase_id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_code VARCHAR(50) NOT NULL UNIQUE,
        request_id INT NULL,
        product_id INT NOT NULL,
        requested_qty INT NOT NULL,
        supplier_name VARCHAR(100) DEFAULT 'Primary Supplier',
        estimated_cost DECIMAL(10,2) DEFAULT 0.00,
        requested_by VARCHAR(100) DEFAULT 'Warehouse Manager',
        status VARCHAR(50) DEFAULT 'Pending Finance Approval',
        finance_notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS finance_approvals (
        approval_id INT AUTO_INCREMENT PRIMARY KEY,
        approval_ref VARCHAR(50) NOT NULL UNIQUE,
        document_type ENUM('Stock Purchase', 'Payroll') NOT NULL,
        related_id INT NOT NULL,
        approved_by INT NOT NULL,
        approver_name VARCHAR(100) NOT NULL,
        approver_role VARCHAR(100) DEFAULT 'Finance Officer',
        decision VARCHAR(50) DEFAULT 'Approved',
        e_signature LONGTEXT NOT NULL,
        notes TEXT NULL,
        signed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS supplier_orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(50) NOT NULL UNIQUE,
        purchase_id INT NULL,
        product_id INT NOT NULL,
        ordered_qty INT NOT NULL,
        supplier_name VARCHAR(100) DEFAULT 'Primary Supplier',
        expected_date DATE NULL,
        status VARCHAR(50) DEFAULT 'Not Arrived',
        arrived_at DATETIME NULL,
        received_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Handle Actions
$message = '';
$msg_type = '';

$emp_user = intval($_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 0);
$account_type = isset($_SESSION['user_id']) ? 'User' : 'Employee';

$q_sig = mysqli_query($conn, "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1");
$row_sig = mysqli_fetch_assoc($q_sig);
$current_signature = $row_sig ? $row_sig['e_signature'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = intval($_POST['purchase_id']);
    
    $pr_q = mysqli_query($conn, "SELECT pr.*, p.product_name FROM stock_purchase_requests pr JOIN products p ON pr.product_id = p.product_id WHERE pr.purchase_id = $pid LIMIT 1");
    $pr = mysqli_fetch_assoc($pr_q);

    if ($pr) {
        if ($_POST['action'] === 'sign_and_approve_finance') {
            $notes = mysqli_real_escape_string($conn, $_POST['finance_notes'] ?? 'Budget Approved by Finance');
            
            // Fetch registered signature for snapshot
            $q_sig2 = mysqli_query($conn, "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1");
            $row_sig2 = mysqli_fetch_assoc($q_sig2);
            $signature = $row_sig2 ? mysqli_real_escape_string($conn, $row_sig2['e_signature']) : '';
            
            if (empty($signature)) {
                $message = "A registered E-Signature is required.";
                $msg_type = "danger";
            } else {
                $emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User';
                $role = 'Finance Officer'; // Or get from session
                $ref = 'FIN-APP-' . date('Ymd') . '-' . rand(1000, 9999);
                
                // 1. Insert into finance_approvals
                mysqli_query($conn, "
                    INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
                    VALUES ('$ref', 'Stock Purchase', $pid, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')
                ");
                
                // 2. Update purchase request status
                mysqli_query($conn, "UPDATE stock_purchase_requests SET status = 'Approved by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
                
                // 3. Create Order in Order Monitoring (Warehouse)
                $po_code = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
                $supplier = mysqli_real_escape_string($conn, $pr['supplier_name']);
                $est_cost = (float)($pr['estimated_cost'] ?? 0);
                $req_qty  = (int)($pr['requested_qty'] ?? 1);
                
                mysqli_query($conn, "
                    INSERT INTO supplier_orders (order_code, purchase_id, product_id, ordered_qty, supplier_name, expected_date, status)
                    VALUES ('$po_code', $pid, {$pr['product_id']}, {$pr['requested_qty']}, '$supplier', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Not Arrived')
                ");

                // 4. Log restock expense entry in restock_logs for Finance & Sales reporting
                mysqli_query($conn, "
                    INSERT INTO restock_logs (product_id, boxes_received, units_per_box, pieces_added, cost_per_box, total_cost, new_cost_per_piece, new_selling_price, supplier, delivery_note, restocked_by, restocked_at)
                    VALUES ({$pr['product_id']}, 1, $req_qty, $req_qty, $est_cost, $est_cost, 0, 0, '$supplier', 'Finance Approved Stock Purchase Request #{$pr['purchase_code']}', $emp_user, NOW())
                ");
                
                $message = "Purchase Request {$pr['purchase_code']} formally signed and approved! Supplier Purchase Order #{$po_code} generated.";
                $msg_type = "success";
            }
        } elseif ($_POST['action'] === 'reject_finance') {
            $notes = mysqli_real_escape_string($conn, $_POST['finance_notes'] ?? 'Budget Rejected by Finance');
            mysqli_query($conn, "UPDATE stock_purchase_requests SET status = 'Rejected by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
            
            $message = "Purchase Request {$pr['purchase_code']} rejected.";
            $msg_type = "danger";
        }
    }
}

// Fetch signed letter data if requested via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_signed_letter') {
    $pid = intval($_GET['purchase_id']);
    $q = mysqli_query($conn, "
        SELECT fa.*, pr.purchase_code, pr.requested_qty, pr.supplier_name, pr.estimated_cost, pr.requested_by, p.product_name 
        FROM finance_approvals fa 
        JOIN stock_purchase_requests pr ON fa.related_id = pr.purchase_id
        JOIN products p ON pr.product_id = p.product_id
        WHERE fa.document_type = 'Stock Purchase' AND fa.related_id = $pid LIMIT 1
    ");
    $letter = mysqli_fetch_assoc($q);
    header('Content-Type: application/json');
    echo json_encode($letter);
    exit;
}

// Fetch all purchase requests
$requests_q = mysqli_query($conn, "
    SELECT pr.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, COALESCE(p.selling_price, 0) AS price
    FROM stock_purchase_requests pr
    JOIN products p ON pr.product_id = p.product_id
    ORDER BY pr.created_at DESC
");
$requests = [];
$total_pending_cost = 0;
if ($requests_q) {
    while ($r = mysqli_fetch_assoc($requests_q)) {
        $requests[] = $r;
        if ($r['status'] === 'Pending Finance Approval') {
            $total_pending_cost += floatval($r['estimated_cost']);
        }
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2b1055;">
                <i class="bi bi-bank me-2 text-purple" style="color:#7b2cbf;"></i>Stock Purchase Requests (Finance)
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Review supplier procurement requests forwarded from Warehouse when central storage is out of stock.</p>
        </div>
        <div>
            <div class="badge bg-purple px-3 py-2 text-white shadow-sm" style="background:#7b2cbf; border-radius:8px; font-size:13px;">
                <i class="bi bi-wallet2 me-1"></i> Pending Purchase Cost: ₱<?= number_format($total_pending_cost, 2); ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-file-earmark-text me-2"></i>Supplier Procurement Applications</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">PO Code</th>
                        <th>Product Details</th>
                        <th class="text-center">Qty to Purchase</th>
                        <th>Supplier</th>
                        <th class="text-end">Estimated Cost</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Finance Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No stock purchase requests pending finance approval.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): 
                            $st = $r['status'];
                            $badgeClass = 'bg-secondary';
                            if ($st === 'Pending Finance Approval') $badgeClass = 'bg-warning text-dark';
                            elseif ($st === 'Approved by Finance') $badgeClass = 'bg-success';
                            elseif ($st === 'Rejected by Finance') $badgeClass = 'bg-danger';
                            
                            $imgSrc = !empty($r['image']) ? '../uploads/' . htmlspecialchars($r['image']) : 'https://via.placeholder.com/40?text=Product';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <strong class="text-purple d-block" style="color:#7b2cbf;"><?= htmlspecialchars($r['purchase_code']); ?></strong>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($r['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $imgSrc; ?>" class="rounded border" style="width:36px; height:36px; object-fit:cover;">
                                    <div>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($r['product_name']); ?></strong>
                                        <small class="text-muted">Unit Price: ₱<?= number_format($r['price'], 2); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center fw-bold fs-6">
                                <?= number_format($r['requested_qty']); ?> units
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['supplier_name']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6">
                                ₱<?= number_format($r['estimated_cost'], 2); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($st === 'Pending Finance Approval'): ?>
                                    <button class="btn btn-sm btn-success rounded-3 me-1" onclick='openFinanceApproveModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-pen me-1"></i> Review & Sign Approval
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openFinanceRejectModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-x-lg me-1"></i> Reject
                                    </button>
                                <?php elseif ($st === 'Approved by Finance'): ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-3" onclick='viewSignedLetter(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-file-earmark-check me-1"></i> View Signed Letter
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;"><i class="bi bi-lock me-1"></i>Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FORMAL FINANCE APPROVAL LETTER MODAL -->
<div class="modal fade" id="financeApproveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-text me-2"></i>Formal Purchase Approval Letter
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approveFinanceForm">
                <input type="hidden" name="action" value="sign_and_approve_finance">
                <input type="hidden" name="purchase_id" id="app_purchase_id">
                
                <div class="modal-body p-4" style="background:#fafafa;">
                    <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold mb-1">O-CART!</h4>
                            <p class="mb-0 text-muted" style="font-size:14px;">Formal Stock Purchase Authorization</p>
                            <hr>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Date:</strong> <span id="app_date"><?= date('F d, Y') ?></span><br>
                                <strong>Requesting Dept:</strong> Central Warehouse<br>
                                <strong>Ref No:</strong> <span id="app_code" class="text-primary fw-bold"></span>
                            </div>
                            <div class="col-6 text-end">
                                <strong>Document:</strong> Financial Approval<br>
                                <strong>Status:</strong> <span class="badge bg-warning text-dark">Pending Signature</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <p>This document serves as the formal financial authorization to procure the following stock items to replenish the central warehouse.</p>
                            <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Requested Qty</th>
                                        <th>Estimated Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="text-center">
                                        <td id="app_product"></td>
                                        <td id="app_supplier"></td>
                                        <td id="app_qty"></td>
                                        <td id="app_cost" class="fw-bold"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold style-label">Finance Approval Notes</label>
                            <textarea name="finance_notes" class="form-control form-control-sm" rows="2" style="font-family: inherit;">Budget verified and approved for supplier procurement.</textarea>
                        </div>
                        
                        <hr>
                        <div class="mt-4 p-3 bg-light border rounded text-center">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-pen me-1"></i>Electronic Signature</h6>
                            
                            <?php if ($current_signature): ?>
                                <p class="text-muted mb-2" style="font-size:13px;">Your registered Finance signature will be attached to this approval.</p>
                                <div class="mx-auto p-2 bg-white border rounded" style="max-width:350px;">
                                    <img src="<?= htmlspecialchars($current_signature) ?>" style="max-height:80px; max-width:300px; border-bottom:1px solid #ccc;" alt="Registered Signature">
                                    <div class="fw-bold mt-2" style="font-size:13px;"><?= htmlspecialchars($_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User') ?></div>
                                </div>
                                <div class="mt-3 text-muted" style="font-size:11px;">Timestamp: <?= date('Y-m-d H:i:s') ?></div>
                            <?php else: ?>
                                <div class="p-4 bg-white border border-warning rounded">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 mb-2 d-block"></i>
                                    <h6 class="fw-bold">No Registered Signature</h6>
                                    <p class="text-muted" style="font-size:13px;">Please register your Finance signature before approving this document.</p>
                                    <button type="button" class="btn btn-sm btn-primary mt-2" onclick="$('#financeApproveModal').modal('hide'); loadPage('finance_signature_profile.php', this)">Register Signature Now</button>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($current_signature): ?>
                    <button type="submit" class="btn btn-success btn-sm rounded-3 px-3 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Purchase
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success btn-sm rounded-3 px-3 fw-bold" disabled>
                        <i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Purchase
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW SIGNED LETTER MODAL -->
<div class="modal fade" id="viewSignedLetterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-primary text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>Signed Formal Approval Letter
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background:#fafafa;" id="signedLetterContent">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- FINANCE REJECT MODAL -->
<div class="modal fade" id="financeRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-danger text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-x-circle me-2"></i>Reject Stock Purchase Request
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectFinanceForm">
                <input type="hidden" name="action" value="reject_finance">
                <input type="hidden" name="purchase_id" id="rej_purchase_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary style-label">Reason for Rejection</label>
                        <textarea name="finance_notes" class="form-control form-control-sm" rows="3" required>Budget allocation exceeded or request denied by Finance.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-3 px-3">
                        <i class="bi bi-x-lg me-1"></i> Reject Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openFinanceApproveModal(request) {
    document.getElementById('app_purchase_id').value = request.purchase_id;
    document.getElementById('app_code').innerText = request.purchase_code;
    document.getElementById('app_product').innerText = request.product_name;
    document.getElementById('app_supplier').innerText = request.supplier_name;
    document.getElementById('app_qty').innerText = request.requested_qty + ' units';
    document.getElementById('app_cost').innerText = '₱' + parseFloat(request.estimated_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    new bootstrap.Modal(document.getElementById('financeApproveModal')).show();
}

// Canvas logic removed (moved to profile page)
// ---------------------------

function getFinanceTargetUrl() {
    return window.location.pathname.toLowerCase().includes('/finance_employee/') ? 'finance_stock_requests.php' : 'Finance_employee/finance_stock_requests.php';
}

function viewSignedLetter(request) {
    new bootstrap.Modal(document.getElementById('viewSignedLetterModal')).show();
    $('#signedLetterContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
    
    $.get(getFinanceTargetUrl(), { action: 'get_signed_letter', purchase_id: request.purchase_id }, function(data) {
        if(data) {
            const html = `
                <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1">O-CART!</h4>
                        <p class="mb-0 text-muted" style="font-size:14px;">Formal Stock Purchase Authorization</p>
                        <hr>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Signed Date:</strong> ${new Date(data.signed_at).toLocaleString()}<br>
                            <strong>Requesting Dept:</strong> Central Warehouse<br>
                            <strong>Requested By:</strong> ${data.requested_by || 'Warehouse Manager'}<br>
                            <strong>Ref No:</strong> <span class="text-primary fw-bold">${data.purchase_code}</span>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Document:</strong> Financial Approval<br>
                            <strong>Status:</strong> <span class="badge bg-success">Approved & Signed</span><br>
                            <strong>Approval Ref:</strong> ${data.approval_ref}
                        </div>
                    </div>
                    <div class="mb-4">
                        <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                            <thead class="table-light text-center">
                                <tr><th>Product</th><th>Supplier</th><th>Requested Qty</th><th>Estimated Cost</th></tr>
                            </thead>
                            <tbody>
                                <tr class="text-center">
                                    <td>${data.product_name}</td>
                                    <td>${data.supplier_name}</td>
                                    <td>${data.requested_qty} units</td>
                                    <td class="fw-bold">₱${parseFloat(data.estimated_cost).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold">Approval Notes:</label>
                        <p class="mb-0 bg-light p-2 border rounded" style="font-style:italic;">${data.notes}</p>
                    </div>
                    <hr>
                    <div class="mt-4 row">
                        <div class="col-6">
                            <p class="mb-1"><strong>Authorized By:</strong></p>
                            ${data.e_signature.startsWith('data:image') ? `<img src="${data.e_signature}" style="max-height:80px; max-width:250px;" alt="Signature">` : `<h4 class="text-primary signature-font mb-0" style="font-family: 'Brush Script MT', cursive;">${data.e_signature}</h4>`}
                            <div class="border-top border-dark pt-1 mt-1 d-inline-block" style="min-width: 200px;">
                                <p class="mb-0 fw-bold">${data.approver_name}</p>
                                <p class="mb-0 text-muted" style="font-size:12px;">${data.approver_role}</p>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <!-- digital stamp placeholder -->
                            <div class="d-inline-block border border-success text-success p-2 rounded text-center opacity-75" style="border-width: 3px !important; transform: rotate(-5deg);">
                                <h5 class="fw-bold mb-0">APPROVED</h5>
                                <small>${data.signed_at}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#signedLetterContent').html(html);
        } else {
            $('#signedLetterContent').html(`
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-warning mb-3" style="font-size:3rem;"></i>
                    <h5 class="fw-bold">No Signature Found</h5>
                    <p class="text-muted">This request was approved before the electronic signature system was implemented. There is no formal document on file.</p>
                </div>
            `);
        }
    }, 'json');
}

function submitApproveForm(e) {
    var isUploadActive = document.getElementById('upload-tab').classList.contains('active');
    if (isUploadActive && !document.getElementById('sigImageUpload').files[0] && !document.getElementById('stock_e_signature').value) {
        e.preventDefault();
        Swal.fire('Signature Required', 'Please upload a signature image to approve the request.', 'warning');
    } else if (!isUploadActive && !document.getElementById('stock_e_signature').value) {
        e.preventDefault();
        Swal.fire('Signature Required', 'Please draw your signature to approve the request.', 'warning');
    }
}
document.getElementById('approveFinanceForm').addEventListener('submit', submitApproveForm);

function openFinanceRejectModal(request) {
    $('#rej_purchase_id').val(request.purchase_id);
    new bootstrap.Modal(document.getElementById('financeRejectModal')).show();
}

function clearBackdropFinance(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right','');
}

$('#approveFinanceForm, #rejectFinanceForm').on('submit', function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    const isApprove = $(this).attr('id') === 'approveFinanceForm';
    const modalId = isApprove ? '#financeApproveModal' : '#financeRejectModal';

    // Disable button to prevent double click
    const btn = $(this).find('button[type="submit"]');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

    $.post(getFinanceTargetUrl(), formData, function(res){
        $(modalId).modal('hide');
        clearBackdropFinance();
        if (typeof loadPage === 'function') {
            loadPage('Finance_employee/finance_stock_requests.php');
        } else {
            location.reload();
        }
    }).fail(function(){
        btn.prop('disabled', false).html(originalText);
        Swal.fire('Error', 'Failed to process request. The image might be too large.', 'error');
    });
});
</script>
