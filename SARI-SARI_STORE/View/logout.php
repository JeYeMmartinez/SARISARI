<?php
session_start();
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(isset($_SESSION['user_id'])){
    logAction($conn, $_SESSION['user_id'], 'Logout', 'users',
        $_SESSION['user_id'], "{$_SESSION['full_name']} logged out");
}

session_destroy();
header("Location: login.php");
exit();
?>