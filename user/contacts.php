<?php
/**
 * WaMark - User Contacts Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Contacts';
$userId = Auth::id();

// AJAX actions
if (is_ajax() && request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['ajax_action'] ?? '';
    
    switch ($action) {
        case 'add':
            $phone = clean_phone($_POST['phone'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($phone)) json_response(['error' => 'Phone is required'], 400);
            if ($db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $phone])) {
                json_response(['error' => 'Contact already exists'], 400);
            }
            
            // Check plan limit
            $contactCount = $db->count('contacts', 'user_id = ?', [$userId]);
            if (!Middleware::checkPlanLimit('contacts', $contactCount)) {
                json_response(['error' => 'Contact limit reached. Please upgrade your plan.'], 403);
            }
            
            $id = $db->insert('contacts', [
                'user_id' => $userId,
                'phone' => $phone,
                'name' => $name ?: null,
                'email' => $email ?: null,
                'status' => 'active',
                'source' => 'manual',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            json_response(['success' => true, 'id' => $id]);
            break;
            
        case 'delete':
            $contactId = (int)$_POST['contact_id'];
            $db->delete('contacts', 'id = ? AND user_id = ?', [$contactId, $userId]);
            json_response(['success' => true]);
            break;
            
        case 'bulk_delete':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge($ids, [$userId]);
                $db->query("DELETE FROM " . $db->table('contacts') . " WHERE id IN ({$placeholders}) AND user_id = ?", $params);
            }
            json_response(['success' => true]);
            break;
    }
}

// Handle single add via form POST
if (request_method() === 'POST' && !is_ajax()) {
    verify_csrf();
    $phone = clean_phone($_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $group = (int)($_POST['group_id'] ?? 0);

    if (!empty($phone)) {
        if (!$db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $phone])) {
            $contactId = $db->insert('contacts', [
                'user_id' => $userId, 'phone' => $phone, 'name' => $name ?: null,
                'email' => $email ?: null, 'status' => 'active', 'source' => 'manual',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($group && $contactId) {
                $db->insert('contact_group_members', ['group_id' => $group, 'contact_id' => $contactId, 'added_at' => date('Y-m-d H:i:s')]);
            }
            flash('success', 'Contact added successfully.');
        } else {
            flash('error', 'Contact with this phone already exists.');
        }
    }
    redirect(USER_URL . '/contacts.php');
}

// Fetch contacts
$search = $_GET['search'] ?? '';
$groupFilter = (int)($_GET['group'] ?? 0);
$where = 'c.user_id = ?';
$params = [$userId];

if ($search) {
    $where .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%";
}
if ($groupFilter) {
    $where .= " AND c.id IN (SELECT contact_id FROM " . $db->table('contact_group_members') . " WHERE group_id = ?)";
    $params[] = $groupFilter;
}

$total = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('contacts') . " c WHERE {$where}", $params);
$pagination = paginate($total);

$contacts = $db->fetchAll(
    "SELECT c.* FROM " . $db->table('contacts') . " c WHERE {$where} ORDER BY c.created_at DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}", $params
);

$groups = $db->fetchAll("SELECT * FROM " . $db->table('contact_groups') . " WHERE user_id = ? ORDER BY name", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <form class="d-flex gap-2">
        <input type="text" class="form-control form-control-sm" name="search" placeholder="Search..." value="<?= sanitize($search) ?>" style="width:200px;">
        <select class="form-select form-select-sm" name="group" style="width:150px;">
            <option value="">All Groups</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $groupFilter==$g['id']?'selected':'' ?>><?= sanitize($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2">
        <a href="import.php" class="btn btn-sm btn-outline-info"><i class="bi bi-upload"></i> Import</a>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
            <i class="bi bi-plus"></i> Add Contact
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <small class="text-muted"><?= number_format($total) ?> contacts</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Added</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contacts as $c): ?>
                <tr id="contact-<?= $c['id'] ?>">
                    <td><input type="checkbox" class="form-check-input contact-check" value="<?= $c['id'] ?>"></td>
                    <td class="fw-medium"><?= sanitize($c['name'] ?: '—') ?></td>
                    <td><code><?= sanitize($c['phone']) ?></code></td>
                    <td><small><?= sanitize($c['email'] ?? '—') ?></small></td>
                    <td><span class="badge bg-<?= $c['status']==='active'?'success':'secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                    <td><small><?= time_ago($c['created_at']) ?></small></td>
                    <td class="text-end">
                        <a href="send.php?phone=<?= urlencode($c['phone']) ?>" class="btn btn-sm btn-outline-success" title="Send Message"><i class="bi bi-send"></i></a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteContact(<?= $c['id'] ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($contacts)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No contacts found. <a href="#" data-bs-toggle="modal" data-bs-target="#addContactModal">Add your first contact</a></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer bg-transparent"><?= render_pagination($pagination, 'contacts.php') ?></div>
    <?php endif; ?>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Add Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Phone Number *</label><input type="text" class="form-control" name="phone" placeholder="+1234567890" required></div>
                <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" name="name" placeholder="John Doe"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" placeholder="john@example.com"></div>
                <div class="mb-3"><label class="form-label">Group</label><select class="form-select" name="group_id"><option value="">No Group</option><?php foreach($groups as $g): ?><option value="<?=$g['id']?>"><?=sanitize($g['name'])?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add Contact</button></div>
        </form>
    </div>
</div>

<?php
$extraScripts = '<script>
document.getElementById("selectAll").addEventListener("change", function() {
    document.querySelectorAll(".contact-check").forEach(cb => cb.checked = this.checked);
});
function deleteContact(id) {
    if (!confirm("Delete this contact?")) return;
    fetch("contacts.php", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded","X-Requested-With":"XMLHttpRequest"},
        body: `ajax_action=delete&contact_id=${id}&' . CSRF_TOKEN_NAME . '=' . csrf_token() . '`
    }).then(r=>r.json()).then(d=>{ if(d.success) document.getElementById("contact-"+id).remove(); });
}
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
