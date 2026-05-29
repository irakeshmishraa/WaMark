<?php /** Step 4: Admin Account Setup */ 
// Get DB config from session or temp file
$dbConfig = $_SESSION['installer_db'] ?? null;
if (!$dbConfig || empty($dbConfig['pass'])) {
    $tempFile = dirname(dirname(__DIR__)) . '/config/.db_temp.php';
    if (file_exists($tempFile)) {
        $dbConfig = include $tempFile;
    }
}
?>
<h4 class="mb-4"><i class="bi bi-person-badge text-primary"></i> Create Admin Account</h4>
<p class="text-muted mb-4">Set up your super administrator account.</p>

<form method="POST" id="adminForm">
    <input type="hidden" name="action" value="create_admin">
    
    <!-- Pass DB credentials as hidden fields (session backup) -->
    <?php if ($dbConfig): ?>
    <input type="hidden" name="_db_host" value="<?= htmlspecialchars($dbConfig['host'] ?? 'localhost') ?>">
    <input type="hidden" name="_db_port" value="<?= htmlspecialchars($dbConfig['port'] ?? '3306') ?>">
    <input type="hidden" name="_db_name" value="<?= htmlspecialchars($dbConfig['name'] ?? '') ?>">
    <input type="hidden" name="_db_user" value="<?= htmlspecialchars($dbConfig['user'] ?? '') ?>">
    <input type="hidden" name="_db_pass" value="<?= htmlspecialchars($dbConfig['pass'] ?? '') ?>">
    <input type="hidden" name="_db_prefix" value="<?= htmlspecialchars($dbConfig['prefix'] ?? 'wm_') ?>">
    <?php endif; ?>
    
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-medium">Full Name</label>
            <input type="text" class="form-control" name="admin_name" placeholder="Your Full Name" required>
        </div>
        <div class="col-12">
            <label class="form-label fw-medium">Email Address</label>
            <input type="email" class="form-control" name="admin_email" placeholder="admin@yourdomain.com" required>
            <small class="text-muted">This will be your login email</small>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-medium">Password</label>
            <div class="input-group">
                <input type="password" class="form-control" name="admin_password" id="adminPass" 
                       minlength="8" placeholder="Min. 8 characters" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-medium">Confirm Password</label>
            <input type="password" class="form-control" name="admin_password_confirm" 
                   minlength="8" placeholder="Repeat password" required>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        <i class="bi bi-shield-lock"></i> 
        <strong>Security Tip:</strong> Use a strong password with letters, numbers, and symbols.
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="?step=3" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="submit" class="btn btn-primary">
            Create Admin <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</form>

<script>
function togglePass() {
    const p = document.getElementById('adminPass');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
