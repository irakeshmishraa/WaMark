<?php
/**
 * WaMark - White Label Configuration
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'White Label Settings';

// Handle save
if (request_method() === 'POST') {
    verify_csrf();
    
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        update_setting($key, $value);
    }

    // Handle logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $result = upload_file($_FILES['logo'], 'branding', ALLOWED_IMAGE_TYPES);
        if (isset($result['success'])) {
            update_setting('site_logo', $result['url']);
        }
    }
    
    // Handle favicon upload
    if (!empty($_FILES['favicon']['tmp_name'])) {
        $result = upload_file($_FILES['favicon'], 'branding', ALLOWED_IMAGE_TYPES);
        if (isset($result['success'])) {
            update_setting('site_favicon', $result['url']);
        }
    }

    log_activity(Auth::id(), 'white_label_update', 'Updated white label settings', 'settings');
    flash('success', 'White label settings saved successfully.');
    redirect(ADMIN_URL . '/white-label.php');
}

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            
            <!-- Branding -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-palette"></i> Branding</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Brand Name</label>
                            <input type="text" class="form-control" name="settings[site_name]" value="<?= sanitize(get_setting('site_name', APP_NAME)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tagline</label>
                            <input type="text" class="form-control" name="settings[site_tagline]" value="<?= sanitize(get_setting('site_tagline', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logo</label>
                            <input type="file" class="form-control" name="logo" accept="image/*">
                            <?php if ($logo = get_setting('site_logo')): ?>
                            <img src="<?= $logo ?>" alt="Logo" class="mt-2" style="max-height:40px;">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Favicon</label>
                            <input type="file" class="form-control" name="favicon" accept="image/*">
                            <?php if ($fav = get_setting('site_favicon')): ?>
                            <img src="<?= $fav ?>" alt="Favicon" class="mt-2" style="max-height:32px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colors -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-brush"></i> Theme Colors</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Primary Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="settings[primary_color]" value="<?= get_setting('primary_color', '#6366f1') ?>">
                                <input type="text" class="form-control" value="<?= get_setting('primary_color', '#6366f1') ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Secondary Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="settings[secondary_color]" value="<?= get_setting('secondary_color', '#8b5cf6') ?>">
                                <input type="text" class="form-control" value="<?= get_setting('secondary_color', '#8b5cf6') ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Theme</label>
                            <select class="form-select" name="settings[theme_mode]">
                                <option value="light" <?= get_setting('theme_mode','light')==='light'?'selected':'' ?>>Light</option>
                                <option value="dark" <?= get_setting('theme_mode')==='dark'?'selected':'' ?>>Dark</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Domain & Login -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-globe"></i> Custom Domain & URLs</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Custom Domain</label>
                            <input type="text" class="form-control" name="settings[custom_domain]" value="<?= sanitize(get_setting('custom_domain', '')) ?>" placeholder="app.yourbrand.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Custom Login URL Path</label>
                            <input type="text" class="form-control" name="settings[custom_login_url]" value="<?= sanitize(get_setting('custom_login_url', '')) ?>" placeholder="/login">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Footer Text</label>
                            <input type="text" class="form-control" name="settings[footer_text]" value="<?= sanitize(get_setting('footer_text', '')) ?>" placeholder="&copy; 2024 Your Brand">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remove Branding -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-eye-slash"></i> Developer Branding</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="settings[hide_branding]" value="1" <?= get_setting('hide_branding') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">Remove all WaMark developer branding</label>
                    </div>
                    <small class="text-muted">When enabled, removes all references to WaMark from the interface.</small>
                </div>
            </div>

            <!-- Custom CSS -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-code-slash"></i> Custom CSS</h6></div>
                <div class="card-body">
                    <textarea class="form-control font-monospace" name="settings[custom_css]" rows="6" placeholder="/* Your custom CSS here */"><?= sanitize(get_setting('custom_css', '')) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg"></i> Save White Label Settings
            </button>
        </form>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Preview</h6></div>
            <div class="card-body text-center">
                <?php if ($logo = get_setting('site_logo')): ?>
                    <img src="<?= $logo ?>" alt="Brand" class="mb-3" style="max-height:50px;">
                <?php else: ?>
                    <i class="bi bi-whatsapp text-success" style="font-size:40px;"></i>
                <?php endif; ?>
                <h5><?= sanitize(get_setting('site_name', APP_NAME)) ?></h5>
                <p class="text-muted small"><?= sanitize(get_setting('site_tagline', '')) ?></p>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <div style="width:30px;height:30px;border-radius:50%;background:<?= get_setting('primary_color', '#6366f1') ?>;"></div>
                    <div style="width:30px;height:30px;border-radius:50%;background:<?= get_setting('secondary_color', '#8b5cf6') ?>;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
