<?php
/**
 * WaMark - User Campaign Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Campaigns';
$userId = Auth::id();
$action = $_GET['action'] ?? 'list';

// Handle campaign creation
if (request_method() === 'POST' && !is_ajax()) {
    verify_csrf();
    $formAction = $_POST['form_action'] ?? 'create';

    if ($formAction === 'create') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'broadcast';
        $messageType = $_POST['message_type'] ?? 'text';
        $messageBody = $_POST['message_body'] ?? '';
        $targetType = $_POST['target_type'] ?? 'all';
        $targetGroups = $_POST['target_groups'] ?? [];
        $scheduledAt = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
        $waAccountId = (int)($_POST['whatsapp_account_id'] ?? 0);

        if (empty($name) || empty($messageBody)) {
            flash('error', 'Campaign name and message are required.');
        } else {
            // Count recipients
            $recipientCount = 0;
            if ($targetType === 'all') {
                $recipientCount = $db->count('contacts', "user_id = ? AND status = 'active'", [$userId]);
            } elseif ($targetType === 'group' && !empty($targetGroups)) {
                $groupIds = implode(',', array_map('intval', $targetGroups));
                $recipientCount = $db->fetchColumn(
                    "SELECT COUNT(DISTINCT contact_id) FROM " . $db->table('contact_group_members') . 
                    " WHERE group_id IN ({$groupIds})"
                );
            }

            $status = $scheduledAt ? 'scheduled' : 'draft';
            
            $campaignId = $db->insert('campaigns', [
                'user_id' => $userId,
                'whatsapp_account_id' => $waAccountId ?: null,
                'name' => $name,
                'type' => $type,
                'status' => $status,
                'message_type' => $messageType,
                'message_body' => $messageBody,
                'target_type' => $targetType,
                'target_groups' => !empty($targetGroups) ? json_encode($targetGroups) : null,
                'total_recipients' => $recipientCount,
                'scheduled_at' => $scheduledAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            flash('success', 'Campaign created successfully.');
            redirect(USER_URL . '/campaigns.php');
        }
        redirect(USER_URL . '/campaigns.php?action=new');
    }
}

// AJAX actions
if (is_ajax() && request_method() === 'POST') {
    verify_csrf();
    $ajaxAction = $_POST['ajax_action'] ?? '';
    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    
    // Verify ownership
    $campaign = $db->fetch("SELECT * FROM " . $db->table('campaigns') . " WHERE id = ? AND user_id = ?", [$campaignId, $userId]);
    if (!$campaign) json_response(['error' => 'Campaign not found'], 404);

    switch ($ajaxAction) {
        case 'start':
            $db->update('campaigns', ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')], 'id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
        case 'pause':
            $db->update('campaigns', ['status' => 'paused'], 'id = ?', [$campaignId]);
            json_response(['success' => true]);
            break;
        case 'delete':
            $db->delete('campaigns', 'id = ? AND user_id = ?', [$campaignId, $userId]);
            json_response(['success' => true]);
            break;
    }
}

// Fetch campaigns
$campaigns = $db->fetchAll(
    "SELECT * FROM " . $db->table('campaigns') . " WHERE user_id = ? ORDER BY created_at DESC",
    [$userId]
);

$groups = $db->fetchAll("SELECT * FROM " . $db->table('contact_groups') . " WHERE user_id = ?", [$userId]);
$waAccounts = $db->fetchAll("SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? AND status = 'connected'", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<?php if ($action === 'new'): ?>
<!-- Create Campaign -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between">
        <h6 class="mb-0 fw-bold">Create New Campaign</h6>
        <a href="campaigns.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="create">
            
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Campaign Name *</label>
                    <input type="text" class="form-control" name="name" placeholder="e.g., Black Friday Promo" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <option value="broadcast">Broadcast</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">WhatsApp Account</label>
                    <select class="form-select" name="whatsapp_account_id">
                        <option value="">Select Account</option>
                        <?php foreach ($waAccounts as $wa): ?>
                        <option value="<?= $wa['id'] ?>"><?= sanitize($wa['name']) ?> (<?= $wa['phone_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Schedule (optional)</label>
                    <input type="datetime-local" class="form-control" name="scheduled_at">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Target Audience</label>
                    <select class="form-select" name="target_type" id="targetType" onchange="toggleGroups()">
                        <option value="all">All Contacts</option>
                        <option value="group">Specific Groups</option>
                    </select>
                </div>
                <div class="col-md-6" id="groupSelect" style="display:none;">
                    <label class="form-label">Select Groups</label>
                    <select class="form-select" name="target_groups[]" multiple>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= sanitize($g['name']) ?> (<?= $g['contact_count'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Message Type</label>
                    <select class="form-select" name="message_type">
                        <option value="text">Text</option>
                        <option value="image">Image + Caption</option>
                        <option value="document">Document</option>
                        <option value="template">Template</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Message Body *</label>
                    <textarea class="form-control" name="message_body" rows="5" placeholder="Hi {name}, Thank you for connecting with {company}..." required></textarea>
                    <small class="text-muted">Variables: {name}, {phone}, {email}, {company}</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-megaphone"></i> Create Campaign</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Campaign List -->
<div class="d-flex justify-content-between mb-4">
    <p class="text-muted mb-0"><?= count($campaigns) ?> campaigns</p>
    <a href="campaigns.php?action=new" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> New Campaign</a>
</div>

<?php foreach ($campaigns as $c): ?>
<div class="card border-0 shadow-sm mb-3" id="campaign-<?= $c['id'] ?>">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="fw-bold mb-1"><?= sanitize($c['name']) ?></h6>
                <div class="small text-muted">
                    <span class="badge bg-<?= match($c['status']){'running'=>'success','completed'=>'primary','scheduled'=>'warning','draft'=>'secondary','paused'=>'info',default=>'dark'} ?>"><?= ucfirst($c['status']) ?></span>
                    <span class="ms-2"><?= ucfirst($c['type']) ?></span>
                    <span class="ms-2"><?= time_ago($c['created_at']) ?></span>
                </div>
            </div>
            <div class="d-flex gap-1">
                <?php if ($c['status'] === 'draft'): ?>
                <button class="btn btn-sm btn-success" onclick="campaignAction(<?= $c['id'] ?>,'start')"><i class="bi bi-play"></i> Start</button>
                <?php elseif ($c['status'] === 'running'): ?>
                <button class="btn btn-sm btn-warning" onclick="campaignAction(<?= $c['id'] ?>,'pause')"><i class="bi bi-pause"></i></button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger" onclick="campaignAction(<?= $c['id'] ?>,'delete')"><i class="bi bi-trash"></i></button>
            </div>
        </div>
        <?php if ($c['total_recipients'] > 0): ?>
        <div class="mt-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Progress: <?= $c['sent_count'] ?>/<?= $c['total_recipients'] ?></span>
                <span><?= $c['total_recipients'] > 0 ? round(($c['sent_count']/$c['total_recipients'])*100) : 0 ?>%</span>
            </div>
            <div class="progress" style="height:5px;">
                <div class="progress-bar bg-success" style="width:<?= $c['total_recipients'] > 0 ? ($c['sent_count']/$c['total_recipients'])*100 : 0 ?>%"></div>
            </div>
            <div class="d-flex gap-3 mt-2 small">
                <span class="text-success"><i class="bi bi-check"></i> Sent: <?= $c['sent_count'] ?></span>
                <span class="text-primary"><i class="bi bi-check-all"></i> Delivered: <?= $c['delivered_count'] ?></span>
                <span class="text-info"><i class="bi bi-eye"></i> Read: <?= $c['read_count'] ?></span>
                <span class="text-danger"><i class="bi bi-x"></i> Failed: <?= $c['failed_count'] ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($campaigns)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-megaphone text-muted" style="font-size:48px;"></i>
        <h5 class="mt-3">No Campaigns Yet</h5>
        <p class="text-muted">Create your first campaign to start messaging.</p>
        <a href="campaigns.php?action=new" class="btn btn-primary">Create Campaign</a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$extraScripts = '<script>
function toggleGroups(){document.getElementById("groupSelect").style.display=document.getElementById("targetType").value==="group"?"block":"none";}
function campaignAction(id,action){
    if(!confirm("Are you sure?"))return;
    fetch("campaigns.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},body:`ajax_action=${action}&campaign_id=${id}&'.CSRF_TOKEN_NAME.'='.csrf_token().'`}).then(r=>r.json()).then(d=>{if(d.success)location.reload();});
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
