<?php
/**
 * WaMark - Application Bootstrap
 * Loads environment, sets up autoloading, initializes core systems
 */

// Prevent direct access
if (!defined('WAMARK_VERSION')) {
    require_once __DIR__ . '/constants.php';
}

// Load environment configuration
$envFile = ROOT_PATH . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if (empty($line) || $line[0] === '#') continue;
            // Parse KEY=VALUE (handle values with special chars like #)
            if (strpos($line, '=') === false) continue;
            $pos = strpos($line, '=');
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            // Remove surrounding quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Check if installed
define('IS_INSTALLED', file_exists(ROOT_PATH . 'config/installed.lock'));

// Environment helper function
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Set dynamic URL constants
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = rtrim($protocol . '://' . $host . $scriptDir, '/');

// If we're in a subdirectory, adjust
if (strpos($baseUrl, '/admin') !== false || strpos($baseUrl, '/user') !== false || 
    strpos($baseUrl, '/api') !== false || strpos($baseUrl, '/installer') !== false) {
    $baseUrl = dirname($baseUrl);
}

define('BASE_URL', env('APP_URL', $baseUrl));
define('ADMIN_URL', BASE_URL . '/admin');
define('USER_URL', BASE_URL . '/user');
define('API_URL', BASE_URL . '/api');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Application settings from env
define('APP_NAME', env('APP_NAME', WAMARK_NAME));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
define('APP_KEY', env('APP_KEY', ''));

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'wamark'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_PREFIX', env('DB_PREFIX', 'wm_'));
define('DB_CHARSET', 'utf8mb4');

// Mail Configuration
define('MAIL_DRIVER', env('MAIL_DRIVER', 'smtp'));
define('MAIL_HOST', env('MAIL_HOST', ''));
define('MAIL_PORT', env('MAIL_PORT', '587'));
define('MAIL_USER', env('MAIL_USER', ''));
define('MAIL_PASS', env('MAIL_PASS', ''));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME));
define('MAIL_FROM_EMAIL', env('MAIL_FROM_EMAIL', ''));

// WhatsApp API Configuration
define('WA_API_MODE', env('WA_API_MODE', 'cloud')); // cloud, meta, non-api
define('WA_CLOUD_API_TOKEN', env('WA_CLOUD_API_TOKEN', ''));
define('WA_CLOUD_API_URL', 'https://graph.facebook.com/v18.0');
define('WA_WEBHOOK_VERIFY_TOKEN', env('WA_WEBHOOK_VERIFY_TOKEN', ''));
define('WA_PHONE_NUMBER_ID', env('WA_PHONE_NUMBER_ID', ''));
define('WA_BUSINESS_ACCOUNT_ID', env('WA_BUSINESS_ACCOUNT_ID', ''));

// Payment Gateway Configuration
define('STRIPE_KEY', env('STRIPE_KEY', ''));
define('STRIPE_SECRET', env('STRIPE_SECRET', ''));
define('RAZORPAY_KEY', env('RAZORPAY_KEY', ''));
define('RAZORPAY_SECRET', env('RAZORPAY_SECRET', ''));
define('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID', ''));
define('PAYPAL_SECRET', env('PAYPAL_SECRET', ''));
define('PAYU_MERCHANT_KEY', env('PAYU_MERCHANT_KEY', ''));
define('PAYU_SALT', env('PAYU_SALT', ''));

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session Configuration
ini_set('session.name', SESSION_NAME);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $protocol === 'https' ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load core classes
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'functions.php';
require_once CONFIG_PATH . 'auth.php';
require_once CONFIG_PATH . 'middleware.php';
require_once CONFIG_PATH . 'mailer.php';

// Initialize database connection if installed
$db = null;
if (IS_INSTALLED) {
    try {
        $db = Database::getInstance();
    } catch (Exception $e) {
        if (APP_DEBUG) {
            die('Database Error: ' . $e->getMessage());
        }
        // If DB connection fails, maybe bad .env - allow re-install
        // Delete the lock file so installer can run again
        $lockFile = ROOT_PATH . 'config/installed.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        // Redirect to installer
        $installerUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if (strpos($installerUrl, '/admin') !== false || strpos($installerUrl, '/user') !== false) {
            $installerUrl = dirname($installerUrl);
        }
        header('Location: ' . $installerUrl . '/installer/');
        exit;
    }
}
