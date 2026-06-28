<?php
require_once '../Model/database.php';

require_once '../Model/logger.php';
$current_user = 1; // replace with $_SESSION['user_id'] later

/*=========================================================
    IMAGE UPLOAD HELPER
==========================================================*/
define('PRODUCT_UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('PRODUCT_UPLOAD_URL', 'uploads/products/');
define('MIN_INITIAL_STOCK', 1); // minimum stock required when a product is marked Available
define('DEFAULT_MARKUP', 0.25); // 25% real-world markup — fallback if JS didn't compute selling price

if(!is_dir(PRODUCT_UPLOAD_DIR)){
    mkdir(PRODUCT_UPLOAD_DIR, 0755, true);
}

function handleProductImageUpload($file, &$error){
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize     = 2 * 1024 * 1024; // 2MB

    if($file['error'] !== UPLOAD_ERR_OK){
        $error = 'Image upload failed. Please try again.';
        return false;
    }
    if($file['size'] > $maxSize){
        $error = 'Image must be smaller than 2MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowedExt)){
        $error = 'Only JPG, PNG, or WEBP images are allowed.';
        return false;
    }

    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($mime, $allowedMime)){
        $error = 'Invalid image file.';
        return false;
    }

    $newName = 'prod_' . uniqid() . '.' . $ext;
    if(!move_uploaded_file($file['tmp_name'], PRODUCT_UPLOAD_DIR . $newName)){
        $error = 'Could not save the uploaded image.';
        return false;
    }
    return $newName;
}

