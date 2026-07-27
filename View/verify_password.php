<?php
session_start();
require_once '../Model/database.php';

if(!isset($_SESSION['user_id'])){
    echo 'error: Not logged in.';
    exit();
}

$password = $_POST['password'] ?? '';

if($password === ''){
    echo 'error: Password is required.';
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$result  = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $user_id");
$user    = $result ? mysqli_fetch_assoc($result) : null;

if(!$user){
    echo 'error: User not found.';
    exit();
}

if(!password_verify($password, $user['password'])){
    echo 'error: Incorrect password.';
    exit();
}

echo 'success';
exit();