<?php
/**
 * WaMark - Plan Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'Subscription Plans';

// Handle form submission
if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $data = [
            'name' => trim($_POST['name']),
            'slug' => strtolower(str_replace(' ', '-', trim($_POST['name']))),
            'description' => trim($_POST['description'] ?? ''),
            'type' => $_POST['type'],
            'price' => (float)$_POST['price'],
            'currency' => $_POST['currency'] ?? 'USD',
            'max_contacts' => (int)$_POST['max_contacts'],
            'max_messages_per_month' => (int)$_POST['max_messages'],
            'max_campaigns' => (int)$_POST['max_campaigns'],
            'max_whatsapp_accounts' => (int)$_POST['max_wa_accounts'],
            'max_automation' => (int)$_POST['max_automation'],
            'max_templates' => (int)$_POST['max_templates'],
            'trial_days' => (int)($_POST['trial_days'] ?? 0),
            'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
        ];

        if ($action === 'create') {
            $db->insert('plans', $data);
            flash('success', 'Plan created successfully.');
        } else {
            $id = (int)$_POST['plan_id'];
            $db->update('plans', $data, 'id = ?', [$id]);
            flash('success', 'Plan updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['plan_id'];
        $db->delete('plans', 'id = ?', [$id]);
        flash('success', 'Plan deleted.');
    }
    redirect(ADMIN_URL . '/plans.php');
}

$plans = $db->fetchAll("SELECT * FROM " . $db->table('plans') . " ORDER BY sort_order, id");

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage subscription plans for your users</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal" onclick="resetForm()">
        <i class="bi bi-plus"></i> New Plan
    </button>
</div>

<div class="row g-4">
    <?php foreach ($plans as $plan): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 <?= $plan['is_popular'] ? 'border-primary border-2' : '' ?>">
            <?php if ($plan['is_popular']): ?>
            <div class="position-absolute top-0 end-0 m-2">
                <span class="badge bg-primary">Popular</span>
            </div>
            <?php endif; ?>
            <div class="card-body">
                <h5 class="fw-bold"><?= sanitize($plan['name']) ?></h5>
                <p class="text-muted small"><?= sanitize($plan['description']) ?></p>
                <h3 class="text-primary mb-3">
                    <?= get_setting('currency_symbol', '$') ?><?= number_format($plan['price'], 2) ?>
                    <small class="text-muted fw-normal">/<?= $plan['type'] ?></small>
                </h3>
                <ul class="list-unstyled small">
                    <li class="mb-1"><i class="bi bi-check-circle text-success"></i> <?= $plan['max_contacts'] == -1 ? 'Unlimited' : number_format($plan['max_contacts']) ?> Contacts</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success"></i> <?= $plan['max_messages_per_month'] == -1 ? 'Unlimited' : number_format($plan['max_messages_per_month']) ?> Messages/mo</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success"></i> <?= $plan['max_campaigns'] == -1 ? 'Unlimited' : $plan['max_campaigns'] ?> Campaigns</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success"></i> <?= $plan['max_whatsapp_accounts'] == -1 ? 'Unlimited' : $plan['max_whatsapp_accounts'] ?> WA Accounts</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success"></i> <?= $plan['max_automation'] == -1 ? 'Unlimited' : $plan['max_automation'] ?> Automations</li>
                </ul>
                <span class="badge bg-<?= $plan['is_active'] ? 'success' : 'secondary' ?>"><?= $plan['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="card-footer bg-transparent border-0 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-fill" onclick='editPlan(<?= json_encode($plan) ?>)'>
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this plan?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" id="formAction" value="create">
                <input type="hidden" name="plan_id" id="planId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">New Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Plan Name *</label><input type="text" class="form-control" name="name" id="pName" required></div>
                        <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="type" id="pType"><option value="free">Free</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option><option value="lifetime">Lifetime</option></select></div>
                        <div class="col-md-3"><label class="form-label">Price</label><input type="number" class="form-control" name="price" id="pPrice" step="0.01" value="0"></div>
                        <div class="col-12"><label class="form-label">Description</label><input type="text" class="form-control" name="description" id="pDesc"></div>
                        <div class="col-md-4"><label class="form-label">Max Contacts</label><input type="number" class="form-control" name="max_contacts" id="pContacts" value="1000"><small class="text-muted">-1 for unlimited</small></div>
                        <div class="col-md-4"><label class="form-label">Max Messages/Mo</label><input type="number" class="form-control" name="max_messages" id="pMessages" value="5000"><small class="text-muted">-1 for unlimited</small></div>
                        <div class="col-md-4"><label class="form-label">Max Campaigns</label><input type="number" class="form-control" name="max_campaigns" id="pCampaigns" value="10"></div>
                        <div class="col-md-4"><label class="form-label">Max WA Accounts</label><input type="number" class="form-control" name="max_wa_accounts" id="pWa" value="2"></div>
                        <div class="col-md-4"><label class="form-label">Max Automations</label><input type="number" class="form-control" name="max_automation" id="pAuto" value="5"></div>
                        <div class="col-md-4"><label class="form-label">Max Templates</label><input type="number" class="form-control" name="max_templates" id="pTemplates" value="20"></div>
                        <div class="col-md-4"><label class="form-label">Trial Days</label><input type="number" class="form-control" name="trial_days" id="pTrial" value="0"></div>
                        <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" id="pSort" value="0"></div>
                        <div class="col-md-2"><label class="form-label">Currency</label><input type="text" class="form-control" name="currency" id="pCurrency" value="USD"></div>
                        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_active" id="pActive" checked><label class="form-check-label" for="pActive">Active</label></div></div>
                        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_popular" id="pPopular"><label class="form-check-label" for="pPopular">Mark as Popular</label></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
function resetForm() {
    document.getElementById("formAction").value = "create";
    document.getElementById("modalTitle").textContent = "New Plan";
    document.getElementById("pName").value = "";
    document.getElementById("pDesc").value = "";
    document.getElementById("pPrice").value = "0";
    document.getElementById("pType").value = "monthly";
}
function editPlan(p) {
    document.getElementById("formAction").value = "update";
    document.getElementById("planId").value = p.id;
    document.getElementById("modalTitle").textContent = "Edit Plan";
    document.getElementById("pName").value = p.name;
    document.getElementById("pDesc").value = p.description || "";
    document.getElementById("pType").value = p.type;
    document.getElementById("pPrice").value = p.price;
    document.getElementById("pContacts").value = p.max_contacts;
    document.getElementById("pMessages").value = p.max_messages_per_month;
    document.getElementById("pCampaigns").value = p.max_campaigns;
    document.getElementById("pWa").value = p.max_whatsapp_accounts;
    document.getElementById("pAuto").value = p.max_automation;
    document.getElementById("pTemplates").value = p.max_templates;
    document.getElementById("pTrial").value = p.trial_days;
    document.getElementById("pSort").value = p.sort_order;
    document.getElementById("pActive").checked = p.is_active == 1;
    document.getElementById("pPopular").checked = p.is_popular == 1;
    new bootstrap.Modal(document.getElementById("planModal")).show();
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