/*=========================================================
    CRUD ACTIONS
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $name          = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category      = (int)$_POST['category_id'];
    $barcode       = mysqli_real_escape_string($conn, $_POST['barcode']);
    $desc          = mysqli_real_escape_string($conn, $_POST['description']);
    $sell          = (float)$_POST['selling_price'];
    $cost          = (float)$_POST['cost_price'];
    $status        = $_POST['status'];
    $initial_stock = (int)($_POST['initial_stock'] ?? 0);
    $added_by      = 1; // replace with $_SESSION['user_id'] once login is done

    if(strlen($_POST['barcode'] ?? '') > 13){
        echo 'error: Barcode must be at most 13 characters.';
        exit();
    }

    if($cost < 0){
        echo 'error: Cost price cannot be negative.';
        exit();
    }

    // Selling price is computed client-side from cost price; this is just a safety net
    if($sell <= 0){
        $sell = round($cost + ($cost * DEFAULT_MARKUP), 2);
    }

    if($initial_stock < 0){
        $initial_stock = 0;
    }

    if($status === 'Available' && $initial_stock < MIN_INITIAL_STOCK){
        echo 'error: Initial stock must be at least ' . MIN_INITIAL_STOCK . ' unit(s) when status is Available.';
        exit();
    }

    $imageName = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE){
        $uploadError = '';
        $imageName = handleProductImageUpload($_FILES['image'], $uploadError);
        if($imageName === false){
            echo 'error: ' . $uploadError;
            exit();
        }
    }
    $imageSql = $imageName !== '' ? "'$imageName'" : "NULL";

    $query = mysqli_query($conn,"
        INSERT INTO products (category_id, product_name, barcode, description, selling_price, cost_price, image, status, added_by)
        VALUES ($category, '$name', '$barcode', '$desc', $sell, $cost, $imageSql, '$status', $added_by)
    ");

    if($query) {
        $new_product_id = mysqli_insert_id($conn);

        // Create the matching inventory record using the initial stock entered above
        $lastRestock = $initial_stock > 0 ? "NOW()" : "NULL";
        mysqli_query($conn,"
            INSERT INTO inventory (product_id, quantity, minimum_stock, maximum_Stock, aisle, last_restock)
            VALUES ($new_product_id, $initial_stock, 5, NULL, NULL, $lastRestock)
        ");

        logAction($conn, $current_user, 'Create', 'products', $new_product_id,
            "Added product: $name (initial stock: $initial_stock)");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Product Added', 'New product added: $name', 'Products', 0)
        ");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $id       = (int)$_POST['product_id'];
    $name     = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category = (int)$_POST['category_id'];
    $barcode  = mysqli_real_escape_string($conn, $_POST['barcode']);
    $desc     = mysqli_real_escape_string($conn, $_POST['description']);
    $sell     = (float)$_POST['selling_price'];
    $cost     = (float)$_POST['cost_price'];
    $status   = $_POST['status'];
    $reason   = trim($_POST['reason'] ?? '');

    if(strlen($_POST['barcode'] ?? '') > 13){
        echo 'error: Barcode must be at most 13 characters.';
        exit();
    }

    if($cost < 0){
        echo 'error: Cost price cannot be negative.';
        exit();
    }

    // Selling price is computed client-side from cost price; this is just a safety net
    if($sell <= 0){
        $sell = round($cost + ($cost * DEFAULT_MARKUP), 2);
    }

    if($reason === ''){
        echo 'error: A reason is required to update this product.';
        exit();
    }
    $reason = mysqli_real_escape_string($conn, $reason);

    $existingImage = mysqli_real_escape_string($conn, $_POST['existing_image'] ?? '');
    $imageName     = $existingImage;

    if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE){
        $uploadError = '';
        $newImage = handleProductImageUpload($_FILES['image'], $uploadError);
        if($newImage === false){
            echo 'error: ' . $uploadError;
            exit();
        }
        if($existingImage !== '' && file_exists(PRODUCT_UPLOAD_DIR . $existingImage)){
            @unlink(PRODUCT_UPLOAD_DIR . $existingImage);
        }
        $imageName = $newImage;
    }
    $imageSql = $imageName !== '' ? "'$imageName'" : "NULL";

    $query = mysqli_query($conn,"
        UPDATE products SET
            category_id   = $category,
            product_name  = '$name',
            barcode       = '$barcode',
            description   = '$desc',
            selling_price = $sell,
            cost_price    = $cost,
            image         = $imageSql,
            status        = '$status'
        WHERE product_id = $id
    ");

    if($query) {
        logAction($conn, $current_user, 'Update', 'products', $id,
            "Updated product '$name' — Reason: $reason");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Product Updated', 'Product updated: $name', 'Products', 0)
        ");
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// SOFT DELETE — move to trash
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id     = (int)$_POST['product_id'];
    $reason = trim($_POST['reason'] ?? '');

    if($reason === ''){
        echo 'error: A reason is required to delete this product.';
        exit();
    }
    $reason = mysqli_real_escape_string($conn, $reason);

    $nameQuery = mysqli_query($conn, "SELECT product_name FROM products WHERE product_id = $id");
    $nameRow   = $nameQuery ? mysqli_fetch_assoc($nameQuery) : null;
    $name      = $nameRow ? $nameRow['product_name'] : 'Unknown';

    $query = mysqli_query($conn,"
        UPDATE products SET
            deleted_at     = NOW(),
            deleted_reason = '$reason',
            status         = 'Unavailable'
        WHERE product_id = $id
    ");

    if($query){
        logAction($conn, $current_user, 'Trash', 'products', $id,
            "Moved to trash: '$name' — Reason: $reason");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Product Deleted', 'Product moved to trash: $name', 'Products', 0)
        ");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// RESTORE — bring back from trash
if(isset($_POST['action']) && $_POST['action'] == 'restore'){
    $id     = (int)$_POST['product_id'];
    $reason = trim($_POST['reason'] ?? '');

    if($reason === ''){
        echo 'error: A reason is required to restore this product.';
        exit();
    }
    $reason = mysqli_real_escape_string($conn, $reason);

    $query = mysqli_query($conn,"
        UPDATE products SET
            deleted_at     = NULL,
            deleted_reason = NULL
        WHERE product_id = $id
    ");

    if($query){
        $nameRow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT product_name FROM products WHERE product_id = $id"));
        $name = $nameRow ? $nameRow['product_name'] : 'Unknown';
        logAction($conn, $current_user, 'Restore', 'products', $id,
            "Restored product '$name' — Reason: $reason");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Product Restored', 'Product restored: $name', 'Products', 0)
        ");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// PERMANENT DELETE — from trash only
if(isset($_POST['action']) && $_POST['action'] == 'permanent_delete'){
    $id     = (int)$_POST['product_id'];
    $reason = trim($_POST['reason'] ?? '');

    if($reason === ''){
        echo 'error: A reason is required.';
        exit();
    }
    $reason = mysqli_real_escape_string($conn, $reason);

    $nameRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT product_name FROM products WHERE product_id = $id"));
    $name = $nameRow ? $nameRow['product_name'] : 'Unknown';

    mysqli_query($conn, "DELETE FROM sale_items WHERE product_id = $id");
    mysqli_query($conn, "DELETE FROM cart_items WHERE product_id = $id");
    mysqli_query($conn, "DELETE FROM inventory WHERE product_id = $id");

    $query = mysqli_query($conn, "DELETE FROM products WHERE product_id = $id");
    if($query){
        logAction($conn, $current_user, 'Permanent Delete', 'products', $id,
            "Permanently deleted '$name' — Reason: $reason");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Product Permanently Deleted', 'Permanently deleted: $name', 'Products', 0)
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

$products = mysqli_query($conn,"
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
");

// Trash count for badge
$trashCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NOT NULL"
))['total'];

// Trashed products
$trashedProducts = mysqli_query($conn,"
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.deleted_at IS NOT NULL
    ORDER BY p.deleted_at DESC
");

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
$categoriesList = [];
while($cat = mysqli_fetch_assoc($categories)){
    $categoriesList[] = $cat;
}

?>

<style>
/* Force SweetAlert above Bootstrap modals */
body.swal-on-top .swal2-container { z-index: 99999 !important; }
.table-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
</style>

