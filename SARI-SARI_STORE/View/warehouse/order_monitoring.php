<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

// Auto-create supplier_orders table
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

// Handle Actions (2 Actions: Arrived vs Not Arrived)
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $oid = intval($_POST['order_id']);
    
    $order_q = mysqli_query($conn, "
        SELECT so.*, p.product_name
        FROM supplier_orders so
        JOIN products p ON so.product_id = p.product_id
        WHERE so.order_id = $oid LIMIT 1
    ");
    $order = mysqli_fetch_assoc($order_q);

    if ($order) {
        if ($_POST['action'] === 'mark_arrived') {
            $user_id = $_SESSION['user_id'] ?? 1;
            
            // 1. Update order status to Arrived
            mysqli_query($conn, "
                UPDATE supplier_orders
                SET status = 'Arrived', arrived_at = NOW(), received_by = $user_id
                WHERE order_id = $oid
            ");
            
            // 2. Automatically replenish stock into warehouse_storage
            mysqli_query($conn, "
                INSERT INTO warehouse_storage (product_id, quantity)
                VALUES ({$order['product_id']}, {$order['ordered_qty']})
                ON DUPLICATE KEY UPDATE quantity = quantity + {$order['ordered_qty']}
            ");

            // 3. Notify Inventory & Store Branches about stock replenishment
            mysqli_query($conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES (
                    'Warehouse Stock Restocked',
                    '{$order['ordered_qty']} units of {$order['product_name']} have arrived at Central Warehouse. Denied/Draft transfer requests can now be approved.',
                    'Warehouse',
                    0
                )
            ");
            
            if (isset($_POST['is_ajax'])) {
                ob_clean(); echo json_encode(['status' => 'success']); exit();
            }
            $message = "Order {$order['order_code']} marked as ARRIVED! Added {$order['ordered_qty']} units of {$order['product_name']} directly into Warehouse Storage stock.";
            $msg_type = "success";
        } elseif ($_POST['action'] === 'mark_not_arrived') {
            mysqli_query($conn, "UPDATE supplier_orders SET status = 'Not Arrived', arrived_at = NULL WHERE order_id = $oid");
            if (isset($_POST['is_ajax'])) {
                ob_clean(); echo json_encode(['status' => 'success']); exit();
            }
            $message = "Order {$order['order_code']} status set back to NOT ARRIVED.";
            $msg_type = "info";
        }
    }
}

// Fetch supplier orders
$orders_q = mysqli_query($conn, "
    SELECT so.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image,
           e.full_name AS receiver_name
    FROM supplier_orders so
    JOIN products p ON so.product_id = p.product_id
    LEFT JOIN employees e ON so.received_by = e.employee_id
    ORDER BY so.created_at DESC
");
$orders = [];
$pending_deliveries = 0;
$arrived_count = 0;

if ($orders_q) {
    while ($o = mysqli_fetch_assoc($orders_q)) {
        $orders[] = $o;
        if ($o['status'] === 'Arrived') {
            $arrived_count++;
        } else {
            $pending_deliveries++;
        }
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#0f4c81;">
                <i class="bi bi-truck-flatbed me-2 text-primary"></i>Order Monitoring (Supplier Shipments)
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Track incoming stock procurements approved by Finance. Confirm when supplier shipments arrive to restock Warehouse Storage.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 me-2 shadow-sm" style="border-radius:8px; font-size:13px;">
                <i class="bi bi-clock-history me-1"></i> Pending Deliveries: <?= $pending_deliveries; ?>
            </span>
            <span class="badge bg-success px-3 py-2 shadow-sm" style="border-radius:8px; font-size:13px;">
                <i class="bi bi-check2-circle me-1"></i> Received: <?= $arrived_count; ?>
            </span>
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
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-box-arrow-in-down me-2"></i>Supplier Procurement Shipments</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">PO Code</th>
                        <th>Product &amp; Qty Ordered</th>
                        <th>Supplier</th>
                        <th class="text-center">Expected Date</th>
                        <th class="text-center">Arrival Status</th>
                        <th class="text-end pe-4">Warehouse Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No supplier orders in monitoring yet. Approved Finance purchase requests will appear here automatically.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): 
                            $isArrived = ($o['status'] === 'Arrived');
                            $imgSrc = !empty($o['image']) ? '../uploads/' . htmlspecialchars($o['image']) : 'https://via.placeholder.com/40?text=Product';
                        ?>
                        <tr class="<?= $isArrived ? 'table-success bg-opacity-10' : '' ?>">
                            <td class="ps-4">
                                <strong class="text-primary d-block"><?= htmlspecialchars($o['order_code']); ?></strong>
                                <small class="text-muted">Ordered: <?= date('M d, Y', strtotime($o['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $imgSrc; ?>" class="rounded border" style="width:36px; height:36px; object-fit:cover;">
                                    <div>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($o['product_name']); ?></strong>
                                        <span class="badge bg-primary-subtle text-primary border">Qty: <?= number_format($o['ordered_qty']); ?> units</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($o['supplier_name']); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold text-secondary">
                                    <i class="bi bi-calendar-event me-1"></i><?= $o['expected_date'] ? date('M d, Y', strtotime($o['expected_date'])) : 'TBD'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($isArrived): ?>
                                    <span class="badge bg-success px-3 py-2" style="font-size:12px;">
                                        <i class="bi bi-check-circle-fill me-1"></i> Arrived
                                    </span>
                                    <small class="d-block text-muted mt-1" style="font-size:10px;">
                                        Received: <?= date('M d, Y h:i A', strtotime($o['arrived_at'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-2" style="font-size:12px;">
                                        <i class="bi bi-hourglass-split me-1"></i> Not Arrived
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if (!$isArrived): ?>
                                    <button class="btn btn-sm btn-success rounded-3 px-3 shadow-sm" onclick="toggleOrderStatus(<?= $o['order_id']; ?>, 'mark_arrived')">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Mark Arrived
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary rounded-3" onclick="toggleOrderStatus(<?= $o['order_id']; ?>, 'mark_not_arrived')">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Status
                                    </button>
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

<script>
function toggleOrderStatus(orderId, act) {
    $.ajax({
        url: 'warehouse/order_monitoring.php',
        type: 'POST',
        data: { action: act, order_id: orderId, is_ajax: 1 },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Status Updated!',
                    text: act === 'mark_arrived' ? 'Order marked as Arrived and storage stock replenished!' : 'Order status reset.',
                    timer: 1600,
                    showConfirmButton: false
                }).then(() => {
                    loadPage('warehouse/order_monitoring.php');
                });
            }
        }
    });
}
</script>
