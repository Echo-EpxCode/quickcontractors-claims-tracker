<?php
/**
 * Store Management Listing Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role(['Director', 'Supervisor']);

$stores = $conn->query("SELECT * FROM stores ORDER BY store_name");

$page_title = 'Store Management';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-store me-2 text-primary"></i>Store Management</h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-2"></i> Add New Store
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>Store Code</th>
                                    <th>Store Name</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($st = $stores->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo e($st['store_code']); ?></td>
                                        <td><?php echo e($st['store_name']); ?></td>
                                        <td class="small text-muted"><?php echo e($st['address'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $st['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $st['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="edit.php?id=<?php echo $st['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
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