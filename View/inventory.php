<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){ session_start(); }
$current_user = $_SESSION['user_id'] ?? 1;

define('DEFAULT_MARKUP', 0.20); // 20% retail markup

/*=========================================================
    CRUD ACTIONS
==========================================================*/

// ADD TO INVENTORY (Initial inventory setup for unstocked products)
if(isset($_POST['action']) && $_POST['action'] == 'add_stock'){
    $product_id    = (int)($_POST['product_id'] ?? 0);
    $boxes         = max(0, (int)($_POST['boxes_received'] ?? 0));
    $units_per_box = max(1, (int)($_POST['units_per_box'] ?? 1));
    $cost_per_box  = (float)($_POST['cost_per_box'] ?? 0);
    $sell_price    = (float)($_POST['selling_price'] ?? 0);
    $quantity      = $boxes > 0 ? ($boxes * $units_per_box) : max(0, (int)($_POST['quantity'] ?? 0));
    $minimum_stock = max(0, (int)($_POST['minimum_stock'] ?? 5));
    $maximum_stock = max(0, (int)($_POST['maximum_stock'] ?? 100));
    $aisle         = mysqli_real_escape_string($conn, trim($_POST['aisle'] ?? ''));

    if(!$product_id){ echo 'error: Product ID is missing.'; exit(); }

    // Check if product already has inventory record
    $check = mysqli_query($conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id");

    if(mysqli_num_rows($check) > 0){
        echo 'exists';
    } else {
        $lastRestock = $quantity > 0 ? "NOW()" : "NULL";
        $query = mysqli_query($conn,"
            INSERT INTO inventory (product_id, quantity, minimum_stock, maximum_Stock, aisle, last_restock)
            VALUES ($product_id, $quantity, $minimum_stock, $maximum_stock, '$aisle', $lastRestock)
        ");

        if($query){
            // If boxes were entered, update product pricing & log restock
            if($boxes > 0 && $cost_per_box > 0){
                $cost_per_piece = round($cost_per_box / $units_per_box, 4);
                $total_cost     = round($boxes * $cost_per_box, 2);

                mysqli_query($conn, "
                    UPDATE products SET
                        units_per_box = $units_per_box,
                        cost_per_box  = $cost_per_box,
                        cost_price    = $cost_per_piece,
                        selling_price = IF($sell_price > 0, $sell_price, selling_price),
                        status        = 'Available'
                    WHERE product_id = $product_id
                ");

                mysqli_query($conn, "
                    INSERT INTO restock_logs
                        (product_id, boxes_received, units_per_box, pieces_added,
                         cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
                         restocked_by)
                    VALUES
                        ($product_id, $boxes, $units_per_box, $quantity,
                         $cost_per_box, $total_cost, $cost_per_piece, $sell_price,
                         $current_user)
                ");
            } else if($quantity > 0) {
                mysqli_query($conn, "UPDATE products SET status = 'Available' WHERE product_id = $product_id");
            }

            logAction($conn, $current_user, 'Create', 'inventory', mysqli_insert_id($conn),
                "Added product ID $product_id to inventory with $quantity pcs");
            echo 'success';
        } else {
            echo 'error: ' . mysqli_error($conn);
        }
    }
    exit();
}

// RESTOCK PRODUCT (Box-based restocking from Inventory page)
if(isset($_POST['action']) && $_POST['action'] == 'restock'){
    $product_id      = (int)($_POST['product_id'] ?? 0);
    $inventory_id    = (int)($_POST['inventory_id'] ?? 0);
    $boxes_received  = max(1, (int)($_POST['boxes_received'] ?? 1));
    $units_per_box   = max(1, (int)($_POST['units_per_box'] ?? 1));
    $cost_per_box    = (float)($_POST['cost_per_box'] ?? 0);
    $new_sell        = (float)($_POST['selling_price'] ?? 0);
    $minimum_stock   = max(0, (int)($_POST['minimum_stock'] ?? 5));
    $maximum_stock   = max(0, (int)($_POST['maximum_stock'] ?? 100));
    $aisle           = mysqli_real_escape_string($conn, trim($_POST['aisle'] ?? ''));
    $supplier        = mysqli_real_escape_string($conn, trim($_POST['supplier'] ?? ''));
    $delivery_note   = mysqli_real_escape_string($conn, trim($_POST['delivery_note'] ?? ''));

    // Resolve product_id from inventory_id if missing
    if(!$product_id && $inventory_id){
        $invRes = mysqli_query($conn, "SELECT product_id FROM inventory WHERE inventory_id = $inventory_id");
        if($invRes && mysqli_num_rows($invRes) > 0){
            $product_id = (int)mysqli_fetch_assoc($invRes)['product_id'];
        }
    }

    if(!$product_id){ echo 'error: Product ID is missing or invalid.'; exit(); }
    if($boxes_received < 1){ echo 'error: Boxes received must be at least 1.'; exit(); }
    if($cost_per_box <= 0){ echo 'error: Cost per box must be greater than zero.'; exit(); }
    if($new_sell <= 0){ echo 'error: Selling price must be greater than zero.'; exit(); }

    $pieces_added       = $boxes_received * $units_per_box;
    $total_cost         = round($boxes_received * $cost_per_box, 2);
    $new_cost_per_piece = $units_per_box > 0 ? round($cost_per_box / $units_per_box, 4) : 0;
    $sup_sql            = $supplier !== '' ? "'$supplier'" : "NULL";
    $note_sql           = $delivery_note !== '' ? "'$delivery_note'" : "NULL";

    // 1. Log restock in restock_logs
    $logQuery = mysqli_query($conn,"
        INSERT INTO restock_logs
            (product_id, boxes_received, units_per_box, pieces_added,
             cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
             supplier, delivery_note, restocked_by)
        VALUES
            ($product_id, $boxes_received, $units_per_box, $pieces_added,
             $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell,
             $sup_sql, $note_sql, $current_user)
    ");

    if(!$logQuery){
        echo 'error: Restock log failed — ' . mysqli_error($conn);
        exit();
    }

    // 2. Update inventory record
    $invUpdate = false;
    if($inventory_id > 0){
        $invUpdate = mysqli_query($conn,"
            UPDATE inventory SET
                quantity      = quantity + $pieces_added,
                minimum_stock = $minimum_stock,
                maximum_Stock = $maximum_stock,
                aisle         = '$aisle',
                last_restock  = NOW()
            WHERE inventory_id = $inventory_id
        ");
    } else {
        $invUpdate = mysqli_query($conn,"
            UPDATE inventory SET
                quantity      = quantity + $pieces_added,
                minimum_stock = $minimum_stock,
                maximum_Stock = $maximum_stock,
                aisle         = '$aisle',
                last_restock  = NOW()
            WHERE product_id = $product_id
        ");
    }

    if($invUpdate){
        // 3. Update products table
        mysqli_query($conn,"
            UPDATE products SET
                status        = 'Available',
                cost_price    = $new_cost_per_piece,
                cost_per_box  = $cost_per_box,
                units_per_box = $units_per_box,
                selling_price = $new_sell
            WHERE product_id = $product_id
        ");

        $prow  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_name FROM products WHERE product_id = $product_id"));
        $pname = $prow['product_name'] ?? 'Unknown';

        logAction($conn, $current_user, 'Update', 'inventory', $inventory_id,
            "Restocked '$pname': $boxes_received box(es) × $units_per_box pcs = $pieces_added pcs added. Total cost: ₱$total_cost");

        mysqli_query($conn,"
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Stock Restocked', 'Restocked $pieces_added pcs of $pname via Inventory', 'Products', 0)
        ");

        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// REMOVE STOCK
if(isset($_POST['action']) && $_POST['action'] == 'remove_stock'){
    $inventory_id   = (int)$_POST['inventory_id'];
    $remove_quantity = max(1, (int)$_POST['remove_quantity']);

    $query = mysqli_query($conn,"
        UPDATE inventory SET
            quantity = GREATEST(0, quantity - $remove_quantity)
        WHERE inventory_id = $inventory_id
    ");

    if($query){
        $inv = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT product_id, quantity FROM inventory WHERE inventory_id = $inventory_id"
        ));
        $pid = (int)$inv['product_id'];
        $qty = (int)$inv['quantity'];

        mysqli_query($conn,"
            UPDATE products SET status = CASE WHEN $qty = 0 THEN 'Unavailable' ELSE 'Available' END
            WHERE product_id = $pid
        ");

        logAction($conn, $current_user, 'Update', 'inventory', $inventory_id,
            "Removed $remove_quantity units from inventory ID $inventory_id (New Qty: $qty)");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$inventory = mysqli_query($conn,"
    SELECT
        i.*,
        p.product_id,
        p.product_name,
        p.barcode,
        p.selling_price,
        p.cost_price,
        p.cost_per_box,
        p.units_per_box,
        p.status AS product_status,
        c.category_name
    FROM inventory i
    INNER JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.product_name ASC
");

// Products without inventory records (not yet stocked, not deleted)
$unstocked = mysqli_query($conn,"
    SELECT p.product_id, p.product_name, p.units_per_box, p.cost_per_box, p.cost_price, p.selling_price, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE i.inventory_id IS NULL
    AND p.deleted_at IS NULL
    ORDER BY p.product_name ASC
");

$unstockedList = [];
while($row = mysqli_fetch_assoc($unstocked)){
    $unstockedList[] = $row;
}

/*=========================================================
    SUMMARY COUNTS
==========================================================*/

$totalQuery    = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL");
$totalData     = mysqli_fetch_assoc($totalQuery);

$lowQuery      = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity <= i.minimum_stock AND i.quantity > 0");
$lowData       = mysqli_fetch_assoc($lowQuery);

$outQuery      = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity = 0");
$outData       = mysqli_fetch_assoc($outQuery);

$healthyQuery  = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity > i.minimum_stock");
$healthyData   = mysqli_fetch_assoc($healthyQuery);

?>

<style>
.inv-card {
    background: white;
    border-radius: 12px;
    padding: 18px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    height: 100%;
}
.inv-card .icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
    flex-shrink: 0;
}
.table-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
.stock-bar-wrap {
    width: 100px;
}
.stock-bar {
    height: 6px;
    border-radius: 3px;
    background: #e9ecef;
    overflow: hidden;
}
.stock-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width .4s;
}
.box-pill {
    font-size: 11px;
    background: #e8f4fd;
    color: #0d6efd;
    border-radius: 20px;
    padding: 2px 8px;
    display: inline-block;
}
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <div class="col-lg-3 col-md-6">
        <div class="inv-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Total Products</small>
                    <h3 class="fw-bold mt-1"><?= $totalData['total']; ?></h3>
                    <span class="badge bg-secondary mt-1">In Inventory</span>
                </div>
                <div class="icon bg-secondary"><i class="bi bi-boxes"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="inv-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Healthy Stock</small>
                    <h3 class="fw-bold mt-1 text-success"><?= $healthyData['total']; ?></h3>
                    <span class="badge bg-success mt-1">Good</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="inv-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Low Stock</small>
                    <h3 class="fw-bold mt-1 text-warning"><?= $lowData['total']; ?></h3>
                    <span class="badge bg-warning text-dark mt-1">Needs Restock</span>
                </div>
                <div class="icon bg-warning"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="inv-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Out of Stock</small>
                    <h3 class="fw-bold mt-1 text-danger"><?= $outData['total']; ?></h3>
                    <span class="badge bg-danger mt-1">Critical</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-x-circle"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- ACTION BUTTONS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-boxes me-2"></i>Stock Levels & Restocking</h5>
    <?php if(count($unstockedList) > 0){ ?>
    <button class="btn btn-success" onclick="openAddStockModal()">
        <i class="bi bi-plus-lg me-1"></i> Add to Inventory
    </button>
    <?php } ?>
</div>

<!-- INVENTORY TABLE -->
<div class="table-card">
    <table class="table table-bordered table-striped datatable" id="inventoryTable">
        <thead class="table-success">
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Category</th>
                <th>Box Info</th>
                <th>Quantity (pcs)</th>
                <th>Stock Level</th>
                <th>Min Stock</th>
                <th>Max Stock</th>
                <th>Aisle</th>
                <th>Last Restock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            while($row = mysqli_fetch_assoc($inventory)){

                $qty     = (int)$row['quantity'];
                $min     = (int)$row['minimum_stock'];
                $max     = (int)($row['maximum_Stock'] ?: 100);

                // Stock status
                if($qty == 0){
                    $statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                    $barColor    = '#dc3545';
                } elseif($qty <= $min){
                    $statusBadge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                    $barColor    = '#ffc107';
                } else {
                    $statusBadge = '<span class="badge bg-success">In Stock</span>';
                    $barColor    = '#198754';
                }

                $barPercent = $max > 0 ? min(100, round(($qty / $max) * 100)) : 0;
            ?>
            <tr>
                <td><?= $i++; ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($row['product_name']); ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? '—'); ?></td>
                <td>
                    <span class="box-pill">
                        <i class="bi bi-box me-1"></i><?= (int)($row['units_per_box'] ?? 1); ?> pcs/box
                    </span>
                    <div class="text-muted" style="font-size:11px; margin-top:2px;">
                        ₱<?= number_format($row['cost_per_box'] ?? 0, 2); ?>/box
                    </div>
                </td>
                <td><strong><?= $qty; ?> pcs</strong></td>
                <td>
                    <div class="stock-bar-wrap">
                        <small class="text-muted"><?= $barPercent; ?>%</small>
                        <div class="stock-bar">
                            <div class="stock-bar-fill" style="width:<?= $barPercent; ?>%;background:<?= $barColor; ?>"></div>
                        </div>
                    </div>
                </td>
                <td><?= $min; ?> pcs</td>
                <td><?= $row['maximum_Stock'] ? $row['maximum_Stock'] . ' pcs' : '—'; ?></td>
                <td><?= htmlspecialchars($row['aisle'] ?? '—'); ?></td>
                <td><?= $row['last_restock'] ? date("M d, Y h:i A", strtotime($row['last_restock'])) : '—'; ?></td>
                <td><?= $statusBadge; ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" title="Restock by Boxes"
                        onclick="openInvRestockModal(<?= htmlspecialchars(json_encode($row)); ?>)">
                        <i class="bi bi-boxes"></i> Restock
                    </button>
                    <button class="btn btn-sm btn-warning" title="Remove Stock"
                        onclick="openRemoveModal(<?= $row['inventory_id']; ?>, '<?= addslashes($row['product_name']); ?>', <?= $qty; ?>)">
                        <i class="bi bi-dash-circle"></i>
                    </button>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!--=========================================================
    ADD TO INVENTORY MODAL
==========================================================-->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Product to Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_product_id" onchange="onAddProductSelect()">
                            <option value="">-- Select Product --</option>
                            <?php foreach($unstockedList as $p){ ?>
                            <option value="<?= $p['product_id']; ?>" data-json="<?= htmlspecialchars(json_encode($p)); ?>">
                                <?= htmlspecialchars($p['product_name']); ?>
                                (<?= htmlspecialchars($p['category_name'] ?? 'No Category'); ?>)
                            </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Boxes Received</label>
                        <div class="input-group">
                            <input type="number" min="0" class="form-control" id="add_boxes" placeholder="e.g. 2" oninput="calcAddQty()">
                            <span class="input-group-text">boxes</span>
                        </div>
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Total Pieces (pcs)</label>
                        <input type="number" min="0" class="form-control bg-light fw-bold" id="add_quantity" placeholder="0">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Aisle / Location</label>
                        <input type="text" class="form-control" id="add_aisle" placeholder="e.g. A1">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Minimum Stock (pcs)</label>
                        <input type="number" min="0" class="form-control" id="add_min" value="5">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Maximum Stock (pcs)</label>
                        <input type="number" min="0" class="form-control" id="add_max" value="100">
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitAddStock()">
                    <i class="bi bi-check-lg me-1"></i>Add to Inventory
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    INV RESTOCK MODAL (BOX-BASED)
==========================================================-->
<div class="modal fade" id="invRestockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background: linear-gradient(135deg,#0d6efd,#0a58ca);">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-boxes me-2"></i>Restock Inventory — <span id="inv_restock_title"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light-subtle">
                <input type="hidden" id="inv_restock_inventory_id">
                <input type="hidden" id="inv_restock_product_id">

                <!-- Overview -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">CURRENT STOCK</div>
                            <div class="fw-bold fs-5" id="inv_restock_current_stock">0</div>
                            <div class="text-muted" style="font-size:10px;">pieces</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">UNITS/BOX</div>
                            <div class="fw-bold fs-5" id="inv_restock_upb_display">—</div>
                            <div class="text-muted" style="font-size:10px;">pcs/box</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">LAST COST/BOX</div>
                            <div class="fw-bold fs-5 text-primary" id="inv_restock_last_cpb">—</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Boxes Received <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control fw-bold" id="inv_restock_boxes" placeholder="e.g. 5" oninput="onInvRestockCalc()">
                            <span class="input-group-text">boxes</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Units per Box <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control" id="inv_restock_units_per_box" oninput="onInvRestockCalc()">
                            <span class="input-group-text">pcs</span>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Edit only if packaging changed.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Pieces to Add</label>
                        <div class="input-group">
                            <input type="number" class="form-control bg-light fw-bold text-success" id="inv_restock_pieces" readonly>
                            <span class="input-group-text">pcs</span>
                        </div>
                        <small class="text-success fw-semibold" style="font-size:11px;" id="inv_restock_pieces_hint"></small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Box (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="inv_restock_cpb" placeholder="0.00" oninput="onInvRestockCalc()">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Piece (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.0001" class="form-control bg-light" id="inv_restock_cpp" readonly>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Auto: box cost ÷ units</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Total Capital Cost (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" class="form-control bg-light fw-bold text-danger" id="inv_restock_total" readonly>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Auto: boxes × box cost</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price per Piece (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="inv_restock_sell" placeholder="0.00">
                        </div>
                        <small class="text-muted" style="font-size:11px;" id="inv_restock_sell_hint">Auto 20% markup — adjust if needed.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Aisle / Location</label>
                        <input type="text" class="form-control" id="inv_restock_aisle" placeholder="e.g. A1, Shelf 3">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Minimum Stock (pcs)</label>
                        <input type="number" min="0" class="form-control" id="inv_restock_min">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Maximum Stock (pcs)</label>
                        <input type="number" min="0" class="form-control" id="inv_restock_max">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supplier / Distributor</label>
                        <input type="text" class="form-control" id="inv_restock_supplier" placeholder="e.g. Uni-President, JTI">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Delivery Note / DR No. (Optional)</label>
                        <input type="text" class="form-control" id="inv_restock_note" placeholder="e.g. DR-20240727-001">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary fw-semibold" onclick="submitInvRestock()">
                    <i class="bi bi-boxes me-1"></i>Confirm Restock
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    REMOVE STOCK MODAL
==========================================================-->
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Remove Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="remove_id">
                <p class="mb-1">Product: <strong id="remove_name"></strong></p>
                <p class="mb-3">Current Quantity: <strong id="remove_current"></strong> pcs</p>
                <label class="form-label fw-semibold">Quantity to Remove (pcs) <span class="text-danger">*</span></label>
                <input type="number" min="1" class="form-control" id="remove_qty" placeholder="e.g. 10">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="submitRemove()">
                    <i class="bi bi-check-lg me-1"></i>Remove
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    JAVASCRIPT
==========================================================-->
<script>
const MARKUP = 0.20;

function clearBackdrop(){
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

let selectedUnstockedProduct = null;

function onAddProductSelect(){
    const opt = $('#add_product_id option:selected');
    const json = opt.data('json');
    if(json){
        selectedUnstockedProduct = json;
        calcAddQty();
    } else {
        selectedUnstockedProduct = null;
    }
}

function calcAddQty(){
    const boxes = parseInt($('#add_boxes').val()) || 0;
    if(selectedUnstockedProduct && boxes > 0){
        const upb = parseInt(selectedUnstockedProduct.units_per_box) || 1;
        $('#add_quantity').val(boxes * upb);
    }
}

// ADD TO INVENTORY
function openAddStockModal(){
    selectedUnstockedProduct = null;
    $("#add_product_id").val('');
    $("#add_boxes, #add_quantity, #add_aisle").val('');
    $("#add_min").val(5);
    $("#add_max").val(100);
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

function submitAddStock(){
    const product  = $("#add_product_id").val();
    const quantity = $("#add_quantity").val();

    if(!product || quantity === '' || parseInt(quantity) < 0){
        Swal.fire('Missing Fields','Please select a product and enter initial quantity or boxes.','warning');
        return;
    }

    const boxes = parseInt($("#add_boxes").val()) || 0;
    let upb = 1, cpb = 0, sell = 0;
    if(selectedUnstockedProduct){
        upb = parseInt(selectedUnstockedProduct.units_per_box) || 1;
        cpb = parseFloat(selectedUnstockedProduct.cost_per_box) || 0;
        sell = parseFloat(selectedUnstockedProduct.selling_price) || 0;
    }

    $.post('inventory.php', {
        action:        'add_stock',
        product_id:    product,
        boxes_received:boxes,
        units_per_box: upb,
        cost_per_box:  cpb,
        selling_price: sell,
        quantity:      quantity,
        minimum_stock: $("#add_min").val(),
        maximum_stock: $("#add_max").val(),
        aisle:         $("#add_aisle").val()
    }, function(response){
        if(response.trim() == 'success'){
            Swal.fire({ icon:'success', title:'Added to Inventory!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('inventory.php'); });
        } else if(response.trim() == 'exists'){
            Swal.fire('Already Exists','This product already has an inventory record.','info');
        } else {
            Swal.fire('Error', response.replace('error:','').trim(), 'error');
        }
    });
}

// RESTOCK CALCULATOR (INVENTORY PAGE)
function onInvRestockCalc(){
    const boxes   = parseInt($('#inv_restock_boxes').val()) || 0;
    const units   = parseInt($('#inv_restock_units_per_box').val()) || 0;
    const cpb     = parseFloat($('#inv_restock_cpb').val()) || 0;

    const pieces  = boxes * units;
    const total   = boxes * cpb;
    const cpp     = units > 0 && cpb > 0 ? cpb / units : 0;
    const sugSell = cpp > 0 ? cpp * (1 + MARKUP) : 0;

    $('#inv_restock_pieces').val(pieces > 0 ? pieces : '');
    $('#inv_restock_total').val(total > 0 ? total.toFixed(2) : '');
    $('#inv_restock_cpp').val(cpp > 0 ? cpp.toFixed(4) : '');

    if(pieces > 0){
        const cur = parseInt($('#inv_restock_current_stock').text()) || 0;
        $('#inv_restock_pieces_hint').text(`${boxes} boxes × ${units} pcs = ${pieces} pcs → New total: ${cur + pieces} pcs`);
    } else {
        $('#inv_restock_pieces_hint').text('');
    }

    if(sugSell > 0){
        const existing = parseFloat($('#inv_restock_sell').val()) || 0;
        if(existing === 0) $('#inv_restock_sell').val(sugSell.toFixed(2));
        $('#inv_restock_sell_hint').text(`Suggested 20% markup: ₱${sugSell.toFixed(2)} — adjust if needed.`);
    }
}

// RESTOCK MODAL (INVENTORY PAGE)
function openInvRestockModal(p){
    $('#inv_restock_inventory_id').val(p.inventory_id);
    $('#inv_restock_product_id').val(p.product_id);
    $('#inv_restock_title').text(p.product_name);
    $('#inv_restock_current_stock').text(parseInt(p.quantity) || 0);
    $('#inv_restock_upb_display').text((p.units_per_box || 1) + ' pcs');
    $('#inv_restock_last_cpb').text('₱' + parseFloat(p.cost_per_box || 0).toFixed(2));

    $('#inv_restock_boxes').val('');
    $('#inv_restock_units_per_box').val(p.units_per_box || 1);
    $('#inv_restock_cpb').val(parseFloat(p.cost_per_box || 0).toFixed(2));
    $('#inv_restock_pieces, #inv_restock_cpp, #inv_restock_total').val('');
    $('#inv_restock_sell').val(parseFloat(p.selling_price || 0).toFixed(2));
    $('#inv_restock_aisle').val(p.aisle || '');
    $('#inv_restock_min').val(p.minimum_stock || 5);
    $('#inv_restock_max').val(p.maximum_Stock || 100);
    $('#inv_restock_supplier, #inv_restock_note').val('');
    $('#inv_restock_pieces_hint').text('');

    new bootstrap.Modal(document.getElementById('invRestockModal')).show();
}

function submitInvRestock(){
    const invId = $('#inv_restock_inventory_id').val();
    const pid   = $('#inv_restock_product_id').val();
    const boxes = parseInt($('#inv_restock_boxes').val());
    const units = parseInt($('#inv_restock_units_per_box').val());
    const cpb   = parseFloat($('#inv_restock_cpb').val());
    const sell  = parseFloat($('#inv_restock_sell').val());
    const min   = parseInt($('#inv_restock_min').val()) || 0;
    const max   = parseInt($('#inv_restock_max').val()) || 0;
    const aisle = $('#inv_restock_aisle').val().trim();
    const sup   = $('#inv_restock_supplier').val().trim();
    const note  = $('#inv_restock_note').val().trim();
    const pname = $('#inv_restock_title').text();

    if(!pid && !invId){
        Swal.fire('Error','Product ID or Inventory ID is missing.','error'); return;
    }
    if(!boxes || boxes < 1){
        Swal.fire('Required','Enter number of boxes received.','warning'); return;
    }
    if(!units || units < 1){
        Swal.fire('Required','Units per box must be at least 1.','warning'); return;
    }
    if(isNaN(cpb) || cpb <= 0){
        Swal.fire('Required','Cost per box must be greater than zero.','warning'); return;
    }
    if(isNaN(sell) || sell <= 0){
        Swal.fire('Required','Selling price must be greater than zero.','warning'); return;
    }

    const pieces = boxes * units;
    const total  = (boxes * cpb).toFixed(2);

    Swal.fire({
        title: 'Confirm Restock',
        html: `<div class="text-start" style="font-size:13.5px;">
            <strong>${pname}</strong><br>
            📦 <strong>${boxes}</strong> box(es) × <strong>${units}</strong> pcs = <strong>${pieces} pcs</strong> added<br>
            💰 Total Capital: <strong>₱${total}</strong><br>
            🏷️ New Sell Price: <strong>₱${sell.toFixed(2)}</strong>/pc
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Confirm'
    }).then(res => {
        if(!res.isConfirmed) return;

        bootstrap.Modal.getInstance(document.getElementById('invRestockModal')).hide();
        setTimeout(() => {
            $.post('inventory.php', {
                action:        'restock',
                inventory_id:  invId,
                product_id:    pid,
                boxes_received:boxes,
                units_per_box: units,
                cost_per_box:  cpb,
                selling_price: sell,
                minimum_stock: min,
                maximum_stock: max,
                aisle:         aisle,
                supplier:      sup,
                delivery_note: note
            }, function(r){
                if(r.trim() === 'success'){
                    Swal.fire({ icon:'success', title:'Restocked!', showConfirmButton:false, timer:1500 })
                    .then(() => { clearBackdrop(); loadPage('inventory.php'); });
                } else {
                    Swal.fire('Error', r.replace('error:','').trim(), 'error');
                }
            });
        }, 400);
    });
}

// REMOVE STOCK
function openRemoveModal(id, name, currentQty){
    $("#remove_id").val(id);
    $("#remove_name").text(name);
    $("#remove_current").text(currentQty);
    $("#remove_qty").val('');
    new bootstrap.Modal(document.getElementById('removeModal')).show();
}

function submitRemove(){
    const qty = $("#remove_qty").val();

    if(!qty || qty < 1){
        Swal.fire('Missing Fields','Please enter a quantity to remove.','warning');
        return;
    }

    $.post('inventory.php', {
        action:          'remove_stock',
        inventory_id:    $("#remove_id").val(),
        remove_quantity: qty
    }, function(response){
        if(response.trim() == 'success'){
            Swal.fire({ icon:'success', title:'Stock Removed!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('inventory.php'); });
        } else {
            Swal.fire('Error', response.replace('error:','').trim(), 'error');
        }
    });
}

</script>