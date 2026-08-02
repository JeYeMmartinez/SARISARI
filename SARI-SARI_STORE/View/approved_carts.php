<?php
session_start();
require_once '../Model/database.php';
require_once '../Controller/OrderController.php';

if(!isset($_SESSION['user_id'])){
    echo 'unauthorized';
    exit();
}

$current_user = $_SESSION['user_id'];
$orderController = new OrderController($conn);

/*=========================================================
    FETCH COMPLETED ORDERS
==========================================================*/
$orders = $orderController->getApprovedOrdersList();

$stats = $orderController->getApprovedSummaryStats();
$approvedCount = $stats['approved_count'];
$approvedTotal = $stats['approved_total'];
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
    background: #f0faf4; border-radius: 8px; padding: 4px 10px;
    font-size: 12px; display: inline-block; margin: 2px;
}
</style>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Approved Orders</small>
                    <h3 class="fw-bold mb-0 text-success"><?= $approvedCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#198754;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total Value (Approved)</small>
                    <h3 class="fw-bold mb-0">₱<?= number_format($approvedTotal, 2); ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#6c757d;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-bag-check-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cart-card">
    <h5 class="mb-3">Approved Orders — Sale Created & Ready for Pickup</h5>

    <table class="table table-hover datatable">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if(mysqli_num_rows($orders) == 0){
            ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No approved orders yet.</td></tr>
            <?php
            } else {
                while($order = mysqli_fetch_assoc($orders)){
                    $itemsQuery = $orderController->getOrderItems($order['order_id']);
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
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Order Details — <span id="odOrderId"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-4 p-3"
                     style="background:#f0faf4;border-radius:10px;">
                    <div style="width:44px;height:44px;border-radius:50%;background:#198754;
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
                    <thead class="table-success">
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
                        <tr class="table-success fw-bold">
                            <td colspan="4" class="text-end">Total</td>
                            <td class="text-end" id="odTotal"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    if(items && items.length > 0){
        items.forEach((item, i) => {
            const pName = item.product_name || ('Product #' + (item.product_id || (i+1)));
            rows += `<tr>
                <td>${i+1}</td>
                <td>${pName}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">₱${parseFloat(item.selling_price || 0).toFixed(2)}</td>
                <td class="text-end">₱${parseFloat(item.subtotal || 0).toFixed(2)}</td>
            </tr>`;
        });
    } else {
        rows = '<tr><td colspan="5" class="text-center text-muted italic">No items found for this order.</td></tr>';
    }
    $('#odItemsBody').html(rows);
    $('#odTotal').text('₱' + parseFloat(total).toFixed(2));

    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
}

</script>