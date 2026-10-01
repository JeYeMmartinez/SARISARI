<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'Model/database.php';
$q = "SELECT * FROM payroll_periods LIMIT 1";
$res = mysqli_query($conn, $q);
if (!$res) {
    echo "Error: " . mysqli_error($conn);
} else {
    $row = mysqli_fetch_assoc($res);
    print_r(array_keys($row));
}
