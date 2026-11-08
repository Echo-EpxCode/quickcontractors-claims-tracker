<?php
/**
 * Claim Guidance Revision History Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role(['Director', 'Supervisor']);

$guidance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT title FROM claim_guidance WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $guidance_id);
$stmt->execute();
$guidance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$guidance) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

$rev_stmt = $conn->prepare("SELECT r.*, u.full_name AS author_name FROM claim_guidance_revisions r JOIN users u ON r.revision_author_id = u.id WHERE r.guidance_id = ? ORDER BY r.created_at DESC");
$rev_stmt->bind_param("i", $guidance_id);
$rev_stmt->execute();
$revisions = $rev_stmt->get_result();
$rev_stmt->close();

$page_title = 'Revision History: ' . $guidance['title'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Revision History: <?php echo e($guidance['title']); ?></h2>
                <a href="view.php?id=<?php echo $guidance_id; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Guidance
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>Revision Timestamp</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Author</th>
                                    <th>Content Snapshot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rev = $revisions->fetch_assoc()): ?>
                                    <tr>
                                        <td class="small fw-bold"><?php echo format_datetime($rev['created_at']); ?></td>
                                        <td><?php echo e($rev['title']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo e($rev['status']); ?></span></td>
                                        <td><?php echo e($rev['author_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#rev-<?php echo $rev['id']; ?>">
                                                View Content
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="collapse bg-light" id="rev-<?php echo $rev['id']; ?>">
                                        <td colspan="5" class="p-3">
                                            <p class="small text-muted mb-1"><strong>Content Snapshot:</strong></p>
                                            <div class="bg-white p-3 border rounded small"><?php echo nl2br(e($rev['content'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>