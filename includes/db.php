<?php
/**
 * Database Connection Initializer (MySQLi)
 * QuickContractors Claims Tracker System V1.0
 */

// Load database configuration
$config = require __DIR__ . '/../config/database.php';

// Create MySQLi connection
$conn = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);

// Verify connection
if ($conn->connect_error) {
    error_log("Database Connection Error: " . $conn->connect_error);
    exit('Database connection failed.');
}

// Set character encoding
if (!$conn->set_charset($config['charset'])) {
    error_log("Character Set Error: " . $conn->error);
}