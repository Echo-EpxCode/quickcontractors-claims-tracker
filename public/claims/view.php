<?php
/**
 * Detailed Claim View Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$claim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = current_user_role();
$user_id = $_SESSION['user_id'];

// Fetch claim with all related details
$stmt = $conn->prepare("SELECT 
                            c.*, 
                            s.store_code, s.store_name, s.address AS store_address,
                            ct.type_name AS claim_type,
                            cs.status_name AS claim_status,
                            u.full_name AS assigned_handler, u.id AS handler_id,
                            cp.party_type, cp.contractor_email_address, cp.legal_company_name, cp.company_name,
                            sc.sc_job_id, sc.call_status, sc.scheduled_date AS sc_scheduled_date, sc.notes AS sc_notes,
                            fu.due_date AS current_fu_date, fu.status AS current_fu_status, fu.notes AS current_fu_notes
                         FROM claims c
                         JOIN stores s ON c.store_id = s.id
                         JOIN claim_types ct ON c.claim_type_id = ct.id
                         JOIN claim_statuses cs ON c.status_id = cs.id
                         JOIN users u ON c.assigned_handler_id = u.id
                         JOIN claim_parties cp ON c.id = cp.claim_id
                         LEFT JOIN service_calls sc ON c.id = sc.claim_id
                         LEFT JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                         WHERE c.id = ? LIMIT 1");

$stmt->bind_param("i", $claim_id);
$stmt->execute();
$result = $stmt->get_result();
$claim = $result->fetch_assoc();
$stmt->close();

if (!$claim) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

// Fetch Action History & Activity Logs
$history_stmt = $conn->prepare("SELECT ch.*, u.full_name AS actor_name FROM claim_history ch JOIN users u ON ch.actor_id = u.id WHERE ch.claim_id = ? ORDER BY ch.created_at DESC");
$history_stmt->bind_param("i", $claim_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history_stmt->close();

$activity_stmt = $conn->prepare("SELECT cal.*, u.full_name AS actor_name FROM claim_activity_logs cal JOIN users u ON cal.actor_id = u.id WHERE cal.claim_id = ? ORDER BY cal.created_at DESC");
$activity_stmt->bind_param("i", $claim_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$activity_stmt->close();

$page_title = 'Claim Details: ' . $claim['job_id'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 260px;">
            <!-- Header bar -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold text-dark mb-0">
                        <i class="fa-solid fa-file-invoice text-primary me-2"></i> Claim #<?php echo e($claim['job_id']); ?>
                    </h2>
                    <small class="text-muted">Created on <?php echo format_datetime($claim['created_at']); ?></small>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Claims
                    </a>
                    <?php if ($role === 'Supervisor' || $role === 'Director' || $claim['assigned_handler_id'] == $user_id): ?>
                        <a href="edit.php?id=<?php echo $claim['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Claim
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Info Column -->
                <div class="col-lg-8">
                    <!-- Claim Core Details Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-circle-info me-2 text-primary"></i> Core Claim Information
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Job ID:</div>
                                <div class="col-md-8 fw-bold text-dark"><?php echo e($claim['job_id']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Claim Type:</div>
                                <div class="col-md-8"><span class="badge bg-light text-dark border"><?php echo e($claim['claim_type']); ?></span></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Claim Status:</div>
                                <div class="col-md-8"><span class="badge bg-primary"><?php echo e(str_replace('_', ' ', $claim['claim_status'])); ?></span></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Priority:</div>
                                <div class="col-md-8"><span class="badge bg-dark"><?php echo e($claim['priority']); ?></span></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Assigned Handler:</div>
                                <div class="col-md-8 fw-semibold text-dark">
                                    <?php echo e($claim['assigned_handler']); ?>
                                    <?php if ($role === 'Supervisor' || $role === 'Director'): ?>
                                        <a href="assign.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-dark ms-2 py-0">Reassign / Take Over</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Subject:</div>
                                <div class="col-md-8"><?php echo e($claim['subject'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 fw-semibold text-secondary">Description:</div>
                                <div class="col-md-8"><?php echo nl2br(e($claim['description'] ?? 'No description provided.')); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Claim Party Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-users me-2 text-primary"></i> Claim Party Information (1-to-1)
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Party Type:</div>
                                <div class="col-md-8"><span class="badge bg-secondary"><?php echo e(str_replace('_', ' ', $claim['party_type'])); ?></span></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Legal Company Name:</div>
                                <div class="col-md-8 fw-bold"><?php echo e($claim['legal_company_name']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Display Company Name:</div>
                                <div class="col-md-8"><?php echo e($claim['company_name']); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 fw-semibold text-secondary">Contractor Email:</div>
                                <div class="col-md-8"><a href="mailto:<?php echo e($claim['contractor_email_address']); ?>"><?php echo e($claim['contractor_email_address']); ?></a></div>
                            </div>
                        </div>
                    </div>

                    <!-- Store Information Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-store me-2 text-primary"></i> Associated Store Information
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 fw-semibold text-secondary">Store Code & Name:</div>
                                <div class="col-md-8 fw-bold"><?php echo e($claim['store_code'] . ' - ' . $claim['store_name']); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 fw-semibold text-secondary">Store Address:</div>
                                <div class="col-md-8"><?php echo e($claim['store_address'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column: Follow-up & Service Call -->
                <div class="col-lg-4">
                    <!-- Current Follow-Up Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-calendar-check me-2 text-primary"></i> Current Follow-Up
                        </div>
                        <div class="card-body">
                            <?php if (!empty($claim['current_fu_date'])): ?>
                                <p class="mb-2"><strong>Due Date:</strong> <span class="text-primary"><?php echo e($claim['current_fu_date']); ?></span></p>
                                <p class="mb-2"><strong>Status:</strong> <span class="badge bg-success"><?php echo e($claim['current_fu_status']); ?></span></p>
                                <p class="mb-3"><strong>Notes:</strong> <?php echo e($claim['current_fu_notes'] ?? 'None'); ?></p>
                            <?php else: ?>
                                <p class="text-muted small">No active scheduled follow-up.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Service Call Card (Visible to Supervisor/Director or general if present) -->
                    <?php if ($role === 'Supervisor' || $role === 'Director' || !empty($claim['sc_job_id'])): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 fw-bold text-dark border-0">
                            <i class="fa-solid fa-phone me-2 text-primary"></i> Service Call Record
                        </div>
                        <div class="card-body">
                            <?php if (!empty($claim['sc_job_id'])): ?>
                                <p class="mb-2"><strong>SC Job ID:</strong> <?php echo e($claim['sc_job_id']); ?></p>
                                <p class="mb-2"><strong>Call Status:</strong> <?php echo e($claim['call_status']); ?></p>
                                <p class="mb-2"><strong>Scheduled Date:</strong> <?php echo e($claim['sc_scheduled_date'] ?? 'N/A'); ?></p>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo e($claim['sc_notes'] ?? 'None'); ?></p>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No service call record attached (Optional 0-or-1).</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- History and Audit Logs Tabs -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="historyTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="lifecycle-tab" data-bs-toggle="tab" data-bs-target="#lifecycle" type="button" role="tab">Claim History</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">Activity & Audit Logs</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="historyTabContent">
                        <div class="tab-pane fade show active" id="lifecycle" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr class="small text-secondary">
                                            <th>Timestamp (Manila)</th>
                                            <th>Actor</th>
                                            <th>Event Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($h = $history_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="small"><?php echo format_datetime($h['created_at']); ?></td>
                                                <td class="small fw-semibold"><?php echo e($h['actor_name']); ?></td>
                                                <td class="small"><?php echo e($h['event_description']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr class="small text-secondary">
                                            <th>Timestamp (Manila)</th>
                                            <th>Actor</th>
                                            <th>Activity Type</th>
                                            <th>Details / Changes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($act = $activity_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="small"><?php echo format_datetime($act['created_at']); ?></td>
                                                <td class="small fw-semibold"><?php echo e($act['actor_name']); ?></td>
                                                <td class="small"><span class="badge bg-secondary"><?php echo e($act['activity_type']); ?></span></td>
                                                <td class="small"><?php echo e($act['new_value'] ?? 'N/A'); ?></td>
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