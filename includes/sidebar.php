<?php
/**
 * Role-Based Navigation Sidebar Component
 * QuickContractors Claims Tracker System V1.0
 */
$role = current_user_role();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar" class="bg-dark text-white p-3 vh-100 position-fixed" style="width: 230px; background-color: #343a40 !important;">
    <div class="sidebar-header pb-3 mb-3 border-bottom border-secondary">
        <h5 class="m-0 text-white"><i class="fa-solid fa-helmet-safety me-2 text-primary"></i>QC Claims Tracker</h5>
        <small class="text-muted">Role: <?php echo e(str_replace('_', ' ', $role ?? 'Guest')); ?></small>
    </div>
    <ul class="nav flex-column nav-pills">
        <li class="nav-item mb-1">
            <a href="<?php echo APP_URL; ?>/dashboard.php" class="nav-link text-white <?php echo ($current_page === 'dashboard.php') ? 'active bg-primary' : ''; ?>">
                <i class="fa-solid fa-chart-line me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="<?php echo APP_URL; ?>/claims/index.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/claims/') !== false) ? 'active bg-primary' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i> Claims
            </a>
        </li>
        <?php if ($role === 'Director' || $role === 'Supervisor'): ?>
        <li class="nav-item mb-1">
            <a href="<?php echo APP_URL; ?>/guidance/index.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/guidance/') !== false) ? 'active bg-primary' : ''; ?>">
                <i class="fa-solid fa-book-bookmark me-2"></i> Claim Guidance
            </a>
        </li>
        <li class="nav-item mb-1">
            <a href="<?php echo APP_URL; ?>/stores/index.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/stores/') !== false) ? 'active bg-primary' : ''; ?>">
                <i class="fa-solid fa-store me-2"></i> Stores
            </a>
        </li>
        <?php endif; ?>
        <?php if ($role === 'Director'): ?>
        <li class="nav-item mb-1">
            <a href="<?php echo APP_URL; ?>/users/index.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/users/') !== false) ? 'active bg-primary' : ''; ?>">
                <i class="fa-solid fa-users-gear me-2"></i> User Management
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-footer mt-auto pt-4 position-absolute bottom-0 pb-3">
        <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-outline-light btn-sm w-100">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>
</nav>