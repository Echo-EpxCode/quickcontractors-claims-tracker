<?php
/**
 * Database Connection Initializer (MySQLi)
 * QuickContractors Claims Tracker System V1.0
 * 
 * Establishes a secure MySQLi connection using utf8mb4 encoding.
 */

$config = require_once __DIR__ . '/../config/database.php';

// Instantiate MySQLi connection
$conn = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

// Verify connection success
if ($conn->connect_error) {
    error_log("Database Connection Error: " . $conn->connect_error);
    if (ini_get('display_errors')) {
        die("Database connection failed: " . htmlspecialchars($conn->connect_error));
    } else {
        die("A system database error occurred. Please contact the administrator.");
    }
}

// Set character set to utf8mb4
if (!$conn->set_charset($config['charset'])) {
    error_log("Error loading character set utf8mb4: " . $conn->error);
}

return $conn;