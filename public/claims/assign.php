<?php
/**
 * Claim Reassignment & Take-Over Module with SweetAlert2 Confirmation
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();
require_role(['Supervisor', 'Director']); // Restricted to Supervisor and Director per TRD §5.5

$claim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];
$error_message = '';

// Fetch claim and current handler
$stmt = $conn->prepare("SELECT c.id, c.job_id, c.assigned_handler_id, c.status_id, u.full_name AS current_handler FROM claims c JOIN users u ON c.assigned_handler_id = u.id WHERE c.id = ? LIMIT 1");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$claim) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

$adjusters = $conn->query("SELECT id, full_name FROM users WHERE role = 'Claim_Adjuster' AND is_active = 1 ORDER BY full_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token.';
    } else {
        $new_handler_id = (int)($_POST['new_handler_id'] ?? 0);
        $is_takeover = isset($_POST['is_takeover']) && $_POST['is_takeover'] === '1';

        if ($new_handler_id <= 0) {
            $error_message = 'Please select a valid handler.';
        } else {
            $conn->begin_transaction();
            try {
                // Get old handler name for logging
                $oh_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                $oh_stmt->bind_param("i", $claim['assigned_handler_id']);
                $oh_stmt->execute();
                $old_handler_name = $oh_stmt->get_result()->fetch_assoc()['full_name'];
                $oh_stmt->close();

                // Get new handler name
                $nh_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                $nh_stmt->bind_param("i", $new_handler_id);
                $nh_stmt->execute();
                $new_handler_name = $nh_stmt->get_result()->fetch_assoc()['full_name'];
                $nh_stmt->close();

                // 1. Update claim assigned handler
                $up_stmt = $conn->prepare("UPDATE claims SET assigned_handler_id = ? WHERE id = ?");
                $up_stmt->bind_param("ii", $new_handler_id, $claim_id);
                $up_stmt->execute();
                $up_stmt->close();

                // 2. Log in claim_activity_logs (storing assignment history per TRD §12)
                $activity_type = $is_takeover ? 'claim_taken_over' : 'claim_reassigned';
                $activity_details = "Handler changed from {$old_handler_name} to {$new_handler_name}";
                $act_stmt = $conn->prepare("INSERT INTO claim_activity_logs (claim_id, actor_id, activity_type, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
                $act_stmt->bind_param("iisss", $claim_id, $current_user_id, $activity_type, $old_handler_name, $new_handler_name);
                $act_stmt->execute();
                $act_stmt->close();

                // 3. Record in claim history
                $hist_stmt = $conn->prepare("INSERT INTO claim_history (claim_id, actor_id, event_description) VALUES (?, ?, ?)");
                $hist_desc = "Claim reassigned from {$old_handler_name} to {$new_handler_name} by Supervisor/Director.";
                $hist_stmt->bind_param("iis", $claim_id, $current_user_id, $hist_desc);
                $hist_stmt->execute();
                $hist_stmt->close();

                $conn->commit();
                redirect(APP_URL . '/claims/view.php?id=' . $claim_id);

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $error_message = 'Database error during assignment: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Reassign Claim: ' . $claim['job_id'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 260px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Reassign / Take Over Claim</h2>
                <a href="view.php?id=<?php echo $claim_id; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Claim View
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo e($error_message); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm" style="max-width: 600px;">
                <div class="card-body p-4">
                    <p class="mb-3"><strong>Claim Job ID:</strong> <?php echo e($claim['job_id']); ?></p>
                    <p class="mb-4"><strong>Current Handler:</strong> <span class="badge bg-secondary"><?php echo e($claim['current_handler']); ?></span></p>

                    <form id="assignForm" action="assign.php?id=<?php echo $claim_id; ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="is_takeover" id="is_takeover" value="0">

                        <div class="mb-3">
                            <label for="new_handler_id" class="form-label fw-semibold">Select New Assigned Handler</label>
                            <select class="form-select" id="new_handler_id" name="new_handler_id" required>
                                <option value="">Select adjuster...</option>
                                <?php while ($adj = $adjusters->fetch_assoc()): ?>
                                    <option value="<?php echo $adj['id']; ?>"><?php echo e($adj['full_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" id="reassignBtn" class="btn btn-primary">
                                <i class="fa-solid fa-check me-2"></i> Save Reassignment
                            </button>
                            <button type="button" id="takeoverBtn" class="btn btn-dark">
                                <i class="fa-solid fa-shield-halved me-2"><b>Take Over Claim</b></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- SweetAlert2 Confirmation Script per TRD §5.5 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentHandler = "<?php echo e($claim['current_handler']); ?>";
    
    document.getElementById('reassignBtn').addEventListener('click', function() {
        const select = document.getElementById('new_handler_id');
        if (!select.value) {
            Swal.fire('Error', 'Please select a new handler first.', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Confirm Reassignment',
            text: `Are you sure you want to reassign this claim from ${currentHandler}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reassign'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('is_takeover').value = '0';
                document.getElementById('assignForm').submit();
            }
        });
    });

    document.getElementById('takeoverBtn').addEventListener('click', function() {
        Swal.fire({
            title: 'Confirm Claim Take-Over',
            text: `Warning: This claim is currently assigned to ${currentHandler}. Taking over will replace the handler and log an administrative takeover event.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#343a40',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Take Over'
        }).then((result) => {
            if (result.isConfirmed) {
                // If taking over, optionally set handler to current supervisor/director or selected adjuster
                document.getElementById('is_takeover').value = '1';
                document.getElementById('assignForm').submit();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>