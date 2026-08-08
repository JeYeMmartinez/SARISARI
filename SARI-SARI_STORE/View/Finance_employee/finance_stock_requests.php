<?php
error_reporting(E_ALL & ~E_NOTICE);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = intval($_POST['purchase_id']);
    
    $pr_q = mysqli_query($conn, "SELECT pr.*, p.product_name FROM stock_purchase_requests pr JOIN products p ON pr.product_id = p.product_id WHERE pr.purchase_id = $pid LIMIT 1");
    $pr = mysqli_fetch_assoc($pr_q);

    if ($pr) {
        if ($_POST['action'] === 'approve_finance') {
            $notes = mysqli_real_escape_string($conn, $_POST['finance_notes'] ?? 'Budget Approved by Finance');
            
            // 1. Update purchase request status
            mysqli_query($conn, "UPDATE stock_purchase_requests SET status = 'Approved by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
            
            // 2. Create Order in Order Monitoring (Warehouse)
            $po_code = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
            $supplier = mysqli_real_escape_string($conn, $pr['supplier_name']);
            $est_cost = (float)($pr['estimated_cost'] ?? 0);
            $req_qty  = (int)($pr['requested_qty'] ?? 1);
            
            mysqli_query($conn, "
                INSERT INTO supplier_orders (order_code, purchase_id, product_id, ordered_qty, supplier_name, expected_date, status)
                VALUES ('$po_code', $pid, {$pr['product_id']}, {$pr['requested_qty']}, '$supplier', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Not Arrived')
            ");

            // 3. Log restock expense entry in restock_logs for Finance & Sales reporting
            $emp_user = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;
            mysqli_query($conn, "
                INSERT INTO restock_logs (product_id, boxes_received, units_per_box, pieces_added, cost_per_box, total_cost, new_cost_per_piece, new_selling_price, supplier, delivery_note, restocked_by, restocked_at)
                VALUES ({$pr['product_id']}, 1, $req_qty, $req_qty, $est_cost, $est_cost, 0, 0, '$supplier', 'Finance Approved Stock Purchase Request #{$pr['purchase_code']}', $emp_user, NOW())
            ");
            
            $message = "Purchase Request {$pr['purchase_code']} approved by Finance! Supplier Purchase Order #{$po_code} generated for Warehouse Order Monitoring.";
            $msg_type = "success";
        } elseif ($_POST['action'] === 'reject_finance') {
            $notes = mysqli_real_escape_string($conn, $_POST['finance_notes'] ?? 'Budget Rejected by Finance');
            mysqli_query($conn, "UPDATE stock_purchase_requests SET status = 'Rejected by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
            
            $message = "Purchase Request {$pr['purchase_code']} rejected.";
            $msg_type = "danger";
        }
    }
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
                                        <i class="bi bi-check-lg me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openFinanceRejectModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-x-lg me-1"></i> Reject
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

<!-- FINANCE APPROVE MODAL -->
<div class="modal fade" id="financeApproveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-success text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-check-circle me-2"></i>Approve Supplier Stock Purchase
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approveFinanceForm">
                <input type="hidden" name="action" value="approve_finance">
                <input type="hidden" name="purchase_id" id="app_purchase_id">
                <div class="modal-body p-4">
                    <p class="text-muted" style="font-size:13px;">
                        Approving this request authorizes expenditure and creates an incoming shipment record in <strong>Warehouse Order Monitoring</strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary style-label">Finance Approval Notes</label>
                        <textarea name="finance_notes" class="form-control form-control-sm" rows="2">Budget verified and approved for supplier procurement.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm rounded-3 px-3">
                        <i class="bi bi-check-lg me-1"></i> Confirm Approval
                    </button>
                </div>
            </form>
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
function openFinanceApproveModal(r) {
    $('#app_purchase_id').val(r.purchase_id);
    new bootstrap.Modal(document.getElementById('financeApproveModal')).show();
}

function openFinanceRejectModal(r) {
    $('#rej_purchase_id').val(r.purchase_id);
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

    $.post('Finance_employee/finance_stock_requests.php', formData, function(res){
        $(modalId).modal('hide');
        clearBackdropFinance();
        if (typeof loadPage === 'function') {
            loadPage('Finance_employee/finance_stock_requests.php');
        } else {
            location.reload();
        }
    });
});
</script>
