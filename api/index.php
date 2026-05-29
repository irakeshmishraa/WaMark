<?php
/**
 * WaMark - API Router
 * RESTful API endpoint handler
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$path = trim(str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH)), '/');
$method = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', $path);

// API Authentication
$apiUser = null;
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

if ($apiKey) {
    $keyRecord = $db->fetch(
        "SELECT ak.*, u.* FROM " . $db->table('api_keys') . " ak 
         JOIN " . $db->table('users') . " u ON ak.user_id = u.id 
         WHERE ak.api_key = ? AND ak.is_active = 1 AND u.status = 'active'",
        [$apiKey]
    );
    
    if ($keyRecord) {
        // Check expiry
        if ($keyRecord['expires_at'] && strtotime($keyRecord['expires_at']) < time()) {
            json_response(['error' => 'API key expired'], 401);
        }
        $apiUser = $keyRecord;
        // Update last used
        $db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'api_key = ?', [$apiKey]);
    }
}

// Rate limiting
if ($apiUser) {
    Middleware::rateLimit('api_' . $apiUser['user_id'], $apiUser['rate_limit'] ?? 60);
}

// Route API calls
$resource = $segments[0] ?? '';
$resourceId = $segments[1] ?? null;
$subResource = $segments[2] ?? null;

try {
    switch ($resource) {
        case 'contacts':
            require __DIR__ . '/endpoints/contacts.php';
            break;
        case 'messages':
            require __DIR__ . '/endpoints/messages.php';
            break;
        case 'campaigns':
            require __DIR__ . '/endpoints/campaigns.php';
            break;
        case 'templates':
            require __DIR__ . '/endpoints/templates.php';
            break;
        case 'whatsapp':
            require __DIR__ . '/endpoints/whatsapp.php';
            break;
        case 'webhook':
        case 'webhooks':
            require __DIR__ . '/webhook.php';
            break;
        case 'status':
            json_response(['status' => 'ok', 'version' => WAMARK_VERSION, 'time' => date('c')]);
            break;
        default:
            json_response(['error' => 'Endpoint not found', 'available' => ['contacts', 'messages', 'campaigns', 'templates', 'whatsapp', 'webhook', 'status']], 404);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    json_response(['error' => $e->getMessage()], $code);
}
