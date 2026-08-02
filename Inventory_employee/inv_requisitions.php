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

/*=========================================================
    AJAX HANDLERS
==========================================================*/

// CREATE REQUISITION
if(isset($_POST['action']) && $_POST['action'] == 'create_requisition'){
    $product_id    = (int)$_POST['product_id'];
    $requested_qty = (int)$_POST['requested_qty'];
    $priority      = mysqli_real_escape_string($conn, $_POST['priority']);
    $reason        = mysqli_real_escape_string($conn, trim($_POST['reason']));
    $requested_by  = mysqli_real_escape_string($conn, $_SESSION['emp_name'] ?? 'Inventory Staff');

    if($requested_qty <= 0){
        echo 'error: Requested quantity must be greater than 0.';
        exit();
    }

    $q = mysqli_query($conn, "
        INSERT INTO stock_requisitions (product_id, requested_qty, priority, reason, status, requested_by)
        VALUES ($product_id, $requested_qty, '$priority', '$reason', 'Pending Procurement', '$requested_by')
    ");

    if($q){
        $req_id = mysqli_insert_id($conn);
        logAction($conn, 1, 'Create', 'stock_requisitions', $req_id, "Created Stock Requisition #$req_id for product ID $product_id ($requested_qty units)");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$requisitions = mysqli_query($conn, "
    SELECT r.*, p.product_name, p.barcode, i.quantity AS current_stock
    FROM stock_requisitions r
    JOIN products p ON r.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY r.created_at DESC
");

// Products list for selection
$products = mysqli_query($conn, "
    SELECT p.product_id, p.product_name, i.quantity
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.status != 'Unavailable'
    ORDER BY p.product_name ASC
");
?>

<div class="animate__animated animate__fadeIn">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-text-fill me-2 text-warning"></i>Stock Purchase Requisitions</h3>
            <p class="text-muted mb-0" style="font-size:13px;">Request stock procurement from suppliers to replenish inventory & maintain warehouse levels.</p>
        </div>
        <button class="btn btn-warning text-dark fw-bold" onclick="openNewReqModal()">
            <i class="bi bi-plus-circle-fill me-1"></i>New Stock Requisition
        </button>
    </div>

    <!-- REQUISITIONS TABLE CARD -->
    <div class="page-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable w-100" id="reqTable" style="font-size: 13.5px;">
                <thead class="table-light">
                    <tr>
                        <th>Req #</th>
                        <th>Product</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Requested Quantity</th>
                        <th>Priority</th>
                        <th>Requested By</th>
                        <th>Status Workflow</th>
                        <th>Date Filed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($requisitions)){ 
                        $status_badge = '<span class="badge bg-warning text-dark">Pending Procurement</span>';
                        if($row['status'] == 'Procurement Processing') $status_badge = '<span class="badge bg-info text-dark">Procurement Processing</span>';
                        elseif($row['status'] == 'Approved Finance') $status_badge = '<span class="badge bg-primary">Approved by Finance</span>';
                        elseif($row['status'] == 'Received Warehouse') $status_badge = '<span class="badge bg-success">Received & Stocked</span>';
                        elseif($row['status'] == 'Rejected') $status_badge = '<span class="badge bg-danger">Rejected</span>';

                        $prio_badge = '<span class="badge bg-secondary">Normal</span>';
                        if($row['priority'] == 'High') $prio_badge = '<span class="badge bg-warning text-dark">High</span>';
                        elseif($row['priority'] == 'Urgent') $prio_badge = '<span class="badge bg-danger">Urgent</span>';
                    ?>
                    <tr>
                        <td class="fw-bold text-secondary">#REQ-<?= str_pad($row['requisition_id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']); ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['barcode'] ?? 'No Barcode'); ?></small>
                        </td>
                        <td class="text-center fw-semibold"><?= number_format($row['current_stock'] ?? 0); ?></td>
                        <td class="text-center fw-bold text-primary fs-6"><?= number_format($row['requested_qty']); ?></td>
                        <td><?= $prio_badge; ?></td>
                        <td><small class="fw-semibold text-dark"><?= htmlspecialchars($row['requested_by']); ?></small></td>
                        <td><?= $status_badge; ?></td>
                        <td><small class="text-muted"><?= date('M d, Y h:i A', strtotime($row['created_at'])); ?></small></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!--=========================================================
    NEW REQUISITION MODAL
==========================================================-->
<div class="modal fade" id="newReqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus-fill me-2"></i>Create Stock Purchase Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newReqForm">
                <input type="hidden" name="action" value="create_requisition">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Target Product <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">-- Select Product to Replenish --</option>
                                <?php while($p = mysqli_fetch_assoc($products)){ ?>
                                <option value="<?= $p['product_id']; ?>">
                                    <?= htmlspecialchars($p['product_name']); ?> (Current Stock: <?= $p['quantity'] ?? 0; ?>)
                                </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Quantity to Order <span class="text-danger">*</span></label>
                            <input type="number" name="requested_qty" class="form-control" min="1" required placeholder="e.g. 100">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority Level <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select">
                                <option value="Normal" selected>Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Justification / Reason <span class="text-danger">*</span></label>
                            <input type="text" name="reason" class="form-control" required placeholder="e.g. Out of stock due to high demand during sale">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold"><i class="bi bi-send-fill me-1"></i>Submit Requisition</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewReqModal(){
    $('#newReqForm')[0].reset();
    new bootstrap.Modal(document.getElementById('newReqModal')).show();
}

$(document).ready(function(){
    $('#newReqForm').on('submit', function(e){
        e.preventDefault();
        $.post('inv_requisitions.php', $(this).serialize(), function(res){
            res = res.trim();
            if(res === 'success'){
                $('#newReqModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Requisition Filed',
                    text: 'Your stock requisition has been submitted to Procurement.',
                    showConfirmButton: false,
                    timer: 1600
                }).then(() => {
                    loadPage('inv_requisitions.php');
                });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
});
</script>
