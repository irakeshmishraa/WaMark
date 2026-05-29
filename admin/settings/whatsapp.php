<?php /** WhatsApp API Settings Tab */ ?>
<h5 class="fw-bold mb-4">WhatsApp API Configuration</h5>
<p class="text-muted mb-4">Configure WhatsApp Business Cloud API settings.</p>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">API Mode</label>
        <select class="form-select" name="settings[wa_api_mode]">
            <option value="cloud" <?= get_setting('wa_api_mode', 'cloud') === 'cloud' ? 'selected' : '' ?>>WhatsApp Cloud API</option>
            <option value="non_api" <?= get_setting('wa_api_mode') === 'non_api' ? 'selected' : '' ?>>Non-API (QR/Session)</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Default Delay Between Messages (sec)</label>
        <input type="number" class="form-control" name="settings[wa_default_delay]" value="<?= sanitize(get_setting('wa_default_delay', '2')) ?>">
    </div>
    
    <div class="col-12"><hr><h6 class="fw-bold">Cloud API Settings (Meta)</h6></div>
    <div class="col-12">
        <label class="form-label">Access Token</label>
        <input type="text" class="form-control" name="settings[wa_cloud_token]" value="<?= sanitize(get_setting('wa_cloud_token', '')) ?>" placeholder="EAABx...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone Number ID</label>
        <input type="text" class="form-control" name="settings[wa_phone_number_id]" value="<?= sanitize(get_setting('wa_phone_number_id', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Business Account ID</label>
        <input type="text" class="form-control" name="settings[wa_business_account_id]" value="<?= sanitize(get_setting('wa_business_account_id', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Webhook Verify Token</label>
        <input type="text" class="form-control" name="settings[wa_webhook_verify_token]" value="<?= sanitize(get_setting('wa_webhook_verify_token', '')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Webhook URL</label>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= API_URL ?>/webhook.php" readonly>
            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= API_URL ?>/webhook.php')"><i class="bi bi-clipboard"></i></button>
        </div>
    </div>

    <div class="col-12"><hr><h6 class="fw-bold">Messaging Limits</h6></div>
    <div class="col-md-4">
        <label class="form-label">Batch Size</label>
        <input type="number" class="form-control" name="settings[wa_batch_size]" value="<?= sanitize(get_setting('wa_batch_size', '50')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Max Retries</label>
        <input type="number" class="form-control" name="settings[wa_max_retries]" value="<?= sanitize(get_setting('wa_max_retries', '3')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Rate Limit (msg/min)</label>
        <input type="number" class="form-control" name="settings[wa_rate_limit]" value="<?= sanitize(get_setting('wa_rate_limit', '30')) ?>">
    </div>
</div>
