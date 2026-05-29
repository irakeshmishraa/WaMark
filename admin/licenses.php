<?php
/**
 * WaMark - License Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'License Management';

// Handle actions
if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'generate') {
        $count = min(50, max(1, (int)$_POST['count']));
        $type = $_POST['type'] ?? 'subscription';
        $maxUsers = (int)($_POST['max_users'] ?? 1);
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null;

        for ($i = 0; $i < $count; $i++) {
            $db->insert('licenses', [
                'license_key' => generate_license_key(),
                'type' => $type,
                'status' => 'unused',
                'max_users' => $maxUsers,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        flash('success', "{$count} license key(s) generated successfully.");
    } elseif ($action === 'revoke') {
        $id = (int)$_POST['license_id'];
        $db->update('licenses', ['status' => 'revoked'], 'id = ?', [$id]);
        flash('success', 'License revoked.');
    }
    redirect(ADMIN_URL . '/licenses.php');
}

$statusFilter = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($statusFilter) { $where .= ' AND status = ?'; $params[] = $statusFilter; }

$total = $db->count('licenses', $where, $params);
$pagination = paginate($total);
$licenses = $db->fetchAll(
    "SELECT l.*, u.name as user_name, u.email as user_email 
     FROM " . $db->table('licenses') . " l 
     LEFT JOIN " . $db->table('users') . " u ON l.user_id = u.id 
     WHERE {$where} ORDER BY l.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <a href="licenses.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All (<?= $db->count('licenses') ?>)</a>
        <a href="licenses.php?status=unused" class="btn btn-sm btn-outline-secondary">Unused</a>
        <a href="licenses.php?status=active" class="btn btn-sm btn-outline-success">Active</a>
        <a href="licenses.php?status=expired" class="btn btn-sm btn-outline-warning">Expired</a>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#genModal">
        <i class="bi bi-key"></i> Generate Keys
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>License Key</th><th>Type</th><th>Status</th><th>Assigned To</th><th>Domain</th><th>Expires</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($licenses as $lic): ?>
                <tr>
                    <td><code class="small"><?= $lic['license_key'] ?></code></td>
                    <td><span class="badge bg-light text-dark"><?= ucfirst($lic['type']) ?></span></td>
                    <td><span class="badge bg-<?= match($lic['status']) { 'active'=>'success','unused'=>'secondary','expired'=>'warning','revoked'=>'danger',default=>'dark' } ?>"><?= ucfirst($lic['status']) ?></span></td>
                    <td><?= $lic['user_name'] ? sanitize($lic['user_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><small><?= $lic['domain'] ?: '—' ?></small></td>
                    <td><small><?= $lic['expires_at'] ? format_date($lic['expires_at']) : 'Never' ?></small></td>
                    <td class="text-end">
                        <?php if ($lic['status'] === 'active'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this license?')">
                            <?= csrf_field() ?><input type="hidden" name="form_action" value="revoke"><input type="hidden" name="license_id" value="<?= $lic['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($licenses)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No licenses found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'licenses.php') ?></div>
    <?php endif; ?>
</div>

<!-- Generate Modal -->
<div class="modal fade" id="genModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="generate">
            <div class="modal-header"><h5 class="modal-title">Generate License Keys</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Number of Keys</label><input type="number" class="form-control" name="count" value="1" min="1" max="50"></div>
                    <div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="type"><option value="lifetime">Lifetime</option><option value="subscription">Subscription</option><option value="trial">Trial</option></select></div>
                    <div class="col-md-6"><label class="form-label">Max Users per Key</label><input type="number" class="form-control" name="max_users" value="1" min="1"></div>
                    <div class="col-md-6"><label class="form-label">Expires At (optional)</label><input type="date" class="form-control" name="expires_at"></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Generate</button></div>
        </form>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
