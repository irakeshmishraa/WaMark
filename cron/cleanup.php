<?php
/**
 * WaMark Cron - Cleanup Tasks
 * Removes old data, expired sessions, stale records
 */

$processed = 0;

// 1. Clean expired remember tokens
$deleted = $db->query(
    "DELETE FROM " . $db->table('remember_tokens') . " WHERE expires_at < NOW()"
)->rowCount();
$processed += $deleted;

// 2. Clean old login attempts (older than 7 days)
$deleted = $db->query(
    "DELETE FROM " . $db->table('login_attempts') . " WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->rowCount();
$processed += $deleted;

// 3. Clean old cron logs (older than 30 days)
$deleted = $db->query(
    "DELETE FROM " . $db->table('cron_logs') . " WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->rowCount();
$processed += $deleted;

// 4. Clean processed webhooks (older than 14 days)
$deleted = $db->query(
    "DELETE FROM " . $db->table('webhooks') . " WHERE status = 'processed' AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)"
)->rowCount();
$processed += $deleted;

// 5. Clean old notifications (read, older than 30 days)
$deleted = $db->query(
    "DELETE FROM " . $db->table('notifications') . " WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->rowCount();
$processed += $deleted;

// 6. Clean rate limit cache files
$cacheDir = STORAGE_PATH . 'cache/';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . 'rate_*.json');
    foreach ($files as $file) {
        if (filemtime($file) < time() - 3600) {
            unlink($file);
            $processed++;
        }
    }
}

// 7. Clean old session files
$sessionDir = STORAGE_PATH . 'sessions/';
if (is_dir($sessionDir)) {
    $files = glob($sessionDir . 'sess_*');
    foreach ($files as $file) {
        if (filemtime($file) < time() - 86400) {
            unlink($file);
            $processed++;
        }
    }
}

// 8. Remove old backup files beyond retention period
$retentionDays = (int)get_setting('backup_retention_days', 30);
$deleted = $db->fetchAll(
    "SELECT * FROM " . $db->table('backups') . " WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$retentionDays]
);
foreach ($deleted as $backup) {
    if (file_exists($backup['file_path'])) {
        unlink($backup['file_path']);
    }
    $db->delete('backups', 'id = ?', [$backup['id']]);
    $processed++;
}

// 9. Clean completed/failed scheduled jobs older than 7 days
$db->query(
    "DELETE FROM " . $db->table('scheduled_jobs') . " WHERE status IN ('completed','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

return $processed;
