<?php
/**
 * WaMark - Contacts Management (Admin)
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Contacts';

$search = $_GET['search'] ?? '';
$where = '1=1';
$params = [];
if ($search) {
    $where .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}

$total = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('contacts') . " c WHERE {$where}", $params);
$pagination = paginate($total);

$contacts = $db->fetchAll(
    "SELECT c.*, u.name as owner_name FROM " . $db->table('contacts') . " c 
     LEFT JOIN " . $db->table('users') . " u ON c.user_id = u.id 
     WHERE {$where} ORDER BY c.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <form class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" name="search" placeholder="Search contacts..." value="<?= sanitize($search) ?>" style="width:250px;">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
        <span class="text-muted small"><?= number_format($total) ?> total contacts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Contact</th><th>Phone</th><th>Owner</th><th>Status</th><th>Source</th><th>Added</th></tr>
                </thead>
                <tbody>
                <?php foreach ($contacts as $c): ?>
                <tr>
                    <td>
                        <div class="fw-medium"><?= sanitize($c['name'] ?: 'Unknown') ?></div>
                        <small class="text-muted"><?= sanitize($c['email'] ?? '') ?></small>
                    </td>
                    <td><code><?= sanitize($c['phone']) ?></code></td>
                    <td><small><?= sanitize($c['owner_name'] ?? '—') ?></small></td>
                    <td><span class="badge bg-<?= $c['status']==='active'?'success':'secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                    <td><small><?= ucfirst($c['source'] ?? 'manual') ?></small></td>
                    <td><small><?= time_ago($c['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($contacts)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No contacts found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'contacts.php') ?></div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
