<?php
require_once '../Model/database.php';
require_once '../Controller/ProductController.php';

if(session_status() === PHP_SESSION_NONE){ session_start(); }
$current_user = $_SESSION['user_id'] ?? 1;

define('PRODUCT_UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('PRODUCT_UPLOAD_URL', 'uploads/products/');

if(!is_dir(PRODUCT_UPLOAD_DIR)){
    mkdir(PRODUCT_UPLOAD_DIR, 0755, true);
}

$productController = new ProductController($conn);

/*=========================================================
    ACTIONS (POST / GET)
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $result = $productController->createProduct($_POST, $_FILES, $current_user, PRODUCT_UPLOAD_DIR);
    echo $result;
    exit();
}

// RESTOCK
if(isset($_POST['action']) && $_POST['action'] == 'restock'){
    $result = $productController->restockProduct($_POST, $current_user);
    echo $result;
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $result = $productController->updateProduct($_POST, $_FILES, $current_user, PRODUCT_UPLOAD_DIR);
    echo $result;
    exit();
}

// SOFT DELETE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $result = $productController->deleteProduct($_POST['product_id'], $_POST['reason'] ?? '', $current_user);
    echo $result;
    exit();
}

// RESTORE
if(isset($_POST['action']) && $_POST['action'] == 'restore'){
    $result = $productController->restoreProduct($_POST['product_id'], $_POST['reason'] ?? '', $current_user);
    echo $result;
    exit();
}

// GET RESTOCK HISTORY (AJAX)
if(isset($_GET['action']) && $_GET['action'] == 'get_restock_logs'){
    ob_clean();
    $logs = $productController->getRestockLogs($_GET['product_id'] ?? 0);
    $rows = [];
    if($logs) {
        while($row = mysqli_fetch_assoc($logs)){ $rows[] = $row; }
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/
$products = $productController->getProductsList();
$trashCount = $productController->getTrashedCount();
$trashedProducts = $productController->getTrashedProductsList();

$categories = $productController->getCategories();
$categoriesList = [];
while($cat = mysqli_fetch_assoc($categories)){ $categoriesList[] = $cat; }
?>

<style>
body.swal-on-top .swal2-container { z-index: 99999 !important; }
.table-card {
    background: white; border-radius: 12px;
    padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px;
}
.stock-low  { background: #fff3cd !important; }
.stock-ok   { color: #198754; font-weight: 600; }
.stock-zero { color: #dc3545; font-weight: 700; }
.box-pill {
    font-size: 11px; background: #e8f4fd; color: #0d6efd;
    border-radius: 20px; padding: 2px 8px; display: inline-block;
}
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Products & Inventory</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger position-relative" onclick="openTrashModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php if($trashCount > 0){ ?>
            <span id="trashBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $trashCount; ?></span>
            <?php } ?>
        </button>
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Product
        </button>
    </div>
</div>

<!-- PRODUCTS TABLE -->
<div class="table-card">
    <table class="table table-bordered table-striped datatable" id="productsTable">
        <thead class="table-success">
            <tr>
                <th>#</th><th>Image</th><th>Product Name</th><th>Category</th>
                <th>Barcode</th><th>Box Info</th><th>Cost/pc</th><th>Sell/pc</th>
                <th>Stock (pcs)</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while($row = mysqli_fetch_assoc($products)){
            $qty      = (int)$row['stock_qty'];
            $minStock = (int)($row['minimum_stock'] ?? 5);
            $rowCls   = $qty <= 0 ? '' : ($qty <= $minStock ? 'stock-low' : '');
            $qtyCls   = $qty <= 0 ? 'stock-zero' : ($qty <= $minStock ? 'text-warning fw-bold' : 'stock-ok');
        ?>
        <tr class="<?= $rowCls; ?>">
            <td><?= $i++; ?></td>
            <td>
                <?php if(!empty($row['image']) && file_exists(__DIR__.'/uploads/products/'.$row['image'])){ ?>
                    <img src="uploads/products/<?= htmlspecialchars($row['image']); ?>" style="width:42px;height:42px;object-fit:cover;border-radius:6px;">
                <?php } else { ?>
                    <div style="width:42px;height:42px;border-radius:6px;background:#e9ecef;display:flex;align-items:center;justify-content:center;color:#adb5bd;"><i class="bi bi-image"></i></div>
                <?php } ?>
            </td>
            <td class="fw-semibold"><?= htmlspecialchars($row['product_name']); ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
            <td><code><?= htmlspecialchars($row['barcode'] ?? '—'); ?></code></td>
            <td>
                <span class="box-pill"><i class="bi bi-box me-1"></i><?= (int)$row['units_per_box']; ?> pcs/box</span>
                <div class="text-muted" style="font-size:11px;margin-top:2px;">₱<?= number_format($row['cost_per_box'],2); ?>/box</div>
            </td>
            <td>₱<?= number_format($row['cost_price'],2); ?></td>
            <td class="fw-semibold text-success">₱<?= number_format($row['selling_price'],2); ?></td>
            <td>
                <span class="<?= $qtyCls; ?>"><?= $qty; ?> pcs</span>
                <?php if($qty <= 0){ ?><span class="badge bg-danger ms-1" style="font-size:9px;">OUT</span>
                <?php } elseif($qty <= $minStock){ ?><span class="badge bg-warning text-dark ms-1" style="font-size:9px;">LOW</span>
                <?php } ?>
            </td>
            <td>
                <?php if($row['status']=='Available'){ ?>
                    <span class="badge bg-success">Available</span>
                <?php } else { ?>
                    <span class="badge bg-secondary">Unavailable</span>
                <?php } ?>
            </td>
            <td>
                <div class="d-flex gap-1 flex-wrap justify-content-center">
                    <button class="btn btn-sm btn-primary" title="Restock Inventory"
                        onclick="openProdRestockModal(<?= htmlspecialchars(json_encode($row)); ?>)">
                        <i class="bi bi-boxes"></i>
                    </button>
                    <button class="btn btn-sm btn-warning" title="Edit Product"
                        onclick="openEditModal(<?= htmlspecialchars(json_encode($row)); ?>)">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" title="Archive"
                        onclick="deleteProduct(<?= $row['product_id']; ?>,'<?= addslashes($row['product_name']); ?>')">
                        <i class="bi bi-archive-fill"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<!--=========================================================  ADD MODAL  ==========================================================-->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name" placeholder="e.g. Lucky Me Beef Noodles">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_category">
                            <option value="">-- Select Category --</option>
                            <?php foreach($categoriesList as $cat){ ?>
                            <option value="<?= $cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Barcode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_barcode" placeholder="13-digit barcode"
                               maxlength="13" inputmode="numeric"
                               onkeydown="blockNonDigitKey(event)" oninput="sanitizeDigitsOnly(this)">
                        <div class="form-text">Must be exactly 13 digits and unique.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="add_status">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>

                    <!-- BOX / UNIT SECTION -->
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0" style="font-size:12.5px;">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Box-to-Piece Setup:</strong> Enter how your supplier sells this product (per box/case). Stock is tracked per individual piece at the shelf level.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Units per Box <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control" id="add_units_per_box"
                                   placeholder="e.g. 24" oninput="onBoxChange('add')">
                            <span class="input-group-text">pcs/box</span>
                        </div>
                        <div class="form-text">How many pieces come per box.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Box (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_cost_per_box"
                                   placeholder="0.00" oninput="onBoxChange('add')">
                        </div>
                        <div class="form-text">Supplier price per box.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Piece (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.0001" class="form-control bg-light" id="add_cost_per_piece" readonly placeholder="Auto">
                        </div>
                        <div class="form-text">Auto: box cost ÷ units.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price per Piece (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_sell" placeholder="0.00">
                        </div>
                        <div class="form-text" id="add_markup_hint">Auto 20% markup — you may adjust.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Image <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="add_image" accept="image/png, image/jpeg, image/webp">
                        <div class="form-text">JPG, PNG, or WEBP — max 2MB.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="add_desc" rows="2" placeholder="Describe this product..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-secondary py-2 mb-0" style="font-size:12px;">
                            <i class="bi bi-boxes me-1"></i>
                            <strong>Note:</strong> Initial stock is <strong>0 pieces</strong>. Use the <strong>📦 Restock</strong> button after adding the product to log your first delivery.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitAdd()"><i class="bi bi-check-lg me-1"></i>Save Product</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================  PROD RESTOCK MODAL  ==========================================================-->
<div class="modal fade" id="prodRestockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:linear-gradient(135deg,#0d6efd,#0a58ca);">
                <h5 class="modal-title fw-bold"><i class="bi bi-boxes me-2"></i>Restock — <span id="prod_restock_title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light-subtle">
                <input type="hidden" id="prod_restock_product_id">
                <!-- Overview -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">CURRENT STOCK</div>
                            <div class="fw-bold fs-5" id="prod_restock_current_stock">0</div>
                            <div class="text-muted" style="font-size:10px;">pieces</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">UNITS/BOX</div>
                            <div class="fw-bold fs-5" id="prod_restock_upb_display">—</div>
                            <div class="text-muted" style="font-size:10px;">pcs/box</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <div class="text-muted" style="font-size:10px;">LAST COST/BOX</div>
                            <div class="fw-bold fs-5 text-primary" id="prod_restock_last_cpb">—</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Boxes Received <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control fw-bold" id="prod_restock_boxes" placeholder="e.g. 5" oninput="onProdRestockCalc()">
                            <span class="input-group-text">boxes</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Units per Box <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control" id="prod_restock_units_per_box" oninput="onProdRestockCalc()">
                            <span class="input-group-text">pcs</span>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Edit only if supplier changed packaging.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Pieces to Add</label>
                        <div class="input-group">
                            <input type="number" class="form-control bg-light fw-bold text-success" id="prod_restock_pieces" readonly>
                            <span class="input-group-text">pcs</span>
                        </div>
                        <small class="text-success fw-semibold" style="font-size:11px;" id="prod_restock_pieces_hint"></small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Box (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="prod_restock_cpb" placeholder="0.00" oninput="onProdRestockCalc()">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Piece (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.0001" class="form-control bg-light" id="prod_restock_cpp" readonly>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Auto: box cost ÷ units per box</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Total Capital Cost (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" class="form-control bg-light fw-bold text-danger" id="prod_restock_total" readonly>
                        </div>
                        <small class="text-muted" style="font-size:11px;">Auto: boxes × cost per box</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price per Piece (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="prod_restock_sell" placeholder="0.00">
                        </div>
                        <small class="text-muted" style="font-size:11px;" id="prod_restock_sell_hint">Auto-suggested at 20% markup — adjust if needed.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supplier / Distributor</label>
                        <input type="text" class="form-control" id="prod_restock_supplier" placeholder="e.g. Uni-President, JTI Philippines">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Delivery Note / DR No. (Optional)</label>
                        <input type="text" class="form-control" id="prod_restock_note" placeholder="e.g. DR-20240727-001">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary fw-semibold" onclick="submitProdRestock()">
                    <i class="bi bi-boxes me-1"></i>Confirm Restock
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================  EDIT MODAL  ==========================================================-->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <input type="hidden" id="edit_existing_image">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_category">
                            <option value="">-- Select Category --</option>
                            <?php foreach($categoriesList as $cat){ ?>
                            <option value="<?= $cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Barcode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_barcode" maxlength="13" inputmode="numeric"
                               onkeydown="blockNonDigitKey(event)" oninput="sanitizeDigitsOnly(this)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Units per Box <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" step="1" class="form-control" id="edit_units_per_box" oninput="onBoxChange('edit')">
                            <span class="input-group-text">pcs/box</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Box (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_cost_per_box" oninput="onBoxChange('edit')">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cost per Piece (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.0001" class="form-control bg-light" id="edit_cost_per_piece" readonly>
                        </div>
                        <div class="form-text">Auto: box cost ÷ units.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price per Piece (₱) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_sell">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" class="form-control" id="edit_image" accept="image/png, image/jpeg, image/webp">
                        <div class="form-text">Leave blank to keep current image.</div>
                        <img id="edit_image_preview" src="" style="display:none;max-height:80px;margin-top:8px;border-radius:6px;">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_desc" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="submitEdit()"><i class="bi bi-check-lg me-1"></i>Update Product</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================  TRASH MODAL  ==========================================================-->
<div class="modal fade" id="trashModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-archive-fill me-2"></i>Product Archive</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if(mysqli_num_rows($trashedProducts) == 0){ ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-archive" style="font-size:48px;"></i>
                        <p class="mt-3 mb-0">Archive is empty</p>
                    </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="trashTable">
                        <thead class="table-danger">
                            <tr><th>#</th><th>Product Name</th><th>Category</th><th>Sell/pc</th><th>Deleted On</th><th>Reason</th><th>Actions</th></tr>
                        </thead>
                        <tbody id="TrashTableBody">
                        <?php $ti=1; while($tr=mysqli_fetch_assoc($trashedProducts)){ ?>
                        <tr>
                            <td><?= $ti++; ?></td>
                            <td><?= htmlspecialchars($tr['product_name']); ?></td>
                            <td><?= htmlspecialchars($tr['category_name'] ?? '—'); ?></td>
                            <td>₱<?= number_format($tr['selling_price'],2); ?></td>
                            <td><?= date("M d, Y h:i A", strtotime($tr['deleted_at'])); ?></td>
                            <td><span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($tr['deleted_reason']); ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-success"
                                    onclick="restoreProduct(<?= $tr['product_id']; ?>,'<?= addslashes($tr['product_name']); ?>')">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!--=========================================================  JAVASCRIPT  ==========================================================-->
<script>
const MARKUP = 0.20;

function blockNonDigitKey(e){
    const ok=['Backspace','Delete','ArrowLeft','ArrowRight','Tab'];
    if(ok.includes(e.key)) return;
    if(!/^\d$/.test(e.key)) e.preventDefault();
}
function sanitizeDigitsOnly(input){
    let v=$(input).val();
    const d=v.replace(/\D/g,'').slice(0,13);
    if(d!==v) $(input).val(d);
}

/*---- Box/Piece Calculators ----*/
function onBoxChange(prefix){
    const units   = parseFloat($('#'+prefix+'_units_per_box').val()) || 0;
    const boxCost = parseFloat($('#'+prefix+'_cost_per_box').val())  || 0;
    if(units>0 && boxCost>0){
        const cpp  = boxCost/units;
        const sell = cpp*(1+MARKUP);
        $('#'+prefix+'_cost_per_piece').val(cpp.toFixed(4));
        $('#'+prefix+'_sell').val(sell.toFixed(2));
        if(prefix==='add'){
            $('#add_markup_hint').text('Auto 20% markup on ₱'+cpp.toFixed(4)+'/pc = ₱'+sell.toFixed(2)+' — you may adjust.');
        }
    } else {
        $('#'+prefix+'_cost_per_piece').val('');
        if(prefix==='add') $('#'+prefix+'_sell').val('');
    }
}

/*---- Restock Calculator ----*/
function onProdRestockCalc(){
    const boxes = parseInt($('#prod_restock_boxes').val())  || 0;
    const units = parseInt($('#prod_restock_units_per_box').val()) || 0;
    const cpb   = parseFloat($('#prod_restock_cpb').val())  || 0;

    const pieces    = boxes * units;
    const total     = boxes * cpb;
    const cpp       = units>0 && cpb>0 ? cpb/units : 0;
    const sugSell   = cpp>0 ? cpp*(1+MARKUP) : 0;

    $('#prod_restock_pieces').val(pieces>0 ? pieces : '');
    $('#prod_restock_total').val(total>0 ? total.toFixed(2) : '');
    $('#prod_restock_cpp').val(cpp>0 ? cpp.toFixed(4) : '');

    if(pieces>0){
        const cur = parseInt($('#prod_restock_current_stock').text()) || 0;
        $('#prod_restock_pieces_hint').text(boxes+' boxes × '+units+' pcs = '+pieces+' pcs → New total: '+(cur+pieces)+' pcs');
    } else {
        $('#prod_restock_pieces_hint').text('');
    }
    if(sugSell>0){
        const existing = parseFloat($('#prod_restock_sell').val()) || 0;
        if(existing===0) $('#prod_restock_sell').val(sugSell.toFixed(2));
        $('#prod_restock_sell_hint').text('Suggested 20% markup: ₱'+sugSell.toFixed(2)+' — adjust if needed.');
    }
}

/*---- OPEN MODALS ----*/
function openAddModal(){
    $('#add_name,#add_barcode,#add_desc,#add_sell,#add_cost_per_box,#add_cost_per_piece,#add_units_per_box').val('');
    $('#add_category').val('');
    $('#add_status').val('Available');
    $('#add_image').val('');
    $('#add_markup_hint').text('Auto 20% markup — you may adjust.');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function openEditModal(p){
    $('#edit_id').val(p.product_id);
    $('#edit_name').val(p.product_name);
    $('#edit_category').val(p.category_id);
    $('#edit_barcode').val(p.barcode);
    $('#edit_desc').val(p.description);
    $('#edit_units_per_box').val(p.units_per_box);
    $('#edit_cost_per_box').val(parseFloat(p.cost_per_box).toFixed(2));
    $('#edit_cost_per_piece').val(parseFloat(p.cost_price).toFixed(4));
    $('#edit_sell').val(parseFloat(p.selling_price).toFixed(2));
    $('#edit_status').val(p.status);
    $('#edit_existing_image').val(p.image||'');
    $('#edit_image').val('');
    if(p.image){ $('#edit_image_preview').attr('src','uploads/products/'+p.image).show(); }
    else { $('#edit_image_preview').hide(); }
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openProdRestockModal(p){
    $('#prod_restock_product_id').val(p.product_id);
    $('#prod_restock_title').text(p.product_name);
    $('#prod_restock_current_stock').text(parseInt(p.stock_qty)||0);
    $('#prod_restock_upb_display').text((p.units_per_box || 1)+' pcs');
    $('#prod_restock_last_cpb').text('₱'+parseFloat(p.cost_per_box || 0).toFixed(2));
    $('#prod_restock_boxes').val('');
    $('#prod_restock_units_per_box').val(p.units_per_box || 1);
    $('#prod_restock_cpb').val(parseFloat(p.cost_per_box || 0).toFixed(2));
    $('#prod_restock_pieces,#prod_restock_cpp,#prod_restock_total').val('');
    $('#prod_restock_sell').val(parseFloat(p.selling_price || 0).toFixed(2));
    $('#prod_restock_supplier,#prod_restock_note').val('');
    $('#prod_restock_pieces_hint').text('');
    new bootstrap.Modal(document.getElementById('prodRestockModal')).show();
}

/*---- SUBMIT ADD ----*/
function submitAdd(){
    const name    = $('#add_name').val().trim();
    const cat     = $('#add_category').val();
    const barcode = $('#add_barcode').val().trim();
    const desc    = $('#add_desc').val().trim();
    const units   = parseInt($('#add_units_per_box').val());
    const cpb     = parseFloat($('#add_cost_per_box').val());
    const sell    = parseFloat($('#add_sell').val());
    const status  = $('#add_status').val();

    if(!name||!cat||!barcode||!desc){ Swal.fire('Missing Fields','Please fill in all required fields.','warning'); return; }
    if(!/^\d{13}$/.test(barcode)){ Swal.fire('Invalid Barcode','Barcode must be exactly 13 digits.','warning'); return; }
    if(isNaN(units)||units<1){ Swal.fire('Invalid Units','Units per box must be at least 1.','warning'); return; }
    if(isNaN(cpb)||cpb<=0){ Swal.fire('Invalid Cost','Cost per box must be greater than zero.','warning'); return; }
    if(isNaN(sell)||sell<=0){ Swal.fire('Invalid Price','Selling price must be greater than zero.','warning'); return; }
    const imgFile = $('#add_image')[0].files[0];
    if(!imgFile){ Swal.fire('Image Required','Please upload a product image.','warning'); return; }

    const fd = new FormData();
    fd.append('action','create'); fd.append('product_name',name); fd.append('category_id',cat);
    fd.append('barcode',barcode); fd.append('description',desc); fd.append('units_per_box',units);
    fd.append('cost_per_box',cpb); fd.append('selling_price',sell); fd.append('status',status); fd.append('image',imgFile);

    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
    setTimeout(()=>{
        askPassword('add this product').then(ok=>{
            if(!ok) return;
            $.ajax({ url:'products.php', type:'POST', data:fd, contentType:false, processData:false,
                success:function(r){
                    if(r.trim()==='success'){
                        Swal.fire({ icon:'success', title:'Product Added!',
                            text:'Use the 📦 Restock button to log your first delivery.',
                            confirmButtonText:'OK'
                        }).then(()=>{ clearBackdrop(); loadPage('products.php'); });
                    } else { Swal.fire('Error',r.replace('error:','').trim(),'error'); }
                }
            });
        });
    },400);
}

/*---- SUBMIT RESTOCK ----*/
function submitProdRestock(){
    const pid   = $('#prod_restock_product_id').val();
    const boxes = parseInt($('#prod_restock_boxes').val());
    const units = parseInt($('#prod_restock_units_per_box').val());
    const cpb   = parseFloat($('#prod_restock_cpb').val());
    const sell  = parseFloat($('#prod_restock_sell').val());
    const sup   = $('#prod_restock_supplier').val().trim();
    const note  = $('#prod_restock_note').val().trim();
    const pname = $('#prod_restock_title').text();

    if(!pid){ Swal.fire('Error','Product ID is missing.','error'); return; }
    if(!boxes||boxes<1){ Swal.fire('Required','Enter number of boxes received.','warning'); return; }
    if(!units||units<1){ Swal.fire('Required','Units per box must be at least 1.','warning'); return; }
    if(isNaN(cpb)||cpb<=0){ Swal.fire('Required','Cost per box must be greater than zero.','warning'); return; }
    if(isNaN(sell)||sell<=0){ Swal.fire('Required','Selling price must be greater than zero.','warning'); return; }

    const pieces = boxes*units;
    const total  = (boxes*cpb).toFixed(2);

    Swal.fire({
        title:'Confirm Restock',
        html:`<div class="text-start" style="font-size:13.5px;">
            <strong>${pname}</strong><br>
            📦 <strong>${boxes}</strong> box(es) × <strong>${units}</strong> pcs = <strong>${pieces} pcs</strong> added<br>
            💰 Total Capital: <strong>₱${total}</strong><br>
            🏷️ New Sell Price: <strong>₱${sell.toFixed(2)}</strong>/pc
        </div>`,
        icon:'question', showCancelButton:true,
        confirmButtonColor:'#0d6efd', confirmButtonText:'Confirm'
    }).then(res=>{
        if(!res.isConfirmed) return;
        bootstrap.Modal.getInstance(document.getElementById('prodRestockModal')).hide();
        setTimeout(()=>{
        askPassword('restock this product').then(ok=>{
            if(!ok) return;
            $.post('products.php',{
                action:'restock', product_id:pid, boxes_received:boxes,
                units_per_box:units, cost_per_box:cpb, selling_price:sell,
                supplier:sup, delivery_note:note
            },function(r){
                if(r.trim()==='success'){
                    Swal.fire({ icon:'success', title:'Restocked!', showConfirmButton:false, timer:1500 })
                    .then(()=>{ clearBackdrop(); loadPage('products.php'); });
                } else { Swal.fire('Error',r.replace('error:','').trim(),'error'); }
            });
        });
        },400);
    });
}

/*---- SUBMIT EDIT ----*/
function submitEdit(){
    const name  = $('#edit_name').val().trim();
    const cat   = $('#edit_category').val();
    const bc    = $('#edit_barcode').val().trim();
    const desc  = $('#edit_desc').val().trim();
    const units = parseInt($('#edit_units_per_box').val());
    const cpb   = parseFloat($('#edit_cost_per_box').val());
    const sell  = parseFloat($('#edit_sell').val());

    if(!name||!cat||!bc||!desc){ Swal.fire('Missing Fields','Please fill in all required fields.','warning'); return; }
    if(!/^\d{13}$/.test(bc)){ Swal.fire('Invalid Barcode','Barcode must be exactly 13 digits.','warning'); return; }
    if(isNaN(units)||units<1){ Swal.fire('Invalid Units','Units per box must be at least 1.','warning'); return; }
    if(isNaN(cpb)||cpb<=0){ Swal.fire('Invalid Cost','Cost per box must be greater than zero.','warning'); return; }
    if(isNaN(sell)||sell<=0){ Swal.fire('Invalid Price','Selling price must be greater than zero.','warning'); return; }

    Swal.fire({
        title:'Reason for Update', input:'text',
        inputPlaceholder:'e.g. Price adjustment, wrong category...',
        showCancelButton:true, confirmButtonColor:'#ffc107', confirmButtonText:'Confirm Update',
        inputValidator:(v)=>{ if(!v||!v.trim()) return 'Please provide a reason.'; }
    }).then(res=>{
        if(!res.isConfirmed) return;
        const reason=res.value;
        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
        setTimeout(()=>{
        askPassword('update this product').then(ok=>{
            if(!ok) return;
            const fd=new FormData();
            fd.append('action','update'); fd.append('product_id',$('#edit_id').val());
            fd.append('product_name',name); fd.append('category_id',cat); fd.append('barcode',bc);
            fd.append('description',desc); fd.append('units_per_box',units); fd.append('cost_per_box',cpb);
            fd.append('selling_price',sell); fd.append('status',$('#edit_status').val());
            fd.append('reason',reason); fd.append('existing_image',$('#edit_existing_image').val());
            const img=$('#edit_image')[0].files[0];
            if(img) fd.append('image',img);
            $.ajax({ url:'products.php', type:'POST', data:fd, contentType:false, processData:false,
                success:function(r){
                    if(r.trim()==='success'){
                        Swal.fire({ icon:'success', title:'Product Updated!', showConfirmButton:false, timer:1500 })
                        .then(()=>{ clearBackdrop(); loadPage('products.php'); });
                    } else { Swal.fire('Error',r.replace('error:','').trim(),'error'); }
                }
            });
        });
        },400);
    });
}

/*---- DELETE / RESTORE ----*/
function deleteProduct(id,name){
    setTimeout(()=>{
    Swal.fire({
        title:'Archive '+name+'?',
        html:'<input id="delR" class="swal2-input" placeholder="Reason e.g. Expired, Discontinued...">',
        icon:'warning', showCancelButton:true, confirmButtonColor:'#dc3545', confirmButtonText:'Yes, Archive',
        preConfirm:()=>{ const r=document.getElementById('delR').value.trim(); if(!r){ Swal.showValidationMessage('Please provide a reason.'); return false; } return r; }
    }).then(res=>{
        if(!res.isConfirmed) return;
        askPassword('archive this product').then(ok=>{
            if(!ok) return;
            $.post('products.php',{ action:'delete', product_id:id, reason:res.value },function(r){
                if(r.trim()==='success'){
                    Swal.fire({ icon:'success', title:'Archived!', showConfirmButton:false, timer:1500 })
                    .then(()=>{ clearBackdrop(); loadPage('products.php'); });
                } else { Swal.fire('Error',r.replace('error:','').trim(),'error'); }
            });
        });
    });
    },50);
}

function restoreProduct(id,name){
    document.body.classList.add('swal-on-top');
    bootstrap.Modal.getInstance(document.getElementById('trashModal')).hide();
    setTimeout(()=>{
    Swal.fire({
        title:'Restore "'+name+'"?',
        html:'<input id="resR" class="swal2-input" placeholder="e.g. Back in stock, Re-listed...">',
        showCancelButton:true, confirmButtonColor:'#198754', confirmButtonText:'Next',
        preConfirm:()=>{ const r=document.getElementById('resR').value.trim(); if(!r){ Swal.showValidationMessage('Please provide a reason.'); return false; } return r; }
    }).then(rr=>{
        if(!rr.isConfirmed){ document.body.classList.remove('swal-on-top'); return; }
        askPassword('restore this product').then(ok=>{
            if(!ok){ document.body.classList.remove('swal-on-top'); return; }
            $.post('products.php',{ action:'restore', product_id:id, reason:rr.value },function(r){
                document.body.classList.remove('swal-on-top');
                if(r.trim()==='success'){
                    Swal.fire({ icon:'success', title:'Restored!', showConfirmButton:false, timer:1500 })
                    .then(()=>{ clearBackdrop(); loadPage('products.php'); refreshTrashModal(); });
                } else { Swal.fire('Error',r.replace('error:','').trim(),'error'); }
            });
        });
    });
    },400);
}

function openTrashModal(){ new bootstrap.Modal(document.getElementById('trashModal')).show(); }

function askPassword(label){
    return Swal.fire({
        title:'<i class="bi bi-shield-lock-fill text-success me-2"></i>Confirm Your Identity',
        html:`<p class="text-muted mb-3" style="font-size:14px;">Enter your password to <strong>${label}</strong>.</p>
              <input type="password" id="swal_pw" class="swal2-input" placeholder="Your password" autocomplete="current-password">`,
        showCancelButton:true, confirmButtonColor:'#198754', confirmButtonText:'<i class="bi bi-unlock-fill me-1"></i>Verify & Proceed',
        focusConfirm:false,
        didOpen:()=>{ document.getElementById('swal_pw').addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); Swal.getConfirmButton().click(); } }); },
        preConfirm:()=>{
            const pw=document.getElementById('swal_pw').value;
            if(!pw){ Swal.showValidationMessage('Password is required.'); return false; }
            return $.post('verify_password.php',{ password:pw })
                .then(r=>{ if(r.trim()!=='success'){ Swal.showValidationMessage(r.replace('error: ','')||'Incorrect password.'); return false; } return true; })
                .catch(()=>{ Swal.showValidationMessage('Could not verify password.'); return false; });
        }
    }).then(r=>r.isConfirmed===true);
}

function refreshTrashModal(){
    $.get('products.php',function(html){
        const doc = new DOMParser().parseFromString(html,'text/html');
        const b = doc.getElementById('TrashTableBody'), bd = doc.getElementById('trashBadge');
        if(b) document.getElementById('TrashTableBody').innerHTML = b.innerHTML;
        if(bd && document.getElementById('trashBadge')) document.getElementById('trashBadge').innerHTML = bd.innerHTML;
    });
}
</script>