<?php
/**
 * WaMark - Admin Login Page
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

// If not installed, redirect to installer
if (!IS_INSTALLED) {
    header('Location: ' . BASE_URL . '/installer/');
    exit;
}

// If DB connection failed
if (!$db) {
    die('Database connection failed. Please check your .env file configuration or <a href="' . BASE_URL . '/installer/">re-run the installer</a>.');
}

// Redirect if already logged in
if (Auth::check()) {
    redirect(Auth::isClient() ? USER_URL : ADMIN_URL);
}

$authTitle = 'Login';
$authSubtitle = 'Sign in to your account';

// Handle login submission
if (request_method() === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        flash('error', 'Email and password are required.');
    } elseif (!Auth::checkRateLimit($email)) {
        flash('error', 'Too many login attempts. Please try again in 15 minutes.');
    } else {
        $result = Auth::attempt($email, $password, $remember);
        
        if ($result === true) {
            Auth::recordLoginAttempt($email, true);
            $role = Auth::role();
            redirect($role === 'client' ? USER_URL : ADMIN_URL);
        } elseif ($result === '2fa_required') {
            redirect(ADMIN_URL . '/2fa-verify.php');
        } else {
            Auth::recordLoginAttempt($email, false);
            flash('error', 'Invalid email or password.');
        }
    }
    redirect(ADMIN_URL . '/login.php');
}

include ASSETS_PATH . 'templates/auth_layout.php';
?>

<form method="POST" action="">
    <?= csrf_field() ?>
    
    <div class="mb-3">
        <label class="form-label fw-medium">Email Address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" name="email" placeholder="admin@example.com" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between">
            <label class="form-label fw-medium">Password</label>
            <a href="forgot-password.php" class="small text-decoration-none">Forgot Password?</a>
        </div>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" name="password" placeholder="••••••••" required>
        </div>
    </div>

    <div class="mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
    </button>
</form>

<?php if (get_setting('registration_enabled', '1') === '1'): ?>
<p class="text-center mt-3 small">
    Don't have an account? <a href="register.php" class="fw-medium">Sign Up</a>
</p>
<?php endif; ?>

<?php include ASSETS_PATH . 'templates/auth_footer.php'; ?>
