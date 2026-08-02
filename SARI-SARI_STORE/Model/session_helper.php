<?php
if (!function_exists('start_ocart_session')) {
    function start_ocart_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $script_path = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
        $uri         = $_SERVER['REQUEST_URI'] ?? '';
        $referer     = $_SERVER['HTTP_REFERER'] ?? '';

        // Check if explicitly within Admin Portal (including AJAX calls from admin portal pages)
        $is_admin_portal = (
            strpos($script_path, 'admin_') !== false ||
            strpos($script_path, 'admin.php') !== false ||
            strpos($script_path, 'login.php') !== false ||
            strpos($script_path, 'hrms') !== false ||
            strpos($uri, 'admin_') !== false ||
            strpos($uri, 'admin.php') !== false ||
            strpos($referer, 'admin_') !== false ||
            strpos($referer, 'admin.php') !== false
        );

        if ($is_admin_portal && !strpos($script_path, 'work_login.php')) {
            session_name('OCART_ADMIN_SESS');
        } else {
            session_name('OCART_EMP_SESS');
        }

        session_start();
    }
}

// Automatically start the appropriate session
start_ocart_session();
?>
