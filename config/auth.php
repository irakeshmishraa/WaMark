<?php
/**
 * WaMark - Authentication & Authorization System
 */

class Auth {
    private static $user = null;

    /**
     * Attempt login
     */
    public static function attempt($email, $password, $remember = false) {
        global $db;
        
        $user = $db->fetch(
            "SELECT * FROM " . $db->table('users') . " WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        );

        if (!$user || !verify_password($password, $user['password'])) {
            return false;
        }

        // Check 2FA if enabled
        if ($user['two_factor_enabled']) {
            $_SESSION['2fa_user_id'] = $user['id'];
            return '2fa_required';
        }

        self::loginUser($user, $remember);
        return true;
    }

    /**
     * Login user and set session
     */
    public static function loginUser($user, $remember = false) {
        global $db;

        // Regenerate session ID
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = get_client_ip();

        // Update last login
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => get_client_ip(),
        ], 'id = ?', [$user['id']]);

        // Remember me
        if ($remember) {
            $token = random_string(64);
            $hashedToken = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

            $db->insert('remember_tokens', [
                'user_id' => $user['id'],
                'token' => $hashedToken,
                'expires_at' => $expires,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            setcookie(COOKIE_PREFIX . 'remember', $token, [
                'expires' => strtotime('+30 days'),
                'path' => '/',
                'httponly' => true,
                'secure' => isset($_SERVER['HTTPS']),
                'samesite' => 'Lax',
            ]);
        }

        // Log activity
        log_activity($user['id'], 'login', 'User logged in', 'auth');

        self::$user = $user;
    }

    /**
     * Logout
     */
    public static function logout() {
        global $db;

        if (self::check()) {
            log_activity(self::id(), 'logout', 'User logged out', 'auth');

            // Remove remember token
            if (isset($_COOKIE[COOKIE_PREFIX . 'remember'])) {
                $token = hash('sha256', $_COOKIE[COOKIE_PREFIX . 'remember']);
                $db->delete('remember_tokens', 'token = ?', [$token]);
                setcookie(COOKIE_PREFIX . 'remember', '', time() - 3600, '/');
            }
        }

        // Destroy session
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }

    /**
     * Check if user is authenticated
     */
    public static function check() {
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // Check remember token
        if (isset($_COOKIE[COOKIE_PREFIX . 'remember'])) {
            return self::loginFromRememberToken();
        }

        return false;
    }

    /**
     * Login from remember token
     */
    private static function loginFromRememberToken() {
        global $db;

        $token = hash('sha256', $_COOKIE[COOKIE_PREFIX . 'remember']);
        $record = $db->fetch(
            "SELECT rt.*, u.* FROM " . $db->table('remember_tokens') . " rt 
             JOIN " . $db->table('users') . " u ON rt.user_id = u.id 
             WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active' LIMIT 1",
            [$token]
        );

        if ($record) {
            self::loginUser($record, true);
            return true;
        }

        // Invalid token, clear cookie
        setcookie(COOKIE_PREFIX . 'remember', '', time() - 3600, '/');
        return false;
    }

    /**
     * Get current user
     */
    public static function user() {
        global $db;

        if (self::$user !== null) {
            return self::$user;
        }

        if (!self::check()) {
            return null;
        }

        self::$user = $db->fetch(
            "SELECT * FROM " . $db->table('users') . " WHERE id = ? LIMIT 1",
            [$_SESSION['user_id']]
        );

        return self::$user;
    }

    /**
     * Get current user ID
     */
    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function role() {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if user is super admin
     */
    public static function isAdmin() {
        return self::role() === 'super_admin';
    }

    /**
     * Check if user is reseller
     */
    public static function isReseller() {
        return self::role() === 'reseller';
    }

    /**
     * Check if user is client
     */
    public static function isClient() {
        return self::role() === 'client';
    }

    /**
     * Check user has specific role
     */
    public static function hasRole($roles) {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        return in_array(self::role(), $roles);
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function requireAuth($redirectUrl = null) {
        if (!self::check()) {
            $redirectUrl = $redirectUrl ?? BASE_URL . '/admin/login.php';
            flash('error', 'Please login to continue.');
            redirect($redirectUrl);
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($roles, $redirectUrl = null) {
        self::requireAuth();
        
        if (!self::hasRole((array)$roles)) {
            http_response_code(403);
            flash('error', 'You do not have permission to access this resource.');
            redirect($redirectUrl ?? BASE_URL);
        }
    }

    /**
     * Verify 2FA code
     */
    public static function verify2FA($code) {
        global $db;

        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) return false;

        $user = $db->fetch(
            "SELECT * FROM " . $db->table('users') . " WHERE id = ? LIMIT 1",
            [$userId]
        );

        if (!$user) return false;

        // Verify TOTP code
        $secret = $user['two_factor_secret'];
        if (self::verifyTOTP($secret, $code)) {
            unset($_SESSION['2fa_user_id']);
            self::loginUser($user);
            return true;
        }

        return false;
    }

    /**
     * Verify TOTP code (Time-based One-Time Password)
     */
    private static function verifyTOTP($secret, $code, $window = 1) {
        $timeSlice = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate TOTP code
     */
    private static function generateTOTP($secret, $timeSlice) {
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode for TOTP
     */
    private static function base32Decode($input) {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    /**
     * Generate 2FA secret
     */
    public static function generate2FASecret($length = 16) {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $map[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Rate limiting for login attempts
     */
    public static function checkRateLimit($email, $maxAttempts = 5, $lockoutTime = 900) {
        global $db;

        $ip = get_client_ip();
        $since = date('Y-m-d H:i:s', time() - $lockoutTime);

        $attempts = $db->fetchColumn(
            "SELECT COUNT(*) FROM " . $db->table('login_attempts') . 
            " WHERE (email = ? OR ip_address = ?) AND attempted_at > ?",
            [$email, $ip, $since]
        );

        return (int)$attempts < $maxAttempts;
    }

    /**
     * Record login attempt
     */
    public static function recordLoginAttempt($email, $success = false) {
        global $db;

        $db->insert('login_attempts', [
            'email' => $email,
            'ip_address' => get_client_ip(),
            'user_agent' => get_user_agent(),
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
