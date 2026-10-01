<?php
$conn = mysqli_connect('localhost', 'root', '', 'sarisari_db');
$res = mysqli_query($conn, "SELECT user_id, username, password FROM users LIMIT 10");
while($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
