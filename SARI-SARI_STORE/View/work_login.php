<?php
session_start();
require_once '../Model/database.php';
require_once '../Controller/AuthController.php';

// Helper function to resolve employee work redirect URL based on position & department
function resolveEmployeeWorkRedirect($position_name, $department_name){
    $pos  = strtolower($position_name ?? '');
    $dept = strtolower($department_name ?? '');

    // Inventory & Warehouse Work Operations
    if(str_contains($pos, 'inventory') || str_contains($dept, 'inventory') || 
       str_contains($pos, 'warehouse') || str_contains($dept, 'warehouse') ||
       str_contains($pos, 'stock') || str_contains($pos, 'store clerk')){
        return 'Inventory_employee/index.php';
    }

    // Cashier & POS Operations
    if(str_contains($pos, 'cashier') || str_contains($dept, 'cashiering') || str_contains($pos, 'sales')){
        return 'cashier_pos.php';
    }

    // Procurement Work Operations
    if(str_contains($pos, 'procurement') || str_contains($dept, 'procurement') || str_contains($pos, 'buyer')){
        return 'procurement_employee/index.php';
    }

    // Finance & Payroll Operations
    if(str_contains($pos, 'finance') || str_contains($dept, 'finance') || str_contains($pos, 'accountant')){
        return 'finance_employee/index.php';
    }

    // Default Fallback: Employee Self-Service Dashboard
    return 'employee_portal/emp_dashboard.php';
}

// Check if already logged in via work session
if(isset($_SESSION['emp_id']) && isset($_SESSION['is_work_session'])){
    $redirect = resolveEmployeeWorkRedirect($_SESSION['emp_position'] ?? '', $_SESSION['emp_department'] ?? '');
    header("Location: $redirect");
    exit();
}

$error = '';

if(isset($_POST['login'])){
    $auth = new AuthController($conn);
    $result = $auth->loginEmployeeWork($_POST['employee_no'], $_POST['password']);

    if(is_array($result)){
        // Redirect dynamically to their work side page
        $redirect = resolveEmployeeWorkRedirect($result['position_name'], $result['department_name']);
        header("Location: $redirect");
        exit();
    } else {
        $error = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Work Login — O-Cart!</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
        min-height: 100vh;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #084298 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', sans-serif;
    }

    .login-wrapper {
        display: flex;
        width: 850px;
        min-height: 500px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }

    /* LEFT PANEL */
    .login-left {
        flex: 1;
        background: #084298;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        color: white;
    }

    .login-left .portal-icon {
        font-size: 60px;
        margin-bottom: 20px;
    }

    .login-left h1 {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .login-left p {
        font-size: 14px;
        opacity: .85;
        line-height: 1.7;
    }

    .feature-list {
        list-style: none;
        padding: 0;
        margin-top: 30px;
    }

    .feature-list li {
        font-size: 13px;
        opacity: .9;
        margin-bottom: 10px;
    }

    .feature-list li i {
        color: #6ea8fe;
        margin-right: 8px;
    }

    /* RIGHT PANEL */
    .login-right {
        width: 390px;
        background: white;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-right h2 {
        font-size: 24px;
        font-weight: 700;
        color: #084298;
        margin-bottom: 6px;
    }

    .login-right p {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 30px;
    }

    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #333;
    }

    .form-control {
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        border: 1.5px solid #dee2e6;
        transition: .2s;
    }

    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,.15);
    }

    .input-group-text {
        background: #f8f9fa;
        border: 1.5px solid #dee2e6;
        border-radius: 8px 0 0 8px;
        color: #0d6efd;
    }

    .input-group .form-control {
        border-radius: 0 8px 8px 0;
        border-left: none;
    }

    .btn-login {
        background: #084298;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px;
        font-size: 15px;
        font-weight: 600;
        width: 100%;
        transition: .2s;
        margin-top: 10px;
    }

    .btn-login:hover {
        background: #0a58ca;
        color: white;
    }

    .error-box {
        background: #fff5f5;
        border: 1px solid #f5c2c7;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        color: #842029;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .toggle-pass {
        cursor: pointer;
        border: 1.5px solid #dee2e6;
        border-left: none;
        border-radius: 0 8px 8px 0;
        background: #f8f9fa;
        padding: 0 14px;
        color: #6c757d;
    }

    .toggle-pass:hover { color: #0d6efd; }

    @media(max-width:768px){
        .login-wrapper { flex-direction: column; width: 95%; }
        .login-left { padding: 30px; }
        .login-right { width: 100%; padding: 30px; }
    }
    </style>
</head>
<body>

<div class="login-wrapper animate__animated animate__fadeIn">

    <!-- LEFT -->
    <div class="login-left">
        <div class="portal-icon"><i class="bi bi-briefcase-fill"></i></div>
        <h1>Employee Work Portal<br>Role Dashboard Access</h1>
        <p>Sign in to access your assigned work department page (Inventory, Cashier, Procurement, or Finance).</p>

        <ul class="feature-list">
            <li><i class="bi bi-check-circle-fill"></i> Inventory & Stock Operations</li>
            <li><i class="bi bi-check-circle-fill"></i> POS & Cashiering Terminal</li>
            <li><i class="bi bi-check-circle-fill"></i> Procurement Purchase Requisitions</li>
            <li><i class="bi bi-check-circle-fill"></i> Automatic Role-Based Redirection</li>
        </ul>
        
        <div class="mt-4 pt-2 d-flex flex-column gap-2" style="font-size:13px;">
            <a href="employee_portal/emp_login.php" class="text-white text-decoration-none opacity-75 hover-opacity-100">
                <i class="bi bi-arrow-left me-1"></i> Personal Employee Portal Login (HR Self-Service)
            </a>
            <a href="login.php" class="text-white text-decoration-none opacity-75 hover-opacity-100">
                <i class="bi bi-shield-lock me-1"></i> Administrator POS System Login
            </a>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
        <h2>Work Portal Sign In</h2>
        <p>Enter your employee credentials to start working</p>

        <?php if($error){ ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error); ?>
        </div>
        <?php } ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Employee Number</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person-badge-fill"></i>
                    </span>
                    <input type="text" name="employee_no" class="form-control"
                           placeholder="Enter your Employee Number"
                           value="<?= isset($_POST['employee_no']) ? htmlspecialchars($_POST['employee_no']) : ''; ?>"
                           required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password" id="passwordField"
                           class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()">
                        <i class="bi bi-eye-fill" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Work Portal
            </button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">
            © 2026 O-Cart! Work Operations
        </p>
    </div>

</div>

<script>
function togglePassword(){
    const field = document.getElementById('passwordField');
    const icon  = document.getElementById('eyeIcon');
    if(field.type === 'password'){
        field.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}
</script>

</body>
</html>
