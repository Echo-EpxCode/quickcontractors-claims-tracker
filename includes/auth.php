<?php
/**
 * Authentication and Authorization Library
 * QuickContractors Claims Tracker System V1.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently authenticated.
 * 
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Ensure the user is logged in; otherwise redirect to login.
 * 
 * @return void
 */
function require_login(): void {
    if (!is_logged_in()) {
        redirect(APP_URL . '/login.php');
    }
    
    // Verify that the user account is still active in the database
    global $conn;
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] === 0) {
                // Account deactivated — destroy session and redirect
                session_unset();
                session_destroy();
                redirect(APP_URL . '/login.php?error=inactive');
            }
        } else {
            // User no longer exists
            session_unset();
            session_destroy();
            redirect(APP_URL . '/login.php');
        }
        $stmt->close();
    }
}

/**
 * Get the current user's stored role.
 * 
 * @return string|null
 */
function current_user_role(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Enforce that the current user possesses one of the allowed roles.
 * 
 * @param array|string $allowed_roles Single role string or array of allowed roles
 * @return void
 */
function require_role($allowed_roles): void {
    require_login();
    
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    $role = current_user_role();
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../public/errors/403.php';
        exit;
    }
}