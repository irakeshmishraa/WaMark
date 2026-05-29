<?php
/**
 * WaMark - Message Templates (Admin)
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Message Templates';

$templates = $db->fetchAll(
    "SELECT t.*, u.name as user_name FROM " . $db->table('templates') . " t 
     LEFT JOIN " . $db->table('users') . " u ON t.user_id = u.id 
     ORDER BY t.created_at DESC"
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between">
        <h6 class="mb-0">All Templates</h6>
        <span class="text-muted small"><?= count($templates) ?> templates</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Category</th><th>Language</th><th>Owner</th><th>API Status</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td class="fw-medium"><?= sanitize($t['name']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= ucfirst($t['category']) ?></span></td>
                    <td><?= strtoupper($t['language']) ?></td>
                    <td><small><?= sanitize($t['user_name'] ?? 'System') ?></small></td>
                    <td>
                        <?php if ($t['wa_status']): ?>
                        <span class="badge bg-<?= $t['wa_status']==='approved'?'success':($t['wa_status']==='rejected'?'danger':'warning') ?>"><?= ucfirst($t['wa_status']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $t['status']==='active'?'success':'secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No templates found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
