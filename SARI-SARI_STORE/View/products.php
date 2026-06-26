<?php
require_once '../Model/database.php';

/*=========================================================
    CRUD ACTIONS
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $name     = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category = (int)$_POST['category_id'];
    $barcode  = mysqli_real_escape_string($conn, $_POST['barcode']);
    $desc     = mysqli_real_escape_string($conn, $_POST['description']);
    $sell     = (float)$_POST['selling_price'];
    $cost     = (float)$_POST['cost_price'];
    $status   = $_POST['status'];
    $added_by = 1; // replace with $_SESSION['user_id'] once login is done

    $query = mysqli_query($conn,"
        INSERT INTO products (category_id, product_name, barcode, description, selling_price, cost_price, status, added_by)
        VALUES ($category, '$name', '$barcode', '$desc', $sell, $cost, '$status', $added_by)
    ");

    echo $query ? 'success' : 'error: ' . mysqli_error($conn);
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

    $query = mysqli_query($conn,"
        UPDATE products SET
            category_id   = $category,
            product_name  = '$name',
            barcode       = '$barcode',
            description   = '$desc',
            selling_price = $sell,
            cost_price    = $cost,
            status        = '$status'
        WHERE product_id = $id
    ");

    echo $query ? 'success' : 'error';
    exit();
}

// DELETE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['product_id'];
    $query = mysqli_query($conn, "DELETE FROM products WHERE product_id = $id");
    echo $query ? 'success' : 'error';
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$products = mysqli_query($conn,"
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.created_at DESC
");

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
$categoriesList = [];
while($cat = mysqli_fetch_assoc($categories)){
    $categoriesList[] = $cat;
}

?>

<!-- ADD BUTTON -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Products</h5>
    <button class="btn btn-success" onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i> Add Product
    </button>
</div>

<!-- PRODUCTS TABLE -->
<div class="table-card">
    <table class="table table-bordered table-striped datatable" id="productsTable">
        <thead class="table-success">
            <tr>
                <th>#</th>
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
                            '<?= $row['status']; ?>'
                        )">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-danger"
                        onclick="deleteProduct(<?= $row['product_id']; ?>)">
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
                        <input type="text" class="form-control" id="add_barcode" placeholder="Optional">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_sell" placeholder="0.00">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cost Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_cost" placeholder="0.00">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="add_status">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="add_stock_wrap">
                        <label class="form-label fw-semibold">Initial Stock <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="add_initial_stock" placeholder="e.g. 50" value="0">
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
                        <input type="text" class="form-control" id="edit_barcode">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_sell">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cost Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_cost">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
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
    JAVASCRIPT
==========================================================-->

<script>

// Open Add Modal
function openAddModal(){
    $("#add_name, #add_barcode, #add_desc").val('');
    $("#add_category").val('');
    $("#add_sell, #add_cost").val('');
    $("#add_status").val('Available');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

// Open Edit Modal
function openEditModal(id, name, category, barcode, desc, sell, cost, status){
    $("#edit_id").val(id);
    $("#edit_name").val(name);
    $("#edit_category").val(category);
    $("#edit_barcode").val(barcode);
    $("#edit_desc").val(desc);
    $("#edit_sell").val(sell);
    $("#edit_cost").val(cost);
    $("#edit_status").val(status);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Submit Add
function submitAdd(){
    const name     = $("#add_name").val().trim();
    const category = $("#add_category").val();
    const sell     = $("#add_sell").val();
    const cost     = $("#add_cost").val();

    if(!name || !category || !sell || !cost){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    $.post('products.php', {
        action:         'create',
        product_name:   name,
        category_id:    category,
        barcode:        $("#add_barcode").val(),
        description:    $("#add_desc").val(),
        selling_price:  sell,
        cost_price:     cost,
        status:         $("#add_status").val(),
        initial_stock:  $("#add_initial_stock").val()
    }, function(response){
        console.log(response);
        if(response == 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Product Added!',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
            $(".modal-backdrop").remove();
            $("body").removeClass("modal-open").css("padding-right","");
            loadPage('products.php');
        });
        } else {
            Swal.fire('Error', 'Something went wrong.', 'error');
        }
    });
}

// Submit Edit
function submitEdit(){
    const name     = $("#edit_name").val().trim();
    const category = $("#edit_category").val();
    const sell     = $("#edit_sell").val();
    const cost     = $("#edit_cost").val();

    if(!name || !category || !sell || !cost){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    $.post('products.php', {
        action:        'update',
        product_id:    $("#edit_id").val(),
        product_name:  name,
        category_id:   category,
        barcode:       $("#edit_barcode").val(),
        description:   $("#edit_desc").val(),
        selling_price: sell,
        cost_price:    cost,
        status:        $("#edit_status").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Product Updated!',
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
            $(".modal-backdrop").remove();
            $("body").removeClass("modal-open").css("padding-right","");
            loadPage('products.php');
        });
        } else {
            Swal.fire('Error', 'Something went wrong.', 'error');
        }
    });
}

// Delete
function deleteProduct(id){
    Swal.fire({
        title: 'Delete Product?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if(result.isConfirmed){
            $.post('products.php', {
                action:     'delete',
                product_id: id
            }, function(response){
                if(response == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => { loadPage('products.php'); });
                } else {
                    Swal.fire('Error', 'Could not delete product.', 'error');
                }
            });
        }
    });
}

function toggleStockField(prefix){
    const status = $("#" + prefix + "_status").val();
    if(status === 'Available'){
        $("#" + prefix + "_stock_wrap").show();
    } else {
        $("#" + prefix + "_stock_wrap").hide();
        $("#" + prefix + "_initial_stock").val(0);
    }
}

</script>