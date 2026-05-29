<?php
/**
 * WaMark - WhatsApp Accounts Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'WhatsApp Accounts';

$accounts = $db->fetchAll(
    "SELECT wa.*, u.name as user_name FROM " . $db->table('whatsapp_accounts') . " wa 
     LEFT JOIN " . $db->table('users') . " u ON wa.user_id = u.id 
     ORDER BY wa.created_at DESC"
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <?php foreach ($accounts as $acc): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-whatsapp text-success fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold"><?= sanitize($acc['name']) ?></h6>
                        <small class="text-muted"><?= sanitize($acc['phone_number'] ?? 'Not connected') ?></small>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="badge bg-<?= $acc['status']==='connected'?'success':($acc['status']==='expired'?'warning':'secondary') ?>">
                        <?= ucfirst($acc['status']) ?>
                    </span>
                    <small class="text-muted">Owner: <?= sanitize($acc['user_name'] ?? 'System') ?></small>
                </div>
                <div class="mt-2 small text-muted">
                    Mode: <?= strtoupper(str_replace('_', ' ', $acc['mode'])) ?>
                    <?php if ($acc['last_connected_at']): ?>
                    | Last: <?= time_ago($acc['last_connected_at']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($accounts)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-whatsapp text-muted" style="font-size:48px;"></i>
                <h5 class="mt-3">No WhatsApp Accounts</h5>
                <p class="text-muted">Users haven't connected any WhatsApp accounts yet.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
