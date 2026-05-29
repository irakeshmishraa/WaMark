<?php
/**
 * WaMark Cron - Automated Backup
 * Creates daily database backup
 */

$processed = 0;

// Check if backups are enabled
if (get_setting('backup_enabled', '1') !== '1') {
    return 0;
}

// Check if already backed up today
$todayBackup = $db->exists('backups', "DATE(created_at) = CURDATE() AND status = 'completed'");
if ($todayBackup) {
    return 0;
}

$filename = 'auto_backup_' . date('Y-m-d_His') . '.sql';
$filepath = STORAGE_PATH . 'backups/' . $filename;

// Ensure backup directory exists
if (!is_dir(STORAGE_PATH . 'backups/')) {
    mkdir(STORAGE_PATH . 'backups/', 0755, true);
}

$backupId = $db->insert('backups', [
    'filename' => $filename,
    'file_path' => $filepath,
    'file_size' => 0,
    'type' => 'database',
    'status' => 'in_progress',
    'notes' => 'Automated daily backup',
    'created_at' => date('Y-m-d H:i:s'),
]);

try {
    $tables = $db->fetchAll("SHOW TABLES");
    $sql = "-- WaMark Automated Database Backup\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Version: " . WAMARK_VERSION . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        
        // Get create statement
        $createResult = $db->fetch("SHOW CREATE TABLE `{$tableName}`");
        $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
        $sql .= $createResult['Create Table'] . ";\n\n";

        // Get data (in batches for large tables)
        $count = $db->fetchColumn("SELECT COUNT(*) FROM `{$tableName}`");
        $batchSize = 1000;
        
        for ($offset = 0; $offset < $count; $offset += $batchSize) {
            $rows = $db->fetchAll("SELECT * FROM `{$tableName}` LIMIT {$batchSize} OFFSET {$offset}");
            foreach ($rows as $row) {
                $values = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return "'" . addslashes($v) . "'";
                }, array_values($row));
                $sql .= "INSERT INTO `{$tableName}` VALUES (" . implode(',', $values) . ");\n";
            }
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Write file
    file_put_contents($filepath, $sql);
    $fileSize = filesize($filepath);

    // Try to compress if gzip available
    if (function_exists('gzencode')) {
        $gzFilepath = $filepath . '.gz';
        file_put_contents($gzFilepath, gzencode($sql, 9));
        unlink($filepath);
        $filepath = $gzFilepath;
        $filename .= '.gz';
        $fileSize = filesize($gzFilepath);
    }

    $db->update('backups', [
        'filename' => $filename,
        'file_path' => $filepath,
        'file_size' => $fileSize,
        'status' => 'completed',
    ], 'id = ?', [$backupId]);

    $processed = 1;
} catch (Exception $e) {
    $db->update('backups', [
        'status' => 'failed',
        'notes' => 'Error: ' . $e->getMessage(),
    ], 'id = ?', [$backupId]);
}

return $processed;
