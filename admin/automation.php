<?php
/**
 * WaMark - Automation Overview (Admin)
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Automation';

$automations = $db->fetchAll(
    "SELECT a.*, u.name as user_name FROM " . $db->table('automations') . " a 
     LEFT JOIN " . $db->table('users') . " u ON a.user_id = u.id 
     ORDER BY a.created_at DESC"
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <?php foreach ($automations as $auto): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($auto['name']) ?></h6>
                    <span class="badge bg-<?= $auto['status']==='active'?'success':($auto['status']==='draft'?'secondary':'warning') ?>"><?= ucfirst($auto['status']) ?></span>
                </div>
                <p class="text-muted small mb-2">Type: <?= ucfirst(str_replace('_',' ',$auto['type'])) ?></p>
                <p class="text-muted small mb-0">Trigger: <?= ucfirst($auto['trigger_type']) ?></p>
                <hr>
                <div class="d-flex justify-content-between small text-muted">
                    <span><i class="bi bi-people"></i> <?= $auto['total_enrolled'] ?> enrolled</span>
                    <span><i class="bi bi-check-circle"></i> <?= $auto['total_completed'] ?> completed</span>
                </div>
                <small class="text-muted">Owner: <?= sanitize($auto['user_name'] ?? 'System') ?></small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($automations)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-robot text-muted" style="font-size:48px;"></i>
                <h5 class="mt-3">No Automations</h5>
                <p class="text-muted">Users haven't created any automation workflows yet.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
