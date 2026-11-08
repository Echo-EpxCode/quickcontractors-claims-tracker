<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';

// Define the new plaintext password you want to use
$new_password = 'Password123!';
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'adjuster_john'");
$stmt->bind_param("s", $new_hash);

if ($stmt->execute()) {
    echo "Password successfully updated for adjuster_john to: " . htmlspecialchars($new_password);
} else {
    echo "Error updating password: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();