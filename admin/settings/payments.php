<?php /** Payment Gateway Settings Tab */ ?>
<h5 class="fw-bold mb-4">Payment Gateways</h5>
<p class="text-muted mb-4">Configure payment gateway credentials.</p>

<div class="accordion" id="paymentAccordion">
    <!-- Stripe -->
    <div class="accordion-item border-0 mb-3 shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#stripe">Stripe</button>
        </h2>
        <div id="stripe" class="accordion-collapse collapse show" data-bs-parent="#paymentAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Publishable Key</label><input type="text" class="form-control" name="settings[stripe_key]" value="<?= sanitize(get_setting('stripe_key', '')) ?>" placeholder="pk_..."></div>
                    <div class="col-md-6"><label class="form-label">Secret Key</label><input type="password" class="form-control" name="settings[stripe_secret]" value="<?= sanitize(get_setting('stripe_secret', '')) ?>" placeholder="sk_..."></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="settings[stripe_enabled]" value="1" <?= get_setting('stripe_enabled') === '1' ? 'checked' : '' ?>><label class="form-check-label">Enable Stripe</label></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Razorpay -->
    <div class="accordion-item border-0 mb-3 shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#razorpay">Razorpay</button>
        </h2>
        <div id="razorpay" class="accordion-collapse collapse" data-bs-parent="#paymentAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Key ID</label><input type="text" class="form-control" name="settings[razorpay_key]" value="<?= sanitize(get_setting('razorpay_key', '')) ?>" placeholder="rzp_..."></div>
                    <div class="col-md-6"><label class="form-label">Key Secret</label><input type="password" class="form-control" name="settings[razorpay_secret]" value="<?= sanitize(get_setting('razorpay_secret', '')) ?>"></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="settings[razorpay_enabled]" value="1" <?= get_setting('razorpay_enabled') === '1' ? 'checked' : '' ?>><label class="form-check-label">Enable Razorpay</label></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PayPal -->
    <div class="accordion-item border-0 mb-3 shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#paypal">PayPal</button>
        </h2>
        <div id="paypal" class="accordion-collapse collapse" data-bs-parent="#paymentAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Client ID</label><input type="text" class="form-control" name="settings[paypal_client_id]" value="<?= sanitize(get_setting('paypal_client_id', '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Client Secret</label><input type="password" class="form-control" name="settings[paypal_secret]" value="<?= sanitize(get_setting('paypal_secret', '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Mode</label><select class="form-select" name="settings[paypal_mode]"><option value="sandbox" <?= get_setting('paypal_mode','sandbox')==='sandbox'?'selected':'' ?>>Sandbox</option><option value="live" <?= get_setting('paypal_mode')==='live'?'selected':'' ?>>Live</option></select></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="settings[paypal_enabled]" value="1" <?= get_setting('paypal_enabled') === '1' ? 'checked' : '' ?>><label class="form-check-label">Enable PayPal</label></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- PayU -->
    <div class="accordion-item border-0 mb-3 shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#payu">PayU</button>
        </h2>
        <div id="payu" class="accordion-collapse collapse" data-bs-parent="#paymentAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Merchant Key</label><input type="text" class="form-control" name="settings[payu_merchant_key]" value="<?= sanitize(get_setting('payu_merchant_key', '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Salt</label><input type="password" class="form-control" name="settings[payu_salt]" value="<?= sanitize(get_setting('payu_salt', '')) ?>"></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="settings[payu_enabled]" value="1" <?= get_setting('payu_enabled') === '1' ? 'checked' : '' ?>><label class="form-check-label">Enable PayU</label></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
