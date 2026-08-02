<?php
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$emp_id   = $_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 1;
$emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Inventory Clerk';

/*=========================================================
    ACTION: PROCESS SHIPMENT RECEIVING (APPROVE / PARTIAL / REJECT)
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'process_receiving') {
    $dispatch_id   = (int)$_POST['dispatch_id'];
    $decision      = mysqli_real_escape_string($conn, trim($_POST['decision'] ?? 'Approve'));
    $disc_reason   = mysqli_real_escape_string($conn, trim($_POST['discrepancy_reason'] ?? ''));
    $item_ids      = $_POST['item_ids'] ?? [];
    $received_qtys = $_POST['received_qtys'] ?? [];

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM warehouse_dispatches WHERE dispatch_id = $dispatch_id LIMIT 1"));
    if (!$disp) { ob_clean(); echo 'error: Transfer shipment record not found.'; exit(); }

    if ($decision !== 'Approve' && empty($disc_reason)) {
        ob_clean(); echo 'error: A discrepancy reason is required when rejecting or partially receiving a shipment.'; exit();
    }

    // Handle Proof Image Upload (optional)
    $proof_path = null;
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../Uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadDir . $filename)) {
            $proof_path = 'Uploads/' . $filename;
        }
    }

    if ($decision === 'Reject') {
        // Mark shipment as Rejected — No inventory updated
        mysqli_query($conn, "
            UPDATE warehouse_dispatches 
            SET status = 'Rejected', received_at = NOW(), received_by = $emp_id, discrepancy_reason = '$disc_reason'" .
            ($proof_path ? ", proof_image = '$proof_path'" : "") . " 
            WHERE dispatch_id = $dispatch_id
        ");

        logAction($conn, 1, 'Reject Transfer Shipment', 'warehouse_dispatches', $dispatch_id, 
            "Shipment {$disp['dispatch_code']} REJECTED by {$emp_name}. Reason: {$disc_reason}");
        
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Shipment Rejected', 'Shipment {$disp['dispatch_code']} was rejected by {$disp['destination_branch']}. Reason: {$disc_reason}', 'Approval', 0)
        ");

        ob_clean(); echo 'rejected'; exit();
    }

    // Approve or Partially Receive — Process itemized quantities & update branch inventory
    $has_discrepancy = false;

    for ($i = 0; $i < count($item_ids); $i++) {
        $itemId = (int)$item_ids[$i];
        $rcvQty = (int)($received_qtys[$i] ?? 0);

        $itemRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM warehouse_dispatch_items WHERE item_id = $itemId LIMIT 1"));
        if ($itemRow) {
            $expected = (int)$itemRow['expected_qty'];
            $prodId   = (int)$itemRow['product_id'];

            if ($rcvQty != $expected) {
                $has_discrepancy = true;
            }

            $itemStatus = ($rcvQty >= $expected) ? 'Received' : (($rcvQty > 0) ? 'Discrepancy' : 'Missing');

            mysqli_query($conn, "
                UPDATE warehouse_dispatch_items 
                SET received_qty = $rcvQty, item_status = '$itemStatus' 
                WHERE item_id = $itemId
            ");

            // Add received stock to branch inventory
            if ($rcvQty > 0) {
                $invRes = mysqli_query($conn, "SELECT inventory_id, quantity FROM inventory WHERE product_id = $prodId LIMIT 1");
                $inv = mysqli_fetch_assoc($invRes);
                if ($inv) {
                    $newQty = (int)$inv['quantity'] + $rcvQty;
                    $invId  = (int)$inv['inventory_id'];
                    mysqli_query($conn, "UPDATE inventory SET quantity = $newQty WHERE inventory_id = $invId");
                } else {
                    mysqli_query($conn, "INSERT INTO inventory (product_id, quantity) VALUES ($prodId, $rcvQty)");
                    $invId  = mysqli_insert_id($conn);
                }

                // Log Stock Movement
                mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($invId, 'Transfer In', $rcvQty, '{$disp['dispatch_code']}', 'Received from {$disp['source_warehouse']}', $emp_id, NOW())");
            }
        }
    }

    $final_status = ($has_discrepancy || $decision === 'Partially Receive') ? 'Partially Received' : 'Received';

    mysqli_query($conn, "
        UPDATE warehouse_dispatches 
        SET status = '$final_status', received_at = NOW(), received_by = $emp_id, discrepancy_reason = '$disc_reason'" .
        ($proof_path ? ", proof_image = '$proof_path'" : "") . " 
        WHERE dispatch_id = $dispatch_id
    ");

    logAction($conn, 1, 'Receive Transfer Shipment', 'warehouse_dispatches', $dispatch_id, 
        "Shipment {$disp['dispatch_code']} processed as {$final_status} by {$emp_name}");

    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    ACTION: FETCH SHIPMENT RECEIVING DETAILS (AJAX GET)
==========================================================*/
if (isset($_GET['action']) && $_GET['action'] === 'get_receiving_details') {
    $dispatch_id = (int)$_GET['dispatch_id'];
    $disp = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT d.*, e.full_name AS clerk_name
        FROM warehouse_dispatches d
        LEFT JOIN employees e ON d.dispatched_by = e.employee_id
        WHERE d.dispatch_id = $dispatch_id LIMIT 1
    "));

    $items = mysqli_query($conn, "
        SELECT i.*, p.product_name, c.category_name
        FROM warehouse_dispatch_items i
        JOIN products p ON i.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE i.dispatch_id = $dispatch_id
    ");
    $itemList = [];
    if ($items) {
        while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['dispatch' => $disp, 'items' => $itemList]);
    exit();
}

/*=========================================================
    ACTION: REQUEST LOW-STOCK TRANSFER FROM WAREHOUSE
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'request_transfer') {
    $product_id    = (int)$_POST['product_id'];
    $requested_qty = (int)$_POST['requested_qty'];
    $urgency       = mysqli_real_escape_string($conn, trim($_POST['urgency'] ?? 'Medium'));
    $notes         = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $branch        = mysqli_real_escape_string($conn, $_SESSION['branch_name'] ?? 'Main Branch');
    $req_code      = 'TRQ-' . date('Ymd') . '-' . rand(1000, 9999);
    
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
    
    $ins = mysqli_query($conn, "
        INSERT INTO transfer_requests (request_code, branch_name, requested_by, product_id, requested_qty, urgency, status, notes)
        VALUES ('$req_code', '$branch', $emp_id, $product_id, $requested_qty, '$urgency', 'Pending Warehouse', '$notes')
    ");
    
    if ($ins) {
        ob_clean(); echo 'requested'; exit();
    } else {
        ob_clean(); echo 'error: ' . mysqli_error($conn); exit();
    }
}

// Fetch products for request dropdown
$prods_q = mysqli_query($conn, "SELECT product_id, product_name, COALESCE(barcode, CONCAT('PRD-', product_id)) AS product_code, COALESCE(selling_price, 0) AS price FROM products WHERE deleted_at IS NULL ORDER BY product_name ASC");
$productList = [];
if ($prods_q) {
    while ($p = mysqli_fetch_assoc($prods_q)) $productList[] = $p;
}

/*=========================================================
    FETCH INCOMING TRANSFER SHIPMENTS
==========================================================*/
$transfers = mysqli_query($conn, "
    SELECT d.*, e.full_name AS sender_name
    FROM warehouse_dispatches d
    LEFT JOIN employees e ON d.dispatched_by = e.employee_id
    ORDER BY d.dispatched_at DESC
");
$transferList = [];
if ($transfers) {
    while ($r = mysqli_fetch_assoc($transfers)) $transferList[] = $r;
}
?>

<style>
.badge-transit { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
.badge-rec     { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-partial { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 600; }
.badge-reject  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600; }

.receiving-item-row {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px;
}
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f4c81;">
            <i class="bi bi-arrow-down-left-square-fill me-2 text-info"></i>Stock Transfer (Receiving Branch)
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">Receive, inspect, and approve incoming warehouse stock transfers, or submit low-stock transfer requests to Central Warehouse.</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm px-3 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#requestTransferModal">
            <i class="bi bi-send-plus me-1"></i> Request Low-Stock Transfer
        </button>
    </div>
</div>

<!-- REQUEST TRANSFER MODAL -->
<div class="modal fade" id="requestTransferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-primary text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-send-plus me-2"></i>Send Low-Stock Transfer Request to Warehouse
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestTransferForm">
                <input type="hidden" name="action" value="request_transfer">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Select Low-Stock Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select form-select-sm" required style="border-radius:8px;">
                            <option value="">-- Select Product --</option>
                            <?php foreach ($productList as $p): ?>
                                <option value="<?= $p['product_id']; ?>">
                                    <?= htmlspecialchars($p['product_name']); ?> (<?= htmlspecialchars($p['product_code'] ?? 'PRD-'.$p['product_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Requested Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="requested_qty" class="form-control form-control-sm" min="1" value="50" required style="border-radius:8px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Urgency Level</label>
                            <select name="urgency" class="form-select form-select-sm" style="border-radius:8px;">
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High (Low Stock Alert)</option>
                                <option value="Critical">Critical (Out of Stock)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Clerk Notes / Justification</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Current branch stock is below 10 units..." style="border-radius:8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 px-3">
                        <i class="bi bi-send me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- INCOMING TRANSFERS TABLE -->
<div class="page-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-info"></i>Incoming Branch Transfers</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="transfersTable">
            <thead class="table-light" style="font-size:12px;text-transform:uppercase;">
                <tr>
                    <th>Transfer ID</th>
                    <th>Warehouse</th>
                    <th>Destination Branch</th>
                    <th>Date Sent</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transferList as $t): ?>
                <tr>
                    <td class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($t['dispatch_code']); ?></td>
                    <td><span class="badge bg-secondary-subtle text-dark border"><?= htmlspecialchars($t['source_warehouse']); ?></span></td>
                    <td class="fw-semibold text-success"><?= htmlspecialchars($t['destination_branch']); ?></td>
                    <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($t['dispatched_at'])); ?></td>
                    <td>
                        <?php 
                        $st = $t['status'];
                        $bClass = 'badge-transit';
                        if (in_array($st, ['Received', 'Delivered'])) $bClass = 'badge-rec';
                        if ($st === 'Partially Received')            $bClass = 'badge-partial';
                        if ($st === 'Rejected')                     $bClass = 'badge-reject';
                        ?>
                        <span class="badge <?= $bClass; ?>"><?= $st; ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($st === 'In Transit' || $st === 'Pending' || $st === 'Packed'): ?>
                        <button class="btn btn-sm btn-success fw-semibold me-1" onclick="openProcessReceivingModal(<?= $t['dispatch_id']; ?>)">
                            <i class="bi bi-box-seam me-1"></i>Process Receiving
                        </button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewTransferDetails(<?= $t['dispatch_id']; ?>)">
                            <i class="bi bi-eye-fill me-1"></i>View Details
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PROCESS RECEIVING MODAL -->
<div class="modal fade" id="receivingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check2-square me-2"></i>Inspect & Process Incoming Shipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <form id="receivingForm" enctype="multipart/form-data">
                    <input type="hidden" id="rcv_dispatch_id" name="dispatch_id">
                    <input type="hidden" id="rcv_decision" name="decision" value="Approve">

                    <div class="p-3 mb-3 bg-light rounded border d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold fs-5 text-primary" id="rcv_code"></div>
                            <div class="text-muted" style="font-size:12px;" id="rcv_source_dest"></div>
                        </div>
                        <span class="badge bg-primary fs-6" id="rcv_status_badge">In Transit</span>
                    </div>

                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-list-check me-2 text-primary"></i>Inspect Received Line Items</h6>
                    <div id="receivingItemsContainer" class="mb-3">
                        <!-- Loaded dynamically -->
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size:12px;">Optional Proof Image Upload (Damaged / Missing Cargo)</label>
                        <input type="file" class="form-control" name="proof_image" accept="image/*">
                    </div>

                    <div class="mb-3" id="discrepancyBox" style="display:none;">
                        <label class="form-label fw-bold text-danger" style="font-size:12px;">Discrepancy / Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="discrepancy_reason" name="discrepancy_reason" rows="3" placeholder="Please describe missing quantities, damaged goods, or rejection rationale..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="submitReceivingDecision('Reject')">
                    <i class="bi bi-x-circle-fill me-1"></i> Reject Shipment
                </button>
                <div>
                    <button type="button" class="btn btn-warning text-dark me-2" onclick="submitReceivingDecision('Partially Receive')">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Partially Receive
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitReceivingDecision('Approve')">
                        <i class="bi bi-check-circle-fill me-1"></i> Approve & Receive Stock
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="modal fade" id="viewTransferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-info-circle-fill me-2"></i>Shipment Receiving Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewTransferBody" style="padding:24px;">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function openProcessReceivingModal(dispatchId) {
    $.get('Inventory_employee/inv_transfer.php?action=get_receiving_details&dispatch_id=' + dispatchId, function(data) {
        if (!data || !data.dispatch) {
            Swal.fire('Error', 'Unable to fetch shipment details.', 'error');
            return;
        }

        const d = data.dispatch;
        const items = data.items;

        $('#rcv_dispatch_id').val(d.dispatch_id);
        $('#rcv_code').text(d.dispatch_code);
        $('#rcv_source_dest').text('From ' + d.source_warehouse + ' → ' + d.destination_branch);
        $('#rcv_status_badge').text(d.status);

        let itemsHtml = '';
        items.forEach(it => {
            itemsHtml += `
                <div class="receiving-item-row d-flex align-items-center justify-content-between gap-3">
                    <input type="hidden" name="item_ids[]" value="${it.item_id}">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">${it.product_name}</div>
                        <small class="text-muted">Expected Shipment Quantity: <strong class="text-primary fs-6">${it.expected_qty} units</strong></small>
                    </div>
                    <div style="width: 160px;">
                        <label class="form-label mb-1 fw-bold text-success" style="font-size:11px;">Received Qty</label>
                        <input type="number" class="form-control form-control-sm fw-bold" name="received_qtys[]" min="0" value="${it.expected_qty}" required onchange="checkDiscrepancyNotice()">
                    </div>
                </div>
            `;
        });
        $('#receivingItemsContainer').html(itemsHtml);
        $('#discrepancyBox').hide();
        $('#discrepancy_reason').val('');
        new bootstrap.Modal(document.getElementById('receivingModal')).show();
    });
}

function checkDiscrepancyNotice() {
    let hasMismatch = false;
    $('#receivingItemsContainer .receiving-item-row').each(function() {
        const expected = parseInt($(this).find('.text-primary').text()) || 0;
        const rcvd = parseInt($(this).find('input[name="received_qtys[]"]').val()) || 0;
        if (rcvd !== expected) {
            hasMismatch = true;
        }
    });

    if (hasMismatch) {
        $('#discrepancyBox').slideDown();
    }
}

function submitReceivingDecision(decision) {
    $('#rcv_decision').val(decision);
    const reason = $('#discrepancy_reason').val().trim();

    if ((decision === 'Reject' || decision === 'Partially Receive') && !reason) {
        $('#discrepancyBox').slideDown();
        Swal.fire('Discrepancy Reason Required', 'Please enter a discrepancy or rejection reason before submitting.', 'warning');
        return;
    }

    Swal.fire({
        target: document.getElementById('receivingModal'),
        title: `Confirm ${decision}?`,
        text: decision === 'Approve' ? 'Stock will be added to your branch inventory.' : 'Submit receiving decision to warehouse.',
        icon: decision === 'Reject' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: decision === 'Reject' ? '#dc3545' : '#16a34a',
        confirmButtonText: `Yes, ${decision} Shipment`
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = new FormData(document.getElementById('receivingForm'));
        formData.append('action', 'process_receiving');

        $.ajax({
            url: 'Inventory_employee/inv_transfer.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                res = res.trim();
                if (res === 'success') {
                    Swal.fire({ icon:'success', title:'Shipment Processed!', text:'Branch inventory has been updated.', timer:1800, showConfirmButton:false })
                    .then(() => {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        loadPage('Inventory_employee/inv_transfer.php');
                    });
                } else if (res === 'rejected') {
                    Swal.fire({ icon:'info', title:'Shipment Rejected', text:'Rejection notice sent to warehouse.', confirmButtonColor:'#dc3545' })
                    .then(() => {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        loadPage('Inventory_employee/inv_transfer.php');
                    });
                } else {
                    Swal.fire('Error', res.replace(/^error:\s*/i, ''), 'error');
                }
            }
        });
    });
}

function viewTransferDetails(dispatchId) {
    $.get('warehouse/get_dispatch_details.php?dispatch_id=' + dispatchId, function(html) {
        $('#viewTransferBody').html(html);
        new bootstrap.Modal(document.getElementById('viewTransferModal')).show();
    });
}

$('#requestTransferForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'Inventory_employee/inv_transfer.php',
        type: 'POST',
        data: $(this).serialize(),
        success: function(res) {
            res = res.trim();
            if (res === 'requested') {
                Swal.fire({ icon:'success', title:'Transfer Request Sent!', text:'Your request has been forwarded to Central Warehouse.', timer:1800, showConfirmButton:false })
                .then(() => {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    loadPage('Inventory_employee/inv_transfer.php');
                });
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i, ''), 'error');
            }
        }
    });
});
</script>
