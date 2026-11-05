<?php
/**
 * 403 Forbidden Error View
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
$page_title = 'Access Denied';
include __DIR__ . '/../../includes/header.php';
?>
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center">
        <div class="text-danger mb-3">
            <i class="fa-solid fa-ban fa-4x"></i>
        </div>
        <h1 class="display-5 fw-bold text-dark">Access Denied</h1>
        <p class="text-muted">You do not have the required permissions to access this page or resource.</p>
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-primary mt-3">
            <i class="fa-solid fa-arrow-left me-2"></i> Return to Dashboard
        </a>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>