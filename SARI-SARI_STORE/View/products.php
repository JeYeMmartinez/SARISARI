<?php
require_once __DIR__ . '/../Model/database.php';
require_once __DIR__ . '/../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){ session_start(); }
$current_user = $_SESSION['user_id'] ?? 1;

if(!defined('PRODUCT_UPLOAD_DIR')) define('PRODUCT_UPLOAD_DIR', __DIR__ . '/uploads/products/');
if(!defined('PRODUCT_UPLOAD_URL')) define('PRODUCT_UPLOAD_URL', 'uploads/products/');
if(!defined('DEFAULT_MARKUP')) define('DEFAULT_MARKUP', 0.20); // 20% retail markup on cost_per_piece

if(!is_dir(PRODUCT_UPLOAD_DIR)){
    mkdir(PRODUCT_UPLOAD_DIR, 0755, true);
}

function handleProductImageUpload($file, &$error){
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize     = 2 * 1024 * 1024;
    if($file['error'] !== UPLOAD_ERR_OK){ $error = 'Image upload failed.'; return false; }
    if($file['size'] > $maxSize){ $error = 'Image must be smaller than 2MB.'; return false; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowedExt)){ $error = 'Only JPG, PNG, or WEBP images are allowed.'; return false; }
    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($mime, $allowedMime)){ $error = 'Invalid image file.'; return false; }
    $newName = 'prod_' . uniqid() . '.' . $ext;
    if(!move_uploaded_file($file['tmp_name'], PRODUCT_UPLOAD_DIR . $newName)){ $error = 'Could not save image.'; return false; }
    return $newName;
}

