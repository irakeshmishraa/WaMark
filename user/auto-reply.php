<?php
/**
 * WaMark - Auto Reply Rules
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Auto Reply';
$userId = Auth::id();

// Handle actions
if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create') {
        $db->insert('auto_replies', [
            'user_id' => $userId,
            'name' => trim($_POST['name']),
            'trigger_type' => $_POST['trigger_type'] ?? 'contains',
            'trigger_keyword' => trim($_POST['trigger_keyword'] ?? ''),
            'response_type' => $_POST['response_type'] ?? 'text',
            'response_body' => $_POST['response_body'],
            'is_active' => 1,
            'priority' => (int)($_POST['priority'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Auto-reply rule created.');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['rule_id'];
        $rule = $db->fetch("SELECT is_active FROM " . $db->table('auto_replies') . " WHERE id = ? AND user_id = ?", [$id, $userId]);
        if ($rule) {
            $db->update('auto_replies', ['is_active' => $rule['is_active'] ? 0 : 1], 'id = ?', [$id]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['rule_id'];
        $db->delete('auto_replies', 'id = ? AND user_id = ?', [$id, $userId]);
        flash('success', 'Rule deleted.');
    }
    redirect(USER_URL . '/auto-reply.php');
}

$rules = $db->fetchAll("SELECT * FROM " . $db->table('auto_replies') . " WHERE user_id = ? ORDER BY priority DESC, created_at DESC", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between mb-4">
    <p class="text-muted mb-0">Automatically reply to incoming messages</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRuleModal"><i class="bi bi-plus"></i> Add Rule</button>
</div>

<div class="row g-3">
    <?php foreach ($rules as $rule): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="fw-bold mb-1"><?= sanitize($rule['name']) ?></h6>
                    <div class="form-check form-switch">
                        <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                        <input class="form-check-input" type="checkbox" <?= $rule['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                <div class="small mb-2">
                    <span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$rule['trigger_type'])) ?></span>
                    <?php if ($rule['trigger_keyword']): ?>
                    <code class="ms-1"><?= sanitize($rule['trigger_keyword']) ?></code>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mb-2 text-truncate"><?= sanitize($rule['response_body']) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="bi bi-reply"></i> <?= $rule['reply_count'] ?> replies</small>
                    <form method="POST" onsubmit="return confirm('Delete this rule?')"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="rule_id" value="<?= $rule['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($rules)): ?>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
        <i class="bi bi-reply text-muted" style="font-size:48px;"></i>
        <h5 class="mt-3">No Auto-Reply Rules</h5>
        <p class="text-muted">Set up automatic responses for incoming messages.</p>
    </div></div></div>
    <?php endif; ?>
</div>

<!-- Add Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="create">
            <div class="modal-header"><h5 class="modal-title">Add Auto-Reply Rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Rule Name *</label><input type="text" class="form-control" name="name" placeholder="e.g., Welcome Reply" required></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Match Type</label><select class="form-select" name="trigger_type"><option value="exact">Exact Match</option><option value="contains" selected>Contains</option><option value="starts_with">Starts With</option><option value="regex">Regex</option><option value="default">Default (any)</option></select></div>
                    <div class="col-md-6"><label class="form-label">Keyword</label><input type="text" class="form-control" name="trigger_keyword" placeholder="hello, hi, hey"></div>
                </div>
                <div class="mb-3"><label class="form-label">Response *</label><textarea class="form-control" name="response_body" rows="4" placeholder="Thank you for messaging us! We'll get back to you shortly." required></textarea></div>
                <div class="mb-3"><label class="form-label">Priority</label><input type="number" class="form-control" name="priority" value="0"><small class="text-muted">Higher = processed first</small></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Rule</button></div>
        </form>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
