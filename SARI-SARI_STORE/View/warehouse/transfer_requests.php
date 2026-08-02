<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

// Auto-create transfer_requests & stock_purchase_requests tables
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS transfer_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        request_code VARCHAR(50) NOT NULL UNIQUE,
        branch_name VARCHAR(100) NOT NULL,
        requested_by INT NULL,
        product_id INT NOT NULL,
        requested_qty INT NOT NULL,
        urgency VARCHAR(20) DEFAULT 'Medium',
        status VARCHAR(50) DEFAULT 'Pending Warehouse',
        notes TEXT NULL,
        denial_reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

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

// Handle Actions
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $is_ajax = isset($_POST['is_ajax']);
    if ($is_ajax) {
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
    }

    try {
        $req_id = intval($_POST['request_id']);
        
        // Ensure dispatch tables exist and have ALL required columns
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS warehouse_dispatches (
                dispatch_id INT AUTO_INCREMENT PRIMARY KEY,
                dispatch_code VARCHAR(50) NULL,
                reference_no VARCHAR(50) NULL,
                source_warehouse VARCHAR(100) DEFAULT 'Central Warehouse',
                destination_branch VARCHAR(100) NULL,
                expected_delivery_date DATE NULL,
                transport_method VARCHAR(50) DEFAULT 'Company Vehicle',
                courier_name VARCHAR(100) NULL,
                driver_info VARCHAR(150) NULL,
                total_products INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'Pending',
                notes TEXT NULL,
                dispatched_by INT NOT NULL DEFAULT 1,
                dispatched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                received_at DATETIME NULL,
                received_by INT NULL,
                discrepancy_reason TEXT NULL,
                proof_image VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $disp_cols = [
            'dispatch_code'          => "VARCHAR(50) NULL",
            'reference_no'           => "VARCHAR(50) NULL",
            'source_warehouse'       => "VARCHAR(100) DEFAULT 'Central Warehouse'",
            'destination_branch'     => "VARCHAR(100) NULL",
            'expected_delivery_date' => "DATE NULL",
            'transport_method'       => "VARCHAR(50) DEFAULT 'Company Vehicle'",
            'courier_name'           => "VARCHAR(100) NULL",
            'driver_info'            => "VARCHAR(150) NULL",
            'total_products'         => "INT DEFAULT 0",
            'status'                 => "VARCHAR(50) DEFAULT 'Pending'",
            'notes'                  => "TEXT NULL",
            'dispatched_by'          => "INT DEFAULT 1",
            'dispatched_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            'received_at'            => "DATETIME NULL",
            'received_by'            => "INT NULL",
            'discrepancy_reason'     => "TEXT NULL",
            'proof_image'            => "VARCHAR(255) NULL"
        ];

        foreach ($disp_cols as $col => $def) {
            $chk = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_dispatches LIKE '$col'");
            if ($chk && mysqli_num_rows($chk) == 0) {
                @mysqli_query($conn, "ALTER TABLE warehouse_dispatches ADD COLUMN $col $def");
            }
        }

        @mysqli_query($conn, "UPDATE warehouse_dispatches SET dispatch_code = reference_no WHERE (dispatch_code IS NULL OR dispatch_code = '') AND reference_no IS NOT NULL");
        @mysqli_query($conn, "UPDATE warehouse_dispatches SET reference_no = dispatch_code WHERE (reference_no IS NULL OR reference_no = '') AND dispatch_code IS NOT NULL");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS warehouse_dispatch_items (
                item_id INT AUTO_INCREMENT PRIMARY KEY,
                dispatch_id INT NOT NULL,
                product_id INT NOT NULL,
                expected_qty INT NOT NULL DEFAULT 0,
                received_qty INT DEFAULT 0,
                item_status VARCHAR(50) DEFAULT 'Pending'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $item_cols = [
            'dispatch_id'  => "INT NOT NULL",
            'product_id'   => "INT NOT NULL",
            'expected_qty' => "INT NOT NULL DEFAULT 0",
            'received_qty' => "INT DEFAULT 0",
            'item_status'  => "VARCHAR(50) DEFAULT 'Pending'"
        ];

        foreach ($item_cols as $col => $def) {
            $chk = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_dispatch_items LIKE '$col'");
            if ($chk && mysqli_num_rows($chk) == 0) {
                @mysqli_query($conn, "ALTER TABLE warehouse_dispatch_items ADD COLUMN $col $def");
            }
        }

        // Fetch request details
        $req_q = mysqli_query($conn, "
            SELECT tr.*, p.product_name, COALESCE(p.selling_price, 0) AS price, COALESCE(ws.quantity, 0) AS storage_qty
            FROM transfer_requests tr
            JOIN products p ON tr.product_id = p.product_id
            LEFT JOIN warehouse_storage ws ON tr.product_id = ws.product_id
            WHERE tr.request_id = $req_id LIMIT 1
        ");
        $req = mysqli_fetch_assoc($req_q);

        if ($req) {
            if ($_POST['action'] === 'approve_request') {
                // 1. Update storage stock
                mysqli_query($conn, "
                    INSERT INTO warehouse_storage (product_id, quantity)
                    VALUES ({$req['product_id']}, 0)
                    ON DUPLICATE KEY UPDATE quantity = GREATEST(0, quantity - {$req['requested_qty']})
                ");
                
                // 2. Create dispatch record in warehouse_dispatches
                $dispatch_code = 'DSP-' . date('Ymd') . '-' . rand(1000, 9999);
                $user_id = intval($_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1);
                if ($user_id <= 0) $user_id = 1;
                $branch = mysqli_real_escape_string($conn, $req['branch_name']);
                
                $ins_disp = mysqli_query($conn, "
                    INSERT INTO warehouse_dispatches (dispatch_code, reference_no, source_warehouse, destination_branch, expected_delivery_date, transport_method, total_products, status, dispatched_by)
                    VALUES ('$dispatch_code', '$dispatch_code', 'Central Warehouse', '$branch', CURDATE(), 'Company Vehicle', 1, 'Pending', $user_id)
                ");
                if (!$ins_disp) {
                    throw new Exception('Failed to create dispatch: ' . mysqli_error($conn));
                }
                $dispatch_id = mysqli_insert_id($conn);
                
                // 3. Add dispatch line item
                $ins_item = mysqli_query($conn, "
                    INSERT INTO warehouse_dispatch_items (dispatch_id, product_id, expected_qty)
                    VALUES ($dispatch_id, {$req['product_id']}, {$req['requested_qty']})
                ");
                if (!$ins_item) {
                    throw new Exception('Failed to add dispatch items: ' . mysqli_error($conn));
                }
                
                // 4. Update request status
                mysqli_query($conn, "UPDATE transfer_requests SET status = 'Approved & Dispatched' WHERE request_id = $req_id");
                
                if ($is_ajax) {
                    echo json_encode(['status' => 'success', 'dispatch_code' => $dispatch_code]); exit();
                }
                $message = "Transfer Request {$req['request_code']} approved! Created Dispatch #{$dispatch_code} in Warehouse Dispatches.";
                $msg_type = "success";
            } elseif ($_POST['action'] === 'deny_request') {
                $reason = mysqli_real_escape_string($conn, $_POST['denial_reason'] ?? 'Warehouse Storage Out of Stock');
                
                // 1. Update transfer request status
                mysqli_query($conn, "UPDATE transfer_requests SET status = 'Denied - Sent to Finance', denial_reason = '$reason' WHERE request_id = $req_id");
                
                // 2. Forward to Finance as Stock Purchase Request
                $purchase_code = 'PR-' . date('Ymd') . '-' . rand(1000, 9999);
                $est_cost = floatval($req['price']) * intval($req['requested_qty']);
                
                $ins_pr = mysqli_query($conn, "
                    INSERT INTO stock_purchase_requests (purchase_code, request_id, product_id, requested_qty, supplier_name, estimated_cost, requested_by, status)
                    VALUES ('$purchase_code', $req_id, {$req['product_id']}, {$req['requested_qty']}, 'Primary Wholesale Supplier', $est_cost, 'Warehouse Manager', 'Pending Finance Approval')
                ");
                if (!$ins_pr) {
                    throw new Exception('Failed to forward to Finance: ' . mysqli_error($conn));
                }
                
                if ($is_ajax) {
                    echo json_encode(['status' => 'success', 'purchase_code' => $purchase_code]); exit();
                }
                $message = "Transfer Request {$req['request_code']} denied and automatically forwarded to Finance as Stock Purchase Request #{$purchase_code}.";
                $msg_type = "info";
            }
        } else {
            throw new Exception('Transfer request record not found.');
        }
    } catch (Throwable $ex) {
        if ($is_ajax) {
            echo json_encode(['status' => 'error', 'message' => $ex->getMessage()]); exit();
        }
        $message = $ex->getMessage();
        $msg_type = 'danger';
    }
}

// Fetch all transfer requests
$requests_q = mysqli_query($conn, "
    SELECT tr.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, COALESCE(p.selling_price, 0) AS price,
           COALESCE(ws.quantity, 0) AS storage_qty,
           e.full_name AS requester_name
    FROM transfer_requests tr
    JOIN products p ON tr.product_id = p.product_id
    LEFT JOIN warehouse_storage ws ON tr.product_id = ws.product_id
    LEFT JOIN employees e ON tr.requested_by = e.employee_id
    ORDER BY tr.created_at DESC
");
$requests = [];
if ($requests_q) {
    while ($r = mysqli_fetch_assoc($requests_q)) $requests[] = $r;
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#0f4c81;">
                <i class="bi bi-arrow-left-right me-2 text-primary"></i>Branch Transfer Requests
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Review low-stock transfer alerts from store branches. Approve against available storage stock or deny to send procurement request to Finance.</p>
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
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-clock-history me-2"></i>Pending &amp; History Transfer Requests</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">Request Code</th>
                        <th>Branch</th>
                        <th>Product &amp; Qty</th>
                        <th class="text-center">Warehouse Stock</th>
                        <th class="text-center">Urgency</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Warehouse Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No transfer requests submitted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): 
                            $st = $r['status'];
                            $badgeClass = 'bg-secondary';
                            if ($st === 'Pending Warehouse') $badgeClass = 'bg-warning text-dark';
                            elseif ($st === 'Approved & Dispatched') $badgeClass = 'bg-success';
                            elseif (strpos($st, 'Denied') !== false) $badgeClass = 'bg-danger';
                            
                            $imgSrc = !empty($r['image']) ? '../uploads/' . htmlspecialchars($r['image']) : 'https://via.placeholder.com/40?text=Product';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <strong class="text-primary d-block"><?= htmlspecialchars($r['request_code']); ?></strong>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($r['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong class="d-block text-dark"><?= htmlspecialchars($r['branch_name']); ?></strong>
                                <small class="text-muted">By: <?= htmlspecialchars($r['requester_name'] ?? 'Inventory Clerk'); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $imgSrc; ?>" class="rounded border" style="width:36px; height:36px; object-fit:cover;">
                                    <div>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($r['product_name']); ?></strong>
                                        <span class="badge bg-primary-subtle text-primary border">Requested: <?= number_format($r['requested_qty']); ?> units</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold fs-6 <?= intval($r['storage_qty']) >= intval($r['requested_qty']) ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($r['storage_qty']); ?>
                                </span>
                                <small class="d-block text-muted" style="font-size:10px;">Available in Storage</small>
                            </td>
                            <td class="text-center">
                                <?php
                                $u = $r['urgency'];
                                $uBadge = 'bg-info';
                                if ($u === 'High') $uBadge = 'bg-warning text-dark';
                                if ($u === 'Critical' || $u === 'Urgent') $uBadge = 'bg-danger';
                                ?>
                                <span class="badge <?= $uBadge; ?>"><?= $u; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($st === 'Pending Warehouse'): ?>
                                    <button class="btn btn-sm btn-success rounded-3 me-1" onclick="approveTransfer(<?= $r['request_id']; ?>, '<?= htmlspecialchars($r['request_code']); ?>')" title="Approve & Dispatch">
                                        <i class="bi bi-check-circle me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openDenyModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-x-circle me-1"></i> Deny
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

<!-- DENY MODAL -->
<div class="modal fade" id="denyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-danger text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-octagon me-2"></i>Deny &amp; Forward to Finance
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="denyForm">
                <input type="hidden" name="action" value="deny_request">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="request_id" id="deny_request_id">
                <div class="modal-body p-4">
                    <p class="text-muted" style="font-size:13px;">
                        Denying this transfer request will automatically create a <strong>Stock Purchase Request</strong> sent to <strong>Finance</strong> so they can approve supplier purchasing.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary style-label">Reason for Denial</label>
                        <textarea name="denial_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Out of stock in Warehouse Storage. Purchase order required." required>Out of stock in Central Warehouse storage. Purchase order required from supplier.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-3 px-3">
                        <i class="bi bi-send me-1"></i> Deny &amp; Send to Finance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function getTargetUrl() {
    return window.location.pathname.includes('/warehouse/') ? 'transfer_requests.php' : 'warehouse/transfer_requests.php';
}

function approveTransfer(reqId, reqCode) {
    Swal.fire({
        title: 'Approve & Create Dispatch?',
        text: 'This will approve request ' + reqCode + ' and automatically create a dispatch in Warehouse Dispatches.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Approve & Dispatch'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.ajax({
            url: getTargetUrl(),
            type: 'POST',
            data: { action: 'approve_request', request_id: reqId, is_ajax: 1 },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Approved & Dispatched!',
                        text: 'Created Dispatch #' + res.dispatch_code + ' in Warehouse Dispatches.',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {
                        loadPage('warehouse/transfer_requests.php');
                    });
                } else {
                    Swal.fire('Error', res.message || 'Action failed.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("Approve Request Error:", xhr.responseText);
                var raw = xhr.responseText ? xhr.responseText.replace(/<[^>]*>?/gm, '').trim().substring(0, 150) : '';
                Swal.fire('Error', 'Server error: ' + (raw || error || status), 'error');
            }
        });
    });
}

function openDenyModal(r) {
    $('#deny_request_id').val(r.request_id);
    new bootstrap.Modal(document.getElementById('denyModal')).show();
}

$('#denyForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: getTargetUrl(),
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'info',
                    title: 'Denied & Sent to Finance',
                    text: 'Purchase Request #' + res.purchase_code + ' forwarded to Finance.',
                    timer: 1800,
                    showConfirmButton: false
                }).then(() => {
                    loadPage('warehouse/transfer_requests.php');
                });
            } else {
                Swal.fire('Error', res.message || 'Action failed.', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error("Deny Request Error:", xhr.responseText);
            var raw = xhr.responseText ? xhr.responseText.replace(/<[^>]*>?/gm, '').trim().substring(0, 150) : '';
            Swal.fire('Error', 'Server error: ' + (raw || error || status), 'error');
        }
    });
});
</script>
