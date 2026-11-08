<?php
/**
 * Claim Guidance View Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
$role = current_user_role();
$guidance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT cg.*, u.full_name AS author_name FROM claim_guidance cg JOIN users u ON cg.author_id = u.id WHERE cg.id = ? LIMIT 1");
$stmt->bind_param("i", $guidance_id);
$stmt->execute();
$guidance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$guidance) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

$page_title = $guidance['title'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold text-dark mb-1"><?php echo e($guidance['title']); ?></h2>
                    <small class="text-muted">Author: <strong><?php echo e($guidance['author_name']); ?></strong> | Updated: <?php echo format_datetime($guidance['updated_at']); ?></small>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Guidance
                    </a>
                    <?php if ($role === 'Director' || $role === 'Supervisor'): ?>
                        <a href="edit.php?id=<?php echo $guidance['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-pen me-1"></i> Edit Guidance
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary"><?php echo e($guidance['status']); ?></span>
                    </div>
                    <hr>
                    <div class="guidance-content text-dark" style="line-height: 1.7;">
                        <?php echo nl2br(e($guidance['content'])); ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>