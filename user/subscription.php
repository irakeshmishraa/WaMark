<?php
/**
 * WaMark - User Subscription & Billing
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Subscription';
$userId = Auth::id();
$user = Auth::user();

$currentPlan = $db->fetch("SELECT * FROM " . $db->table('plans') . " WHERE id = ?", [$user['plan_id'] ?? 0]);
$allPlans = $db->fetchAll("SELECT * FROM " . $db->table('plans') . " WHERE is_active = 1 ORDER BY sort_order");
$payments = $db->fetchAll("SELECT * FROM " . $db->table('payments') . " WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$userId]);
$symbol = get_setting('currency_symbol', '$');

include ASSETS_PATH . 'templates/header.php';
?>

<!-- Current Plan -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1">Current Plan: <?= sanitize($currentPlan['name'] ?? 'None') ?></h5>
                <p class="text-muted mb-0">
                    <?php if ($user['subscription_expires_at']): ?>
                    Expires: <strong><?= format_date($user['subscription_expires_at']) ?></strong>
                    <?php else: ?>
                    No active subscription
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if ($currentPlan): ?>
                <h4 class="text-primary"><?= $symbol ?><?= number_format($currentPlan['price'], 2) ?><small class="text-muted">/<?= $currentPlan['type'] ?></small></h4>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Available Plans -->
<h5 class="fw-bold mb-3">Available Plans</h5>
<div class="row g-4 mb-4">
    <?php foreach ($allPlans as $plan): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 <?= $plan['is_popular'] ? 'border-primary border-2' : '' ?> <?= ($user['plan_id'] ?? 0) == $plan['id'] ? 'bg-light' : '' ?>">
            <?php if ($plan['is_popular']): ?><div class="position-absolute top-0 end-0 m-2"><span class="badge bg-primary">Popular</span></div><?php endif; ?>
            <div class="card-body text-center">
                <h5 class="fw-bold"><?= sanitize($plan['name']) ?></h5>
                <h3 class="text-primary my-3"><?= $symbol ?><?= number_format($plan['price'], 2) ?><small class="text-muted fs-6">/<?= $plan['type'] ?></small></h3>
                <ul class="list-unstyled small text-start mb-4">
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $plan['max_contacts'] == -1 ? 'Unlimited' : number_format($plan['max_contacts']) ?> Contacts</li>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $plan['max_messages_per_month'] == -1 ? 'Unlimited' : number_format($plan['max_messages_per_month']) ?> Messages/mo</li>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $plan['max_campaigns'] == -1 ? 'Unlimited' : $plan['max_campaigns'] ?> Campaigns</li>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $plan['max_whatsapp_accounts'] ?> WA Accounts</li>
                    <li><i class="bi bi-check text-success"></i> <?= $plan['max_automation'] == -1 ? 'Unlimited' : $plan['max_automation'] ?> Automations</li>
                </ul>
                <?php if (($user['plan_id'] ?? 0) == $plan['id']): ?>
                <button class="btn btn-outline-success btn-sm w-100" disabled>Current Plan</button>
                <?php else: ?>
                <a href="checkout.php?plan=<?= $plan['id'] ?>" class="btn btn-primary btn-sm w-100">Choose Plan</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Payment History -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Payment History</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Invoice</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><small><?= format_date($p['created_at']) ?></small></td>
                    <td class="fw-bold"><?= $symbol ?><?= number_format($p['amount'], 2) ?></td>
                    <td><span class="badge bg-light text-dark"><?= ucfirst($p['gateway']) ?></span></td>
                    <td><span class="badge bg-<?= $p['status']==='completed'?'success':'warning' ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><small><?= $p['invoice_number'] ?? '—' ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No payment history</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
