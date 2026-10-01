<?php
session_start();
require_once 'Model/database.php';

$admin_id = $_SESSION['user_id'] ?? 0;
echo "Admin ID from Session: $admin_id\n";

if(isset($_GET['pass'])) {
    $password = $_GET['pass'];
    
    $admin_id = (int) $admin_id;
    $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $admin_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    
    if (!$row || empty($row['password'])) {
        echo "No hash found in DB for user_id = $admin_id\n";
    } else {
        echo "Hash found: " . $row['password'] . "\n";
        $valid = password_verify($password, $row['password']);
        echo "Password verify result: " . ($valid ? 'true' : 'false') . "\n";
    }
} else {
    echo "Please provide ?pass=yourpassword to test.";
}
