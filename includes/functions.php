<?php
/**
 * Core Shared Procedural Helper Functions
 * QuickContractors Claims Tracker System V1.0
 */

/**
 * Escape output for HTML context to prevent XSS.
 * 
 * @param mixed $string
 * @return string
 */
function e($string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a given URL path and exit execution.
 * 
 * @param string $path Relative or absolute URL path
 * @return void
 */
function redirect(string $path): void {
    header("Location: " . $path);
    exit;
}

/**
 * Format a UTC datetime string for display in Asia/Manila timezone.
 * 
 * @param string|null $utc_datetime
 * @return string Formatted date string
 */
function format_datetime(?string $utc_datetime): string {
    if (empty($utc_datetime)) {
        return 'N/A';
    }
    try {
        $dt = new DateTime($utc_datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $utc_datetime;
    }
}

/**
 * Safely log an application error without exposing sensitive details.
 * 
 * @param string $message
 * @return void
 */
function log_app_error(string $message): void {
    error_log("[QuickContractors Error] " . $message);
}