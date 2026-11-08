<?php
/**
 * User Management Module (Director Only)
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('Director'); // Strict Director-only restriction per PRD §6

// Handle toggle active status
if (isset($_GET['toggle_id'])) {
    $toggle_id = (int)$_GET['toggle_id'];
    if ($toggle_id !== (int)$_SESSION['user_id']) { // Prevent self-deactivation
        $up = $conn->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
        $up->bind_param("i", $toggle_id);
        $up->execute();
        $up->close();
    }
    redirect(APP_URL . '/users/index.php');
}

$users = $conn->query("SELECT * FROM users ORDER BY role, full_name");

$page_title = 'User Management';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-users-gear me-2 text-primary"></i>User Management</h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus me-2"></i> Create New User
                </a>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($u = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo e($u['full_name']); ?></td>
                                        <td><?php echo e($u['username']); ?></td>
                                        <td class="small text-muted"><?php echo e($u['email'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo e(str_replace('_', ' ', $u['role'])); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $u['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $u['is_active'] ? 'Active' : 'Deactivated'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                                <a href="index.php?toggle_id=<?php echo $u['id']; ?>" class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="Toggle Status">
                                                    <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Current User</span>
                                            <?php endif; ?>
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