<?php
/**
 * Claim Follow-Up Management & Completion Workflow Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();

$claim_id = isset($_GET['claim_id']) ? (int)$_GET['claim_id'] : 0;
$user_id = $_SESSION['user_id'];
$role = current_user_role();
$error_message = '';
$success_message = '';

// Fetch claim details and current scheduled follow-up
$stmt = $conn->prepare("SELECT 
                            c.id, c.job_id, c.assigned_handler_id,
                            fu.id AS follow_up_id, fu.due_date AS current_due_date, fu.notes AS current_fu_notes
                        FROM claims c
                        LEFT JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                        WHERE c.id = ? LIMIT 1");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$claim) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

// Authorization check: Adjusters can only manage follow-ups for claims assigned to them; Supervisors/Directors can manage any.
if ($role === 'Claim_Adjuster' && (int)$claim['assigned_handler_id'] !== (int)$user_id) {
    http_response_code(403);
    include __DIR__ . '/../errors/403.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $action_type = trim($_POST['action_type'] ?? ''); // 'complete_and_schedule' or 'schedule_new'
        $completion_notes = trim($_POST['completion_notes'] ?? '');
        $next_due_date = trim($_POST['next_due_date'] ?? '');
        $next_notes = trim($_POST['next_notes'] ?? '');

        if (empty($next_due_date)) {
            $error_message = 'Please specify the due date for the next scheduled follow-up.';
        } else {
            $conn->begin_transaction();
            try {
                $current_datetime = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

                // 1. If there is an active scheduled follow-up, mark it completed
                if (!empty($claim['follow_up_id'])) {
                    $up_fu = $conn->prepare("UPDATE follow_ups SET status = 'Completed', notes = CONCAT(IFNULL(notes, ''), '\n[Completed Note]: ', ?), completed_at = ? WHERE id = ?");
                    $up_fu->bind_param("ssi", $completion_notes, $current_datetime, $claim['follow_up_id']);
                    $up_fu->execute();
                    $up_fu->close();
                }

                // 2. Insert new scheduled follow-up
                $new_fu_status = 'Scheduled';
                $ins_fu = $conn->prepare("INSERT INTO follow_ups (claim_id, handler_id, due_date, status, notes) VALUES (?, ?, ?, ?, ?)");
                $ins_fu->bind_param("iisss", $claim_id, $claim['assigned_handler_id'], $next_due_date, $new_fu_status, $next_notes);
                $ins_fu->execute();
                $new_fu_id = $ins_fu->insert_id;
                $ins_fu->close();

                // 3. Record in claim history & activity logs
                $hist_desc = "Follow-up completed and next follow-up scheduled for {$next_due_date}.";
                $hist_stmt = $conn->prepare("INSERT INTO claim_history (claim_id, actor_id, event_description) VALUES (?, ?, ?)");
                $hist_stmt->bind_param("iis", $claim_id, $user_id, $hist_desc);
                $hist_stmt->execute();
                $hist_stmt->close();

                $act_type = 'Follow-up updated';
                $act_stmt = $conn->prepare("INSERT INTO claim_activity_logs (claim_id, actor_id, activity_type, new_value) VALUES (?, ?, ?, ?)");
                $act_stmt->bind_param("iiss", $claim_id, $user_id, $act_type, $next_due_date);
                $act_stmt->execute();
                $act_stmt->close();

                $conn->commit();
                redirect(APP_URL . '/claims/view.php?id=' . $claim_id);

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $error_message = 'Database error during follow-up update: ' . $e->getMessage();
            }
        }
    }
}

// Fetch historical follow-ups for this claim
$history_fu_stmt = $conn->prepare("SELECT fu.*, u.full_name AS handler_name FROM follow_ups fu JOIN users u ON fu.handler_id = u.id WHERE fu.claim_id = ? ORDER BY fu.created_at DESC");
$history_fu_stmt->bind_param("i", $claim_id);
$history_fu_stmt->execute();
$history_fu_result = $history_fu_stmt->get_result();
$history_fu_stmt->close();

$page_title = 'Manage Follow-Up: ' . $claim['job_id'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 260px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold text-dark mb-0">
                        <i class="fa-solid fa-calendar-check text-primary me-2"></i> Manage Follow-Up — Claim #<?php echo e($claim['job_id']); ?>
                    </h2>
                    <small class="text-muted">Complete current follow-up and schedule the next operational checkpoint.</small>
                </div>
                <a href="view.php?id=<?php echo $claim_id; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Claim Details
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo e($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Form Column -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Follow-Up Completion & Scheduling Form
                        </div>
                        <div class="card-body">
                            <form action="follow-up.php?claim_id=<?php echo $claim_id; ?>" method="POST" class="needs-validation" novalidate>
                                <?php echo csrf_field(); ?>

                                <?php if (!empty($claim['follow_up_id'])): ?>
                                    <div class="alert alert-light border mb-4">
                                        <h6 class="fw-bold text-dark mb-1">Active Scheduled Follow-Up</h6>
                                        <p class="mb-1 small"><strong>Due Date:</strong> <span class="text-primary"><?php echo e($claim['current_due_date']); ?></span></p>
                                        <p class="mb-0 small"><strong>Current Notes:</strong> <?php echo e($claim['current_fu_notes'] ?? 'None'); ?></p>
                                    </div>

                                    <div class="mb-3">
                                        <label for="completion_notes" class="form-label small fw-semibold">Completion Notes / Action Summary</label>
                                        <textarea class="form-control" id="completion_notes" name="completion_notes" rows="2" placeholder="Summarize what was accomplished during this follow-up..."></textarea>
                                    </div>
                                <hr class="my-4">
                                <?php endif; ?>

                                <h5 class="fw-bold text-dark h6 mb-3"><i class="fa-solid fa-calendar-plus me-2 text-success"></i> Schedule Next Follow-Up</h5>
                                
                                <div class="mb-3">
                                    <label for="next_due_date" class="form-label small fw-semibold">Next Follow-Up Due Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="next_due_date" name="next_due_date" required value="<?php echo e(date('Y-m-d', strtotime('+3 days'))); ?>">
                                    <small class="text-muted">Pure calendar date.</small>
                                </div>

                                <div class="mb-4">
                                    <label for="next_notes" class="form-label small fw-semibold">Next Follow-Up Instructions / Notes</label>
                                    <textarea class="form-control" id="next_notes" name="next_notes" rows="3" placeholder="What needs to be checked or followed up on next?"></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                        <i class="fa-solid fa-check me-2"></i> Complete & Schedule Next Follow-Up
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History Column -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Follow-Up History Log
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-light">
                                        <tr class="small text-secondary">
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Handler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($fu = $history_fu_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="small fw-bold"><?php echo e($fu['due_date']); ?></td>
                                                <td>
                                                    <?php 
                                                        $badge_color = match($fu['status']) {
                                                            'Completed' => 'bg-success',
                                                            'Scheduled' => 'bg-primary',
                                                            'Cancelled' => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                    ?>
                                                    <span class="badge <?php echo $badge_color; ?>"><?php echo e($fu['status']); ?></span>
                                                </td>
                                                <td class="small"><?php echo e($fu['handler_name']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>