/*=========================================================
    ACTIONS (POST)
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $name          = mysqli_real_escape_string($conn, trim($_POST['product_name']));
    $category      = (int)$_POST['category_id'];
    $barcode       = mysqli_real_escape_string($conn, trim($_POST['barcode'] ?? ''));
    $desc          = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $units_per_box = max(1, (int)($_POST['units_per_box'] ?? 1));
    $cost_per_box  = (float)$_POST['cost_per_box'];
    $cost_per_piece= $units_per_box > 0 ? round($cost_per_box / $units_per_box, 4) : 0;
    $sell          = (float)$_POST['selling_price'];
    $status        = $_POST['status'] ?? 'Available';

    if($barcode === '' || !preg_match('/^\d{13}$/', $barcode)){
        echo 'error: Barcode must be exactly 13 digits.'; exit();
    }
    $dup = mysqli_query($conn, "SELECT product_id FROM products WHERE barcode='$barcode' AND deleted_at IS NULL");
    if($dup && mysqli_num_rows($dup) > 0){ echo 'error: Barcode already in use.'; exit(); }
    if($desc === ''){ echo 'error: Description is required.'; exit(); }
    if($cost_per_box <= 0){ echo 'error: Cost per box must be greater than zero.'; exit(); }
    if($sell <= 0){ $sell = round($cost_per_piece * (1 + DEFAULT_MARKUP), 2); }

    if(!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE){
        echo 'error: A product image is required.'; exit();
    }
    $uploadError = '';
    $imageName = handleProductImageUpload($_FILES['image'], $uploadError);
    if($imageName === false){ echo 'error: ' . $uploadError; exit(); }

    $q = mysqli_query($conn,"
        INSERT INTO products
            (category_id, product_name, barcode, description,
             selling_price, cost_price, units_per_box, cost_per_box,
             image, status, added_by)
        VALUES
            ($category, '$name', '$barcode', '$desc',
             $sell, $cost_per_piece, $units_per_box, $cost_per_box,
             '$imageName', '$status', $current_user)
    ");

    if($q){
        $pid = mysqli_insert_id($conn);
        mysqli_query($conn,"INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($pid, 0, 5, NULL)");
        logAction($conn, $current_user, 'Create', 'products', $pid, "Added product: $name (units/box: $units_per_box, cost/box: P$cost_per_box)");
        mysqli_query($conn,"INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Added','New product: $name','Products',0)");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// RESTOCK (FROM PRODUCTS PAGE)
if(isset($_POST['action']) && $_POST['action'] == 'restock'){
    $product_id    = (int)($_POST['product_id'] ?? 0);
    $boxes         = max(1, (int)($_POST['boxes_received'] ?? 0));
    $units_per_box = max(1, (int)($_POST['units_per_box'] ?? 1));
    $cost_per_box  = (float)($_POST['cost_per_box'] ?? 0);
    $new_sell      = (float)($_POST['selling_price'] ?? 0);
    $supplier      = mysqli_real_escape_string($conn, trim($_POST['supplier'] ?? ''));
    $note          = mysqli_real_escape_string($conn, trim($_POST['delivery_note'] ?? ''));

    if(!$product_id){ echo 'error: Product ID is missing.'; exit(); }
    if($boxes < 1){ echo 'error: Boxes received must be at least 1.'; exit(); }
    if($cost_per_box <= 0){ echo 'error: Cost per box must be greater than zero.'; exit(); }
    if($new_sell <= 0){ echo 'error: Selling price must be greater than zero.'; exit(); }

    $pieces_added      = $boxes * $units_per_box;
    $total_cost        = round($boxes * $cost_per_box, 2);
    $new_cost_per_piece= round($cost_per_box / $units_per_box, 4);
    $sup_sql           = $supplier !== '' ? "'$supplier'" : "NULL";
    $note_sql          = $note !== '' ? "'$note'" : "NULL";

    mysqli_query($conn,"
        INSERT INTO restock_logs
            (product_id, boxes_received, units_per_box, pieces_added,
             cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
             supplier, delivery_note, restocked_by)
        VALUES
            ($product_id, $boxes, $units_per_box, $pieces_added,
             $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell,
             $sup_sql, $note_sql, $current_user)
    ");

    $inv = mysqli_query($conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id LIMIT 1");
    if($inv && mysqli_num_rows($inv) > 0){
        mysqli_query($conn,"UPDATE inventory SET quantity = quantity + $pieces_added, last_restock = NOW() WHERE product_id = $product_id");
    } else {
        mysqli_query($conn,"INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($product_id, $pieces_added, 5, NOW())");
    }

    mysqli_query($conn,"
        UPDATE products SET
            cost_price    = $new_cost_per_piece,
            cost_per_box  = $cost_per_box,
            units_per_box = $units_per_box,
            selling_price = $new_sell,
            status        = 'Available'
        WHERE product_id = $product_id
    ");

    $prow  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_name FROM products WHERE product_id = $product_id"));
    $pname = $prow['product_name'] ?? 'Unknown';
    logAction($conn, $current_user, 'Restock', 'products', $product_id,
        "Restocked '$pname': $boxes box(es) x $units_per_box pcs = $pieces_added pcs. Total: P$total_cost");
    mysqli_query($conn,"INSERT INTO notifications (title, message, type, is_read) VALUES ('Restocked','$pname: +$pieces_added pcs','Products',0)");

    ob_clean();
    echo 'success';
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $id            = (int)$_POST['product_id'];
    $name          = mysqli_real_escape_string($conn, trim($_POST['product_name']));
    $category      = (int)$_POST['category_id'];
    $barcode       = mysqli_real_escape_string($conn, trim($_POST['barcode'] ?? ''));
    $desc          = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $units_per_box = max(1, (int)($_POST['units_per_box'] ?? 1));
    $cost_per_box  = (float)$_POST['cost_per_box'];
    $cost_per_piece= $units_per_box > 0 ? round($cost_per_box / $units_per_box, 4) : 0;
    $sell          = (float)$_POST['selling_price'];
    $status        = $_POST['status'];
    $reason        = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));

    if($barcode === '' || !preg_match('/^\d{13}$/', $barcode)){ echo 'error: Barcode must be exactly 13 digits.'; exit(); }
    $dup = mysqli_query($conn,"SELECT product_id FROM products WHERE barcode='$barcode' AND product_id != $id AND deleted_at IS NULL");
    if($dup && mysqli_num_rows($dup) > 0){ echo 'error: Barcode already in use.'; exit(); }
    if($desc === ''){ echo 'error: Description is required.'; exit(); }
    if($cost_per_box <= 0){ echo 'error: Cost per box must be greater than zero.'; exit(); }
    if($reason === ''){ echo 'error: A reason is required to update this product.'; exit(); }
    if($sell <= 0){ $sell = round($cost_per_piece * (1 + DEFAULT_MARKUP), 2); }

    $existingImage = mysqli_real_escape_string($conn, $_POST['existing_image'] ?? '');
    $imageName = $existingImage;
    if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE){
        $uploadError = '';
        $newImg = handleProductImageUpload($_FILES['image'], $uploadError);
        if($newImg === false){ echo 'error: ' . $uploadError; exit(); }
        if($existingImage !== '' && file_exists(PRODUCT_UPLOAD_DIR . $existingImage)){ @unlink(PRODUCT_UPLOAD_DIR . $existingImage); }
        $imageName = $newImg;
    }
    $imgSql = $imageName !== '' ? "'$imageName'" : "NULL";

    $q = mysqli_query($conn,"
        UPDATE products SET
            category_id   = $category,
            product_name  = '$name',
            barcode       = '$barcode',
            description   = '$desc',
            selling_price = $sell,
            cost_price    = $cost_per_piece,
            units_per_box = $units_per_box,
            cost_per_box  = $cost_per_box,
            image         = $imgSql,
            status        = '$status'
        WHERE product_id = $id
    ");

    if($q){
        logAction($conn, $current_user, 'Update', 'products', $id, "Updated product '$name' - Reason: $reason");
        mysqli_query($conn,"INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Updated','Updated: $name','Products',0)");
        echo 'success';
    } else { echo 'error: ' . mysqli_error($conn); }
    exit();
}

// SOFT DELETE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['product_id'];
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
    if($reason === ''){ echo 'error: A reason is required.'; exit(); }
    $nameRow = mysqli_fetch_assoc(mysqli_query($conn,"SELECT product_name FROM products WHERE product_id=$id"));
    $name = $nameRow ? $nameRow['product_name'] : 'Unknown';
    $q = mysqli_query($conn,"UPDATE products SET deleted_at=NOW(), deleted_reason='$reason', status='Unavailable' WHERE product_id=$id");
    if($q){
        logAction($conn, $current_user, 'Trash', 'products', $id, "Archived '$name' - Reason: $reason");
        mysqli_query($conn,"INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Archived','Archived: $name','Products',0)");
        echo 'success';
    } else { echo 'error: ' . mysqli_error($conn); }
    exit();
}

// RESTORE
if(isset($_POST['action']) && $_POST['action'] == 'restore'){
    $id = (int)$_POST['product_id'];
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));
    if($reason === ''){ echo 'error: A reason is required.'; exit(); }
    $q = mysqli_query($conn,"UPDATE products SET deleted_at=NULL, deleted_reason=NULL WHERE product_id=$id");
    if($q){
        $nameRow = mysqli_fetch_assoc(mysqli_query($conn,"SELECT product_name FROM products WHERE product_id=$id"));
        $name = $nameRow ? $nameRow['product_name'] : 'Unknown';
        logAction($conn, $current_user, 'Restore', 'products', $id, "Restored product '$name' - Reason: $reason");
        mysqli_query($conn,"INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Restored','Restored: $name','Products',0)");
        echo 'success';
    } else { echo 'error: ' . mysqli_error($conn); }
    exit();
}

// GET RESTOCK HISTORY (AJAX)
if(isset($_GET['action']) && $_GET['action'] == 'get_restock_logs'){
    ob_clean();
    $id = (int)($_GET['product_id'] ?? 0);
    if(!$id){ echo json_encode([]); exit(); }
    $res = mysqli_query($conn,"
        SELECT r.*, u.full_name AS restocked_by_name
        FROM restock_logs r
        LEFT JOIN users u ON r.restocked_by = u.user_id
        WHERE r.product_id = $id
        ORDER BY r.restocked_at DESC
        LIMIT 20
    ");
    $rows = [];
    while($row = mysqli_fetch_assoc($res)){ $rows[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/
$products = mysqli_query($conn,"
    SELECT p.*, c.category_name,
           COALESCE(i.quantity, 0) AS stock_qty,
           i.minimum_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory  i ON i.product_id  = p.product_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
");

$trashCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NOT NULL"))['total'];

$trashedProducts = mysqli_query($conn,"
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NOT NULL
    ORDER BY p.deleted_at DESC
");

$categories = mysqli_query($conn,"SELECT * FROM categories ORDER BY category_name ASC");
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
                <th>Stock (pcs)</th><th>Actions</th>
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
                        <div class="input-group">
                            <input type="text" class="form-control" id="add_barcode" placeholder="13-digit barcode"
                                   maxlength="13" inputmode="numeric"
                                   onkeydown="blockNonDigitKey(event)" oninput="sanitizeDigitsOnly(this)">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomBarcode('add_barcode')">
                                <i class="bi bi-dice-5 me-1"></i>Generate
                            </button>
                        </div>
                        <div class="form-text">Must be exactly 13 digits and unique.</div>
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

/*---- BARCODE GENERATOR ----*/
function generateRandomBarcode(targetId = 'add_barcode'){
    // Generate valid EAN-13 style numeric barcode starting with 480 (Philippines country prefix)
    let code = '480' + Math.floor(100000000 + Math.random() * 900000000);
    // EAN-13 checksum calculation
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    let checkDigit = (10 - (sum % 10)) % 10;
    code += checkDigit;

    if (targetId && $('#' + targetId).length) {
        $('#' + targetId).val(code);
    }
    return code;
}
window.generateRandomBarcode = generateRandomBarcode;

/*---- OPEN MODALS ----*/
function openAddModal(){
    $('#add_name,#add_barcode,#add_desc,#add_sell,#add_cost_per_box,#add_cost_per_piece,#add_units_per_box').val('');
    $('#add_category').val('');
    $('#add_image').val('');
    generateRandomBarcode('add_barcode');
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
    fd.append('cost_per_box',cpb); fd.append('selling_price',sell); fd.append('status','Available'); fd.append('image',imgFile);

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