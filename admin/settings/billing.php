<?php /** Billing Settings Tab */ ?>
<h5 class="fw-bold mb-4">Billing Settings</h5>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Currency</label>
        <select class="form-select" name="settings[currency]">
            <option value="USD" <?= get_setting('currency','USD')==='USD'?'selected':'' ?>>USD - US Dollar</option>
            <option value="EUR" <?= get_setting('currency')==='EUR'?'selected':'' ?>>EUR - Euro</option>
            <option value="GBP" <?= get_setting('currency')==='GBP'?'selected':'' ?>>GBP - British Pound</option>
            <option value="INR" <?= get_setting('currency')==='INR'?'selected':'' ?>>INR - Indian Rupee</option>
            <option value="AUD" <?= get_setting('currency')==='AUD'?'selected':'' ?>>AUD - Australian Dollar</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Currency Symbol</label>
        <input type="text" class="form-control" name="settings[currency_symbol]" value="<?= sanitize(get_setting('currency_symbol', '$')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Tax Rate (%)</label>
        <input type="number" class="form-control" name="settings[tax_rate]" value="<?= sanitize(get_setting('tax_rate', '0')) ?>" step="0.01">
    </div>
    <div class="col-md-6">
        <label class="form-label">Invoice Prefix</label>
        <input type="text" class="form-control" name="settings[invoice_prefix]" value="<?= sanitize(get_setting('invoice_prefix', 'INV-')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Default Plan (for new users)</label>
        <select class="form-select" name="settings[default_plan]">
            <?php $allPlans = $db->fetchAll("SELECT id, name FROM " . $db->table('plans') . " WHERE is_active = 1"); ?>
            <?php foreach ($allPlans as $p): ?>
            <option value="<?= $p['id'] ?>" <?= get_setting('default_plan') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
