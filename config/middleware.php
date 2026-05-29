<?php
/**
 * WaMark - Middleware Layer
 * Handles XSS filtering, rate limiting, maintenance mode, etc.
 */

class Middleware {

    /**
     * Run all middleware checks
     */
    public static function run($options = []) {
        self::checkMaintenanceMode($options);
        self::sanitizeInput();
        self::setSecurityHeaders();
        self::checkSessionTimeout();
    }

    /**
     * Check maintenance mode
     */
    public static function checkMaintenanceMode($options = []) {
        if (!IS_INSTALLED) return;
        
        $bypass = $options['bypass_maintenance'] ?? false;
        if ($bypass) return;

        $maintenance = get_setting('maintenance_mode', '0');
        if ($maintenance === '1' && !Auth::isAdmin()) {
            http_response_code(503);
            include ROOT_PATH . 'assets/templates/maintenance.php';
            exit;
        }
    }

    /**
     * Sanitize all input data (XSS protection)
     */
    public static function sanitizeInput() {
        $_GET = self::cleanArray($_GET);
        // Don't sanitize POST globally - do it per-field to preserve rich content
    }

    /**
     * Clean array recursively
     */
    private static function cleanArray($data) {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            if (is_array($value)) {
                $cleaned[$key] = self::cleanArray($value);
            } else {
                $cleaned[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }
        return $cleaned;
    }

    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        if (headers_sent()) return;
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Check session timeout (30 min inactivity)
     */
    public static function checkSessionTimeout($timeout = 1800) {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                Auth::logout();
                if (is_ajax()) {
                    json_response(['error' => 'Session expired'], 401);
                }
                flash('warning', 'Session expired. Please login again.');
                redirect(BASE_URL . '/admin/login.php');
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Require POST method
     */
    public static function requirePost() {
        if (request_method() !== 'POST') {
            if (is_ajax()) {
                json_response(['error' => 'Method not allowed'], 405);
            }
            http_response_code(405);
            die('Method Not Allowed');
        }
    }

    /**
     * Verify CSRF and require POST
     */
    public static function requirePostWithCsrf() {
        self::requirePost();
        verify_csrf();
    }

    /**
     * Simple rate limiter (IP-based)
     */
    public static function rateLimit($key, $maxRequests = 60, $windowSeconds = 60) {
        $cacheFile = STORAGE_PATH . 'cache/rate_' . md5($key . get_client_ip()) . '.json';
        
        $data = ['count' => 0, 'window_start' => time()];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }

        // Reset window if expired
        if (time() - $data['window_start'] > $windowSeconds) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        $data['count']++;
        file_put_contents($cacheFile, json_encode($data));

        if ($data['count'] > $maxRequests) {
            if (is_ajax()) {
                json_response(['error' => 'Too many requests. Please try again later.'], 429);
            }
            http_response_code(429);
            die('Too Many Requests. Please try again later.');
        }
    }

    /**
     * Check user plan limits
     */
    public static function checkPlanLimit($resource, $currentCount) {
        if (Auth::isAdmin()) return true;

        $user = Auth::user();
        if (!$user || !$user['plan_id']) return false;

        global $db;
        $plan = $db->fetch(
            "SELECT * FROM " . $db->table('plans') . " WHERE id = ?",
            [$user['plan_id']]
        );

        if (!$plan) return false;

        $limitField = 'max_' . $resource;
        if (!isset($plan[$limitField])) return true;

        $limit = (int)$plan[$limitField];
        if ($limit === -1) return true; // Unlimited

        return $currentCount < $limit;
    }
}
