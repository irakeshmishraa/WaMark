<?php
/**
 * WaMark - License Engine Module
 * Handles license validation, activation, and management
 */

class LicenseEngine {
    private $db;

    public function __construct() {
        global $db;
        $this->db = $db;
    }

    /**
     * Activate a license key
     */
    public function activate($licenseKey, $userId, $domain = null) {
        $license = $this->db->fetch(
            "SELECT * FROM " . $this->db->table('licenses') . " WHERE license_key = ?",
            [$licenseKey]
        );

        if (!$license) {
            return ['error' => 'Invalid license key'];
        }

        if ($license['status'] === 'revoked') {
            return ['error' => 'This license has been revoked'];
        }

        if ($license['status'] === 'expired') {
            return ['error' => 'This license has expired'];
        }

        if ($license['status'] === 'active' && $license['user_id'] !== $userId) {
            return ['error' => 'This license is already activated by another user'];
        }

        // Check domain restriction
        if ($license['domain'] && $domain && $license['domain'] !== $domain) {
            return ['error' => 'License domain mismatch'];
        }

        // Check expiry
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->db->update('licenses', ['status' => 'expired'], 'id = ?', [$license['id']]);
            return ['error' => 'This license has expired'];
        }

        // Activate
        $this->db->update('licenses', [
            'user_id' => $userId,
            'status' => 'active',
            'domain' => $domain ?? $_SERVER['HTTP_HOST'] ?? null,
            'ip_address' => get_client_ip(),
            'activated_at' => date('Y-m-d H:i:s'),
            'last_verified_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$license['id']]);

        return ['success' => true, 'type' => $license['type'], 'expires_at' => $license['expires_at']];
    }

    /**
     * Verify current license
     */
    public function verify($licenseKey = null) {
        $licenseKey = $licenseKey ?? env('LICENSE_KEY', '');
        if (empty($licenseKey)) return ['valid' => true]; // No license required in self-hosted

        $license = $this->db->fetch(
            "SELECT * FROM " . $this->db->table('licenses') . " WHERE license_key = ? AND status = 'active'",
            [$licenseKey]
        );

        if (!$license) return ['valid' => false, 'error' => 'License not found or inactive'];

        // Check expiry
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->db->update('licenses', ['status' => 'expired'], 'id = ?', [$license['id']]);
            return ['valid' => false, 'error' => 'License expired'];
        }

        // Check domain
        $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
        if ($license['domain'] && $license['domain'] !== $currentDomain) {
            return ['valid' => false, 'error' => 'Domain mismatch'];
        }

        // Update last verified
        $this->db->update('licenses', ['last_verified_at' => date('Y-m-d H:i:s')], 'id = ?', [$license['id']]);

        return [
            'valid' => true,
            'type' => $license['type'],
            'expires_at' => $license['expires_at'],
            'max_users' => $license['max_users'],
        ];
    }

    /**
     * Generate new license keys
     */
    public function generate($count = 1, $type = 'subscription', $maxUsers = 1, $expiresAt = null) {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $key = generate_license_key();
            $this->db->insert('licenses', [
                'license_key' => $key,
                'type' => $type,
                'status' => 'unused',
                'max_users' => $maxUsers,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $keys[] = $key;
        }
        return $keys;
    }

    /**
     * Revoke a license
     */
    public function revoke($licenseKey) {
        return $this->db->update('licenses', ['status' => 'revoked'], 'license_key = ?', [$licenseKey]);
    }

    /**
     * Get license info
     */
    public function getInfo($licenseKey) {
        return $this->db->fetch(
            "SELECT l.*, u.name as user_name, u.email as user_email 
             FROM " . $this->db->table('licenses') . " l 
             LEFT JOIN " . $this->db->table('users') . " u ON l.user_id = u.id 
             WHERE l.license_key = ?",
            [$licenseKey]
        );
    }

    /**
     * Check if current installation has valid license
     */
    public static function isLicensed() {
        $engine = new self();
        $result = $engine->verify();
        return $result['valid'] ?? false;
    }
}
