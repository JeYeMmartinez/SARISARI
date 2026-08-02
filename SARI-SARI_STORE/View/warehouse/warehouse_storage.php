<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

// Auto-create warehouse_storage table
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS warehouse_storage (
        storage_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL UNIQUE,
        quantity INT DEFAULT 100,
        min_reorder_level INT DEFAULT 20,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Seed products into warehouse_storage if missing
mysqli_query($conn, "
    INSERT IGNORE INTO warehouse_storage (product_id, quantity, min_reorder_level)
    SELECT product_id, 150, 30 FROM products
");

// Handle Stock Adjustments
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_storage_stock') {
    $pid = intval($_POST['product_id']);
    $new_qty = intval($_POST['quantity']);
    $new_min = intval($_POST['min_reorder_level']);
    
    $stmt = $conn->prepare("INSERT INTO warehouse_storage (product_id, quantity, min_reorder_level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?, min_reorder_level = ?");
    $stmt->bind_param("iiiii", $pid, $new_qty, $new_min, $new_qty, $new_min);
    if ($stmt->execute()) {
        $message = "Warehouse storage stock updated successfully.";
        $msg_type = "success";
    } else {
        $message = "Failed to update storage stock: " . $conn->error;
        $msg_type = "danger";
    }
}

// Fetch products joined with warehouse storage
$query = "
    SELECT p.product_id, p.product_name, p.image,
           COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code,
           COALESCE(c.category_name, 'General') AS category,
           COALESCE(p.selling_price, 0) AS price,
           COALESCE(ws.quantity, 0) AS storage_qty,
           COALESCE(ws.min_reorder_level, 20) AS min_reorder,
           ws.last_updated
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN warehouse_storage ws ON p.product_id = ws.product_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.product_name ASC
";
$result = mysqli_query($conn, $query);
$items = [];
$total_products = 0;
$total_units = 0;
$low_stock_count = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
        $total_products++;
        $total_units += intval($row['storage_qty']);
        if (intval($row['storage_qty']) <= intval($row['min_reorder'])) {
            $low_stock_count++;
        }
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#0f4c81;">
                <i class="bi bi-boxes me-2 text-primary"></i>Central Warehouse Storage
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Manage physical stock availability at the central warehouse with real-time stock levels & image previews.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius:12px; background: linear-gradient(135deg, #0f172a, #1e293b); color:#fff;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Total Storage Products</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($total_products); ?></h3>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="bi bi-box-seam fs-3 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius:12px; background: linear-gradient(135deg, #0284c7, #0369a1); color:#fff;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Total Warehouse Stock Units</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($total_units); ?></h3>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="bi bi-layers-fill fs-3 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3" style="border-radius:12px; background: linear-gradient(135deg, <?= $low_stock_count > 0 ? '#b91c1c, #991b1b' : '#059669, #047857' ?>); color:#fff;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Low Storage Stock Alerts</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($low_stock_count); ?></h3>
                    </div>
                    <div class="p-3 bg-white bg-opacity-10 rounded-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-light"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Storage Table -->
    <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-secondary">
                <i class="bi bi-list-task me-2"></i>Warehouse Inventory Stock List
            </h6>
            <div style="width: 250px;">
                <input type="text" id="storageSearch" class="form-control form-control-sm" placeholder="Search product name, code..." style="border-radius:20px;">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="storageTable">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">Product</th>
                        <th>Code / SKU</th>
                        <th>Category</th>
                        <th class="text-center">Warehouse Stock</th>
                        <th class="text-center">Min Reorder Level</th>
                        <th class="text-center">Stock Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php foreach ($items as $item): 
                        $qty = intval($item['storage_qty']);
                        $min = intval($item['min_reorder']);
                        if ($qty <= 0) {
                            $badge = '<span class="badge bg-danger px-2 py-1"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>';
                        } elseif ($qty <= $min) {
                            $badge = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock</span>';
                        } else {
                            $badge = '<span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>In Stock</span>';
                        }
                        
                        // Image fallback
                        $imgSrc = !empty($item['image']) ? '../uploads/' . htmlspecialchars($item['image']) : 'https://via.placeholder.com/48?text=Product';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $imgSrc; ?>" onerror="this.src='https://via.placeholder.com/48?text=No+Img';" class="rounded border" style="width:44px; height:44px; object-fit:cover;">
                                <div>
                                    <strong class="d-block text-dark"><?= htmlspecialchars($item['product_name']); ?></strong>
                                    <small class="text-muted">₱<?= number_format($item['price'], 2); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code class="text-primary fw-semibold"><?= htmlspecialchars($item['product_code'] ?? 'PRD-'.$item['product_id']); ?></code></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['category'] ?? 'General'); ?></span></td>
                        <td class="text-center fw-bold fs-6 <?= $qty <= $min ? 'text-danger' : 'text-dark' ?>">
                            <?= number_format($qty); ?>
                        </td>
                        <td class="text-center text-muted"><?= number_format($min); ?></td>
                        <td class="text-center"><?= $badge; ?></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-outline-primary btn-sm rounded-circle p-1" style="width:32px; height:32px;" 
                                    onclick='openAdjustModal(<?= json_encode($item); ?>)' title="Adjust Stock">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADJUST STOCK MODAL -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold" id="modalProductName">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Adjust Warehouse Stock
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_storage_stock">
                <input type="hidden" name="product_id" id="adj_product_id">
                <div class="modal-body p-4">
                    <div class="mb-3 text-center">
                        <img id="adj_img" src="" class="rounded border mb-2" style="width:70px; height:70px; object-fit:cover;">
                        <h6 id="adj_name" class="fw-bold mb-0"></h6>
                        <small id="adj_code" class="text-muted"></small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary style-label">Warehouse Stock Quantity</label>
                            <input type="number" name="quantity" id="adj_quantity" class="form-control form-control-sm fw-bold fs-6 text-center" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary style-label">Min Reorder Level</label>
                            <input type="number" name="min_reorder_level" id="adj_min_reorder_level" class="form-control form-control-sm text-center" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 px-3">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $('#storageSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#storageTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});

function openAdjustModal(item) {
    $('#adj_product_id').val(item.product_id);
    $('#adj_name').text(item.product_name);
    $('#adj_code').text('Code: ' + (item.product_code || ('PRD-' + item.product_id)));
    $('#adj_quantity').val(item.storage_qty);
    $('#adj_min_reorder_level').val(item.min_reorder);
    
    var imgSrc = item.image ? '../uploads/' + item.image : 'https://via.placeholder.com/70?text=Product';
    $('#adj_img').attr('src', imgSrc);
    
    new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
}
</script>
