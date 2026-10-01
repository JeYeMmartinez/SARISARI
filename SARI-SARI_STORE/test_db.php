<?php
$conn = mysqli_connect('localhost', 'root', '', 'sarisari_db');
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$res = mysqli_query($conn, "SELECT * FROM users WHERE gmail='jmarkmartinez14@gmail.com'");
if($res && mysqli_num_rows($res) > 0) {
    echo "Found in users table:\n";
    print_r(mysqli_fetch_assoc($res));
} else {
    echo "Not in users table.\n";
}

$res2 = mysqli_query($conn, "SELECT * FROM active_employees WHERE gmail='jmarkmartinez14@gmail.com'");
if($res2 && mysqli_num_rows($res2) > 0) {
    echo "Found in active_employees table:\n";
    print_r(mysqli_fetch_assoc($res2));
} else {
    echo "Not in active_employees table.\n";
}
