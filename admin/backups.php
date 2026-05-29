<?php
/**
 * WaMark - Backup Management
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['super_admin']);
Middleware::run();

$pageTitle = 'Backups';

// Handle backup creation
if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create_backup') {
        $type = $_POST['type'] ?? 'database';
        $filename = 'backup_' . date('Y-m-d_His') . '_' . $type . '.sql';
        $filepath = STORAGE_PATH . 'backups/' . $filename;

        // Create database backup
        $dbConf = ['host' => DB_HOST, 'user' => DB_USER, 'pass' => DB_PASS, 'name' => DB_NAME];
        $backupId = $db->insert('backups', [
            'filename' => $filename,
            'file_path' => $filepath,
            'file_size' => 0,
            'type' => $type,
            'status' => 'in_progress',
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Simple SQL dump using PDO
        try {
            $tables = $db->fetchAll("SHOW TABLES");
            $sql = "-- WaMark Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $createTable = $db->fetch("SHOW CREATE TABLE `{$tableName}`");
                $sql .= $createTable['Create Table'] . ";\n\n";
                
                $rows = $db->fetchAll("SELECT * FROM `{$tableName}`");
                foreach ($rows as $row) {
                    $values = array_map(function($v) {
                        return $v === null ? 'NULL' : "'" . addslashes($v) . "'";
                    }, array_values($row));
                    $sql .= "INSERT INTO `{$tableName}` VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }

            file_put_contents($filepath, $sql);
            $fileSize = filesize($filepath);
            $db->update('backups', ['status' => 'completed', 'file_size' => $fileSize], 'id = ?', [$backupId]);
            flash('success', 'Backup created successfully (' . format_size($fileSize) . ')');
        } catch (Exception $e) {
            $db->update('backups', ['status' => 'failed', 'notes' => $e->getMessage()], 'id = ?', [$backupId]);
            flash('error', 'Backup failed: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['backup_id'];
        $backup = $db->fetch("SELECT * FROM " . $db->table('backups') . " WHERE id = ?", [$id]);
        if ($backup && file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        $db->delete('backups', 'id = ?', [$id]);
        flash('success', 'Backup deleted.');
    }
    redirect(ADMIN_URL . '/backups.php');
}

$backups = $db->fetchAll("SELECT * FROM " . $db->table('backups') . " ORDER BY created_at DESC LIMIT 50");

include ASSETS_PATH . 'templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage database and file backups</p>
    <form method="POST" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="create_backup">
        <input type="hidden" name="type" value="database">
        <button class="btn btn-primary btn-sm"><i class="bi bi-cloud-download"></i> Create Backup Now</button>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Filename</th><th>Type</th><th>Size</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td><i class="bi bi-file-earmark-zip text-warning"></i> <?= sanitize($b['filename']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= ucfirst($b['type']) ?></span></td>
                    <td><?= format_size($b['file_size']) ?></td>
                    <td><span class="badge bg-<?= $b['status']==='completed'?'success':($b['status']==='failed'?'danger':'warning') ?>"><?= ucfirst($b['status']) ?></span></td>
                    <td><small><?= format_datetime($b['created_at']) ?></small></td>
                    <td class="text-end">
                        <?php if ($b['status'] === 'completed' && file_exists($b['file_path'])): ?>
                        <a href="download-backup.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup?')">
                            <?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="backup_id" value="<?= $b['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($backups)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No backups yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
