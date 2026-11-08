<?php
/**
 * Claims List and Search Module
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$role = current_user_role();
$user_id = $_SESSION['user_id'];

// Pagination parameters
$limit = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter parameters
$search_job_id = trim($_GET['job_id'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_priority = trim($_GET['priority'] ?? '');
$filter_handler = trim($_GET['handler'] ?? '');
$filter_store = trim($_GET['store'] ?? '');
$filter_party = trim($_GET['party_type'] ?? '');
$filter_fu_state = trim($_GET['fu_state'] ?? ''); // Overdue, Due Today, Upcoming

// Build dynamic WHERE clause securely with prepared statements
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search_job_id)) {
    $where_clauses[] = "c.job_id LIKE ?";
    $params[] = "%" . $search_job_id . "%";
    $types .= "s";
}

if (!empty($filter_status)) {
    $where_clauses[] = "cs.status_name = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_priority)) {
    $where_clauses[] = "c.priority = ?";
    $params[] = $filter_priority;
    $types .= "s";
}

if (!empty($filter_handler)) {
    $where_clauses[] = "c.assigned_handler_id = ?";
    $params[] = (int)$filter_handler;
    $types .= "i";
}

if (!empty($filter_store)) {
    $where_clauses[] = "s.id = ?";
    $params[] = (int)$filter_store;
    $types .= "i";
}

if (!empty($filter_party)) {
    $where_clauses[] = "cp.party_type = ?";
    $params[] = $filter_party;
    $types .= "s";
}

// Philippine Date Logic for follow-up state filters
$current_date = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
if (!empty($filter_fu_state)) {
    if ($filter_fu_state === 'Overdue') {
        $where_clauses[] = "fu_curr.due_date < ? AND fu_curr.status = 'Scheduled'";
        $params[] = $current_date;
        $types .= "s";
    } elseif ($filter_fu_state === 'Due Today') {
        $where_clauses[] = "fu_curr.due_date = ? AND fu_curr.status = 'Scheduled'";
        $params[] = $current_date;
        $types .= "s";
    } elseif ($filter_fu_state === 'Upcoming') {
        $where_clauses[] = "fu_curr.due_date > ? AND fu_curr.status = 'Scheduled'";
        $params[] = $current_date;
        $types .= "s";
    }
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total records for pagination
$count_sql = "SELECT COUNT(DISTINCT c.id) AS total 
              FROM claims c
              JOIN stores s ON c.store_id = s.id
              JOIN claim_types ct ON c.claim_type_id = ct.id
              JOIN claim_statuses cs ON c.status_id = cs.id
              JOIN claim_parties cp ON c.id = cp.claim_id
              LEFT JOIN follow_ups fu_curr ON c.id = fu_curr.claim_id AND fu_curr.status = 'Scheduled'
              $where_sql";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt->close();

// Fetch paginated claims data with the exact 11 required columns[cite: 1, 3]
$data_sql = "SELECT 
                c.id, 
                c.job_id, 
                s.store_name, 
                ct.type_name AS claim_type, 
                cp.party_type, 
                u.full_name AS assigned_handler, 
                cs.status_name AS claim_status, 
                c.priority, 
                fu_curr.due_date AS next_follow_up_date,
                (SELECT MAX(fu_hist.created_at) FROM follow_ups fu_hist WHERE fu_hist.claim_id = c.id) AS last_follow_up_date,
                fu_curr.status AS follow_up_status
             FROM claims c
             JOIN stores s ON c.store_id = s.id
             JOIN claim_types ct ON c.claim_type_id = ct.id
             JOIN claim_statuses cs ON c.status_id = cs.id
             JOIN claim_parties cp ON c.id = cp.claim_id
             JOIN users u ON c.assigned_handler_id = u.id
             LEFT JOIN follow_ups fu_curr ON c.id = fu_curr.claim_id AND fu_curr.status = 'Scheduled'
             $where_sql
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?";

$data_types = $types . "ii";
$data_params = array_merge($params, [$limit, $offset]);

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($data_types, ...$data_params);
$stmt->execute();
$claims_result = $stmt->get_result();

// Fetch filter options for dropdowns
$stores_list = $conn->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name");
$statuses_list = $conn->query("SELECT status_name FROM claim_statuses ORDER BY id");
$handlers_list = $conn->query("SELECT id, full_name FROM users WHERE role = 'Claim_Adjuster' AND is_active = 1 ORDER BY full_name");

$page_title = 'Claims Management';
include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" style="margin-left: 260px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Claims Management</h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-2"></i> Create New Claim
                </a>
            </div>

            <!-- Search & Filters Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body bg-white rounded">
                    <form method="GET" action="index.php" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Job ID Search</label>
                            <input type="text" class="form-control form-control-sm" name="job_id" value="<?php echo e($search_job_id); ?>" placeholder="Enter Job ID...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-secondary">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <?php while ($st = $statuses_list->fetch_assoc()): ?>
                                    <option value="<?php echo e($st['status_name']); ?>" <?php echo ($filter_status === $st['status_name']) ? 'selected' : ''; ?>>
                                        <?php echo e(str_replace('_', ' ', $st['status_name'])); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-secondary">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="">All Priorities</option>
                                <?php foreach (['Non-Urgent', 'Low', 'Normal', 'High', 'Urgent', 'Escalated'] as $prio): ?>
                                    <option value="<?php echo $prio; ?>" <?php echo ($filter_priority === $prio) ? 'selected' : ''; ?>><?php echo $prio; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-secondary">Follow-Up State</label>
                            <select name="fu_state" class="form-select form-select-sm">
                                <option value="">All States</option>
                                <option value="Overdue" <?php echo ($filter_fu_state === 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Due Today" <?php echo ($filter_fu_state === 'Due Today') ? 'selected' : ''; ?>>Due Today</option>
                                <option value="Upcoming" <?php echo ($filter_fu_state === 'Upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-dark me-2 w-100">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                            <a href="index.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Claims Table (11 Required Columns) -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 font-roboto">
                            <thead class="table-dark text-white" style="background-color: #343a40;">
                                <tr class="small text-uppercase">
                                    <th>1. Job ID</th>
                                    <th>2. Store</th>
                                    <th>3. Claim Type</th>
                                    <th>4. Party Type</th>
                                    <th>5. Assigned Handler</th>
                                    <th>6. Claim Status</th>
                                    <th>7. Priority</th>
                                    <th>8. Next Follow-up</th>
                                    <th>9. Last Follow-up</th>
                                    <th>10. FU Status</th>
                                    <th class="text-end">11. Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($claims_result->num_rows > 0): ?>
                                    <?php while ($claim = $claims_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold text-primary">
                                                <a href="view.php?id=<?php echo $claim['id']; ?>" class="text-decoration-none">
                                                    <?php echo e($claim['job_id']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo e($claim['store_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo e($claim['claim_type']); ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo e(str_replace('_', ' ', $claim['party_type'])); ?></span></td>
                                            <td><?php echo e($claim['assigned_handler']); ?></td>
                                            <td>
                                                <?php 
                                                    $status_badge = match($claim['claim_status']) {
                                                        'Open' => 'bg-info text-dark',
                                                        'In_Progress' => 'bg-primary',
                                                        'Waiting' => 'bg-warning text-dark',
                                                        'Resolved' => 'bg-success',
                                                        'Closed' => 'bg-secondary',
                                                        default => 'bg-secondary'
                                                    };
                                                ?>
                                                <span class="badge <?php echo $status_badge; ?>"><?php echo e(str_replace('_', ' ', $claim['claim_status'])); ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                    $prio_badge = match($claim['priority']) {
                                                        'Escalated', 'Urgent' => 'bg-danger',
                                                        'High' => 'bg-warning text-dark',
                                                        'Normal' => 'bg-primary',
                                                        default => 'bg-secondary'
                                                    };
                                                ?>
                                                <span class="badge <?php echo $prio_badge; ?>"><?php echo e($claim['priority']); ?></span>
                                            </td>
                                            <td><?php echo e($claim['next_follow_up_date'] ?? 'None'); ?></td>
                                            <td><?php echo e(format_datetime($claim['last_follow_up_date'])); ?></td>
                                            <td>
                                                <?php if (!empty($claim['next_follow_up_date'])): ?>
                                                    <?php 
                                                        $fu_date = $claim['next_follow_up_date'];
                                                        if ($fu_date < $current_date) {
                                                            echo '<span class="text-danger fw-bold small"><i class="fa-solid fa-circle-exclamation"></i> Overdue</span>';
                                                        } elseif ($fu_date === $current_date) {
                                                            echo '<span class="text-warning fw-bold small"><i class="fa-solid fa-clock"></i> Due Today</span>';
                                                        } else {
                                                            echo '<span class="text-success small">Upcoming</span>';
                                                        }
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">None Scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="view.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Claim Details">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <?php if ($role === 'Supervisor' || $role === 'Director'): ?>
                                                    <a href="assign.php?id=<?php echo $claim['id']; ?>" class="btn btn-sm btn-outline-dark" title="Reassign / Take Over">
                                                        <i class="fa-solid fa-user-gear"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4 text-muted">No claims found matching your search criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Pagination Footer -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (Total: <?php echo $total_records; ?> claims)</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="index.php?page=<?php echo $page - 1; ?>&job_id=<?php echo urlencode($search_job_id); ?>&status=<?php echo urlencode($filter_status); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="index.php?page=<?php echo $i; ?>&job_id=<?php echo urlencode($search_job_id); ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="index.php?page=<?php echo $page + 1; ?>&job_id=<?php echo urlencode($search_job_id); ?>&status=<?php echo urlencode($filter_status); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>