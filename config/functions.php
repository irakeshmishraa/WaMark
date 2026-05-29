<?php
/**
 * WaMark - Core Helper Functions
 */

/**
 * Sanitize input string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Output CSRF hidden field
 */
function csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf($token = null) {
    $token = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || !hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token mismatch']));
    }
    return true;
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Set flash message
 */
function flash($type, $message) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Generate random string
 */
function random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate UUID v4
 */
function uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Format date
 */
function format_date($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function format_datetime($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

/**
 * Time ago format
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return format_date($datetime);
}

/**
 * Format number with suffix (1K, 1M, etc.)
 */
function format_number($num) {
    if ($num >= 1000000000) return round($num / 1000000000, 1) . 'B';
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return number_format($num);
}

/**
 * Format file size
 */
function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Validate email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic)
 */
function is_valid_phone($phone) {
    return preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone));
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get client IP
 */
function get_client_ip() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Get user agent
 */
function get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Send JSON response
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if request is AJAX
 */
function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request method
 */
function request_method() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Get POST data
 */
function post($key = null, $default = null) {
    if ($key === null) return $_POST;
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 */
function get($key = null, $default = null) {
    if ($key === null) return $_GET;
    return $_GET[$key] ?? $default;
}

/**
 * Paginate results
 */
function paginate($total, $perPage = DEFAULT_PER_PAGE, $currentPage = null) {
    $currentPage = $currentPage ?? max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * Render pagination HTML
 */
function render_pagination($pagination, $baseUrl = '') {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Previous
    $html .= '<li class="page-item ' . (!$pagination['has_prev'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] - 1) . '">&laquo;</a></li>';
    
    // Pages
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next
    $html .= '<li class="page-item ' . (!$pagination['has_next'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] + 1) . '">&raquo;</a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Upload file securely
 */
function upload_file($file, $directory, $allowedTypes = [], $maxSize = MAX_UPLOAD_SIZE) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }

    if ($file['size'] > $maxSize) {
        return ['error' => 'File too large. Maximum size: ' . format_size($maxSize)];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedTypes) && !in_array($extension, $allowedTypes)) {
        return ['error' => 'File type not allowed'];
    }

    $filename = uuid_v4() . '.' . $extension;
    $uploadDir = UPLOADS_PATH . trim($directory, '/') . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filepath = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'url' => UPLOADS_URL . '/' . trim($directory, '/') . '/' . $filename,
            'size' => $file['size'],
            'extension' => $extension,
        ];
    }

    return ['error' => 'Failed to upload file'];
}

/**
 * Delete uploaded file
 */
function delete_file($path) {
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    return false;
}

/**
 * Log activity
 */
function log_activity($userId, $action, $description = '', $module = 'system') {
    global $db;
    if (!$db) return;

    $db->insert('audit_logs', [
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'module' => $module,
        'ip_address' => get_client_ip(),
        'user_agent' => get_user_agent(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Get application setting
 */
function get_setting($key, $default = null) {
    global $db;
    if (!$db) return $default;
    
    static $settings = null;
    if ($settings === null) {
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM " . $db->table('settings'));
        $settings = array_column($rows, 'setting_value', 'setting_key');
    }
    return $settings[$key] ?? $default;
}

/**
 * Update application setting
 */
function update_setting($key, $value) {
    global $db;
    if (!$db) return false;

    $exists = $db->exists('settings', 'setting_key = ?', [$key]);
    if ($exists) {
        $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
    } else {
        $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
    return true;
}

/**
 * Parse message template variables
 */
function parse_template($template, $variables = []) {
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

/**
 * Clean phone number
 */
function clean_phone($phone) {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (substr($phone, 0, 1) !== '+') {
        $phone = '+' . $phone;
    }
    return $phone;
}

/**
 * Generate license key
 */
function generate_license_key() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    for ($i = 0; $i < 4; $i++) {
        if ($i > 0) $key .= '-';
        for ($j = 0; $j < 5; $j++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $key;
}

/**
 * Check file extension
 */
function get_file_icon($extension) {
    $icons = [
        'pdf' => 'bi-file-earmark-pdf text-danger',
        'doc' => 'bi-file-earmark-word text-primary',
        'docx' => 'bi-file-earmark-word text-primary',
        'xls' => 'bi-file-earmark-excel text-success',
        'xlsx' => 'bi-file-earmark-excel text-success',
        'csv' => 'bi-file-earmark-spreadsheet text-success',
        'jpg' => 'bi-file-earmark-image text-warning',
        'jpeg' => 'bi-file-earmark-image text-warning',
        'png' => 'bi-file-earmark-image text-warning',
        'mp4' => 'bi-file-earmark-play text-info',
        'mp3' => 'bi-file-earmark-music text-purple',
    ];
    return $icons[$extension] ?? 'bi-file-earmark text-muted';
}
