<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

$current_user = $_SESSION['user_id'] ?? 1;

function verifyAdminPassword($conn, $admin_id, $password) {
    if (empty($password)) return false;
    $admin_id = (int) $admin_id;
    $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $admin_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if (!$row || empty($row['password'])) return false;
    return password_verify($password, $row['password']);
}

// CREATE CUSTOMER
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    if (!verifyAdminPassword($conn, $current_user, $_POST['admin_password'] ?? '')) {
        echo 'error: Incorrect admin password. Customer was not created.';
        exit();
    }

    $username  = mysqli_real_escape_string($conn, trim($_POST['username']));
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $role      = 'Customer';
    $status    = 'Active';
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if username exists
    $check = mysqli_query($conn,
        "SELECT user_id FROM users WHERE gmail = '$username'"
    );

    if(mysqli_num_rows($check) > 0){
        echo 'exists';
    } else {
        $query = mysqli_query($conn,"
            INSERT INTO users (gmail, password, full_name, role, status)
            VALUES ('$username', '$password', '$full_name', '$role', '$status')
        ");

        if($query){
            $new_id = mysqli_insert_id($conn);
            logAction($conn, $current_user, 'Create', 'users', $new_id,
                "Created customer account: $full_name");
            mysqli_query($conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('Customer Created', 'New Customer account created: $full_name', 'Approval', 0)
            ");
            echo 'success';
        } else {
            echo 'error: ' . mysqli_error($conn);
        }
    }
    exit();
}



// DELETE CUSTOMER
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    if (!verifyAdminPassword($conn, $current_user, $_POST['admin_password'] ?? '')) {
        echo 'error: Incorrect admin password. Customer was not deleted.';
        exit();
    }

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
            "Deleted customer account: {$user['full_name']}");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Customer Deleted', 'Customer account deleted: {$user['full_name']}', 'Approval', 0)
        ");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// FETCH CUSTOMER USERS
$users = mysqli_query($conn,"
    SELECT * FROM users WHERE role = 'Customer' OR role IS NULL OR role = '' ORDER BY created_at DESC
");
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Registered Customer Accounts</h5>
    <button class="btn btn-success" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Add Customer
    </button>
</div>

<div class="table-card">
    <table class="table table-bordered table-striped datatable">
        <thead class="table-success">
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Gmail</th>
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
                <td><?= htmlspecialchars($user['gmail'] ?? '—'); ?></td>
                <td><?= $user['last_login'] ? date("M d, Y h:i A", strtotime($user['last_login'])) : '—'; ?></td>
                <td><?= date("M d, Y", strtotime($user['created_at'])); ?></td>
                <td>
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
                    <i class="bi bi-person-plus me-2"></i>Add Customer
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
                        <label class="form-label fw-semibold">Gmail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="add_username"
                               placeholder="e.g. juan@gmail.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="add_password"
                               placeholder="Min. 6 characters" autocomplete="new-password" value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitAdd()">
                    <i class="bi bi-check-lg me-1"></i>Save Customer
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
    new bootstrap.Modal(document.getElementById('addModal')).show();
}



function submitAdd(){
    const fullname = $("#add_fullname").val().trim();
    const username = $("#add_username").val().trim();
    const password = $("#add_password").val();

    if(!fullname || !username || !password){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }

    const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
    if(!gmailRegex.test(username)){
        Swal.fire('Invalid Gmail', 'Please enter a valid @gmail.com address.', 'warning');
        return;
    }

    if(password.length < 6){
        Swal.fire('Weak Password', 'Password must be at least 6 characters.', 'warning');
        return;
    }

    Swal.fire({
        target: document.getElementById('addModal'),
        title: 'Confirm Admin Password',
        html: 'Enter your administrator password to save this new customer account.',
        input: 'password',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'new-password' },
        didOpen: () => {
            const input = Swal.getInput();
            if (input) { input.value = ''; input.setAttribute('autocomplete', 'new-password'); }
        },
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Confirm & Save',
        cancelButtonText: 'Cancel',
        inputValidator: (val) => {
            if (!val) return 'Password is required to confirm.';
        }
    }).then(confirmResult => {
        if (!confirmResult.isConfirmed) return;

        $.post('register.php', {
            action:         'create',
            full_name:      fullname,
            username:       username,
            password:       password,
            admin_password: confirmResult.value
        }, function(response){
            response = response.trim();
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Customer Created!',
                    showConfirmButton:false, timer:1500 })
                .then(() => { clearBackdrop(); loadPage('register.php'); });
            } else if(response == 'exists'){
                Swal.fire('Gmail Already Registered',
                    'That Gmail is already linked to an account.', 'warning');
            } else {
                Swal.fire('Error', response.replace(/^error:\s*/i, ''), 'error');
            }
        });
    });
}



function deleteUser(id){
    Swal.fire({
        title: 'Confirm Admin Password',
        html: 'Enter your administrator password to confirm deletion of this customer account.',
        input: 'password',
        inputPlaceholder: 'Enter your password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'new-password' },
        didOpen: () => {
            const input = Swal.getInput();
            if (input) { input.value = ''; input.setAttribute('autocomplete', 'new-password'); }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete Account',
        cancelButtonText: 'Cancel',
        inputValidator: (val) => {
            if (!val) return 'Password is required to confirm.';
        }
    }).then(result => {
        if (!result.isConfirmed) return;

        $.post('register.php', {
            action:         'delete',
            user_id:        id,
            admin_password: result.value
        }, function(response){
            response = response.trim();
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Deleted!',
                    showConfirmButton:false, timer:1500 })
                .then(() => { loadPage('register.php'); });
            } else if(response == 'self'){
                Swal.fire('Not Allowed',
                    "You can't delete your own account.", 'warning');
            } else {
                Swal.fire('Error', response.replace(/^error:\s*/i, ''), 'error');
            }
        });
    });
}
</script>