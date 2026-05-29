<?php /** Security Settings Tab */ ?>
<h5 class="fw-bold mb-4">Security Settings</h5>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Max Login Attempts (before lockout)</label>
        <input type="number" class="form-control" name="settings[max_login_attempts]" value="<?= sanitize(get_setting('max_login_attempts', '5')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Lockout Duration (seconds)</label>
        <input type="number" class="form-control" name="settings[lockout_duration]" value="<?= sanitize(get_setting('lockout_duration', '900')) ?>">
        <small class="text-muted">Default: 900 (15 minutes)</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Session Timeout (seconds)</label>
        <input type="number" class="form-control" name="settings[session_timeout]" value="<?= sanitize(get_setting('session_timeout', '1800')) ?>">
        <small class="text-muted">Default: 1800 (30 minutes)</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Password Minimum Length</label>
        <input type="number" class="form-control" name="settings[password_min_length]" value="<?= sanitize(get_setting('password_min_length', '8')) ?>">
    </div>
    <div class="col-12">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="settings[force_2fa]" value="1" <?= get_setting('force_2fa') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Require 2FA for All Admin Users</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="settings[ip_whitelist_enabled]" value="1" <?= get_setting('ip_whitelist_enabled') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Enable IP Whitelist for Admin Panel</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Allowed IPs (one per line)</label>
        <textarea class="form-control" name="settings[allowed_ips]" rows="3" placeholder="192.168.1.1"><?= sanitize(get_setting('allowed_ips', '')) ?></textarea>
    </div>
</div>
