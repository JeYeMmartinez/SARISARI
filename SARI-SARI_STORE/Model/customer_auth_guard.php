<?php
// customer_auth_guard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$timeout = 1800; // 30 minutes

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Instead of forcing a redirect that breaks the store, APIs can check this
    $_SESSION['auth_error'] = 'unauthorized';
} else {
    if (strtolower($_SESSION['role']) !== 'customer') {
        $_SESSION['auth_error'] = 'forbidden';
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        $_SESSION['auth_error'] = 'timeout';
    } else {
        $_SESSION['last_activity'] = time();
        unset($_SESSION['auth_error']);
    }
}
?>
