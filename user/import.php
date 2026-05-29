<?php
/**
 * WaMark - Contact Import (CSV/Excel)
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Import Contacts';
$userId = Auth::id();

if (request_method() === 'POST') {
    verify_csrf();
    
    if (empty($_FILES['file']['tmp_name'])) {
        flash('error', 'Please select a file to upload.');
        redirect(USER_URL . '/import.php');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $groupId = (int)($_POST['group_id'] ?? 0);
    $phoneCol = (int)($_POST['phone_col'] ?? 0);
    $nameCol = isset($_POST['name_col']) && $_POST['name_col'] !== '' ? (int)$_POST['name_col'] : null;
    $emailCol = isset($_POST['email_col']) && $_POST['email_col'] !== '' ? (int)$_POST['email_col'] : null;
    $skipFirst = isset($_POST['skip_header']);

    if (!in_array($ext, ['csv', 'txt'])) {
        flash('error', 'Only CSV and TXT files are supported.');
        redirect(USER_URL . '/import.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Could not read file.');
        redirect(USER_URL . '/import.php');
    }

    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $lineNum = 0;

    // Check plan limit
    $currentCount = $db->count('contacts', 'user_id = ?', [$userId]);
    $user = Auth::user();
    $plan = $db->fetch("SELECT * FROM " . $db->table('plans') . " WHERE id = ?", [$user['plan_id'] ?? 0]);
    $maxContacts = $plan ? (int)$plan['max_contacts'] : 100;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if ($skipFirst && $lineNum === 1) continue;
        if (empty($row[$phoneCol])) { $errors++; continue; }

        // Check limit
        if ($maxContacts !== -1 && ($currentCount + $imported) >= $maxContacts) {
            flash('warning', "Contact limit reached ({$maxContacts}). Some contacts were not imported.");
            break;
        }

        $phone = clean_phone($row[$phoneCol]);
        if (empty($phone) || strlen($phone) < 8) { $errors++; continue; }

        $name = $nameCol !== null && isset($row[$nameCol]) ? trim($row[$nameCol]) : null;
        $email = $emailCol !== null && isset($row[$emailCol]) ? trim($row[$emailCol]) : null;

        // Check duplicate
        if ($db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $phone])) {
            $skipped++;
            continue;
        }

        $contactId = $db->insert('contacts', [
            'user_id' => $userId,
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
            'status' => 'active',
            'source' => 'import',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add to group
        if ($groupId && $contactId) {
            $db->insert('contact_group_members', [
                'group_id' => $groupId, 'contact_id' => $contactId, 'added_at' => date('Y-m-d H:i:s')
            ]);
        }
        $imported++;
    }
    fclose($handle);

    // Update group count
    if ($groupId) {
        $count = $db->count('contact_group_members', 'group_id = ?', [$groupId]);
        $db->update('contact_groups', ['contact_count' => $count], 'id = ?', [$groupId]);
    }

    flash('success', "Import complete: {$imported} imported, {$skipped} duplicates skipped, {$errors} errors.");
    redirect(USER_URL . '/contacts.php');
}

$groups = $db->fetchAll("SELECT * FROM " . $db->table('contact_groups') . " WHERE user_id = ?", [$userId]);

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-upload text-primary"></i> Import Contacts</h6></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    
                    <div class="mb-4">
                        <label class="form-label fw-medium">Upload CSV File *</label>
                        <input type="file" class="form-control" name="file" accept=".csv,.txt" required>
                        <small class="text-muted">Supported: .csv, .txt (comma-separated)</small>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Phone Column # *</label>
                            <input type="number" class="form-control" name="phone_col" value="0" min="0" required>
                            <small class="text-muted">0 = first column</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Name Column #</label>
                            <input type="number" class="form-control" name="name_col" value="1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email Column #</label>
                            <input type="number" class="form-control" name="email_col" placeholder="Leave empty">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Add to Group</label>
                            <select class="form-select" name="group_id">
                                <option value="0">No Group</option>
                                <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= sanitize($g['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="skip_header" checked>
                                <label class="form-check-label">Skip first row (header)</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import Contacts</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">CSV Format Example</h6></div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded small mb-3">phone,name,email
+1234567890,John Doe,john@example.com
+0987654321,Jane Smith,jane@example.com
+1122334455,Bob Wilson,</pre>
                <h6 class="small fw-bold">Notes:</h6>
                <ul class="small text-muted mb-0">
                    <li>Include country code (+1, +91, etc.)</li>
                    <li>Duplicates are automatically skipped</li>
                    <li>Invalid numbers are ignored</li>
                    <li>Column numbers start from 0</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
