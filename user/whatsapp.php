<?php
/**
 * WaMark - WhatsApp Account Connection
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'WhatsApp Accounts';
$userId = Auth::id();

// Handle actions
if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_cloud') {
        $name = trim($_POST['name'] ?? '');
        $phoneNumberId = trim($_POST['phone_number_id'] ?? '');
        $accessToken = trim($_POST['access_token'] ?? '');
        $businessId = trim($_POST['business_account_id'] ?? '');

        if (empty($name) || empty($phoneNumberId) || empty($accessToken)) {
            flash('error', 'Name, Phone Number ID, and Access Token are required.');
        } else {
            $db->insert('whatsapp_accounts', [
                'user_id' => $userId,
                'name' => $name,
                'mode' => 'cloud_api',
                'phone_number_id' => $phoneNumberId,
                'business_account_id' => $businessId,
                'access_token' => $accessToken,
                'status' => 'connected',
                'last_connected_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            flash('success', 'WhatsApp Cloud API account connected successfully.');
        }
    } elseif ($action === 'add_session') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            flash('error', 'Account name is required.');
        } else {
            $db->insert('whatsapp_accounts', [
                'user_id' => $userId,
                'name' => $name,
                'mode' => 'non_api',
                'status' => 'disconnected',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            flash('success', 'Session account added. Scan QR code to connect.');
        }
    } elseif ($action === 'disconnect') {
        $accountId = (int)$_POST['account_id'];
        $db->update('whatsapp_accounts', ['status' => 'disconnected', 'session_data' => null], 'id = ? AND user_id = ?', [$accountId, $userId]);
        flash('success', 'Account disconnected.');
    } elseif ($action === 'delete') {
        $accountId = (int)$_POST['account_id'];
        $db->delete('whatsapp_accounts', 'id = ? AND user_id = ?', [$accountId, $userId]);
        flash('success', 'Account deleted.');
    }
    redirect(USER_URL . '/whatsapp.php');
}

$accounts = $db->fetchAll("SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage your WhatsApp connections</p>
    <div class="dropdown">
        <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-plus"></i> Add Account
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#cloudApiModal"><i class="bi bi-cloud"></i> Cloud API</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sessionModal"><i class="bi bi-qr-code"></i> QR Code Session</a></li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($accounts as $acc): ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-<?= $acc['status']==='connected'?'success':'secondary' ?> bg-opacity-10 p-3 me-3">
                            <i class="bi bi-whatsapp text-<?= $acc['status']==='connected'?'success':'secondary' ?> fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold"><?= sanitize($acc['name']) ?></h6>
                            <small class="text-muted"><?= $acc['phone_number'] ?: 'No number' ?></small>
                        </div>
                    </div>
                    <span class="badge bg-<?= $acc['status']==='connected'?'success':($acc['status']==='expired'?'warning':'secondary') ?>">
                        <?= ucfirst($acc['status']) ?>
                    </span>
                </div>
                
                <div class="mt-3 small text-muted">
                    <div><strong>Mode:</strong> <?= $acc['mode'] === 'cloud_api' ? 'Cloud API' : 'QR Session' ?></div>
                    <?php if ($acc['last_connected_at']): ?>
                    <div><strong>Last Active:</strong> <?= time_ago($acc['last_connected_at']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($acc['mode'] === 'non_api' && $acc['status'] === 'disconnected'): ?>
                <div class="mt-3 text-center p-3 bg-light rounded">
                    <i class="bi bi-qr-code" style="font-size:40px;"></i>
                    <p class="small text-muted mt-2 mb-0">QR Code will appear here when connecting</p>
                    <button class="btn btn-sm btn-success mt-2" onclick="generateQR(<?= $acc['id'] ?>)"><i class="bi bi-arrow-repeat"></i> Generate QR</button>
                </div>
                <?php endif; ?>

                <div class="mt-3 d-flex gap-2">
                    <?php if ($acc['status'] === 'connected'): ?>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="form_action" value="disconnect"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning">Disconnect</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this account?')">
                        <?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($accounts)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-whatsapp text-success" style="font-size:60px;"></i>
                <h5 class="mt-3">Connect Your WhatsApp</h5>
                <p class="text-muted">Choose a connection method to start sending messages.</p>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#cloudApiModal"><i class="bi bi-cloud"></i> Cloud API</button>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#sessionModal"><i class="bi bi-qr-code"></i> QR Session</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Cloud API Modal -->
<div class="modal fade" id="cloudApiModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="add_cloud">
            <div class="modal-header"><h5 class="modal-title">Connect via Cloud API</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Get credentials from <a href="https://developers.facebook.com" target="_blank">Meta Developer Portal</a></div>
                <div class="mb-3"><label class="form-label">Account Name *</label><input type="text" class="form-control" name="name" placeholder="My Business" required></div>
                <div class="mb-3"><label class="form-label">Phone Number ID *</label><input type="text" class="form-control" name="phone_number_id" placeholder="1234567890" required></div>
                <div class="mb-3"><label class="form-label">Access Token *</label><textarea class="form-control" name="access_token" rows="3" placeholder="EAABx..." required></textarea></div>
                <div class="mb-3"><label class="form-label">Business Account ID</label><input type="text" class="form-control" name="business_account_id" placeholder="Optional"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Connect</button></div>
        </form>
    </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?><input type="hidden" name="form_action" value="add_session">
            <div class="modal-header"><h5 class="modal-title">Connect via QR Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> QR code connection requires a Node.js backend service. This creates the account record - connection is handled by the WhatsApp engine.</div>
                <div class="mb-3"><label class="form-label">Account Name *</label><input type="text" class="form-control" name="name" placeholder="My WhatsApp" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Add Account</button></div>
        </form>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
