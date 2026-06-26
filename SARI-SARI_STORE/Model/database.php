<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "sarisari";

// Create Connection
$conn = new mysqli($host, $username, $password, $database);

// Check Connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set Character Encoding
$conn->set_charset("utf8");
?>