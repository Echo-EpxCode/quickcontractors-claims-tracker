<?php
/**
 * Operational Dashboard Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$role = current_user_role();
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Philippine current date
$current_date = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$upcoming_limit_date = (new DateTime('now', new DateTimeZone('Asia/Manila')))->modify('+7 days')->format('Y-m-d');

// Build role-based restriction clauses for queries
$role_where_claims = "";
$role_where_fu = "";
$params = [];
$types = "";

if ($role === 'Claim_Adjuster') {
    $role_where_claims = " AND c.assigned_handler_id = ?";
    $role_where_fu = " AND fu.handler_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

// -------------------------------------------------------------------------
// 1. CALCULATE REQUIRED METRICS FROM AUTHORITATIVE DATABASE RECORDS
// -------------------------------------------------------------------------

// Metric 1: Open Claims (Status is Open, In_Progress, or Waiting)
$open_claims_sql = "SELECT COUNT(DISTINCT c.id) AS count 
                    FROM claims c 
                    JOIN claim_statuses cs ON c.status_id = cs.id 
                    WHERE cs.status_name IN ('Open', 'In_Progress', 'Waiting') $role_where_claims";
$stmt = $conn->prepare($open_claims_sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$open_claims_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Metric 2: Overdue Follow-ups (Current Scheduled follow-up due before today)
$overdue_sql = "SELECT COUNT(DISTINCT c.id) AS count 
                FROM claims c
                JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                WHERE fu.due_date < ? $role_where_claims";
$stmt = $conn->prepare($overdue_sql);
$stmt->bind_param("s" . $types, $current_date, ...$params);
$stmt->execute();
$overdue_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Metric 3: Due Today (Current Scheduled follow-up due on today's Philippine date)
$due_today_sql = "SELECT COUNT(DISTINCT c.id) AS count 
                  FROM claims c
                  JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                  WHERE fu.due_date = ? $role_where_claims";
$stmt = $conn->prepare($due_today_sql);
$stmt->bind_param("s" . $types, $current_date, ...$params);
$stmt->execute();
$due_today_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Metric 4: Upcoming Follow-ups (Current Scheduled follow-up due after today through +7 days)
$upcoming_sql = "SELECT COUNT(DISTINCT c.id) AS count 
                 FROM claims c
                 JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                 WHERE fu.due_date > ? AND fu.due_date <= ? $role_where_claims";
$stmt = $conn->prepare($upcoming_sql);
$stmt->bind_param("ss" . $types, $current_date, $upcoming_limit_date, ...$params);
$stmt->execute();
$upcoming_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Metric 5: Claims Requiring Attention (Overdue follow-up OR priority Urgent/Escalated)
$attention_sql = "SELECT COUNT(DISTINCT c.id) AS count 
                  FROM claims c
                  LEFT JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                  WHERE (fu.due_date < ? OR c.priority IN ('Urgent', 'Escalated')) $role_where_claims";
$stmt = $conn->prepare($attention_sql);
$stmt->bind_param("s" . $types, $current_date, ...$params);
$stmt->execute();
$attention_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// -------------------------------------------------------------------------
// 2. FETCH CLAIMS REQUIRING ATTENTION TABLE LIST
// -------------------------------------------------------------------------
$attention_list_sql = "SELECT DISTINCT
                            c.id, 
                            c.job_id, 
                            s.store_name, 
                            ct.type_name AS claim_type, 
                            cs.status_name AS claim_status, 
                            c.priority, 
                            fu.due_date AS next_follow_up_date,
                            u.full_name AS assigned_handler,
                            c.created_at
                       FROM claims c
                       JOIN stores s ON c.store_id = s.id
                       JOIN claim_types ct ON c.claim_type_id = ct.id
                       JOIN claim_statuses cs ON c.status_id = cs.id
                       JOIN users u ON c.assigned_handler_id = u.id
                       LEFT JOIN follow_ups fu ON c.id = fu.claim_id AND fu.status = 'Scheduled'
                       WHERE (fu.due_date < ? OR c.priority IN ('Urgent', 'Escalated')) $role_where_claims
                       ORDER BY c.created_at DESC 
                       LIMIT 10";

$stmt = $conn->prepare($attention_list_sql);
$stmt->bind_param("s" . $types, $current_date, ...$params);
$stmt->execute();
$attention_claims = $stmt->get_result();
$stmt->close();

$page_title = 'Operational Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 250px;">
            <!-- Top Banner / Welcome -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold text-dark mb-1">
                        <i class="fa-solid fa-chart-line text-primary me-2"></i> Operational Dashboard
                    </h2>
                    <p class="text-muted small mb-0">Welcome back, <strong><?php echo e($full_name); ?></strong> (Role: <?php echo e(str_replace('_', ' ', $role)); ?>)</p>
                </div>
                <div>
                    <span class="badge bg-white text-dark border px-3 py-2 shadow-sm">
                        <i class="fa-regular fa-calendar me-1 text-primary"></i> Philippine Date: <?php echo e($current_date); ?>
                    </span>
                </div>
            </div>

            <!-- Required Metrics Cards (6.1) -->
            <div class="row g-3 mb-4">
                <!-- 1. Open Claims -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Open Claims</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($open_claims_count); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- 2. Overdue Follow-ups -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 border-start border-danger border-4">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Overdue Follow-ups</div>
                            <h3 class="fw-bold text-danger mb-0"><?php echo number_format($overdue_count); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- 3. Due Today -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Due Today</div>
                            <h3 class="fw-bold text-warning mb-0"><?php echo number_format($due_today_count); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- 4. Upcoming Follow-ups -->
                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
                        <div class="card-body">
                            <div class="text-muted small fw-semibold text-uppercase mb-1">Upcoming (Next 7 Days)</div>
                            <h3 class="fw-bold text-success mb-0"><?php echo number_format($upcoming_count); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- 5. Claims Requiring Attention -->
                <div class="col-xl-3 col-md-8 col-sm-12">
                    <div class="card border-0 shadow-sm h-100 border-start border-dark border-4 bg-dark text-white">
                        <div class="card-body">
                            <div class="text-white-50 small fw-semibold text-uppercase mb-1">Requires Attention</div>
                            <h3 class="fw-bold text-white mb-0"><?php echo number_format($attention_count); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Claims Requiring Attention Section (6.2) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
                    <h5 class="fw-bold text-dark m-0">
                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Claims Requiring Attention
                    </h5>
                    <a href="<?php echo APP_URL; ?>/claims/index.php" class="btn btn-sm btn-outline-primary">View All Claims</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>Job ID</th>
                                    <th>Store</th>
                                    <th>Claim Type</th>
                                    <th>Assigned Handler</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Next Follow-up</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($attention_claims->num_rows > 0): ?>
                                    <?php while ($row = $attention_claims->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <a href="<?php echo APP_URL; ?>/claims/view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                                    <?php echo e($row['job_id']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo e($row['store_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo e($row['claim_type']); ?></span></td>
                                            <td><?php echo e($row['assigned_handler']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo e(str_replace('_', ' ', $row['claim_status'])); ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $prio_badge = in_array($row['priority'], ['Urgent', 'Escalated']) ? 'bg-danger' : 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $prio_badge; ?>"><?php echo e($row['priority']); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['next_follow_up_date'])): ?>
                                                    <?php if ($row['next_follow_up_date'] < $current_date): ?>
                                                        <span class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($row['next_follow_up_date']); ?> (Overdue)</span>
                                                    <?php else: ?>
                                                        <span><?php echo e($row['next_follow_up_date']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?php echo APP_URL; ?>/claims/view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fa-solid fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-check-circle fa-2x text-success mb-2"></i>
                                            <p class="mb-0">No claims currently require immediate attention.</p>
                                        </td>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>