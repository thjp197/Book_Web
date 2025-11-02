<!-- Hiệp -->
<?php
// Database configuration for XAMPP (Port 3307)
define('DB_HOST', 'localhost:3307');     // Thêm port 3307
define('DB_USER', 'root');
define('DB_PASS', '');                   // XAMPP mặc định không có password
define('DB_NAME', 'book_management');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>