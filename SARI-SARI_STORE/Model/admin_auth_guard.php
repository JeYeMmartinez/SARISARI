<?php
// admin_auth_guard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$timeout = 1800; // 30 minutes inactivity timeout

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    session_unset();
    session_destroy();
    header("Location: index.php?error=unauthorized");
    exit();
}

// Strict role check for Admin
if (strtolower($_SESSION['role']) !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: index.php?error=forbidden");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: index.php?error=timeout");
    exit();
}

$_SESSION['last_activity'] = time();
?>
