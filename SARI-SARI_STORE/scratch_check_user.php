<?php
require 'Model/database.php';
$q = mysqli_query($conn, "SELECT user_id, full_name, gmail, role FROM users WHERE gmail = 'jmarkmartinez14@gmail.com'");
while($r = mysqli_fetch_assoc($q)) {
    print_r($r);
}
?>
