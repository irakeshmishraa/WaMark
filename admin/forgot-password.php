<?php
/**
 * WaMark - Forgot Password
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

$authTitle = 'Reset Password';
$authSubtitle = 'Enter your email to receive a reset link';

if (request_method() === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !is_valid_email($email)) {
        flash('error', 'Please enter a valid email address.');
    } else {
        $user = $db->fetch("SELECT * FROM " . $db->table('users') . " WHERE email = ?", [$email]);
        
        if ($user) {
            $token = random_string(64);
            $db->update('users', [
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$user['id']]);
            
            // Store reset token (using remember_tokens table)
            $db->insert('remember_tokens', [
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $resetUrl = BASE_URL . '/admin/reset-password.php?token=' . $token;
            $mailer = new Mailer();
            $mailer->sendTemplate($email, 'password_reset', [
                'name' => $user['name'],
                'reset_url' => $resetUrl,
            ]);
        }

        // Always show success (prevent email enumeration)
        flash('success', 'If an account with that email exists, a reset link has been sent.');
    }
    redirect(ADMIN_URL . '/forgot-password.php');
}

include ASSETS_PATH . 'templates/auth_layout.php';
?>

<form method="POST" action="">
    <?= csrf_field() ?>
    
    <div class="mb-4">
        <label class="form-label fw-medium">Email Address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" name="email" placeholder="your@email.com" required autofocus>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-send"></i> Send Reset Link
    </button>
</form>

<p class="text-center mt-3 small">
    Remember your password? <a href="login.php" class="fw-medium">Sign In</a>
</p>

<?php include ASSETS_PATH . 'templates/auth_footer.php'; ?>
