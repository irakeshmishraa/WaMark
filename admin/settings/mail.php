<?php /** Mail/SMTP Settings Tab */ ?>
<h5 class="fw-bold mb-4">Email & SMTP Settings</h5>
<p class="text-muted mb-4">Configure your SMTP settings for sending emails.</p>

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">SMTP Host</label>
        <input type="text" class="form-control" name="settings[smtp_host]" value="<?= sanitize(get_setting('smtp_host', '')) ?>" placeholder="smtp.gmail.com">
    </div>
    <div class="col-md-4">
        <label class="form-label">SMTP Port</label>
        <input type="number" class="form-control" name="settings[smtp_port]" value="<?= sanitize(get_setting('smtp_port', '587')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">SMTP Username</label>
        <input type="text" class="form-control" name="settings[smtp_username]" value="<?= sanitize(get_setting('smtp_username', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">SMTP Password</label>
        <input type="password" class="form-control" name="settings[smtp_password]" value="<?= sanitize(get_setting('smtp_password', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Encryption</label>
        <select class="form-select" name="settings[smtp_encryption]">
            <option value="tls" <?= get_setting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="ssl" <?= get_setting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="none" <?= get_setting('smtp_encryption') === 'none' ? 'selected' : '' ?>>None</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">From Name</label>
        <input type="text" class="form-control" name="settings[mail_from_name]" value="<?= sanitize(get_setting('mail_from_name', APP_NAME)) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">From Email</label>
        <input type="email" class="form-control" name="settings[mail_from_email]" value="<?= sanitize(get_setting('mail_from_email', '')) ?>" placeholder="noreply@yourdomain.com">
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle"></i> After saving, you can <a href="?tab=mail&test=1">send a test email</a> to verify your configuration.
</div>
