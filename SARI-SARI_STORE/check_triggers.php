<?php
require_once 'Model/database.php';
$res = mysqli_query($conn, "SHOW TRIGGERS");
while($row = mysqli_fetch_assoc($res)){
    print_r($row);
}
