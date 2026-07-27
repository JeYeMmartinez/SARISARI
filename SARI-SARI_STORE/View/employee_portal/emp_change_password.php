<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id   = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

$success_msg = '';
$error_msg = '';

if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_pass'];
    $new_pass     = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_msg = "Please fill in all password fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = "New password and confirmation password do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error_msg = "New password must be at least 6 characters long.";
    } else {
        // Fetch current hashed password from DB
        $res = mysqli_query($conn, "SELECT password FROM employees WHERE employee_id = $emp_id LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        
        if (!$row || !password_verify($current_pass, $row['password'])) {
            $error_msg = "The current password you entered is incorrect.";
        } else {
            // Hash and update
            $new_hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $q = mysqli_query($conn, "UPDATE employees SET password = '$new_hashed' WHERE employee_id = $emp_id");
            if ($q) {
                logAction($conn, 1, 'Update', 'employees', $emp_id, "Employee $emp_name updated their portal password");
                $success_msg = "Your portal password has been changed successfully!";
            } else {
                $error_msg = "Failed to update password: " . mysqli_error($conn);
            }
        }
    }
    
    ob_clean();
    if (!empty($error_msg)) {
        echo "error: " . $error_msg;
    } else {
        echo "success: " . $success_msg;
    }
    exit();
}
?>
<div class="animate__animated animate__fadeIn">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: #0f5132;">Change Portal Password</h4>
            <small class="text-muted">Keep your account secure by regularly updating your password</small>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 col-md-8 mx-auto">
            <div class="page-card">
                <h5 class="fw-bold text-success mb-3 pb-2 border-bottom"><i class="bi bi-shield-lock me-2"></i>Update Password</h5>
                
                <form id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_pass" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_pass" id="new_pass" required>
                        <div class="form-text" style="font-size:11px;">Must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_pass" id="confirm_pass" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check-circle me-1"></i> Update Password</button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
$(document).ready(function(){
    $('#changePasswordForm').on('submit', function(e){
        e.preventDefault();
        
        const newPass = $('#new_pass').val();
        const confPass = $('#confirm_pass').val();
        
        if (newPass.length < 6) {
            Swal.fire('Password Too Short', 'New password must be at least 6 characters long.', 'warning');
            return;
        }
        
        if (newPass !== confPass) {
            Swal.fire('Password Mismatch', 'New password and confirmation password do not match.', 'warning');
            return;
        }

        const formData = $(this).serialize();
        $.ajax({
            url: 'emp_change_password.php',
            type: 'POST',
            data: formData,
            success: function(res){
                res = res.trim();
                if (res.startsWith('success:')) {
                    const msg = res.substring(8);
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Updated!',
                        text: msg,
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        $('#changePasswordForm')[0].reset();
                        loadPage('emp_home.php');
                    });
                } else {
                    const errorMsg = res.startsWith('error:') ? res.substring(6) : res;
                    Swal.fire('Error', errorMsg, 'error');
                }
            },
            error: function(){
                Swal.fire('Error', 'Communication failure with the server.', 'error');
            }
        });
    });
});
</script>
