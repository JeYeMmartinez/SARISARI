<?php
require_once "../../Model/database.php";
session_unset();
session_destroy();
header("Location: ../work_login.php");
exit();
?>
