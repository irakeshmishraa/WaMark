<?php
/**
 * WaMark - System Settings
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'System Settings';
$tab = $_GET['tab'] ?? 'general';

// Handle save
if (request_method() === 'POST') {
    verify_csrf();
    $settings = $_POST['settings'] ?? [];
    
    foreach ($settings as $key => $value) {
        update_setting($key, $value);
    }
    
    log_activity(Auth::id(), 'settings_update', "Updated {$tab} settings", 'settings');
    flash('success', 'Settings saved successfully.');
    redirect(ADMIN_URL . '/settings.php?tab=' . $tab);
}

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-2">
                <nav class="nav nav-pills flex-column">
                    <a class="nav-link <?= $tab==='general' ? 'active' : '' ?>" href="?tab=general"><i class="bi bi-gear me-2"></i>General</a>
                    <a class="nav-link <?= $tab==='mail' ? 'active' : '' ?>" href="?tab=mail"><i class="bi bi-envelope me-2"></i>Email / SMTP</a>
                    <a class="nav-link <?= $tab==='whatsapp' ? 'active' : '' ?>" href="?tab=whatsapp"><i class="bi bi-whatsapp me-2"></i>WhatsApp API</a>
                    <a class="nav-link <?= $tab==='payments' ? 'active' : '' ?>" href="?tab=payments"><i class="bi bi-credit-card me-2"></i>Payment Gateways</a>
                    <a class="nav-link <?= $tab==='billing' ? 'active' : '' ?>" href="?tab=billing"><i class="bi bi-receipt me-2"></i>Billing</a>
                    <a class="nav-link <?= $tab==='security' ? 'active' : '' ?>" href="?tab=security"><i class="bi bi-shield me-2"></i>Security</a>
                    <a class="nav-link <?= $tab==='advanced' ? 'active' : '' ?>" href="?tab=advanced"><i class="bi bi-sliders me-2"></i>Advanced</a>
                </nav>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="card-body">
                    <?php include __DIR__ . "/settings/{$tab}.php"; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
