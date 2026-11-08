<?php
/**
 * Authentication Login Page
 * QuickContractors Claims Tracker System V1.0
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect(APP_URL . '/dashboard.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_csrf)) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
            // if (!$conn instanceof mysqli) {
            //     die('Database connection is not a valid MySQLi object.');
            // }
        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE username = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    // Check if account is active
                    if ((int)$user['is_active'] === 0) {
                        $error_message = 'This account has been deactivated. Please contact the Director.';
                    } elseif (password_verify($password, $user['password_hash'])) {
                        // Successful authentication: regenerate session ID
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];

                        redirect(APP_URL . '/dashboard.php');
                    } else {
                        // Generic error to prevent user enumeration
                        $error_message = 'Invalid username or password.';
                    }
                } else {
                    $error_message = 'Invalid username or password.';
                }
                $stmt->close();
            } else {
                $error_message = 'A system error occurred. Please try again later.';
            }
        }
    }
}

// Handle GET error parameters
if (isset($_GET['error']) && $_GET['error'] === 'inactive') {
    $error_message = 'Your session has expired because your account was deactivated.';
}

$page_title = 'Login';
include __DIR__ . '/../includes/header.php';
?>

<div class="container d-flex align-items-center justify-content-center min-vh-100" style="background-color: #f4f6f9;">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 420px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="bg-dark text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background-color: #343a40 !important;">
                    <i class="fa-solid fa-helmet-safety fa-2x"></i>
                </div>
                <h4 class="fw-bold text-dark">QC Claims Tracker</h4>
                <p class="text-muted small">Sign in to your account</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo e($error_message); ?>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus value="<?php echo e($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="py-2 btn btn-primary fw-semibold" style="background-color: #0d6efd; border-color: #0d6efd;">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer bg-light text-center py-3 border-0">
            <small class="text-muted">&copy; <?php echo date('Y'); ?> QuickContractors Claims Tracker V1.0</small>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>