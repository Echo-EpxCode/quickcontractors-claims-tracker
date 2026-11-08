<?php
/**
 * Create Store Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();
require_role(['Director', 'Supervisor']);

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token.';
    } else {
        $store_code = trim($_POST['store_code'] ?? '');
        $store_name = trim($_POST['store_name'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($store_code) || empty($store_name)) {
            $error_message = 'Store Code and Store Name are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO stores (store_code, store_name, address) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $store_code, $store_name, $address);
            if ($stmt->execute()) {
                redirect(APP_URL . '/stores/index.php');
            } else {
                $error_message = 'Error creating store (Store Code must be unique).';
            }
            $stmt->close();
        }
    }
}

$page_title = 'Add Store';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-store me-2 text-primary"></i>Add New Store</h2>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Stores</a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo e($error_message); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm" style="max-width: 600px;">
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Store Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="store_code" required value="<?php echo e($_POST['store_code'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Store Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="store_name" required value="<?php echo e($_POST['store_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo e($_POST['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-semibold">Save Store</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>