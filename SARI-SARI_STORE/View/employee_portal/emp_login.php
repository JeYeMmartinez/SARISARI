<?php
session_start();
require_once '../../Model/database.php';

// Already logged in
if (isset($_SESSION['emp_id'])) {
    header("Location: emp_dashboard.php");
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $employee_no = mysqli_real_escape_string($conn, trim($_POST['employee_no']));
    $password = $_POST['password'];

    if (empty($employee_no) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Authenticate against the employees table
        $query = mysqli_query($conn, "
            SELECT * FROM employees
            WHERE employee_no = '$employee_no'
            AND status = 'Active'
            LIMIT 1
        ");

        if (mysqli_num_rows($query) == 1) {
            $emp = mysqli_fetch_assoc($query);

            if (password_verify($password, $emp['password'])) {
                $_SESSION['emp_id'] = $emp['employee_id'];
                $_SESSION['emp_no'] = $emp['employee_no'];
                $_SESSION['emp_name'] = $emp['full_name'];
                $_SESSION['emp_email'] = $emp['email'];
                $_SESSION['emp_role'] = 'Employee';

                // Log login action
                require_once '../../Model/logger.php';
                logAction($conn, 1, 'Login', 'employees', $emp['employee_id'], "Employee {$emp['full_name']} logged in to Portal");

                header("Location: emp_dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Account not found or inactive employee.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal Login — O-Cart!</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f5132 0%, #198754 50%, #0c4128 100%);
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
        }

        /* LEFT PANEL */
        .login-left {
            flex: 1;
            background: #0f5132;
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
            color: #52b788;
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
            color: #0f5132;
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
            box-shadow: 0 0 0 3px rgba(25, 135, 84, .15);
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
            background: #0f5132;
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
            background: #146c43;
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

        .toggle-pass:hover {
            color: #198754;
        }

        @media(max-width:768px) {
            .login-wrapper {
                flex-direction: column;
                width: 95%;
            }

            .login-left {
                padding: 30px;
            }

            .login-right {
                width: 100%;
                padding: 30px;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper animate__animated animate__fadeIn">

        <!-- LEFT -->
        <div class="login-left">
            <div class="portal-icon"><i class="bi bi-people-fill"></i></div>
            <h1>OCART!<br>EMPLOYEE PORTAL</h1>
            <p>Access your profile details, check attendance logs, view payslips, and request leave or file resignation
                online.</p>

            <ul class="feature-list">
                <li><i class="bi bi-check-circle-fill"></i> Check attendance records</li>
                <li><i class="bi bi-check-circle-fill"></i> View & download payslips</li>
                <li><i class="bi bi-check-circle-fill"></i> File leave requests & track status</li>
                <li><i class="bi bi-check-circle-fill"></i> Manage profile details</li>
                <li><i class="bi bi-check-circle-fill"></i> Secure password change</li>
            </ul>

            <div class="mt-4 pt-2">
                <a href="../login.php" class="text-white text-decoration-none" style="font-size:13px;">
                    <i class="bi bi-arrow-left me-1"></i> Back to POS Admin Login
                </a>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="login-right">
            <h2>Welcome, Associate!</h2>
            <p>Sign in to access your dashboard</p>

            <?php if ($error) { ?>
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
                        <input type="text" name="employee_no" class="form-control" placeholder="Enter your Employee Number"
                            value="<?= isset($_POST['employee_no']) ? htmlspecialchars($_POST['employee_no']) : ''; ?>" required
                            autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                        <input type="password" name="password" id="passwordField" class="form-control"
                            placeholder="Enter your password" required>
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
                © 2026 O-Cart! HRMS Portal
            </p>
        </div>

    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('passwordField');
            const icon = document.getElementById('eyeIcon');
            if (field.type === 'password') {
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