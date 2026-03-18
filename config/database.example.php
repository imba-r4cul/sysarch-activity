<?php
/**
 * Database Configuration Template
 * Rename this file to 'database.php' and update the credentials.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'student_management');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>