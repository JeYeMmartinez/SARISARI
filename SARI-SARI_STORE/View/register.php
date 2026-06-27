<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

$current_user = $_SESSION['user_id'] ?? 1;

// CREATE USER
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $role      = $_POST['role'];
    $status    = $_POST['status'];
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if username exists
    $check = mysqli_query($conn,
        "SELECT user_id FROM users WHERE username = '$username'"
    );

    if(mysqli_num_rows($check) > 0){
        echo 'exists';
    } else {
        $query = mysqli_query($conn,"
            INSERT INTO users (username, password, full_name, role, status)
            VALUES ('$username', '$password', '$full_name', '$role', '$status')
        ");

        if($query){
            $new_id = mysqli_insert_id($conn);
            logAction($conn, $current_user, 'Create', 'users', $new_id,
                "Created user account: $full_name ($role)");
            echo 'success';
        } else {
            echo 'error: ' . mysqli_error($conn);
        }
    }
    exit();
}

// UPDATE USER
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $id        = (int)$_POST['user_id'];
    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $role      = $_POST['role'];
    $status    = $_POST['status'];

    // If password provided, update it too
    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $query = mysqli_query($conn,"
            UPDATE users SET
                username  = '$username',
                full_name = '$full_name',
                role      = '$role',
                status    = '$status',
                password  = '$password'
            WHERE user_id = $id
        ");
    } else {
        $query = mysqli_query($conn,"
            UPDATE users SET
                username  = '$username',
                full_name = '$full_name',
                role      = '$role',
                status    = '$status'
            WHERE user_id = $id
        ");
    }

    if($query){
        logAction($conn, $current_user, 'Update', 'users', $id,
            "Updated user account: $full_name ($role)");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// DELETE USER
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['user_id'];
    $user = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT full_name FROM users WHERE user_id = $id"
    ));

    // Prevent deleting yourself
    if($id == $current_user){
        echo 'self';
        exit();
    }

    $query = mysqli_query($conn, "DELETE FROM users WHERE user_id = $id");
    if($query){
        logAction($conn, $current_user, 'Delete', 'users', $id,
            "Deleted user account: {$user['full_name']}");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// FETCH USERS
$users = mysqli_query($conn,"
    SELECT * FROM users ORDER BY role ASC, created_at DESC
");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">User Accounts</h5>
    <button class="btn btn-success" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Add User
    </button>
</div>

<div class="table-card">
    <table class="table table-bordered table-striped datatable">
        <thead class="table-success">
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; while($user = mysqli_fetch_assoc($users)){ ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($user['full_name']); ?></td>
                <td><?= htmlspecialchars($user['username']); ?></td>
                <td>
                    <span class="badge <?= $user['role']=='Admin' ? 'bg-success' : 'bg-primary'; ?>">
                        <?= $user['role']; ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $user['status']=='Active' ? 'bg-success' : 'bg-secondary'; ?>">
                        <?= $user['status']; ?>
                    </span>
                </td>
                <td><?= $user['last_login'] ? date("M d, Y h:i A", strtotime($user['last_login'])) : '—'; ?></td>
                <td><?= date("M d, Y", strtotime($user['created_at'])); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning"
                        onclick="openEditModal(
                            <?= $user['user_id']; ?>,
                            '<?= addslashes($user['full_name']); ?>',
                            '<?= addslashes($user['username']); ?>',
                            '<?= $user['role']; ?>',
                            '<?= $user['status']; ?>'
                        )">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <?php if($user['user_id'] != $current_user){ ?>
                    <button class="btn btn-sm btn-danger"
                        onclick="deleteUser(<?= $user['user_id']; ?>)">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Add User
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_fullname"
                               placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_username"
                               placeholder="e.g. juan123">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="add_password"
                               placeholder="Min. 6 characters">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Role</label>
                        <select class="form-select" id="add_role">
                            <option value="Cashier">Cashier</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="add_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitAdd()">
                    <i class="bi bi-check-lg me-1"></i>Save User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_fullname">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_username">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            New Password
                            <small class="text-muted fw-normal">(leave blank to keep current)</small>
                        </label>
                        <input type="password" class="form-control" id="edit_password"
                               placeholder="Leave blank to keep current password">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Role</label>
                        <select class="form-select" id="edit_role">
                            <option value="Cashier">Cashier</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="submitEdit()">
                    <i class="bi bi-check-lg me-1"></i>Update User
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function clearBackdrop(){
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

function openAddModal(){
    $("#add_fullname, #add_username, #add_password").val('');
    $("#add_role").val('Cashier');
    $("#add_status").val('Active');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function openEditModal(id, fullname, username, role, status){
    $("#edit_id").val(id);
    $("#edit_fullname").val(fullname);
    $("#edit_username").val(username);
    $("#edit_role").val(role);
    $("#edit_status").val(status);
    $("#edit_password").val('');
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function submitAdd(){
    const fullname = $("#add_fullname").val().trim();
    const username = $("#add_username").val().trim();
    const password = $("#add_password").val();

    if(!fullname || !username || !password){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    if(password.length < 6){
        Swal.fire('Weak Password', 'Password must be at least 6 characters.', 'warning');
        return;
    }

    $.post('register.php', {
        action:    'create',
        full_name: fullname,
        username:  username,
        password:  password,
        role:      $("#add_role").val(),
        status:    $("#add_status").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'User Created!',
                showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('register.php'); });
        } else if(response == 'exists'){
            Swal.fire('Username Taken',
                'That username is already in use. Try another.', 'warning');
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

function submitEdit(){
    const fullname = $("#edit_fullname").val().trim();
    const username = $("#edit_username").val().trim();
    const password = $("#edit_password").val();

    if(!fullname || !username){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    if(password && password.length < 6){
        Swal.fire('Weak Password', 'Password must be at least 6 characters.', 'warning');
        return;
    }

    $.post('register.php', {
        action:    'update',
        user_id:   $("#edit_id").val(),
        full_name: fullname,
        username:  username,
        password:  password,
        role:      $("#edit_role").val(),
        status:    $("#edit_status").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'User Updated!',
                showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('register.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

function deleteUser(id){
    Swal.fire({
        title: 'Delete User?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if(result.isConfirmed){
            $.post('register.php', {
                action: 'delete',
                user_id: id
            }, function(response){
                if(response == 'success'){
                    Swal.fire({ icon:'success', title:'Deleted!',
                        showConfirmButton:false, timer:1500 })
                    .then(() => { loadPage('register.php'); });
                } else if(response == 'self'){
                    Swal.fire('Not Allowed',
                        "You can't delete your own account.", 'warning');
                } else {
                    Swal.fire('Error', response, 'error');
                }
            });
        }
    });
}
</script>