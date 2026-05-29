<?php
/**
 * WaMark - User Automation Workflows
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Automation';
$userId = Auth::id();
$action = $_GET['action'] ?? 'list';

// Handle creation
if (request_method() === 'POST' && !is_ajax()) {
    verify_csrf();
    
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'welcome';
    $triggerType = $_POST['trigger_type'] ?? 'keyword';
    $triggerValue = trim($_POST['trigger_value'] ?? '');
    $waAccountId = (int)($_POST['whatsapp_account_id'] ?? 0);

    if (empty($name)) {
        flash('error', 'Automation name is required.');
    } else {
        $automationId = $db->insert('automations', [
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'status' => 'draft',
            'trigger_type' => $triggerType,
            'trigger_value' => $triggerValue ?: null,
            'whatsapp_account_id' => $waAccountId ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add steps if provided
        $steps = $_POST['steps'] ?? [];
        foreach ($steps as $i => $step) {
            if (empty($step['message_body'])) continue;
            $db->insert('automation_steps', [
                'automation_id' => $automationId,
                'step_order' => $i + 1,
                'action_type' => $step['action_type'] ?? 'send_message',
                'message_type' => $step['message_type'] ?? 'text',
                'message_body' => $step['message_body'],
                'delay_value' => (int)($step['delay_value'] ?? 0),
                'delay_unit' => $step['delay_unit'] ?? 'hours',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        flash('success', 'Automation created successfully.');
        redirect(USER_URL . '/automation.php');
    }
    redirect(USER_URL . '/automation.php?action=new');
}

// AJAX toggle
if (is_ajax() && request_method() === 'POST') {
    verify_csrf();
    $ajaxAction = $_POST['ajax_action'] ?? '';
    $autoId = (int)($_POST['automation_id'] ?? 0);
    
    $auto = $db->fetch("SELECT * FROM " . $db->table('automations') . " WHERE id = ? AND user_id = ?", [$autoId, $userId]);
    if (!$auto) json_response(['error' => 'Not found'], 404);

    if ($ajaxAction === 'toggle') {
        $newStatus = $auto['status'] === 'active' ? 'inactive' : 'active';
        $db->update('automations', ['status' => $newStatus], 'id = ?', [$autoId]);
        json_response(['success' => true, 'status' => $newStatus]);
    } elseif ($ajaxAction === 'delete') {
        $db->delete('automation_steps', 'automation_id = ?', [$autoId]);
        $db->delete('automations', 'id = ? AND user_id = ?', [$autoId, $userId]);
        json_response(['success' => true]);
    }
}

$automations = $db->fetchAll(
    "SELECT * FROM " . $db->table('automations') . " WHERE user_id = ? ORDER BY created_at DESC", [$userId]
);
$waAccounts = $db->fetchAll("SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? AND status = 'connected'", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<?php if ($action === 'new'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between">
        <h6 class="mb-0 fw-bold">Create Automation</h6>
        <a href="automation.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name *</label><input type="text" class="form-control" name="name" placeholder="Welcome Sequence" required></div>
                <div class="col-md-6">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <option value="welcome">Welcome Sequence</option>
                        <option value="follow_up">Follow-Up</option>
                        <option value="drip">Drip Campaign</option>
                        <option value="birthday">Birthday</option>
                        <option value="anniversary">Anniversary</option>
                        <option value="cart_recovery">Cart Recovery</option>
                        <option value="lead_nurture">Lead Nurturing</option>
                        <option value="trigger">Custom Trigger</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Trigger</label>
                    <select class="form-select" name="trigger_type">
                        <option value="keyword">Keyword Match</option>
                        <option value="webhook">Webhook</option>
                        <option value="schedule">Schedule</option>
                        <option value="event">Event</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Trigger Value</label><input type="text" class="form-control" name="trigger_value" placeholder="e.g., 'hello', '#start'"></div>
                <div class="col-md-4">
                    <label class="form-label">WA Account</label>
                    <select class="form-select" name="whatsapp_account_id">
                        <option value="">Select</option>
                        <?php foreach ($waAccounts as $wa): ?><option value="<?= $wa['id'] ?>"><?= sanitize($wa['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12"><hr><h6 class="fw-bold">Steps</h6></div>
                
                <div id="stepsContainer">
                    <div class="step-item border rounded p-3 mb-3">
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label small">Delay</label><div class="input-group input-group-sm"><input type="number" class="form-control" name="steps[0][delay_value]" value="0"><select class="form-select" name="steps[0][delay_unit]"><option value="minutes">Min</option><option value="hours" selected>Hours</option><option value="days">Days</option></select></div></div>
                            <div class="col-md-3"><label class="form-label small">Action</label><select class="form-select form-select-sm" name="steps[0][action_type]"><option value="send_message">Send Message</option><option value="wait">Wait</option></select></div>
                            <div class="col-md-6"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="steps[0][message_type]"><option value="text">Text</option><option value="image">Image</option><option value="template">Template</option></select></div>
                            <div class="col-12"><label class="form-label small">Message</label><textarea class="form-control form-control-sm" name="steps[0][message_body]" rows="2" placeholder="Message content..."></textarea></div>
                        </div>
                    </div>
                </div>
                <div class="col-12"><button type="button" class="btn btn-sm btn-outline-primary" onclick="addStep()"><i class="bi bi-plus"></i> Add Step</button></div>
                <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-robot"></i> Create Automation</button></div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="d-flex justify-content-between mb-4">
    <p class="text-muted mb-0"><?= count($automations) ?> automations</p>
    <a href="automation.php?action=new" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> New Automation</a>
</div>

<div class="row g-4">
    <?php foreach ($automations as $auto): ?>
    <div class="col-md-6 col-xl-4" id="auto-<?= $auto['id'] ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($auto['name']) ?></h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" <?= $auto['status']==='active'?'checked':'' ?> onchange="toggleAuto(<?= $auto['id'] ?>)">
                    </div>
                </div>
                <span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$auto['type'])) ?></span>
                <span class="badge bg-<?= $auto['status']==='active'?'success':($auto['status']==='draft'?'secondary':'warning') ?>"><?= ucfirst($auto['status']) ?></span>
                <p class="small text-muted mt-2 mb-0">Trigger: <?= ucfirst($auto['trigger_type']) ?> <?= $auto['trigger_value'] ? '("'.sanitize($auto['trigger_value']).'")' : '' ?></p>
                <hr>
                <div class="d-flex justify-content-between small">
                    <span><i class="bi bi-people"></i> <?= $auto['total_enrolled'] ?> enrolled</span>
                    <span><i class="bi bi-check-circle text-success"></i> <?= $auto['total_completed'] ?> done</span>
                </div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAuto(<?= $auto['id'] ?>)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($automations)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
            <i class="bi bi-robot text-muted" style="font-size:48px;"></i>
            <h5 class="mt-3">No Automations Yet</h5>
            <p class="text-muted">Automate your messaging with workflows.</p>
            <a href="automation.php?action=new" class="btn btn-primary">Create Automation</a>
        </div></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$extraScripts = '<script>
let stepCount = 1;
function addStep(){
    const html = `<div class="step-item border rounded p-3 mb-3"><div class="row g-2"><div class="col-md-3"><label class="form-label small">Delay</label><div class="input-group input-group-sm"><input type="number" class="form-control" name="steps[${stepCount}][delay_value]" value="1"><select class="form-select" name="steps[${stepCount}][delay_unit]"><option value="minutes">Min</option><option value="hours" selected>Hours</option><option value="days">Days</option></select></div></div><div class="col-md-3"><label class="form-label small">Action</label><select class="form-select form-select-sm" name="steps[${stepCount}][action_type]"><option value="send_message">Send Message</option><option value="wait">Wait</option></select></div><div class="col-md-6"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="steps[${stepCount}][message_type]"><option value="text">Text</option><option value="image">Image</option></select></div><div class="col-12"><label class="form-label small">Message</label><textarea class="form-control form-control-sm" name="steps[${stepCount}][message_body]" rows="2"></textarea></div></div></div>`;
    document.getElementById("stepsContainer").insertAdjacentHTML("beforeend",html);
    stepCount++;
}
function toggleAuto(id){fetch("automation.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},body:`ajax_action=toggle&automation_id=${id}&'.CSRF_TOKEN_NAME.'='.csrf_token().'`}).then(r=>r.json()).then(d=>{if(d.success)location.reload();});}
function deleteAuto(id){if(!confirm("Delete?"))return;fetch("automation.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},body:`ajax_action=delete&automation_id=${id}&'.CSRF_TOKEN_NAME.'='.csrf_token().'`}).then(r=>r.json()).then(d=>{if(d.success)document.getElementById("auto-"+id).remove();});}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
