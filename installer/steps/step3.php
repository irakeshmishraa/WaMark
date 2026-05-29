<?php /** Step 3: Database Configuration */ ?>
<h4 class="mb-4"><i class="bi bi-database text-primary"></i> Database Configuration</h4>
<p class="text-muted mb-4">Enter your MySQL database connection details.</p>

<form method="POST" id="dbForm">
    <input type="hidden" name="action" value="setup_database">
    
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label fw-medium">Database Host</label>
            <input type="text" class="form-control" name="db_host" value="localhost" required>
            <small class="text-muted">Usually "localhost" for shared hosting</small>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-medium">Port</label>
            <input type="text" class="form-control" name="db_port" value="3306">
        </div>
        <div class="col-12">
            <label class="form-label fw-medium">Database Name</label>
            <input type="text" class="form-control" name="db_name" placeholder="wamark_db" required>
            <small class="text-muted">Database will be created if it doesn't exist</small>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-medium">Database Username</label>
            <input type="text" class="form-control" name="db_user" placeholder="root" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-medium">Database Password</label>
            <input type="text" class="form-control" name="db_pass" placeholder="Your database password" autocomplete="off">
            <small class="text-muted">Leave empty if no password</small>
        </div>
        <div class="col-12">
            <label class="form-label fw-medium">Table Prefix</label>
            <input type="text" class="form-control" name="db_prefix" value="wm_">
            <small class="text-muted">Useful if sharing database with other applications</small>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-between">
        <a href="?step=2" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="submit" class="btn btn-primary" id="btnSetupDb">
            <span class="spinner-border spinner-border-sm d-none" id="dbSpinner"></span>
            Setup Database <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</form>

<script>
document.getElementById('dbForm').addEventListener('submit', function() {
    document.getElementById('btnSetupDb').disabled = true;
    document.getElementById('dbSpinner').classList.remove('d-none');
});
</script>
