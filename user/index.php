<?php
/**
 * WaMark - User Dashboard
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Dashboard';
$userId = Auth::id();
$user = Auth::user();

// User stats
$totalContacts = $db->count('contacts', 'user_id = ?', [$userId]);
$totalCampaigns = $db->count('campaigns', 'user_id = ?', [$userId]);
$totalSent = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE user_id = ? AND direction = 'outgoing'", [$userId]) ?: 0;
$sentToday = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE user_id = ? AND DATE(created_at) = CURDATE() AND direction = 'outgoing'", [$userId]) ?: 0;
$deliveredCount = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE user_id = ? AND status = 'delivered'", [$userId]) ?: 0;
$readCount = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE user_id = ? AND status = 'read'", [$userId]) ?: 0;
$waAccounts = $db->count('whatsapp_accounts', 'user_id = ?', [$userId]);
$activeAutomations = $db->count('automations', "user_id = ? AND status = 'active'", [$userId]);

// Plan info
$plan = $db->fetch("SELECT * FROM " . $db->table('plans') . " WHERE id = ?", [$user['plan_id'] ?? 0]);
$planName = $plan['name'] ?? 'No Plan';
$expiresAt = $user['subscription_expires_at'];

// Recent campaigns
$recentCampaigns = $db->fetchAll(
    "SELECT * FROM " . $db->table('campaigns') . " WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

// Weekly message stats
$weeklyStats = $db->fetchAll(
    "SELECT DATE(created_at) as day, COUNT(*) as count FROM " . $db->table('messages') . 
    " WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND direction = 'outgoing' GROUP BY day ORDER BY day",
    [$userId]
);

include ASSETS_PATH . 'templates/header.php';
?>

<!-- Plan Banner -->
<?php if ($expiresAt && strtotime($expiresAt) < strtotime('+7 days')): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4">
    <i class="bi bi-exclamation-triangle"></i>
    Your <strong><?= sanitize($planName) ?></strong> plan expires on <?= format_date($expiresAt) ?>. 
    <a href="subscription.php" class="alert-link">Renew now</a> to avoid service interruption.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <h3><?= format_number($totalContacts) ?></h3>
                <p>Contacts</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-card-success">
            <div class="stat-icon"><i class="bi bi-send"></i></div>
            <div class="stat-content">
                <h3><?= format_number($totalSent) ?></h3>
                <p>Messages Sent</p>
            </div>
            <span class="stat-badge"><?= $sentToday ?> today</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-card-warning">
            <div class="stat-icon"><i class="bi bi-megaphone"></i></div>
            <div class="stat-content">
                <h3><?= $totalCampaigns ?></h3>
                <p>Campaigns</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card stat-card-info">
            <div class="stat-icon"><i class="bi bi-robot"></i></div>
            <div class="stat-content">
                <h3><?= $activeAutomations ?></h3>
                <p>Active Automations</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Message Activity Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">Message Activity (7 Days)</h6>
                <div class="small text-muted">
                    <span class="text-success"><i class="bi bi-check-all"></i> <?= $deliveredCount ?> delivered</span> |
                    <span class="text-primary"><i class="bi bi-eye"></i> <?= $readCount ?> read</span>
                </div>
            </div>
            <div class="card-body">
                <canvas id="activityChart" height="130"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Plan -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Your Plan</h6></div>
            <div class="card-body text-center">
                <h5 class="text-primary fw-bold"><?= sanitize($planName) ?></h5>
                <?php if ($plan): ?>
                <div class="small text-muted mb-2">
                    <div><?= $plan['max_contacts'] == -1 ? '∞' : number_format($plan['max_contacts']) ?> contacts (<?= number_format($totalContacts) ?> used)</div>
                    <div><?= $plan['max_messages_per_month'] == -1 ? '∞' : number_format($plan['max_messages_per_month']) ?> msg/mo</div>
                </div>
                <?php if ($plan['max_contacts'] > 0): ?>
                <div class="progress mb-2" style="height:6px;">
                    <div class="progress-bar" style="width:<?= min(100, ($totalContacts / $plan['max_contacts']) * 100) ?>%"></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($expiresAt): ?>
                <small class="text-muted">Expires: <?= format_date($expiresAt) ?></small>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="subscription.php" class="btn btn-sm btn-outline-primary">Upgrade Plan</a>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Quick Actions</h6></div>
            <div class="card-body d-grid gap-2">
                <a href="send.php" class="btn btn-success btn-sm text-start"><i class="bi bi-send"></i> Send Message</a>
                <a href="campaigns.php?action=new" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-megaphone"></i> New Campaign</a>
                <a href="import.php" class="btn btn-outline-info btn-sm text-start"><i class="bi bi-upload"></i> Import Contacts</a>
                <a href="whatsapp.php" class="btn btn-outline-success btn-sm text-start"><i class="bi bi-whatsapp"></i> Connect WhatsApp</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Campaigns -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between">
        <h6 class="mb-0 fw-bold">Recent Campaigns</h6>
        <a href="campaigns.php" class="btn btn-sm btn-link">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Campaign</th><th>Status</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentCampaigns as $c): ?>
                <tr>
                    <td class="fw-medium"><?= sanitize($c['name']) ?></td>
                    <td><span class="badge bg-<?= match($c['status']){'running'=>'success','completed'=>'primary','scheduled'=>'warning','failed'=>'danger',default=>'secondary'} ?>"><?= ucfirst($c['status']) ?></span></td>
                    <td><?= $c['sent_count'] ?></td>
                    <td><?= $c['delivered_count'] ?></td>
                    <td><?= $c['read_count'] ?></td>
                    <td><small><?= time_ago($c['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentCampaigns)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No campaigns yet. <a href="campaigns.php?action=new">Create your first campaign</a></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
new Chart(document.getElementById("activityChart"), {
    type: "line",
    data: {
        labels: ' . json_encode(array_column($weeklyStats, 'day')) . ',
        datasets: [{
            label: "Messages",
            data: ' . json_encode(array_column($weeklyStats, 'count')) . ',
            borderColor: "rgb(99, 102, 241)",
            backgroundColor: "rgba(99, 102, 241, 0.1)",
            fill: true,
            tension: 0.4
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
