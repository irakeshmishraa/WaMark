<?php
/**
 * WaMark - Campaign Management (Admin)
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Campaigns';
$action = $_GET['action'] ?? 'list';

// Handle campaign actions
if (is_ajax() && request_method() === 'POST') {
    verify_csrf();
    $ajaxAction = $_POST['ajax_action'] ?? '';
    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    
    switch ($ajaxAction) {
        case 'pause':
            $db->update('campaigns', ['status' => 'paused'], 'id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
        case 'resume':
            $db->update('campaigns', ['status' => 'running'], 'id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
        case 'cancel':
            $db->update('campaigns', ['status' => 'cancelled'], 'id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
        case 'delete':
            $db->delete('campaigns', 'id = ?', [$campaignId]);
            $db->delete('messages', 'campaign_id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
    }
}

// Fetch campaigns
$statusFilter = $_GET['status'] ?? '';
$where = '1=1';
$params = [];

if ($statusFilter) {
    $where .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$total = $db->fetchColumn(
    "SELECT COUNT(*) FROM " . $db->table('campaigns') . " c WHERE {$where}", $params
);
$pagination = paginate($total);

$campaigns = $db->fetchAll(
    "SELECT c.*, u.name as user_name FROM " . $db->table('campaigns') . " c 
     LEFT JOIN " . $db->table('users') . " u ON c.user_id = u.id 
     WHERE {$where} ORDER BY c.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <a href="campaigns.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <a href="campaigns.php?status=running" class="btn btn-sm <?= $statusFilter==='running' ? 'btn-success' : 'btn-outline-secondary' ?>">Running</a>
        <a href="campaigns.php?status=scheduled" class="btn btn-sm <?= $statusFilter==='scheduled' ? 'btn-warning' : 'btn-outline-secondary' ?>">Scheduled</a>
        <a href="campaigns.php?status=completed" class="btn btn-sm <?= $statusFilter==='completed' ? 'btn-info' : 'btn-outline-secondary' ?>">Completed</a>
        <a href="campaigns.php?status=draft" class="btn btn-sm <?= $statusFilter==='draft' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Drafts</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Campaign</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Sent/Delivered</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($campaigns as $c): ?>
                    <tr id="campaign-<?= $c['id'] ?>">
                        <td>
                            <div class="fw-medium"><?= sanitize($c['name']) ?></div>
                            <small class="text-muted">by <?= sanitize($c['user_name'] ?? 'System') ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= ucfirst($c['type']) ?></span></td>
                        <td>
                            <span class="badge bg-<?= match($c['status']) {
                                'running' => 'success', 'completed' => 'primary',
                                'scheduled' => 'warning', 'failed' => 'danger',
                                'paused' => 'info', 'cancelled' => 'dark',
                                default => 'secondary'
                            } ?>"><?= ucfirst($c['status']) ?></span>
                        </td>
                        <td><?= number_format($c['total_recipients']) ?></td>
                        <td>
                            <div class="small">
                                <span class="text-success"><?= $c['sent_count'] ?></span> / 
                                <span class="text-primary"><?= $c['delivered_count'] ?></span>
                                <?php if ($c['total_recipients'] > 0): ?>
                                <div class="progress mt-1" style="height:3px;">
                                    <div class="progress-bar bg-success" style="width:<?= ($c['sent_count']/$c['total_recipients'])*100 ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><small><?= time_ago($c['created_at']) ?></small></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="campaign-view.php?id=<?= $c['id'] ?>"><i class="bi bi-eye"></i> View Details</a></li>
                                    <?php if ($c['status'] === 'running'): ?>
                                    <li><a class="dropdown-item" href="#" onclick="campaignAction(<?= $c['id'] ?>,'pause')"><i class="bi bi-pause"></i> Pause</a></li>
                                    <?php elseif ($c['status'] === 'paused'): ?>
                                    <li><a class="dropdown-item" href="#" onclick="campaignAction(<?= $c['id'] ?>,'resume')"><i class="bi bi-play"></i> Resume</a></li>
                                    <?php endif; ?>
                                    <?php if (in_array($c['status'], ['running','paused','scheduled'])): ?>
                                    <li><a class="dropdown-item text-warning" href="#" onclick="campaignAction(<?= $c['id'] ?>,'cancel')"><i class="bi bi-x-circle"></i> Cancel</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="campaignAction(<?= $c['id'] ?>,'delete')"><i class="bi bi-trash"></i> Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($campaigns)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No campaigns found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'campaigns.php') ?></div>
    <?php endif; ?>
</div>

<?php
$extraScripts = '<script>
function campaignAction(id, action) {
    if (!confirm("Are you sure you want to " + action + " this campaign?")) return;
    fetch("campaigns.php", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},
        body: `ajax_action=${action}&campaign_id=${id}&' . CSRF_TOKEN_NAME . '=' . csrf_token() . '`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
