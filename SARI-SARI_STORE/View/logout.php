<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

$is_work = !empty($_SESSION['is_work_session']);

if(isset($_SESSION['user_id'])){
    logAction($conn, $_SESSION['user_id'], 'Logout', 'users',
        $_SESSION['user_id'], "{$_SESSION['full_name']} logged out");
}

session_unset();
session_destroy();

if($is_work){
    header("Location: work_login.php");
} else {
    header("Location: login.php");
}
exit();
?>