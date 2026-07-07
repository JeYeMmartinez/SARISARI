<?php
session_start();
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(!isset($_SESSION['user_id'])){
    echo 'unauthorized';
    exit();
}

$current_user = $_SESSION['user_id'];

/*=========================================================
    ACTION: APPROVE ORDER → CREATE SALE
==========================================================*/
if(isset($_POST['action']) && $_POST['action'] == 'approve'){
    $order_id = (int)$_POST['order_id'];
    $note     = trim($_POST['reason'] ?? '');

    if($note === ''){
        echo 'error: A note is required to approve this order.';
        exit();
    }
    $note = mysqli_real_escape_string($conn, $note);

    $orderRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT o.*, u.full_name, u.gmail
        FROM orders o
        LEFT JOIN users u ON o.cashier_id = u.user_id
        WHERE o.order_id = $order_id AND o.status = 'Pending'
    "));

    if(!$orderRow){
        echo 'error: Order not found or already processed.';
        exit();
    }

    $itemsQuery = mysqli_query($conn,"
        SELECT oi.*, p.product_name AS pname
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = $order_id
    ");
    $items = [];
    while($row = mysqli_fetch_assoc($itemsQuery)) $items[] = $row;

    if(empty($items)){
        echo 'error: Order has no items.';
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        // Validate stock first
        foreach($items as $item){
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $stock = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT quantity FROM inventory WHERE product_id = $pid"));
            if(!$stock || (int)$stock['quantity'] < $qty){
                throw new Exception("Insufficient stock for '{$item['product_name']}'. Available: " . ($stock['quantity'] ?? 0));
            }
        }

        $total = (float)$orderRow['total'];

        // Create sale
        $saleInsert = mysqli_query($conn,"
            INSERT INTO sales (cashier_id, total_amount, payment, change_amount, status, created_at)
            VALUES ($current_user, $total, $total, 0, 'Completed', NOW())
        ");
        if(!$saleInsert) throw new Exception("Failed to create sale: " . mysqli_error($conn));

        $sale_id = mysqli_insert_id($conn);

        // Insert sale_items + deduct inventory
        foreach($items as $item){
            $pid      = (int)$item['product_id'];
            $qty      = (int)$item['quantity'];
            $price    = (float)$item['selling_price'];
            $subtotal = (float)$item['subtotal'];

            mysqli_query($conn,"
                INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal)
                VALUES ($sale_id, $pid, $qty, $price, $subtotal)
            ");

            mysqli_query($conn,"
                UPDATE inventory SET quantity = GREATEST(0, quantity - $qty)
                WHERE product_id = $pid
            ");

            mysqli_query($conn,"
                UPDATE products SET status =
                    CASE WHEN (SELECT quantity FROM inventory WHERE product_id = $pid) = 0
                    THEN 'Unavailable' ELSE 'Available' END
                WHERE product_id = $pid
            ");
        }

        // Mark order as Completed
        mysqli_query($conn,"UPDATE orders SET status = 'Completed' WHERE order_id = $order_id");

        $custName = mysqli_real_escape_string($conn, $orderRow['full_name']);
        logAction($conn, $current_user, 'Create', 'sales', $sale_id,
            "Approved order #$order_id for $custName → Sale #$sale_id created — Note: $note");

        mysqli_query($conn,"
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Order Approved', 'Order #$order_id for $custName approved → Sale #$sale_id created', 'Approval', 0)
        ");

        mysqli_commit($conn);
        echo 'success';

    } catch(Exception $e){
        mysqli_rollback($conn);
        echo 'error: ' . $e->getMessage();
    }
    exit();
}

/*=========================================================
    ACTION: CANCEL ORDER
==========================================================*/
if(isset($_POST['action']) && $_POST['action'] == 'cancel'){
    $order_id = (int)$_POST['order_id'];
    $reason   = trim($_POST['reason'] ?? '');

    if($reason === ''){
        echo 'error: A reason is required to cancel this order.';
        exit();
    }
    $reason = mysqli_real_escape_string($conn, $reason);

    $orderRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT o.*, u.full_name
        FROM orders o
        LEFT JOIN users u ON o.cashier_id = u.user_id
        WHERE o.order_id = $order_id AND o.status = 'Pending'
    "));

    if(!$orderRow){
        echo 'error: Order not found or already processed.';
        exit();
    }

    $query = mysqli_query($conn,"UPDATE orders SET status = 'Voided' WHERE order_id = $order_id");

    if($query){
        $custName = mysqli_real_escape_string($conn, $orderRow['full_name']);
        logAction($conn, $current_user, 'Void', 'orders', $order_id,
            "Cancelled order #$order_id for $custName — Reason: $reason");

        mysqli_query($conn,"
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Order Cancelled', 'Order #$order_id for $custName was cancelled', 'Approval', 0)
        ");

        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH PENDING ORDERS
==========================================================*/
$orders = mysqli_query($conn,"
    SELECT o.*, u.full_name, u.gmail,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
        o.total AS order_total
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.user_id
    WHERE o.status = 'Pending'
    ORDER BY o.created_at ASC
");

$pendingCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
))['total'];
?>

<style>
body.swal-on-top .swal2-container { z-index: 99999 !important; }
.summary-card {
    background: white; border-radius: 12px; padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06); height: 100%;
}
.cart-card {
    background: white; border-radius: 12px; padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.item-pill {
    background: #f8f9fa; border-radius: 8px; padding: 4px 10px;
    font-size: 12px; display: inline-block; margin: 2px;
}
</style>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Pending Orders</small>
                    <h3 class="fw-bold mb-0 text-warning"><?= $pendingCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#ffc107;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-cart-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cart-card">
    <h5 class="mb-3">Pending Orders — Awaiting Approval</h5>

    <table class="table table-hover datatable">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if(mysqli_num_rows($orders) == 0){
            ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No pending orders right now.</td></tr>
            <?php
            } else {
                while($order = mysqli_fetch_assoc($orders)){
                    $itemsQuery = mysqli_query($conn,"
                        SELECT oi.product_name, oi.quantity, oi.selling_price, oi.subtotal
                        FROM order_items oi
                        WHERE oi.order_id = {$order['order_id']}
                    ");
                    $itemsHtml = '';
                    $itemsArr  = [];
                    while($it = mysqli_fetch_assoc($itemsQuery)){
                        $itemsHtml .= '<span class="item-pill">' . htmlspecialchars($it['product_name']) . ' ×' . $it['quantity'] . '</span>';
                        $itemsArr[] = $it;
                    }
                    $itemsJson = htmlspecialchars(json_encode($itemsArr), ENT_QUOTES);
            ?>
            <tr style="cursor:pointer;"
                onclick="viewOrderDetails(
                    <?= $order['order_id']; ?>,
                    '<?= htmlspecialchars($order['full_name'] ?? 'Unknown', ENT_QUOTES); ?>',
                    '<?= htmlspecialchars($order['gmail'] ?? '', ENT_QUOTES); ?>',
                    '<?= $itemsJson; ?>',
                    <?= $order['order_total']; ?>,
                    '<?= date('M d, Y h:i A', strtotime($order['created_at'])); ?>'
                )">
                <td>#<?= $order['order_id']; ?></td>
                <td>
                    <?= htmlspecialchars($order['full_name'] ?? 'Unknown'); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($order['gmail'] ?? ''); ?></small>
                </td>
                <td>
                    <span class="text-muted" style="font-size:12px;">
                        <?= $order['item_count']; ?> item<?= $order['item_count'] != 1 ? 's' : ''; ?>
                    </span>
                    <button class="btn btn-sm btn-outline-success ms-1"
                        onclick="event.stopPropagation(); viewOrderDetails(
                            <?= $order['order_id']; ?>,
                            '<?= htmlspecialchars($order['full_name'] ?? 'Unknown', ENT_QUOTES); ?>',
                            '<?= htmlspecialchars($order['gmail'] ?? '', ENT_QUOTES); ?>',
                            '<?= $itemsJson; ?>',
                            <?= $order['order_total']; ?>,
                            '<?= date('M d, Y h:i A', strtotime($order['created_at'])); ?>'
                        )">
                        <i class="bi bi-eye me-1"></i>View
                    </button>
                </td>
                <td><strong>₱<?= number_format($order['order_total'], 2); ?></strong></td>
                <td><?= date("M d, Y h:i A", strtotime($order['created_at'])); ?></td>
                <td onclick="event.stopPropagation()">
                    <button class="btn btn-sm btn-success me-1"
                        onclick="approveOrder(<?= $order['order_id']; ?>, '<?= htmlspecialchars($order['full_name'], ENT_QUOTES); ?>')">
                        <i class="bi bi-check-lg me-1"></i>Approve
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                        onclick="cancelOrder(<?= $order['order_id']; ?>, '<?= htmlspecialchars($order['full_name'], ENT_QUOTES); ?>')">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                </td>
            </tr>
            <?php
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!--=========================================================
    ORDER DETAILS MODAL
==========================================================-->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-cart-fill me-2"></i>
                    Order Details — <span id="odOrderId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-4 p-3"
                     style="background:#fff9e6;border-radius:10px;">
                    <div style="width:44px;height:44px;border-radius:50%;background:#ffc107;
                                display:flex;align-items:center;justify-content:center;color:white;font-size:20px;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <div class="fw-semibold" id="odCustomerName"></div>
                        <small class="text-muted" id="odCustomerEmail"></small>
                    </div>
                    <div class="ms-auto text-muted" style="font-size:12px;" id="odDate"></div>
                </div>
                <table class="table table-bordered table-striped">
                    <thead class="table-warning">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="odItemsBody"></tbody>
                    <tfoot>
                        <tr class="table-warning fw-bold">
                            <td colspan="4" class="text-end">Total</td>
                            <td class="text-end" id="odTotal"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="odApproveBtn">
                    <i class="bi bi-check-lg me-1"></i>Approve & Create Sale
                </button>
                <button class="btn btn-outline-danger" id="odCancelBtn">
                    <i class="bi bi-x-lg me-1"></i>Cancel Order
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let _odId   = null;
let _odName = null;

function viewOrderDetails(id, name, email, itemsJson, total, date){
    _odId   = id;
    _odName = name;

    const items = typeof itemsJson === 'string' ? JSON.parse(itemsJson) : itemsJson;

    $('#odOrderId').text('#' + id);
    $('#odCustomerName').text(name);
    $('#odCustomerEmail').text(email);
    $('#odDate').text(date);

    let rows = '';
    items.forEach((item, i) => {
        rows += `<tr>
            <td>${i+1}</td>
            <td>${item.product_name}</td>
            <td class="text-center">${item.quantity}</td>
            <td class="text-end">₱${parseFloat(item.selling_price).toFixed(2)}</td>
            <td class="text-end">₱${parseFloat(item.subtotal).toFixed(2)}</td>
        </tr>`;
    });
    $('#odItemsBody').html(rows);
    $('#odTotal').text('₱' + parseFloat(total).toFixed(2));

    $('#odApproveBtn').off('click').on('click', function(){
        bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();
        setTimeout(() => approveOrder(_odId, _odName), 300);
    });
    $('#odCancelBtn').off('click').on('click', function(){
        bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();
        setTimeout(() => cancelOrder(_odId, _odName), 300);
    });

    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
}

function approveOrder(id, name){
    document.body.classList.add('swal-on-top');
    Swal.fire({
        title: `Approve order for ${name}?`,
        html: `<p class="text-muted mb-2" style="font-size:14px;">This will create a sale and deduct inventory.</p>
               <input id="approveNote" class="swal2-input" placeholder="Note e.g. Paid in full, Confirmed...">`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Approve & Create Sale',
        preConfirm: () => {
            const note = document.getElementById('approveNote').value.trim();
            if(!note){ Swal.showValidationMessage('Please add a note.'); return false; }
            return note;
        }
    }).then(result => {
        document.body.classList.remove('swal-on-top');
        if(!result.isConfirmed) return;
        $.post('pending_carts.php', {
            action: 'approve', order_id: id, reason: result.value
        }, function(response){
            if(response.trim() == 'success'){
                Swal.fire({ icon:'success', title:'Order Approved!', text:'Sale created and inventory updated.', showConfirmButton:false, timer:2000 })
                .then(() => { loadPage('pending_carts.php'); });
            } else {
                Swal.fire('Error', response.replace('error:','').trim(), 'error');
            }
        });
    });
}

function cancelOrder(id, name){
    document.body.classList.add('swal-on-top');
    Swal.fire({
        title: `Cancel order for ${name}?`,
        html: `<input id="cancelReason" class="swal2-input" placeholder="Reason e.g. No-show, Customer cancelled...">`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Cancel Order',
        preConfirm: () => {
            const reason = document.getElementById('cancelReason').value.trim();
            if(!reason){ Swal.showValidationMessage('Please add a reason.'); return false; }
            return reason;
        }
    }).then(result => {
        document.body.classList.remove('swal-on-top');
        if(!result.isConfirmed) return;
        $.post('pending_carts.php', {
            action: 'cancel', order_id: id, reason: result.value
        }, function(response){
            if(response.trim() == 'success'){
                Swal.fire({ icon:'success', title:'Order Cancelled', showConfirmButton:false, timer:1500 })
                .then(() => { loadPage('pending_carts.php'); });
            } else {
                Swal.fire('Error', response.replace('error:','').trim(), 'error');
            }
        });
    });
}
</script>