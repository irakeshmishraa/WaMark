<?php /** General Settings Tab */ ?>
<h5 class="fw-bold mb-4">General Settings</h5>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Site Name</label>
        <input type="text" class="form-control" name="settings[site_name]" value="<?= sanitize(get_setting('site_name', 'WaMark')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Site Tagline</label>
        <input type="text" class="form-control" name="settings[site_tagline]" value="<?= sanitize(get_setting('site_tagline', '')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label">Site Description</label>
        <textarea class="form-control" name="settings[site_description]" rows="2"><?= sanitize(get_setting('site_description', '')) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Support Email</label>
        <input type="email" class="form-control" name="settings[support_email]" value="<?= sanitize(get_setting('support_email', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Support Phone</label>
        <input type="text" class="form-control" name="settings[support_phone]" value="<?= sanitize(get_setting('support_phone', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Terms of Service URL</label>
        <input type="url" class="form-control" name="settings[terms_url]" value="<?= sanitize(get_setting('terms_url', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Privacy Policy URL</label>
        <input type="url" class="form-control" name="settings[privacy_url]" value="<?= sanitize(get_setting('privacy_url', '')) ?>">
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="settings[registration_enabled]" value="1" <?= get_setting('registration_enabled', '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Allow User Registration</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="settings[email_verification]" value="1" <?= get_setting('email_verification', '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Require Email Verification</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="settings[maintenance_mode]" value="1" <?= get_setting('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Maintenance Mode</label>
        </div>
    </div>
</div>
