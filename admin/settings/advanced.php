<?php /** Advanced Settings Tab */ ?>
<h5 class="fw-bold mb-4">Advanced Settings</h5>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Google Analytics ID</label>
        <input type="text" class="form-control" name="settings[google_analytics]" value="<?= sanitize(get_setting('google_analytics', '')) ?>" placeholder="G-XXXXXXXXXX">
    </div>
    <div class="col-md-6">
        <label class="form-label">Facebook Pixel ID</label>
        <input type="text" class="form-control" name="settings[facebook_pixel]" value="<?= sanitize(get_setting('facebook_pixel', '')) ?>">
    </div>
    <div class="col-12"><hr></div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="settings[backup_enabled]" value="1" <?= get_setting('backup_enabled', '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label">Enable Automatic Backups</label>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Backup Retention (days)</label>
        <input type="number" class="form-control" name="settings[backup_retention_days]" value="<?= sanitize(get_setting('backup_retention_days', '30')) ?>">
    </div>
    <div class="col-12"><hr></div>
    <div class="col-12">
        <label class="form-label">Custom Header Code (JS/CSS)</label>
        <textarea class="form-control font-monospace" name="settings[custom_header_code]" rows="4" placeholder="<!-- Custom tracking codes, stylesheets -->"><?= sanitize(get_setting('custom_header_code', '')) ?></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Custom Footer Code</label>
        <textarea class="form-control font-monospace" name="settings[custom_footer_code]" rows="4" placeholder="<!-- Custom scripts -->"><?= sanitize(get_setting('custom_footer_code', '')) ?></textarea>
    </div>
</div>

<div class="alert alert-warning mt-4">
    <i class="bi bi-exclamation-triangle"></i> <strong>Danger Zone:</strong> 
    <a href="?tab=advanced&action=clear_cache" class="text-danger ms-2">Clear Cache</a> | 
    <a href="?tab=advanced&action=clear_logs" class="text-danger ms-2">Clear Logs</a>
</div>
