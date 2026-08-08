<?php
require_once '../Model/database.php';

$password = $_POST['password'] ?? '';

if ($password === '') {
    echo 'error: Password is required.';
    exit();
}

// Fallback session lookup: If primary session is empty, check alternative session (Admin vs Employee)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['emp_id'])) {
    $alt_name = (session_name() === 'OCART_ADMIN_SESS') ? 'OCART_EMP_SESS' : 'OCART_ADMIN_SESS';
    session_write_close();
    session_name($alt_name);
    session_start();
}

// System user session (Admin/User)
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $result  = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $user_id LIMIT 1");
    $user    = $result ? mysqli_fetch_assoc($result) : null;
    if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
        echo 'error: Incorrect password.';
        exit();
    }
    echo 'success';
    exit();
}

// Employee work session
if (isset($_SESSION['emp_id'])) {
    $emp_id = (int)$_SESSION['emp_id'];
    $result = mysqli_query($conn, "SELECT password, employee_no FROM employees WHERE employee_id = $emp_id LIMIT 1");
    $emp    = $result ? mysqli_fetch_assoc($result) : null;
    if (!$emp) {
        echo 'error: Employee record not found.';
        exit();
    }
    $pass_valid = (!empty($emp['password']) && password_verify($password, $emp['password'])) ||
                  ($password === $emp['employee_no']);
    if (!$pass_valid) {
        echo 'error: Incorrect password.';
        exit();
    }
    echo 'success';
    exit();
}

echo 'error: Not logged in.';
exit();