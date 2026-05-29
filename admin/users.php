<?php
/**
 * WaMark - User Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin', 'reseller']);
Middleware::run();

$pageTitle = 'User Management';
$action = $_GET['action'] ?? 'list';

// Handle AJAX actions
if (is_ajax() && request_method() === 'POST') {
    verify_csrf();
    $ajaxAction = $_POST['ajax_action'] ?? '';
    
    switch ($ajaxAction) {
        case 'toggle_status':
            $userId = (int)$_POST['user_id'];
            $newStatus = $_POST['status'];
            $db->update('users', ['status' => $newStatus], 'id = ?', [$userId]);
            log_activity(Auth::id(), 'user_status_change', "Changed user #{$userId} status to {$newStatus}", 'users');
            json_response(['success' => true]);
            break;
            
        case 'delete':
            $userId = (int)$_POST['user_id'];
            $db->update('users', ['status' => 'inactive'], 'id = ?', [$userId]);
            log_activity(Auth::id(), 'user_delete', "Soft-deleted user #{$userId}", 'users');
            json_response(['success' => true]);
            break;
    }
}

// Handle user creation
if (request_method() === 'POST' && !is_ajax()) {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    $planId = (int)($_POST['plan_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($name) || empty($email) || empty($password)) {
        flash('error', 'Name, email, and password are required.');
    } elseif ($db->exists('users', 'email = ?', [$email])) {
        flash('error', 'Email already exists.');
    } else {
        $db->insert('users', [
            'uuid' => uuid_v4(),
            'name' => $name,
            'email' => $email,
            'password' => hash_password($password),
            'role' => $role,
            'status' => $status,
            'plan_id' => $planId ?: null,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        log_activity(Auth::id(), 'user_create', "Created user: {$email}", 'users');
        flash('success', 'User created successfully.');
    }
    redirect(ADMIN_URL . '/users.php');
}

// Fetch users
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = '1=1';
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($roleFilter) {
    $where .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

// If reseller, only show their clients
if (Auth::isReseller()) {
    $where .= " AND parent_id = ?";
    $params[] = Auth::id();
}

$total = $db->count('users', $where, $params);
$pagination = paginate($total);

$users = $db->fetchAll(
    "SELECT u.*, p.name as plan_name FROM " . $db->table('users') . " u 
     LEFT JOIN " . $db->table('plans') . " p ON u.plan_id = p.id 
     WHERE {$where} ORDER BY u.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$plans = $db->fetchAll("SELECT id, name FROM " . $db->table('plans') . " WHERE is_active = 1 ORDER BY sort_order");

include ASSETS_PATH . 'templates/header.php';
?>

<?php if ($action === 'new'): ?>
<!-- Create User Form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Add New User</h6>
        <a href="users.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
    </div>
    <div class="card-body">
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="client">Client</option>
                        <?php if (Auth::isAdmin()): ?>
                        <option value="reseller">Reseller</option>
                        <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Plan</label>
                    <select class="form-select" name="plan_id">
                        <option value="0">No Plan</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>"><?= sanitize($plan['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- User List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <form class="d-flex gap-2" method="GET">
                    <input type="text" class="form-control form-control-sm" name="search" 
                           placeholder="Search..." value="<?= sanitize($search) ?>" style="width:200px;">
                    <select class="form-select form-select-sm" name="role" style="width:130px;">
                        <option value="">All Roles</option>
                        <option value="client" <?= $roleFilter === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="reseller" <?= $roleFilter === 'reseller' ? 'selected' : '' ?>>Reseller</option>
                        <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <a href="users.php?action=new" class="btn btn-sm btn-primary">
                <i class="bi bi-plus"></i> Add User
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr id="user-row-<?= $u['id'] ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2"><?= strtoupper($u['name'][0]) ?></div>
                                <div>
                                    <div class="fw-medium"><?= sanitize($u['name']) ?></div>
                                    <small class="text-muted"><?= sanitize($u['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
                        <td><?= sanitize($u['plan_name'] ?? 'None') ?></td>
                        <td>
                            <span class="badge bg-<?= match($u['status']) { 'active' => 'success', 'suspended' => 'danger', 'pending' => 'warning', default => 'secondary' } ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td><small><?= format_date($u['created_at']) ?></small></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="user-edit.php?id=<?= $u['id'] ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                    <?php if ($u['status'] === 'active'): ?>
                                    <li><a class="dropdown-item" href="#" onclick="toggleUser(<?= $u['id'] ?>,'suspended')"><i class="bi bi-pause-circle"></i> Suspend</a></li>
                                    <?php else: ?>
                                    <li><a class="dropdown-item" href="#" onclick="toggleUser(<?= $u['id'] ?>,'active')"><i class="bi bi-play-circle"></i> Activate</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $u['id'] ?>)"><i class="bi bi-trash"></i> Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No users found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent">
        <?= render_pagination($pagination, 'users.php') ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$extraScripts = '<script>
function toggleUser(id, status) {
    if (!confirm("Change user status to " + status + "?")) return;
    fetch("users.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"},
        body: `ajax_action=toggle_status&user_id=${id}&status=${status}&' . CSRF_TOKEN_NAME . '=' . csrf_token() . '`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
}
function deleteUser(id) {
    if (!confirm("Are you sure you want to delete this user?")) return;
    fetch("users.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"},
        body: `ajax_action=delete&user_id=${id}&' . CSRF_TOKEN_NAME . '=' . csrf_token() . '`
    }).then(r => r.json()).then(d => { if(d.success) document.getElementById("user-row-"+id).remove(); });
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
