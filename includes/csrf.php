<?php
/**
 * CSRF Protection Helpers
 * QuickContractors Claims Tracker System V1.0
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or return the active CSRF token for the session.
 * 
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden input field containing the CSRF token.
 * 
 * @return string HTML hidden input
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify a submitted CSRF token against the session token.
 * 
 * @param string|null $token Submitted token
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}