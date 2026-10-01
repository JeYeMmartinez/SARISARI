<?php
require_once __DIR__ . '/session_helper.php';

if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Local XAMPP Credentials
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "sarisari";
} else {
    // InfinityFree Live Credentials
    // IMPORTANT: Verify these in your InfinityFree Control Panel -> MySQL Databases!
    $host = "sql213.infinityfree.com"; // <-- DOUBLE CHECK THIS HOSTNAME
    $username = "if0_43038509";
    $password = "PXdrs9daT3kic"; // <-- MUST BE YOUR vPanel PASSWORD
    $database = "if0_43038509_sariasri"; // Note the typo here matching the server!
}

// Create Connection
$conn = new mysqli($host, $username, $password, $database);

// Check Connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set Character Encoding
$conn->set_charset("utf8");
?>