<!-- ADD BUTTON -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Products</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger position-relative" onclick="openTrashModal()">
            <i class="bi bi-trash3-fill me-1"></i> Trash
            <?php if($trashCount > 0){ ?>
            <span id="trashBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $trashCount; ?>
            </span>
            <?php } ?>
        </button>
        <button class="btn btn-success" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i> Add Product
        </button>
    </div>
</div>

<!-- PRODUCTS TABLE -->
<div class="table-card">
    <table class="table table-bordered table-striped datatable" id="productsTable">
        <thead class="table-success">
            <tr>
                <th>#</th>
                <th>Image</th>
                <th>Product Name</th>
                <th>Category</th>
                <th>Barcode</th>
                <th>Selling Price</th>
                <th>Cost Price</th>
                <th>Status</th>
                <th>Date Added</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            while($row = mysqli_fetch_assoc($products)){
            ?>
            <tr>
                <td><?= $i++; ?></td>
                <td>
                    <?php if(!empty($row['image']) && file_exists(__DIR__ . '/uploads/products/' . $row['image'])){ ?>
                        <img src="uploads/products/<?= htmlspecialchars($row['image']); ?>"
                             style="width:42px;height:42px;object-fit:cover;border-radius:6px;">
                    <?php } else { ?>
                        <div style="width:42px;height:42px;border-radius:6px;background:#e9ecef;
                                    display:flex;align-items:center;justify-content:center;color:#adb5bd;">
                            <i class="bi bi-image"></i>
                        </div>
                    <?php } ?>
                </td>
                <td><?= htmlspecialchars($row['product_name']); ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                <td><?= htmlspecialchars($row['barcode'] ?? '—'); ?></td>
                <td>₱<?= number_format($row['selling_price'],2); ?></td>
                <td>₱<?= number_format($row['cost_price'],2); ?></td>
                <td>
                    <?php if($row['status'] == 'Available'){ ?>
                        <span class="badge bg-success">Available</span>
                    <?php } else { ?>
                        <span class="badge bg-secondary">Unavailable</span>
                    <?php } ?>
                </td>
                <td><?= date("M d, Y", strtotime($row['created_at'])); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning"
                        onclick="openEditModal(
                            <?= $row['product_id']; ?>,
                            '<?= addslashes($row['product_name']); ?>',
                            <?= $row['category_id']; ?>,
                            '<?= addslashes($row['barcode'] ?? ''); ?>',
                            '<?= addslashes($row['description'] ?? ''); ?>',
                            <?= $row['selling_price']; ?>,
                            <?= $row['cost_price']; ?>,
                            '<?= $row['status']; ?>',
                            '<?= addslashes($row['image'] ?? ''); ?>'
                        )">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-danger"
                        onclick="deleteProduct(<?= $row['product_id']; ?>,'<?= addslashes($row['product_name']); ?>')">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!--=========================================================
    ADD MODAL
