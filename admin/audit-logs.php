<?php
/**
 * WaMark - Audit Logs
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'Audit Logs';

$total = $db->count('audit_logs');
$pagination = paginate($total);

$logs = $db->fetchAll(
    "SELECT al.*, u.name as user_name FROM " . $db->table('audit_logs') . " al 
     LEFT JOIN " . $db->table('users') . " u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Action</th><th>Description</th><th>Module</th><th>IP</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><small class="fw-medium"><?= sanitize($log['user_name'] ?? 'System') ?></small></td>
                    <td><span class="badge bg-light text-dark"><?= sanitize($log['action']) ?></span></td>
                    <td><small><?= sanitize($log['description'] ?? '') ?></small></td>
                    <td><small><?= sanitize($log['module']) ?></small></td>
                    <td><code class="small"><?= $log['ip_address'] ?></code></td>
                    <td><small><?= format_datetime($log['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No audit logs</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'audit-logs.php') ?></div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
