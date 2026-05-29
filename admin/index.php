<?php
/**
 * WaMark - Admin Dashboard
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'Dashboard';
$user = Auth::user();

// Dashboard Statistics
$totalUsers = $db->count('users', 'role = ?', ['client']);
$activeUsers = $db->count('users', "role = 'client' AND status = 'active'");
$totalCampaigns = $db->count('campaigns');
$activeCampaigns = $db->count('campaigns', "status = 'running'");
$totalMessages = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages'));
$sentToday = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE DATE(created_at) = CURDATE() AND direction = 'outgoing'");
$totalContacts = $db->count('contacts');
$totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM " . $db->table('payments') . " WHERE status = 'completed'") ?: 0;

// Recent activity
$recentUsers = $db->fetchAll(
    "SELECT id, name, email, role, status, created_at FROM " . $db->table('users') . " ORDER BY created_at DESC LIMIT 5"
);
$recentCampaigns = $db->fetchAll(
    "SELECT c.*, u.name as user_name FROM " . $db->table('campaigns') . " c 
     LEFT JOIN " . $db->table('users') . " u ON c.user_id = u.id 
     ORDER BY c.created_at DESC LIMIT 5"
);

// Monthly stats for chart
$monthlyMessages = $db->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
     FROM " . $db->table('messages') . " 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
     GROUP BY month ORDER BY month"
);

include ASSETS_PATH . 'templates/header.php';
?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <h3><?= format_number($totalUsers) ?></h3>
                <p>Total Users</p>
            </div>
            <span class="stat-badge"><?= $activeUsers ?> active</span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-success">
            <div class="stat-icon"><i class="bi bi-chat-dots"></i></div>
            <div class="stat-content">
                <h3><?= format_number($totalMessages) ?></h3>
                <p>Total Messages</p>
            </div>
            <span class="stat-badge"><?= $sentToday ?> today</span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-warning">
            <div class="stat-icon"><i class="bi bi-megaphone"></i></div>
            <div class="stat-content">
                <h3><?= format_number($totalCampaigns) ?></h3>
                <p>Campaigns</p>
            </div>
            <span class="stat-badge"><?= $activeCampaigns ?> running</span>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-card-info">
            <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
            <div class="stat-content">
                <h3><?= get_setting('currency_symbol', '$') ?><?= format_number($totalRevenue) ?></h3>
                <p>Total Revenue</p>
            </div>
            <span class="stat-badge"><?= format_number($totalContacts) ?> contacts</span>
        </div>
    </div>
</div>

<!-- Charts & Activity -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Message Activity (Last 6 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="messagesChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-bold">Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="campaigns.php?action=new" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-plus-circle"></i> New Campaign
                </a>
                <a href="users.php?action=new" class="btn btn-outline-success btn-sm text-start">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
                <a href="contacts.php" class="btn btn-outline-info btn-sm text-start">
                    <i class="bi bi-people"></i> Manage Contacts
                </a>
                <a href="whatsapp.php" class="btn btn-outline-warning btn-sm text-start">
                    <i class="bi bi-whatsapp"></i> WhatsApp Accounts
                </a>
                <a href="settings.php" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-gear"></i> System Settings
                </a>
                <a href="backups.php" class="btn btn-outline-dark btn-sm text-start">
                    <i class="bi bi-cloud-download"></i> Backup Now
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users & Campaigns -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Recent Users</h6>
                <a href="users.php" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2"><?= strtoupper($u['name'][0]) ?></div>
                                        <div>
                                            <div class="fw-medium"><?= sanitize($u['name']) ?></div>
                                            <small class="text-muted"><?= sanitize($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                            <tr><td class="text-center text-muted py-4">No users yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Recent Campaigns</h6>
                <a href="campaigns.php" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($recentCampaigns as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= sanitize($c['name']) ?></div>
                                    <small class="text-muted">by <?= sanitize($c['user_name'] ?? 'N/A') ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-<?= match($c['status']) {
                                        'running' => 'success', 'completed' => 'primary',
                                        'scheduled' => 'warning', 'failed' => 'danger',
                                        default => 'secondary'
                                    } ?>"><?= ucfirst($c['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentCampaigns)): ?>
                            <tr><td class="text-center text-muted py-4">No campaigns yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const ctx = document.getElementById("messagesChart");
if (ctx) {
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ' . json_encode(array_column($monthlyMessages, 'month')) . ',
            datasets: [{
                label: "Messages Sent",
                data: ' . json_encode(array_column($monthlyMessages, 'count')) . ',
                backgroundColor: "rgba(99, 102, 241, 0.2)",
                borderColor: "rgb(99, 102, 241)",
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
