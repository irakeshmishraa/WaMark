<?php
/**
 * WaMark - Update/Upgrade Engine Module
 * Handles system updates and version management
 */

class UpdateEngine {
    private $db;
    private $currentVersion;
    private $updateUrl;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->currentVersion = WAMARK_VERSION;
        $this->updateUrl = get_setting('update_server_url', '');
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates() {
        if (empty($this->updateUrl)) {
            return ['available' => false, 'message' => 'Update server not configured'];
        }

        $ch = curl_init($this->updateUrl . '/api/check-update');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'current_version' => $this->currentVersion,
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'license_key' => env('LICENSE_KEY', ''),
                'php_version' => PHP_VERSION,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if ($data && isset($data['latest_version'])) {
            if (version_compare($data['latest_version'], $this->currentVersion, '>')) {
                // Record available update
                $exists = $this->db->exists('updates', 'version = ?', [$data['latest_version']]);
                if (!$exists) {
                    $this->db->insert('updates', [
                        'version' => $data['latest_version'],
                        'description' => $data['description'] ?? '',
                        'changelog' => $data['changelog'] ?? '',
                        'file_url' => $data['download_url'] ?? '',
                        'status' => 'available',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                return ['available' => true, 'version' => $data['latest_version'], 'changelog' => $data['changelog'] ?? ''];
            }
        }

        return ['available' => false, 'current' => $this->currentVersion];
    }

    /**
     * Apply database migrations for version upgrade
     */
    public function runMigrations($targetVersion) {
        $migrationsDir = ROOT_PATH . 'database/migrations/';
        if (!is_dir($migrationsDir)) return true;

        $files = glob($migrationsDir . '*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            // Expected format: v1.0.1_add_feature.sql
            preg_match('/^v([\d.]+)_/', $filename, $matches);
            if (!$matches) continue;

            $fileVersion = $matches[1];
            if (version_compare($fileVersion, $this->currentVersion, '>') && 
                version_compare($fileVersion, $targetVersion, '<=')) {
                
                $sql = file_get_contents($file);
                if ($sql) {
                    try {
                        $this->db->getPdo()->exec($sql);
                    } catch (Exception $e) {
                        return ['error' => "Migration {$filename} failed: " . $e->getMessage()];
                    }
                }
            }
        }

        // Update version in settings
        update_setting('system_version', $targetVersion);
        return true;
    }

    /**
     * Get current version info
     */
    public function getCurrentVersion() {
        return [
            'version' => $this->currentVersion,
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->db->fetchColumn("SELECT VERSION()"),
            'installed_at' => json_decode(file_get_contents(ROOT_PATH . 'config/installed.lock'), true)['installed_at'] ?? 'Unknown',
        ];
    }

    /**
     * Get update history
     */
    public function getHistory() {
        return $this->db->fetchAll("SELECT * FROM " . $this->db->table('updates') . " ORDER BY created_at DESC LIMIT 20");
    }
}
