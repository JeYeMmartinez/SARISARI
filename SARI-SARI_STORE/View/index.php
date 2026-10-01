<?php
require_once __DIR__ . '/../Model/database.php';

// Already logged in
if(isset($_SESSION['user_id'])){
    if(strtolower($_SESSION['role']) === 'admin'){
        header("Location: admin");
    } else if(strtolower($_SESSION['role']) === 'cashier'){
        header("Location: cashier_panel");
    } else {
        // If not admin/cashier, destroy session and force login since this is admin deployment
        session_unset();
        session_destroy();
        // Do NOT exit here so the login form HTML below can render
    }
}

$error = '';

if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    if(empty($username) || empty($password)){
        $error = "Please fill in all fields.";
    } else {
        $query = mysqli_query($conn,"
            SELECT * FROM users
            WHERE gmail = '$username'
        ");

        if(mysqli_num_rows($query) == 1){
            $user = mysqli_fetch_assoc($query);

            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $error = "Your account is disabled.";
            } else if (!isset($user['role']) || strtolower($user['role']) !== 'admin') {
                $error = "Unauthorized. Admins only.";
            } else if(password_verify($password, $user['password'])){
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['gmail'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['last_activity'] = time();

                // Update last login
                mysqli_query($conn,"
                    UPDATE users SET last_login = NOW()
                    WHERE user_id = {$user['user_id']}
                ");

                // Log the login action
                require_once '../Model/logger.php';
                logAction($conn, $user['user_id'], 'Login', 'users',
                    $user['user_id'], "{$user['full_name']} logged in as Admin");

                header("Location: admin");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Account not found or inactive.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — O-Cart!</title>
    <?php
    $script_name = $_SERVER['SCRIPT_NAME'];
    $pos = strpos($script_name, '/View/');
    $base_href = ($pos !== false) ? substr($script_name, 0, $pos + 6) : '/View/';
    ?>
    <base href="<?= htmlspecialchars($base_href) ?>">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/animate.min.css">
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
        min-height: 100vh;
        background: linear-gradient(135deg, #1E5631 0%, #2E7D32 50%, #1B5E20 100%);
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
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
    }

    /* LEFT PANEL */
    .login-left {
        flex: 1;
        background: #1E5631;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        color: white;
    }

    .login-left .store-icon {
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
        opacity: .8;
        line-height: 1.7;
    }

    .feature-list {
        list-style: none;
        padding: 0;
        margin-top: 30px;
    }

    .feature-list li {
        font-size: 13px;
        opacity: .85;
        margin-bottom: 10px;
    }

    .feature-list li i {
        color: #69f0ae;
        margin-right: 8px;
    }

    /* RIGHT PANEL */
    .login-right {
        width: 380px;
        background: white;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-right h2 {
        font-size: 24px;
        font-weight: 700;
        color: #1E5631;
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
        border-color: #198754;
        box-shadow: 0 0 0 3px rgba(25,135,84,.15);
    }

    .input-group-text {
        background: #f8f9fa;
        border: 1.5px solid #dee2e6;
        border-radius: 8px 0 0 8px;
        color: #198754;
    }

    .input-group .form-control {
        border-radius: 0 8px 8px 0;
        border-left: none;
    }

    .btn-login {
        background: #1E5631;
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
        background: #2E7D32;
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

    .toggle-pass:hover { color: #198754; }

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
        <img src="../EXTRA/OCARTLOGO_TRANSPARENT.png" alt="O-Cart Logo" style="max-height: 70px; margin-bottom: 20px;">
        <h1>Administrator</h1>
        <p>Your complete solution for managing products, inventory, sales, and staff.</p>

        <ul class="feature-list">
            <li><i class="bi bi-check-circle-fill"></i> Real-time inventory tracking</li>
            <li><i class="bi bi-check-circle-fill"></i> Sales analytics & reports</li>
            <li><i class="bi bi-check-circle-fill"></i> Fast cashier processing</li>
            <li><i class="bi bi-check-circle-fill"></i> Low stock notifications</li>
            <li><i class="bi bi-check-circle-fill"></i> Activity audit logs</li>
        </ul>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
        <h2>Welcome back!</h2>
        <p>Sign in to your account to continue</p>

        <?php if($error){ ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error); ?>
        </div>
        <?php } ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Gmail</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <input type="email" name="username" class="form-control"
       placeholder="Enter your Gmail (e.g. admin@gmail.com)"
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
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
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">
            © 2026 O-Cart! System
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
