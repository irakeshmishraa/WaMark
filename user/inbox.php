<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';
Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();
$pageTitle = ucwords(str_replace('-', ' ', basename(__FILE__, '.php')));
include ASSETS_PATH . 'templates/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-tools text-muted" style="font-size:48px;"></i>
        <h5 class="mt-3"><?= $pageTitle ?></h5>
        <p class="text-muted">This module is ready for use. Connect your WhatsApp account to get started.</p>
    </div>
</div>
<?php include ASSETS_PATH . 'templates/footer.php'; ?>
