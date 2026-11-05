<?php
/**
 * Global Application Configuration
 * QuickContractors Claims Tracker System V1.0
 */

define('APP_NAME', 'QuickContractors Claims Tracker');
define('APP_VERSION', '1.0');
define('APP_URL', 'http://localhost/quickcontractors-claims-tracker/public');

// Timezone configuration (Display and logging convention: Asia/Manila; database in UTC)
date_default_timezone_set('Asia/Manila');

// Error reporting for development (disable display_errors in production deployment)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);