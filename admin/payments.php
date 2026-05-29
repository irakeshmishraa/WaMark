<?php
/**
 * WaMark - Payment Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'Payments';

$statusFilter = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($statusFilter) { $where .= ' AND p.status = ?'; $params[] = $statusFilter; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('payments') . " p WHERE {$where}", $params);
$pagination = paginate($total);

$payments = $db->fetchAll(
    "SELECT p.*, u.name as user_name, u.email as user_email
     FROM " . $db->table('payments') . " p 
     LEFT JOIN " . $db->table('users') . " u ON p.user_id = u.id 
     WHERE {$where} ORDER BY p.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

// Summary stats
$totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM " . $db->table('payments') . " WHERE status = 'completed'") ?: 0;
$thisMonth = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM " . $db->table('payments') . " WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?: 0;
$pendingAmount = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM " . $db->table('payments') . " WHERE status = 'pending'") ?: 0;

$symbol = get_setting('currency_symbol', '$');

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <h4 class="text-success mb-1"><?= $symbol ?><?= number_format($totalRevenue, 2) ?></h4>
            <small class="text-muted">Total Revenue</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <h4 class="text-primary mb-1"><?= $symbol ?><?= number_format($thisMonth, 2) ?></h4>
            <small class="text-muted">This Month</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <h4 class="text-warning mb-1"><?= $symbol ?><?= number_format($pendingAmount, 2) ?></h4>
            <small class="text-muted">Pending</small>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between">
        <div class="d-flex gap-2">
            <a href="payments.php" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
            <a href="payments.php?status=completed" class="btn btn-sm btn-outline-success">Completed</a>
            <a href="payments.php?status=pending" class="btn btn-sm btn-outline-warning">Pending</a>
            <a href="payments.php?status=failed" class="btn btn-sm btn-outline-danger">Failed</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Transaction ID</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td>
                        <div class="fw-medium"><?= sanitize($p['user_name'] ?? 'Deleted User') ?></div>
                        <small class="text-muted"><?= sanitize($p['user_email'] ?? '') ?></small>
                    </td>
                    <td class="fw-bold"><?= $symbol ?><?= number_format($p['amount'], 2) ?></td>
                    <td><span class="badge bg-light text-dark"><?= ucfirst($p['gateway']) ?></span></td>
                    <td><span class="badge bg-<?= match($p['status']) { 'completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'info',default=>'secondary' } ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><small class="text-muted"><?= $p['transaction_id'] ?? '—' ?></small></td>
                    <td><small><?= format_datetime($p['created_at']) ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No payments found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'payments.php') ?></div>
    <?php endif; ?>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
