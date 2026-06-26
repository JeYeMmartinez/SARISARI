<?php
require_once '../Model/database.php';

/*=========================================================
    CRUD ACTIONS
==========================================================*/

// ADD STOCK (Create inventory record)
if(isset($_POST['action']) && $_POST['action'] == 'add_stock'){
    $product_id    = (int)$_POST['product_id'];
    $quantity      = (int)$_POST['quantity'];
    $minimum_stock = (int)$_POST['minimum_stock'];
    $maximum_stock = (int)$_POST['maximum_stock'];
    $aisle         = mysqli_real_escape_string($conn, $_POST['aisle']);

    // Check if product already has inventory record
    $check = mysqli_query($conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id");

    if(mysqli_num_rows($check) > 0){
        echo 'exists';
    } else {
        $query = mysqli_query($conn,"
            INSERT INTO inventory (product_id, quantity, minimum_stock, maximum_Stock, aisle, last_restock)
            VALUES ($product_id, $quantity, $minimum_stock, $maximum_stock, '$aisle', NOW())
        ");
        echo $query ? 'success' : 'error: ' . mysqli_error($conn);
    }
    exit();
}

// RESTOCK (Update quantity)
if(isset($_POST['action']) && $_POST['action'] == 'restock'){
    $inventory_id  = (int)$_POST['inventory_id'];
    $add_quantity  = (int)$_POST['add_quantity'];
    $minimum_stock = (int)$_POST['minimum_stock'];
    $maximum_stock = (int)$_POST['maximum_stock'];
    $aisle         = mysqli_real_escape_string($conn, $_POST['aisle']);

    $query = mysqli_query($conn,"
        UPDATE inventory SET
            quantity      = quantity + $add_quantity,
            minimum_stock = $minimum_stock,
            maximum_Stock = $maximum_stock,
            aisle         = '$aisle',
            last_restock  = NOW()
        WHERE inventory_id = $inventory_id
    ");

    if($query){
        // Get product_id from inventory record
        $inv = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT product_id, quantity FROM inventory WHERE inventory_id = $inventory_id"
        ));
        // Set available since we're restocking
        mysqli_query($conn,"
            UPDATE products SET status = 'Available'
            WHERE product_id = {$inv['product_id']}
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
    $remove_quantity = (int)$_POST['remove_quantity'];

    // Prevent going below 0
    $query = mysqli_query($conn,"
        UPDATE inventory SET
            quantity = GREATEST(0, quantity - $remove_quantity)
        WHERE inventory_id = $inventory_id
    ");

    echo $query ? 'success' : 'error: ' . mysqli_error($conn);
    if($query){
        $inv = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT product_id, quantity FROM inventory WHERE inventory_id = $inventory_id"
        ));
        mysqli_query($conn,"
            UPDATE products SET status = 
                CASE WHEN {$inv['quantity']} = 0 THEN 'Unavailable' ELSE 'Available' END
            WHERE product_id = {$inv['product_id']}
        ");
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
        p.product_name,
        p.selling_price,
        p.status AS product_status,
        c.category_name
    FROM inventory i
    INNER JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_name ASC
");

// Products without inventory records (not yet stocked)
$unstocked = mysqli_query($conn,"
    SELECT p.product_id, p.product_name, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE i.inventory_id IS NULL
    ORDER BY p.product_name ASC
");

$unstockedList = [];
while($row = mysqli_fetch_assoc($unstocked)){
    $unstockedList[] = $row;
}

/*=========================================================
    SUMMARY COUNTS
==========================================================*/

$totalQuery    = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory");
$totalData     = mysqli_fetch_assoc($totalQuery);

$lowQuery      = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory WHERE quantity <= minimum_stock AND quantity > 0");
$lowData       = mysqli_fetch_assoc($lowQuery);

$outQuery      = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory WHERE quantity = 0");
$outData       = mysqli_fetch_assoc($outQuery);

$healthyQuery  = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inventory WHERE quantity > minimum_stock");
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
    <h5 class="mb-0">Stock Levels</h5>
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
                <th>Quantity</th>
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

                $qty     = $row['quantity'];
                $min     = $row['minimum_stock'];
                $max     = $row['maximum_Stock'] ?: 100;

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
                <td><?= htmlspecialchars($row['product_name']); ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? '—'); ?></td>
                <td><strong><?= $qty; ?></strong></td>
                <td>
                    <div class="stock-bar-wrap">
                        <small class="text-muted"><?= $barPercent; ?>%</small>
                        <div class="stock-bar">
                            <div class="stock-bar-fill" style="width:<?= $barPercent; ?>%;background:<?= $barColor; ?>"></div>
                        </div>
                    </div>
                </td>
                <td><?= $min; ?></td>
                <td><?= $row['maximum_Stock'] ?? '—'; ?></td>
                <td><?= htmlspecialchars($row['aisle'] ?? '—'); ?></td>
                <td><?= $row['last_restock'] ? date("M d, Y", strtotime($row['last_restock'])) : '—'; ?></td>
                <td><?= $statusBadge; ?></td>
                <td>
                    <button class="btn btn-sm btn-success" title="Restock"
                        onclick="openRestockModal(
                            <?= $row['inventory_id']; ?>,
                            '<?= addslashes($row['product_name']); ?>',
                            <?= $min; ?>,
                            <?= $row['maximum_Stock'] ?? 100; ?>,
                            '<?= addslashes($row['aisle'] ?? ''); ?>'
                        )">
                        <i class="bi bi-arrow-up-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" title="Remove Stock"
                        onclick="openRemoveModal(
                            <?= $row['inventory_id']; ?>,
                            '<?= addslashes($row['product_name']); ?>',
                            <?= $qty; ?>
                        )">
                        <i class="bi bi-arrow-down-circle"></i>
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
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add to Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_product_id">
                            <option value="">-- Select Product --</option>
                            <?php foreach($unstockedList as $p){ ?>
                            <option value="<?= $p['product_id']; ?>">
                                <?= htmlspecialchars($p['product_name']); ?>
                                (<?= htmlspecialchars($p['category_name'] ?? 'No Category'); ?>)
                            </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="add_quantity" placeholder="0">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Aisle / Location</label>
                        <input type="text" class="form-control" id="add_aisle" placeholder="e.g. A1">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Minimum Stock</label>
                        <input type="number" min="0" class="form-control" id="add_min" value="5">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Maximum Stock</label>
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
    RESTOCK MODAL
==========================================================-->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Restock Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="restock_id">
                <p class="mb-3">Restocking: <strong id="restock_name"></strong></p>
                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-semibold">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" id="restock_qty" placeholder="e.g. 50">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Minimum Stock</label>
                        <input type="number" min="0" class="form-control" id="restock_min">
                    </div>

                    <div class="col-6">
                        <label class="form-label fw-semibold">Maximum Stock</label>
                        <input type="number" min="0" class="form-control" id="restock_max">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Aisle / Location</label>
                        <input type="text" class="form-control" id="restock_aisle">
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitRestock()">
                    <i class="bi bi-check-lg me-1"></i>Restock
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
                <p class="mb-3">Current Quantity: <strong id="remove_current"></strong></p>
                <label class="form-label fw-semibold">Quantity to Remove <span class="text-danger">*</span></label>
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

function clearBackdrop(){
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

// ADD TO INVENTORY
function openAddStockModal(){
    $("#add_product_id").val('');
    $("#add_quantity, add_aisle").val('');
    $("#add_min").val(5);
    $("#add_max").val(100);
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

function submitAddStock(){
    const product  = $("#add_product_id").val();
    const quantity = $("#add_quantity").val();

    if(!product || !quantity){
        Swal.fire('Missing Fields','Please select a product and enter quantity.','warning');
        return;
    }

    $.post('inventory.php', {
        action:        'add_stock',
        product_id:    product,
        quantity:      quantity,
        minimum_stock: $("#add_min").val(),
        maximum_stock: $("#add_max").val(),
        aisle:         $("#add_aisle").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Added to Inventory!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('inventory.php'); });
        } else if(response == 'exists'){
            Swal.fire('Already Exists','This product already has an inventory record.','info');
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

// RESTOCK
function openRestockModal(id, name, min, max, aisle){
    $("#restock_id").val(id);
    $("#restock_name").text(name);
    $("#restock_qty").val('');
    $("#restock_min").val(min);
    $("#restock_max").val(max);
    $("#restock_aisle").val(aisle);
    new bootstrap.Modal(document.getElementById('restockModal')).show();
}

function submitRestock(){
    const qty = $("#restock_qty").val();

    if(!qty || qty < 1){
        Swal.fire('Missing Fields','Please enter a quantity to add.','warning');
        return;
    }

    $.post('inventory.php', {
        action:        'restock',
        inventory_id:  $("#restock_id").val(),
        add_quantity:  qty,
        minimum_stock: $("#restock_min").val(),
        maximum_stock: $("#restock_max").val(),
        aisle:         $("#restock_aisle").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Restocked!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('inventory.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
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
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Stock Removed!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('inventory.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

</script>