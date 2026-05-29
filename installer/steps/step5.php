<?php /** Step 5: Installation Complete */ ?>
<div class="text-center">
    <div class="success-icon">
        <i class="bi bi-check-lg"></i>
    </div>
    <h3 class="mb-3 text-success">Installation Complete!</h3>
    <p class="text-muted mb-4">
        WaMark has been successfully installed and configured.<br>
        Your platform is ready to use.
    </p>
    
    <div class="bg-light rounded-3 p-4 mb-4 text-start">
        <h6 class="fw-bold mb-3"><i class="bi bi-lightning text-warning"></i> Next Steps:</h6>
        <ol class="mb-0">
            <li class="mb-2">Login to your admin dashboard</li>
            <li class="mb-2">Configure your WhatsApp API settings</li>
            <li class="mb-2">Set up SMTP for email notifications</li>
            <li class="mb-2">Configure payment gateways</li>
            <li class="mb-2">Customize your white label branding</li>
        </ol>
    </div>

    <div class="alert alert-warning text-start">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Security:</strong> For security reasons, please delete or rename the <code>/installer/</code> directory after installation.
    </div>

    <div class="bg-light rounded-3 p-3 mb-4 text-start">
        <h6 class="fw-bold mb-2"><i class="bi bi-clock"></i> Cron Job Setup:</h6>
        <p class="small text-muted mb-2">Add these cron jobs to your server for automated tasks:</p>
        <code class="d-block small bg-dark text-light p-2 rounded mb-1">
            * * * * * php <?= dirname(dirname(__DIR__)) ?>/WaMark/cron/run.php >> /dev/null 2>&1
        </code>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="finish">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right"></i> Go to Admin Panel
        </button>
    </form>
</div>
