<?php
/**
 * WaMark - White Label WhatsApp Marketing Platform
 * Core Constants Configuration
 */

// Application Version
define('WAMARK_VERSION', '1.0.0');
define('WAMARK_NAME', 'WaMark');
define('WAMARK_TAGLINE', 'White Label WhatsApp Marketing Platform');

// Path Constants
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('ADMIN_PATH', ROOT_PATH . 'admin/');
define('USER_PATH', ROOT_PATH . 'user/');
define('API_PATH', ROOT_PATH . 'api/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('STORAGE_PATH', ROOT_PATH . 'storage/');
define('MODULES_PATH', ROOT_PATH . 'modules/');
define('CRON_PATH', ROOT_PATH . 'cron/');
define('INSTALLER_PATH', ROOT_PATH . 'installer/');
define('VENDOR_PATH', ROOT_PATH . 'vendor/');

// URL Constants (set after env loaded)
// These are set dynamically in app.php

// Security Constants
define('CSRF_TOKEN_NAME', 'wamark_csrf_token');
define('SESSION_NAME', 'wamark_session');
define('COOKIE_PREFIX', 'wm_');

// File Upload Limits
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv']);
define('ALLOWED_MEDIA_TYPES', ['mp4', 'mp3', 'ogg', 'wav']);

// Pagination
define('DEFAULT_PER_PAGE', 25);

// WhatsApp Constants
define('WA_SESSION_TIMEOUT', 300); // 5 minutes
define('WA_MESSAGE_BATCH_SIZE', 50);
define('WA_RATE_LIMIT_DELAY', 2); // seconds between messages
define('WA_MAX_RETRIES', 3);

// Plan Limits (defaults, overridden by DB)
define('FREE_TRIAL_DAYS', 7);
define('MAX_CONTACTS_FREE', 100);
define('MAX_MESSAGES_FREE', 500);

// Cron Intervals (seconds)
define('CRON_CAMPAIGN_INTERVAL', 60);
define('CRON_QUEUE_INTERVAL', 30);
define('CRON_CLEANUP_INTERVAL', 86400);
define('CRON_BACKUP_INTERVAL', 86400);
