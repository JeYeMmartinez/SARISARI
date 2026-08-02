<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

/*=========================================================
    AJAX HANDLERS
==========================================================*/

// 1. ADD / RESTOCK QUANTITY
if(isset($_POST['action']) && $_POST['action'] == 'restock'){
    $inventory_id  = (int)$_POST['inventory_id'];
    $add_quantity  = (int)$_POST['add_quantity'];
    $aisle         = mysqli_real_escape_string($conn, trim($_POST['aisle']));

    if($add_quantity <= 0){
        echo 'error: Restock quantity must be greater than 0.';
        exit();
    }

    $q = mysqli_query($conn, "
        UPDATE inventory 
        SET quantity = quantity + $add_quantity,
            aisle = IF('$aisle' != '', '$aisle', aisle),
            last_restock = NOW()
        WHERE inventory_id = $inventory_id
    ");

    if($q){
        // Fetch product info & update status to Available
        $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id FROM inventory WHERE inventory_id = $inventory_id"));
        if($inv){
            mysqli_query($conn, "UPDATE products SET status = 'Available' WHERE product_id = {$inv['product_id']}");
        }
        logAction($conn, 1, 'Update', 'inventory', $inventory_id, "Restocked inventory #$inventory_id: Added +$add_quantity units");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// 2. REMOVE / DEDUCT QUANTITY
if(isset($_POST['action']) && $_POST['action'] == 'remove_stock'){
    $inventory_id    = (int)$_POST['inventory_id'];
    $remove_quantity = (int)$_POST['remove_quantity'];
    $reason          = mysqli_real_escape_string($conn, trim($_POST['reason']));

    if($remove_quantity <= 0){
        echo 'error: Quantity to remove must be greater than 0.';
        exit();
    }

    // Get current inventory details
    $inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id, quantity FROM inventory WHERE inventory_id = $inventory_id"));
    if(!$inv){
        echo 'error: Inventory record not found.';
        exit();
    }

    if($remove_quantity > $inv['quantity']){
        echo 'error: Cannot deduct more than current stock (' . $inv['quantity'] . ').';
        exit();
    }

    $new_qty = $inv['quantity'] - $remove_quantity;

    $q = mysqli_query($conn, "
        UPDATE inventory 
        SET quantity = $new_qty
        WHERE inventory_id = $inventory_id
    ");

    if($q){
        // If stock hits 0, mark product as Unavailable
        if($new_qty == 0){
            mysqli_query($conn, "UPDATE products SET status = 'Unavailable' WHERE product_id = {$inv['product_id']}");
        }
        logAction($conn, 1, 'Update', 'inventory', $inventory_id, "Deducted -$remove_quantity units from inventory #$inventory_id — Reason: $reason");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// 3. EDIT AISLE & THRESHOLDS
if(isset($_POST['action']) && $_POST['action'] == 'update_location'){
    $inventory_id  = (int)$_POST['inventory_id'];
    $aisle         = mysqli_real_escape_string($conn, trim($_POST['aisle']));
    $minimum_stock = (int)$_POST['minimum_stock'];
    $maximum_stock = (int)$_POST['maximum_stock'];

    $q = mysqli_query($conn, "
        UPDATE inventory 
        SET aisle = '$aisle',
            minimum_stock = $minimum_stock,
            maximum_Stock = $maximum_stock
        WHERE inventory_id = $inventory_id
    ");

    if($q){
        logAction($conn, 1, 'Update', 'inventory', $inventory_id, "Updated location & threshold limits for inventory #$inventory_id");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA FOR VIEW
==========================================================*/

// Get stock list joining products and categories
$query = mysqli_query($conn, "
    SELECT i.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.status AS product_status, c.category_name
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY i.quantity ASC, p.product_name ASC
");
?>

<div class="animate__animated animate__fadeIn">

    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Stock & Location Management</h3>
            <p class="text-muted mb-0" style="font-size:13px;">Manage real-time inventory counts, aisle bin locations, and threshold triggers.</p>
        </div>
    </div>

    <!-- STOCK TABLE CARD -->
    <div class="page-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable w-100" id="stockTable" style="font-size: 13.5px;">
                <thead class="table-light">
                    <tr>
                        <th>Product & Barcode</th>
                        <th>Category</th>
                        <th>Aisle / Bin</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Min / Max Threshold</th>
                        <th>Unit Cost</th>
                        <th>Status</th>
                        <th class="text-center" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($query)){ 
                        $status_badge = '<span class="badge bg-success">In Stock</span>';
                        if($row['quantity'] == 0 || $row['product_status'] == 'Unavailable'){
                            $status_badge = '<span class="badge bg-danger">Out of Stock</span>';
                        } elseif($row['quantity'] <= $row['minimum_stock']){
                            $status_badge = '<span class="badge bg-warning text-dark">Low Stock</span>';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']); ?></div>
                            <small class="text-muted"><code><?= htmlspecialchars($row['barcode'] ?? 'N/A'); ?></code></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['category_name'] ?? 'General'); ?></span></td>
                        <td>
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($row['aisle'] ?? 'Unassigned'); ?></span>
                        </td>
                        <td class="text-center fw-bold fs-6 <?= $row['quantity'] <= $row['minimum_stock'] ? 'text-danger' : 'text-primary'; ?>">
                            <?= number_format($row['quantity']); ?>
                        </td>
                        <td class="text-center text-muted">
                            <span class="badge bg-light text-danger border" title="Minimum Threshold"><?= $row['minimum_stock']; ?> min</span> &ndash;
                            <span class="badge bg-light text-secondary border" title="Maximum Threshold"><?= $row['maximum_Stock'] ?? '&infin;'; ?> max</span>
                        </td>
                        <td class="fw-bold text-success">₱<?= number_format($row['cost_price'], 2); ?></td>
                        <td><?= $status_badge; ?></td>
                        <td>
                            <div class="d-flex justify-content-center gap-1">
                                <!-- RESTOCK / STOCK IN -->
                                <button class="btn btn-sm btn-success" 
                                        onclick="openRestockModal(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                        title="Restock (+) Stock In">
                                    <i class="bi bi-plus-lg"></i>
                                </button>

                                <!-- DEDUCT / STOCK OUT -->
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="openDeductModal(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                        title="Remove (-) Stock Out">
                                    <i class="bi bi-dash-lg"></i>
                                </button>

                                <!-- EDIT AISLE / THRESHOLD -->
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="openLocationModal(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                        title="Edit Aisle & Limits">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!--=========================================================
    MODAL 1: RESTOCK / STOCK IN
==========================================================-->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Stock In / Restock Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="restockForm">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="inventory_id" id="r_inv_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-normal" style="font-size:12px;">PRODUCT</label>
                        <div class="fw-bold fs-5 text-dark" id="r_prod_name"></div>
                        <small class="text-muted">Current Quantity: <strong id="r_curr_qty" class="text-primary"></strong></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" name="add_quantity" class="form-control form-control-lg" min="1" required placeholder="e.g. 50">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Aisle / Bin Location</label>
                        <input type="text" name="aisle" id="r_aisle" class="form-control" placeholder="e.g. Aisle 2 - Shelf B">
                        <small class="text-muted">Leave blank to keep existing aisle location.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Confirm Restock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    MODAL 2: STOCK OUT / DEDUCT
==========================================================-->
<div class="modal fade" id="deductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right me-2"></i>Stock Out / Remove Quantity</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="deductForm">
                <input type="hidden" name="action" value="remove_stock">
                <input type="hidden" name="inventory_id" id="d_inv_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-normal" style="font-size:12px;">PRODUCT</label>
                        <div class="fw-bold fs-5 text-dark" id="d_prod_name"></div>
                        <small class="text-muted">Current Quantity: <strong id="d_curr_qty" class="text-danger"></strong></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity to Deduct <span class="text-danger">*</span></label>
                        <input type="number" name="remove_quantity" class="form-control form-control-lg" min="1" required placeholder="e.g. 5">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Reduction <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="Damaged Stock">Damaged Stock / Broken</option>
                            <option value="Expired Item">Expired Item</option>
                            <option value="Store Usage">Store Usage / Internal Demo</option>
                            <option value="Inventory Discrepancy">Inventory Discrepancy Count</option>
                            <option value="Returned to Supplier">Returned to Supplier</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-dash-circle me-1"></i>Confirm Stock Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    MODAL 3: EDIT LOCATION & THRESHOLDS
==========================================================-->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt me-2"></i>Update Aisle Location & Limits</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="locationForm">
                <input type="hidden" name="action" value="update_location">
                <input type="hidden" name="inventory_id" id="l_inv_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-normal" style="font-size:12px;">PRODUCT</label>
                        <div class="fw-bold fs-6 text-dark" id="l_prod_name"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Aisle / Storage Location</label>
                        <input type="text" name="aisle" id="l_aisle" class="form-control" placeholder="e.g. Aisle 1 - Bay 3">
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Minimum Threshold</label>
                            <input type="number" name="minimum_stock" id="l_min" class="form-control" min="0" required>
                            <small class="text-muted">Triggers low stock alert</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Maximum Threshold</label>
                            <input type="number" name="maximum_stock" id="l_max" class="form-control" min="1" required>
                            <small class="text-muted">Maximum shelf capacity</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRestockModal(row){
    $('#r_inv_id').val(row.inventory_id);
    $('#r_prod_name').text(row.product_name);
    $('#r_curr_qty').text(row.quantity);
    $('#r_aisle').val(row.aisle || '');
    new bootstrap.Modal(document.getElementById('restockModal')).show();
}

function openDeductModal(row){
    $('#d_inv_id').val(row.inventory_id);
    $('#d_prod_name').text(row.product_name);
    $('#d_curr_qty').text(row.quantity);
    new bootstrap.Modal(document.getElementById('deductModal')).show();
}

function openLocationModal(row){
    $('#l_inv_id').val(row.inventory_id);
    $('#l_prod_name').text(row.product_name);
    $('#l_aisle').val(row.aisle || '');
    $('#l_min').val(row.minimum_stock);
    $('#l_max').val(row.maximum_Stock || 100);
    new bootstrap.Modal(document.getElementById('locationModal')).show();
}

$(document).ready(function(){
    // Form handlers
    $('#restockForm, #deductForm, #locationForm').on('submit', function(e){
        e.preventDefault();
        const form = $(this);
        $.post('inv_stock.php', form.serialize(), function(res){
            res = res.trim();
            if(res === 'success'){
                $('.modal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Stock Updated',
                    showConfirmButton: false,
                    timer: 1400
                }).then(() => {
                    loadPage('inv_stock.php');
                });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
});
</script>
