<?php
// employee_auth_guard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$timeout = 1800; // 30 minutes

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    session_unset();
    session_destroy();
    header("Location: work_login.php?error=unauthorized");
    exit();
}

// Employee portal accepts employee or admin
$allowed_roles = ['employee', 'admin', 'cashier'];
if (!in_array(strtolower($_SESSION['role']), $allowed_roles)) {
    session_unset();
    session_destroy();
    header("Location: work_login.php?error=forbidden");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: work_login.php?error=timeout");
    exit();
}

$_SESSION['last_activity'] = time();
?>
