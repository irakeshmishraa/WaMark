<?php
/**
 * WaMark - User Registration
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

if (Auth::check()) {
    redirect(Auth::isClient() ? USER_URL : ADMIN_URL);
}

// Check if registration is enabled
if (get_setting('registration_enabled', '1') !== '1') {
    flash('error', 'Registration is currently disabled.');
    redirect(ADMIN_URL . '/login.php');
}

$authTitle = 'Register';
$authSubtitle = 'Create your account';

if (request_method() === 'POST') {
    verify_csrf();
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        flash('error', 'All fields are required.');
    } elseif (!is_valid_email($email)) {
        flash('error', 'Invalid email address.');
    } elseif (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } elseif ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
    } elseif ($db->exists('users', 'email = ?', [$email])) {
        flash('error', 'An account with this email already exists.');
    } else {
        // Get default plan
        $defaultPlanId = (int)get_setting('default_plan', 1);
        $plan = $db->fetch("SELECT * FROM " . $db->table('plans') . " WHERE id = ?", [$defaultPlanId]);
        
        $expiresAt = null;
        if ($plan && $plan['trial_days'] > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $plan['trial_days'] . ' days'));
        }

        $userId = $db->insert('users', [
            'uuid' => uuid_v4(),
            'name' => $name,
            'email' => $email,
            'password' => hash_password($password),
            'phone' => $phone,
            'role' => 'client',
            'status' => get_setting('email_verification', '1') === '1' ? 'pending' : 'active',
            'plan_id' => $defaultPlanId,
            'subscription_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($userId) {
            // Create subscription record
            if ($plan) {
                $db->insert('subscriptions', [
                    'user_id' => $userId,
                    'plan_id' => $defaultPlanId,
                    'status' => 'active',
                    'amount' => 0,
                    'starts_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Send welcome email
            $mailer = new Mailer();
            $mailer->sendTemplate($email, 'welcome', ['name' => $name]);

            log_activity($userId, 'register', 'New user registered', 'auth');
            flash('success', 'Account created successfully! Please login.');
            redirect(ADMIN_URL . '/login.php');
        } else {
            flash('error', 'Registration failed. Please try again.');
        }
    }
    redirect(ADMIN_URL . '/register.php');
}

include ASSETS_PATH . 'templates/auth_layout.php';
?>

<form method="POST" action="">
    <?= csrf_field() ?>
    
    <div class="mb-3">
        <label class="form-label fw-medium">Full Name</label>
        <input type="text" class="form-control" name="name" placeholder="John Doe" required>
    </div>

    <div class="mb-3">
        <label class="form-label fw-medium">Email Address</label>
        <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
    </div>

    <div class="mb-3">
        <label class="form-label fw-medium">Phone (Optional)</label>
        <input type="tel" class="form-control" name="phone" placeholder="+1234567890">
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <label class="form-label fw-medium">Password</label>
            <input type="password" class="form-control" name="password" minlength="8" placeholder="Min 8 chars" required>
        </div>
        <div class="col-6">
            <label class="form-label fw-medium">Confirm</label>
            <input type="password" class="form-control" name="password_confirm" placeholder="Repeat" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Create Account
    </button>
</form>

<p class="text-center mt-3 small">
    Already have an account? <a href="login.php" class="fw-medium">Sign In</a>
</p>

<?php include ASSETS_PATH . 'templates/auth_footer.php'; ?>
