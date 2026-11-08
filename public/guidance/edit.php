<?php
/**
 * Claim Guidance Editor and Revision Control Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();
require_role(['Director', 'Supervisor']);

$user_id = $_SESSION['user_id'];
$guidance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';

$title = '';
$content = '';
$status = 'Draft';
$is_edit = $guidance_id > 0;

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM claim_guidance WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $guidance_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $title = $row['title'];
        $content = $row['content'];
        $status = $row['status'];
    } else {
        http_response_code(404);
        include __DIR__ . '/../errors/404.php';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = trim($_POST['status'] ?? 'Draft');

        if (empty($title) || empty($content)) {
            $error_message = 'Title and content are required.';
        } else {
            $conn->begin_transaction();
            try {
                if ($is_edit) {
                    // 1. Archive current state into claim_guidance_revisions before updating
                    $rev_stmt = $conn->prepare("INSERT INTO claim_guidance_revisions (guidance_id, title, content, status, revision_author_id) SELECT id, title, content, status, ? FROM claim_guidance WHERE id = ?");
                    $rev_stmt->bind_param("ii", $user_id, $guidance_id);
                    $rev_stmt->execute();
                    $rev_stmt->close();

                    // 2. Update claim_guidance record
                    $up_stmt = $conn->prepare("UPDATE claim_guidance SET title = ?, content = ?, status = ? WHERE id = ?");
                    $up_stmt->bind_param("sssi", $title, $content, $status, $guidance_id);
                    $up_stmt->execute();
                    $up_stmt->close();
                } else {
                    // 3. Create new claim_guidance record
                    $ins_stmt = $conn->prepare("INSERT INTO claim_guidance (title, content, status, author_id) VALUES (?, ?, ?, ?)");
                    $ins_stmt->bind_param("sssi", $title, $content, $status, $user_id);
                    $ins_stmt->execute();
                    $guidance_id = $ins_stmt->insert_id;
                    $ins_stmt->close();

                    // Insert initial revision
                    $rev_stmt = $conn->prepare("INSERT INTO claim_guidance_revisions (guidance_id, title, content, status, revision_author_id) VALUES (?, ?, ?, ?, ?)");
                    $rev_stmt->bind_param("isssi", $guidance_id, $title, $content, $status, $user_id);
                    $rev_stmt->execute();
                    $rev_stmt->close();
                }

                $conn->commit();
                redirect(APP_URL . '/guidance/view.php?id=' . $guidance_id);

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Guidance' : 'Create Guidance';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i><?php echo $is_edit ? 'Edit Claim Guidance' : 'Create Claim Guidance'; ?></h2>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Guidance
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo e($error_message); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="title" class="form-label fw-semibold">Guidance Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?php echo e($title); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Draft" <?php echo ($status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Approved" <?php echo ($status === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="Inactive" <?php echo ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="content" class="form-label fw-semibold">Guidance Content / SOP <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="10" required><?php echo e($content); ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <i class="fa-solid fa-save me-2"></i> Save Guidance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>