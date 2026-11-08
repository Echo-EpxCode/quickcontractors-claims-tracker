<?php
/**
 * Claim Guidance Listing Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
$role = current_user_role();

// Fetch all guidance records joined with author name
$sql = "SELECT cg.*, u.full_name AS author_name 
        FROM claim_guidance cg 
        JOIN users u ON cg.author_id = u.id 
        ORDER BY cg.updated_at DESC";
$result = $conn->query($sql);

$page_title = 'Claim Guidance';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-book-bookmark me-2 text-primary"></i>Claim Guidance & SOPs</h2>
                <?php if ($role === 'Director' || $role === 'Supervisor'): ?>
                    <a href="edit.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-2"></i> Create New Guidance
                    </a>
                <?php endif; ?>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Author</th>
                                    <th>Last Updated</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($g = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold text-primary">
                                                <a href="view.php?id=<?php echo $g['id']; ?>" class="text-decoration-none">
                                                    <?php echo e($g['title']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                    $status_badge = match($g['status']) {
                                                        'Approved' => 'bg-success',
                                                        'Draft' => 'bg-warning text-dark',
                                                        'Inactive' => 'bg-secondary',
                                                        default => 'bg-secondary'
                                                    };
                                                ?>
                                                <span class="badge <?php echo $status_badge; ?>"><?php echo e($g['status']); ?></span>
                                            </td>
                                            <td><?php echo e($g['author_name']); ?></td>
                                            <td class="small"><?php echo format_datetime($g['updated_at']); ?></td>
                                            <td class="text-end">
                                                <a href="view.php?id=<?php echo $g['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Guidance">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <?php if ($role === 'Director' || $role === 'Supervisor'): ?>
                                                    <a href="edit.php?id=<?php echo $g['id']; ?>" class="btn btn-sm btn-outline-dark" title="Edit Guidance">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                    <a href="history.php?id=<?php echo $g['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Revision History">
                                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No claim guidance documents available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>