==========================================================-->

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Product
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name" placeholder="e.g. Lucky Me Noodles">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_category">
                            <option value="">-- Select Category --</option>
                            <?php foreach($categoriesList as $cat){ ?>
                            <option value="<?= $cat['category_id']; ?>">
                                <?= htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Barcode</label>
                        <input type="text" class="form-control" id="add_barcode" placeholder="Optional" maxlength="13">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_sell"
                                placeholder="0.00" readonly style="background:#e9ecef;">
                        </div>
                        <div class="form-text">Auto-calculated — 25% markup over cost price.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cost Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_cost"
                                placeholder="0.00" onkeydown="blockNegativeKey(event)"
                                oninput="sanitizeNonNegative(this); calculateSellingPrice('add');"
                                onblur="formatDecimal(this); calculateSellingPrice('add');">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="add_status" onchange="toggleStockField('add')">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="add_stock_wrap">
                        <label class="form-label fw-semibold">Initial Stock <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" id="add_initial_stock"
                            placeholder="e.g. 50" value="1"
                            onkeydown="blockNegativeKey(event)" oninput="sanitizeNonNegative(this)">
                        <div class="form-text">Minimum 1 unit required when status is Available.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" class="form-control" id="add_image"
                               accept="image/png, image/jpeg, image/webp">
                        <div class="form-text">JPG, PNG, or WEBP — max 2MB.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="add_desc" rows="3" placeholder="Optional description..."></textarea>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitAdd()">
                    <i class="bi bi-check-lg me-1"></i>Save Product
                </button>
            </div>

        </div>
    </div>
</div>

<!--=========================================================
    EDIT MODAL
==========================================================-->

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Product
                </h5>
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
                            <option value="<?= $cat['category_id']; ?>">
                                <?= htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Barcode</label>
                        <input type="text" class="form-control" id="edit_barcode" maxlength="13">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_sell"
                                readonly style="background:#e9ecef;">
                        </div>
                        <div class="form-text">Auto-calculated — 25% markup over cost price.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cost Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_cost"
                                onkeydown="blockNegativeKey(event)"
                                oninput="sanitizeNonNegative(this); calculateSellingPrice('edit');"
                                onblur="formatDecimal(this); calculateSellingPrice('edit');">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="add_status" onchange="toggleStockField('add')">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Image</label>
                        <input type="file" class="form-control" id="edit_image"
                               accept="image/png, image/jpeg, image/webp">
                        <div class="form-text">Leave blank to keep the current image.</div>
                        <img id="edit_image_preview" src=""
                             style="display:none;max-height:80px;margin-top:8px;border-radius:6px;">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="edit_desc" rows="3"></textarea>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="submitEdit()">
                    <i class="bi bi-check-lg me-1"></i>Update Product
                </button>
            </div>

        </div>
    </div>
</div>

<!--=========================================================
    TRASH MODAL
==========================================================-->
<div class="modal fade" id="trashModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-trash3-fill me-2"></i>Product Archive
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?php if(mysqli_num_rows($trashedProducts) == 0){ ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-trash3" style="font-size:48px;"></i>
                        <p class="mt-3 mb-0">Trash is empty</p>
                    </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="trashTable">
                        <thead class="table-danger">
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Selling Price</th>
                                <th>Deleted On</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="TrashTableBody">
                            <?php
                            $ti = 1;
                            while($tr = mysqli_fetch_assoc($trashedProducts)){
                            ?>
                            <tr>
                                <td><?= $ti++; ?></td>
                                <td><?= htmlspecialchars($tr['product_name']); ?></td>
                                <td><?= htmlspecialchars($tr['category_name'] ?? '—'); ?></td>
                                <td>₱<?= number_format($tr['selling_price'], 2); ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($tr['deleted_at'])); ?></td>
                                <td><span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($tr['deleted_reason']); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-success me-1"
                                        onclick="restoreProduct(<?= $tr['product_id']; ?>, '<?= addslashes($tr['product_name']); ?>')">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                    </button>
                                    <button class="btn btn-sm btn-danger"
                                        onclick="permanentDelete(<?= $tr['product_id']; ?>, '<?= addslashes($tr['product_name']); ?>')">
                                        <i class="bi bi-x-circle-fill me-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!--=========================================================
    JAVASCRIPT
==========================================================-->

<script>

const MIN_INITIAL_STOCK = 1;    // keep in sync with PHP's MIN_INITIAL_STOCK
const PROFIT_MARKUP     = 0.25; // 25% markup — keep in sync with PHP's DEFAULT_MARKUP

// Prevent typing a minus sign in any numeric input
function blockNegativeKey(e){
    if(e.key === '-' || e.key === 'Subtract'){
        e.preventDefault();
    }
}

// Strip out a minus sign that slipped in anyway (e.g. via paste)
function sanitizeNonNegative(input){
    let val = $(input).val();
    if(val.indexOf('-') !== -1){
        $(input).val(val.replace(/-/g, ''));
    }
}

// Force a number input to always show 2 decimal places
function formatDecimal(input){
    const val = parseFloat($(input).val());
    if(!isNaN(val) && val >= 0){
        $(input).val(val.toFixed(2));
    }
}

// Auto-calculate selling price from cost price using the standard markup
function calculateSellingPrice(prefix){
    const cost = parseFloat($("#" + prefix + "_cost").val());
    if(isNaN(cost) || cost < 0){
        $("#" + prefix + "_sell").val('');
        return;
    }
    const sell = cost + (cost * PROFIT_MARKUP);
    $("#" + prefix + "_sell").val(sell.toFixed(2));
}

// Open Add Modal
function openAddModal(){
    $("#add_name, #add_barcode, #add_desc").val('');
    $("#add_category").val('');
    $("#add_sell, #add_cost").val('');
    $("#add_status").val('Available');
    $("#add_initial_stock").val(1);
    $("#add_image").val('');
    toggleStockField('add');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

// Open Edit Modal
function openEditModal(id, name, category, barcode, desc, sell, cost, status, image){
    $("#edit_id").val(id);
    $("#edit_name").val(name);
    $("#edit_category").val(category);
    $("#edit_barcode").val(barcode);
    $("#edit_desc").val(desc);
    $("#edit_sell").val(sell);
    $("#edit_cost").val(cost);
    $("#edit_status").val(status);
    $("#edit_image").val('');
    $("#edit_existing_image").val(image);

    if(image){
        $("#edit_image_preview").attr('src', 'uploads/products/' + image).show();
    } else {
        $("#edit_image_preview").hide();
    }

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Submit Add
function submitAdd(){
    const name     = $("#add_name").val().trim();
    const category = $("#add_category").val();
    const sell     = parseFloat($("#add_sell").val());
    const cost     = parseFloat($("#add_cost").val());
    const status   = $("#add_status").val();
    const stock    = parseInt($("#add_initial_stock").val());

    if(!name || !category || isNaN(sell) || isNaN(cost)){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    if(sell < 0 || cost < 0){
        Swal.fire('Invalid Price', 'Selling price and cost price cannot be negative.', 'warning');
        return;
    }

    if(status === 'Available' && (isNaN(stock) || stock < MIN_INITIAL_STOCK)){
        Swal.fire('Stock Required',
            `Initial stock must be at least ${MIN_INITIAL_STOCK} unit(s) when status is Available.`,
            'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('product_name', name);
    formData.append('category_id', category);
    formData.append('barcode', $("#add_barcode").val());
    formData.append('description', $("#add_desc").val());
    formData.append('selling_price', sell);
    formData.append('cost_price', cost);
    formData.append('status', $("#add_status").val());
    formData.append('initial_stock', $("#add_initial_stock").val());

    const imageFile = $("#add_image")[0].files[0];
    if(imageFile) formData.append('image', imageFile);

    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
    setTimeout(() => {
    askPassword('add this product').then(verified => {
        if(!verified) return;
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response){
                if(response.trim() == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Product Added!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        clearBackdrop();
                        loadPage('products.php');
                    });
                } else {
                    Swal.fire('Error', response.replace('error:','').trim(), 'error');
                }
            }
        });
    });
}, 400);
}

// Submit Edit
function submitEdit(){
    const name     = $("#edit_name").val().trim();
    const category = $("#edit_category").val();
    const sell     = parseFloat($("#edit_sell").val());
    const cost     = parseFloat($("#edit_cost").val());

    if(!name || !category || isNaN(sell) || isNaN(cost)){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    if(sell < 0 || cost < 0){
        Swal.fire('Invalid Price', 'Selling price and cost price cannot be negative.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Reason for Update',
        input: 'text',
        inputPlaceholder: 'e.g. Price adjustment, wrong category...',
        inputAttributes: { maxlength: 255 },
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Confirm Update',
        inputValidator: (value) => {
            if(!value || value.trim() == ''){
                return 'Please provide a reason for this update.';
            }
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        const reason = result.value;

        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
        setTimeout(() => {
        askPassword('update this product').then(verified => {
            if(!verified) return;

            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('product_id', $("#edit_id").val());
            formData.append('product_name', name);
            formData.append('category_id', category);
            formData.append('barcode', $("#edit_barcode").val());
            formData.append('description', $("#edit_desc").val());
            formData.append('selling_price', sell);
            formData.append('cost_price', cost);
            formData.append('status', $("#edit_status").val());
            formData.append('reason', reason);
            formData.append('existing_image', $("#edit_existing_image").val());

            const imageFile = $("#edit_image")[0].files[0];
            if(imageFile) formData.append('image', imageFile);

            $.ajax({
                url: 'products.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response){
                    if(response.trim() == 'success'){
                        Swal.fire({ icon:'success', title:'Product Updated!',
                            showConfirmButton:false, timer:1500 })
                        .then(() => { clearBackdrop(); loadPage('products.php'); });
                    } else {
                        Swal.fire('Error', response.replace('error:','').trim(), 'error');
                    }
                }
            });
        });
    }, 400);
    });
}

// Delete
function deleteProduct(id, name){
    setTimeout(() => {
    Swal.fire({
        title: 'Delete ' + name + '?',
        html: '<input id="deleteReason" class="swal2-input" placeholder="Reason for deletion e.g. Expired, Discontinued...">',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete',
        preConfirm: () => {
            const reason = document.getElementById('deleteReason').value.trim();
            if(!reason){
                Swal.showValidationMessage('Please provide a reason for deletion.');
                return false;
            }
            return reason;
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        const reason = result.value;

        askPassword('permanently delete this product').then(verified => {
            if(!verified) return;

            $.post('products.php', {
                action:     'delete',
                product_id: id,
                reason:     reason
            }, function(response){
                if(response.trim() == 'success'){
                    Swal.fire({ icon:'success', title:'Product Deleted!',
                        showConfirmButton:false, timer:1500 })
                    .then(() => { clearBackdrop(); loadPage('products.php'); });
                } else {
                    Swal.fire('Error', response.replace('error:','').trim(), 'error');
                }
            });
        });
    });
}, 50);
}

function toggleStockField(prefix){
    const status     = $("#" + prefix + "_status").val();
    const stockInput = $("#" + prefix + "_initial_stock");

    if(status === 'Available'){
        stockInput.prop('disabled', false);
        if(!stockInput.val() || parseInt(stockInput.val()) < 1){
            stockInput.val(1);
        }
    } else {
        stockInput.prop('disabled', true).val(0);
    }
}

function openTrashModal(){
    new bootstrap.Modal(document.getElementById('trashModal')).show();
}

function restoreProduct(id, name){
    document.body.classList.add('swal-on-top');
    bootstrap.Modal.getInstance(document.getElementById('trashModal')).hide();
    setTimeout(() => {
    Swal.fire({
        title: `<i class="bi bi-arrow-counterclockwise text-success me-2"></i>Restore "${name}"?`,
        html: `<p class="text-muted mb-2" style="font-size:14px;">Provide a reason for restoring this product.</p>
               <input id="restoreReason" class="swal2-input" placeholder="e.g. Back in stock, Re-listed...">`,
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Next',
        preConfirm: () => {
            const reason = document.getElementById('restoreReason').value.trim();
            if(!reason){ Swal.showValidationMessage('Please provide a reason.'); return false; }
            return reason;
        }
    }).then(reasonResult => {
        if(!reasonResult.isConfirmed){
            document.body.classList.remove('swal-on-top');
            return;
        }
        const reason = reasonResult.value;

        askPassword('restore this product').then(verified => {
            if(!verified){ document.body.classList.remove('swal-on-top'); return; }
            $.post('products.php', {
                action: 'restore', product_id: id, reason: reason
            }, function(response){
                document.body.classList.remove('swal-on-top');
                if(response.trim() === 'success'){
                    Swal.fire({ icon:'success', title:'Product Restored!', showConfirmButton:false, timer:1500 })
                    .then(() => { clearBackdrop(); loadPage('products.php'); refreshTrashModal(); });
                } else {
                    Swal.fire('Error', response.replace('error:','').trim(), 'error');
                }
            });
        });
    });
}, 400);
}

function permanentDelete(id, name){
    document.body.classList.add('swal-on-top');
    bootstrap.Modal.getInstance(document.getElementById('trashModal')).hide();
    setTimeout(() => {
    Swal.fire({
        title: `<i class="bi bi-x-circle-fill text-danger me-2"></i>Permanently Delete "${name}"?`,
        html: `<p class="text-danger fw-semibold mb-2" style="font-size:14px;">⚠️ This CANNOT be undone!</p>
               <input id="permReason" class="swal2-input" placeholder="e.g. Duplicate entry, No longer carried...">`,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Next',
        preConfirm: () => {
            const reason = document.getElementById('permReason').value.trim();
            if(!reason){ Swal.showValidationMessage('Please provide a reason.'); return false; }
            return reason;
        }
    }).then(reasonResult => {
        if(!reasonResult.isConfirmed){
            document.body.classList.remove('swal-on-top');
            return;
        }
        const reason = reasonResult.value;

        askPassword('permanently delete this product').then(verified => {
            if(!verified){ document.body.classList.remove('swal-on-top'); return; }
            $.post('products.php', {
                action: 'permanent_delete', product_id: id, reason: reason
            }, function(response){
                document.body.classList.remove('swal-on-top');
                if(response.trim() === 'success'){
                    Swal.fire({ icon:'success', title:'Permanently Deleted!', showConfirmButton:false, timer:1500 })
                    .then(() => { clearBackdrop(); loadPage('products.php'); refreshTrashModal(); });
                } else {
                    Swal.fire('Error', response.replace('error:','').trim(), 'error');
                }
            });
        });
    });
}, 400);
}


function askPassword(actionLabel){
    return Swal.fire({
        title: '<i class="bi bi-shield-lock-fill text-success me-2"></i>Confirm Your Identity',
        html: `<p class="text-muted mb-3" style="font-size:14px;">
                   Enter your password to <strong>${actionLabel}</strong>.
               </p>
               <input type="password" id="swal_password" class="swal2-input"
                      placeholder="Your password" autocomplete="current-password">`,
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: '<i class="bi bi-unlock-fill me-1"></i>Verify & Proceed',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        didOpen: () => {
            document.getElementById('swal_password').addEventListener('keydown', function(e){
                if(e.key === 'Enter'){
                    e.preventDefault();
                    Swal.getConfirmButton().click();
                }
            });
        },
        preConfirm: () => {
            const pw = document.getElementById('swal_password').value;
            if(!pw){
                Swal.showValidationMessage('Password is required.');
                return false;
            }
            return $.post('verify_password.php', { password: pw })
                .then(response => {
                    if(response.trim() !== 'success'){
                        Swal.showValidationMessage(
                            response.replace('error: ','') || 'Incorrect password.'
                        );
                        return false;
                    }
                    return true;
                })
                .catch(() => {
                    Swal.showValidationMessage('Could not verify password. Try again.');
                    return false;
                });
        }
    }).then(result => result.isConfirmed === true);
}

function refreshTrashModal(){
    $.get('products.php', function(html){
        const parser  = new DOMParser();
        const doc     = parser.parseFromString(html, 'text/html');
        const newBody = doc.getElementById('trashTableBody');
        const newBadge = doc.getElementById('trashBadge');

        if(newBody)  document.getElementById('trashTableBody').innerHTML  = newBody.innerHTML;
        if(newBadge) document.getElementById('trashBadge').innerHTML = newBadge.innerHTML;
    });
}

</script>