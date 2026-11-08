<?php
/**
 * Create User Module (Director Only)
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();
require_role('Director');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($full_name) || empty($role) || empty($password)) {
            $error_message = 'All required fields must be filled.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $username, $password_hash, $full_name, $email, $role);
            if ($stmt->execute()) {
                redirect(APP_URL . '/users/index.php');
            } else {
                $error_message = 'Error creating user (Username must be unique).';
            }
            $stmt->close();
        }
    }
}

$page_title = 'Create User';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Create New User</h2>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Users</a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo e($error_message); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm" style="max-width: 600px;">
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required value="<?php echo e($_POST['full_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required value="<?php echo e($_POST['username'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="">Select role...</option>
                                <option value="Claim_Adjuster">Claim Adjuster</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Director">Director</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-semibold">Create User Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>