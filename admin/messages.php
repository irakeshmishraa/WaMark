<?php
/**
 * WaMark - Message Logs
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Message Logs';

$statusFilter = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($statusFilter) { $where .= ' AND m.status = ?'; $params[] = $statusFilter; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " m WHERE {$where}", $params);
$pagination = paginate($total);

$messages = $db->fetchAll(
    "SELECT m.*, u.name as user_name, c.name as contact_name 
     FROM " . $db->table('messages') . " m 
     LEFT JOIN " . $db->table('users') . " u ON m.user_id = u.id 
     LEFT JOIN " . $db->table('contacts') . " c ON m.contact_id = c.id 
     WHERE {$where} ORDER BY m.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="messages.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <a href="messages.php?status=sent" class="btn btn-sm btn-outline-success">Sent</a>
    <a href="messages.php?status=delivered" class="btn btn-sm btn-outline-info">Delivered</a>
    <a href="messages.php?status=read" class="btn btn-sm btn-outline-primary">Read</a>
    <a href="messages.php?status=failed" class="btn btn-sm btn-outline-danger">Failed</a>
    <a href="messages.php?status=queued" class="btn btn-sm btn-outline-warning">Queued</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Recipient</th><th>Message</th><th>Type</th><th>Status</th><th>Sent By</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                <tr>
                    <td>
                        <div class="fw-medium small"><?= sanitize($m['contact_name'] ?? $m['phone']) ?></div>
                        <code class="small text-muted"><?= $m['phone'] ?></code>
                    </td>
                    <td><small class="text-truncate d-inline-block" style="max-width:200px;"><?= sanitize(substr($m['message_body'] ?? '', 0, 80)) ?></small></td>
                    <td><span class="badge bg-light text-dark small"><?= ucfirst($m['message_type']) ?></span></td>
                    <td><span class="badge bg-<?= match($m['status']) { 'sent'=>'success','delivered'=>'info','read'=>'primary','failed'=>'danger','queued'=>'warning',default=>'secondary' } ?>"><?= ucfirst($m['status']) ?></span></td>
                    <td><small><?= sanitize($m['user_name'] ?? '—') ?></small></td>
                    <td><small><?= time_ago($m['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($messages)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No messages found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'messages.php') ?></div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
