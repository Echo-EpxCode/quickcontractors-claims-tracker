<?php
/**
 * Root Application Router / Entry Point
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(APP_URL . '/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}