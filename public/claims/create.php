<?php
/**
 * Claim Creation Workflow Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();

$role = current_user_role();
$current_user_id = $_SESSION['user_id'];
$error_message = '';

// Fetch lookup options
$stores = $conn->query("SELECT id, store_code, store_name FROM stores WHERE is_active = 1 ORDER BY store_name");
$claim_types = $conn->query("SELECT id, type_name FROM claim_types ORDER BY id");
$adjusters = $conn->query("SELECT id, full_name FROM users WHERE role = 'Claim_Adjuster' AND is_active = 1 ORDER BY full_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please resubmit.';
    } else {
        $job_id = trim($_POST['job_id'] ?? '');
        $store_id = (int)($_POST['store_id'] ?? 0);
        $claim_type_id = (int)($_POST['claim_type_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Normal');
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Claim Party fields
        $party_type = trim($_POST['party_type'] ?? '');
        $contractor_email = trim($_POST['contractor_email_address'] ?? '');
        $legal_company_name = trim($_POST['legal_company_name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');

        // Initial Follow-up date
        $follow_up_due_date = trim($_POST['follow_up_due_date'] ?? '');

        // Handler assignment rule: Claim Adjusters self-assign; Supervisors/Directors can choose
        if ($role === 'Claim_Adjuster') {
            $assigned_handler_id = $current_user_id;
        } else {
            $assigned_handler_id = (int)($_POST['assigned_handler_id'] ?? $current_user_id);
        }

        // Validation
        if (empty($job_id) || empty($store_id) || empty($claim_type_id) || empty($party_type) || empty($contractor_email) || empty($legal_company_name) || empty($company_name) || empty($follow_up_due_date)) {
            $error_message = 'Please fill in all required fields, including store, party details, and initial follow-up due date.';
        } elseif (!filter_var($contractor_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid contractor email address.';
        } else {
            // Begin Atomic Transaction
            $conn->begin_transaction();
            try {
                // 1. Insert Claim (Default status_id = 1 for 'Open')
                $status_id = 1; 
                $stmt = $conn->prepare("INSERT INTO claims (job_id, store_id, claim_type_id, status_id, priority, assigned_handler_id, subject, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiisisd", $job_id, $store_id, $claim_type_id, $status_id, $priority, $assigned_handler_id, $subject, $description); // Wait, fix binding types
                // Correct bind types: s (job_id), i (store_id), i (claim_type_id), i (status_id), s (priority), i (assigned_handler_id), s (subject), s (description) -> 'siiiisis'
                $stmt->close();

                // Proper prepared statement execution for claim creation:
                $stmt = $conn->prepare("INSERT INTO claims (job_id, store_id, claim_type_id, status_id, priority, assigned_handler_id, subject, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiisiss", $job_id, $store_id, $claim_type_id, $status_id, $priority, $assigned_handler_id, $subject, $description);
                $stmt->execute();
                $claim_id = $stmt->insert_id;
                $stmt->close();

                // 2. Insert Claim Party (One-to-one)
                $stmt = $conn->prepare("INSERT INTO claim_parties (claim_id, party_type, contractor_email_address, legal_company_name, company_name) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $claim_id, $party_type, $contractor_email, $legal_company_name, $company_name);
                $stmt->execute();
                $stmt->close();

                // 3. Insert Initial Follow-Up
                $fu_status = 'Scheduled';
                $stmt = $conn->prepare("INSERT INTO follow_ups (claim_id, handler_id, due_date, status, notes) VALUES (?, ?, ?, ?, ?)");
                $fu_notes = 'Initial follow-up scheduled upon claim creation.';
                $stmt->bind_param("iisss", $claim_id, $assigned_handler_id, $follow_up_due_date, $fu_status, $fu_notes);
                $stmt->execute();
                $stmt->close();

                // 4. Record History & Activity Logs
                $event_desc = "Claim created with Job ID: {$job_id}";
                $stmt = $conn->prepare("INSERT INTO claim_history (claim_id, actor_id, event_description) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $claim_id, $current_user_id, $event_desc);
                $stmt->execute();
                $stmt->close();

                $act_type = 'Claim created';
                $stmt = $conn->prepare("INSERT INTO claim_activity_logs (claim_id, actor_id, activity_type, new_value) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $claim_id, $current_user_id, $act_type, $job_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                redirect(APP_URL . '/claims/view.php?id=' . $claim_id);

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                if ($conn->errno === 1062) { // Duplicate key error for unique job_id
                    $error_message = 'The Job ID "' . htmlspecialchars($job_id) . '" already exists. Job ID must be unique.';
                } else {
                    $error_message = 'Database error during claim creation: ' . $e->getMessage();
                }
            }
        }
    }
}

$page_title = 'Create New Claim';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 260px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Create New Claim</h2>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Claims
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo e($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="create.php" method="POST" class="needs-validation" novalidate>
                <?php echo csrf_field(); ?>

                <div class="row g-4">
                    <!-- Claim Information Card -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                                <i class="fa-solid fa-circle-info me-2 text-primary"></i> Claim Information
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="job_id" class="form-label small fw-semibold">Job ID <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="job_id" name="job_id" required value="<?php echo e($_POST['job_id'] ?? ''); ?>" placeholder="e.g. QC-2026-9001">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="store_id" class="form-label small fw-semibold">Store <span class="text-danger">*</span></label>
                                        <select class="form-select" id="store_id" name="store_id" required>
                                            <option value="">Select Store...</option>
                                            <?php while ($st = $stores->fetch_assoc()): ?>
                                                <option value="<?php echo $st['id']; ?>" <?php echo (isset($_POST['store_id']) && $_POST['store_id'] == $st['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($st['store_code'] . ' - ' . $st['store_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="claim_type_id" class="form-label small fw-semibold">Claim Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="claim_type_id" name="claim_type_id" required>
                                            <option value="">Select Type...</option>
                                            <?php while ($ct = $claim_types->fetch_assoc()): ?>
                                                <option value="<?php echo $ct['id']; ?>" <?php echo (isset($_POST['claim_type_id']) && $_POST['claim_type_id'] == $ct['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($ct['type_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="priority" class="form-label small fw-semibold">Priority</label>
                                        <select class="form-select" id="priority" name="priority">
                                            <?php foreach (['Non-Urgent', 'Low', 'Normal', 'High', 'Urgent', 'Escalated'] as $prio): ?>
                                                <option value="<?php echo $prio; ?>" <?php echo (($prio === 'Normal') && !isset($_POST['priority'])) || (isset($_POST['priority']) && $_POST['priority'] === $prio) ? 'selected' : ''; ?>><?php echo $prio; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="assigned_handler_id" class="form-label small fw-semibold">Assigned Handler</label>
                                        <?php if ($role === 'Claim_Adjuster'): ?>
                                            <input type="text" class="form-control bg-light" value="<?php echo e($_SESSION['full_name']); ?>" disabled>
                                            <small class="text-muted">New claims auto-assign to you.</small>
                                        <?php else: ?>
                                            <select class="form-select" id="assigned_handler_id" name="assigned_handler_id" required>
                                                <?php while ($adj = $adjusters->fetch_assoc()): ?>
                                                    <option value="<?php echo $adj['id']; ?>" <?php echo (isset($_POST['assigned_handler_id']) && $_POST['assigned_handler_id'] == $adj['id']) ? 'selected' : ''; ?>>
                                                        <?php echo e($adj['full_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12">
                                        <label for="subject" class="form-label small fw-semibold">Subject / Summary</label>
                                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo e($_POST['subject'] ?? ''); ?>" placeholder="Brief subject description...">
                                    </div>
                                    <div class="col-12">
                                        <label for="description" class="form-label small fw-semibold">Description / Details</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Detailed description of the claim..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Claim Party Information Card -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                                <i class="fa-solid fa-users me-2 text-primary"></i> Claim Party Information (Required 1-to-1)
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="party_type" class="form-label small fw-semibold">Party Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="party_type" name="party_type" required>
                                            <option value="">Select Party Type...</option>
                                            <option value="Third_Party_Contractor" <?php echo (isset($_POST['party_type']) && $_POST['party_type'] === 'Third_Party_Contractor') ? 'selected' : ''; ?>>Third Party Contractor</option>
                                            <option value="E_Tech" <?php echo (isset($_POST['party_type']) && $_POST['party_type'] === 'E_Tech') ? 'selected' : ''; ?>>E-Tech Technician</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contractor_email_address" class="form-label small fw-semibold">Contractor Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="contractor_email_address" name="contractor_email_address" required value="<?php echo e($_POST['contractor_email_address'] ?? ''); ?>" placeholder="contractor@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="legal_company_name" class="form-label small fw-semibold">Legal Company Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="legal_company_name" name="legal_company_name" required value="<?php echo e($_POST['legal_company_name'] ?? ''); ?>" placeholder="Legal registered company name">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="company_name" class="form-label small fw-semibold">Company / Display Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" required value="<?php echo e($_POST['company_name'] ?? ''); ?>" placeholder="Display company name">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Panel: Initial Follow-Up & Action -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                                <i class="fa-solid fa-calendar-days me-2 text-primary"></i> Initial Follow-Up
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="follow_up_due_date" class="form-label small fw-semibold">Follow-Up Due Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="follow_up_due_date" name="follow_up_due_date" required value="<?php echo e($_POST['follow_up_due_date'] ?? date('Y-m-d', strtotime('+3 days'))); ?>">
                                    <small class="text-muted">Must be a valid calendar date.</small>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                        <i class="fa-solid fa-check me-2"></i> Save and Create Claim
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>