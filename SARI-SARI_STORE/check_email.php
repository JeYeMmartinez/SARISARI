<?php
$conn = mysqli_connect('localhost', 'root', '', 'sarisari_db');
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// Check users table
$q1 = mysqli_query($conn, "SELECT * FROM users WHERE email='jmarkmartinez14@gmail.com'");
if ($q1 && mysqli_num_rows($q1) > 0) {
    echo "Found in USERS table:\n";
    print_r(mysqli_fetch_assoc($q1));
} else {
    echo "Not in USERS table.\n";
}

// Check employees table
$q2 = mysqli_query($conn, "SELECT * FROM employees WHERE email='jmarkmartinez14@gmail.com'");
if ($q2 && mysqli_num_rows($q2) > 0) {
    echo "Found in EMPLOYEES table:\n";
    print_r(mysqli_fetch_assoc($q2));
} else {
    echo "Not in EMPLOYEES table.\n";
}

// Check customers table
$q3 = mysqli_query($conn, "SELECT * FROM customers WHERE email='jmarkmartinez14@gmail.com'");
if ($q3 && mysqli_num_rows($q3) > 0) {
    echo "Found in CUSTOMERS table:\n";
    print_r(mysqli_fetch_assoc($q3));
} else {
    echo "Not in CUSTOMERS table.\n";
}
