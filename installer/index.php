<?php
/**
 * WaMark - Installation Wizard
 * Multi-step installer for first-time setup
 */

session_start();

// Check if already installed
if (file_exists(dirname(__DIR__) . '/config/installed.lock')) {
    header('Location: ../admin/');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step));
$error = '';
$success = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_requirements':
            $step = 2;
            break;
            
        case 'setup_database':
            $result = setupDatabase($_POST);
            if ($result === true) {
                $_SESSION['installer_db'] = $_POST;
                $step = 4;
            } else {
                $error = $result;
                $step = 3;
            }
            break;
            
        case 'create_admin':
            $result = createAdmin($_POST);
            if ($result === true) {
                $step = 5;
            } else {
                $error = $result;
                $step = 4;
            }
            break;
            
        case 'finish':
            finalizeInstallation();
            break;
    }
}

// Installation functions
function checkRequirements() {
    $checks = [];
    
    // PHP Version
    $checks['php_version'] = [
        'name' => 'PHP Version (8.0+)',
        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'current' => PHP_VERSION,
        'required' => true,
    ];
    
    // Extensions
    $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'openssl', 'gd', 'fileinfo', 'zip'];
    foreach ($extensions as $ext) {
        $checks['ext_' . $ext] = [
            'name' => "PHP Extension: {$ext}",
            'status' => extension_loaded($ext),
            'current' => extension_loaded($ext) ? 'Installed' : 'Not Found',
            'required' => true,
        ];
    }
    
    // Optional extensions
    $optional = ['imagick', 'redis', 'memcached'];
    foreach ($optional as $ext) {
        $checks['ext_' . $ext] = [
            'name' => "PHP Extension: {$ext} (Optional)",
            'status' => extension_loaded($ext),
            'current' => extension_loaded($ext) ? 'Installed' : 'Not Found',
            'required' => false,
        ];
    }
    
    // Writable directories
    $dirs = ['config', 'storage', 'storage/logs', 'storage/backups', 'storage/cache', 'uploads'];
    foreach ($dirs as $dir) {
        $path = dirname(__DIR__) . '/' . $dir;
        $writable = is_dir($path) && is_writable($path);
        $checks['dir_' . str_replace('/', '_', $dir)] = [
            'name' => "Directory Writable: /{$dir}/",
            'status' => $writable,
            'current' => $writable ? 'Writable' : 'Not Writable',
            'required' => true,
        ];
    }
    
    // PHP Settings
    $uploadMax = ini_get('upload_max_filesize');
    $checks['upload_size'] = [
        'name' => 'upload_max_filesize (>= 10M)',
        'status' => convertToBytes($uploadMax) >= 10485760,
        'current' => $uploadMax,
        'required' => false,
    ];
    
    return $checks;
}

function convertToBytes($val) {
    $val = trim($val);
    $num = (int)$val;
    $last = strtolower($val[strlen($val)-1]);
    switch ($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return $num;
}


function setupDatabase($data) {
    $host = trim($data['db_host'] ?? 'localhost');
    $port = trim($data['db_port'] ?? '3306');
    $name = trim($data['db_name'] ?? '');
    $user = trim($data['db_user'] ?? '');
    $pass = $data['db_pass'] ?? '';
    $prefix = trim($data['db_prefix'] ?? 'wm_');

    if (empty($name) || empty($user)) {
        return 'Database name and username are required.';
    }

    try {
        // First try connecting directly to the database (shared hosting usually pre-creates it)
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            // If direct connection fails, try without dbname and create it
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$name}`");
        }

        // Import schema
        $schemaFile = dirname(__DIR__) . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            return 'Schema file not found. Please ensure database/schema.sql exists.';
        }

        $schema = file_get_contents($schemaFile);
        // Replace default prefix with custom prefix
        if ($prefix !== 'wm_') {
            $schema = str_replace('wm_', $prefix, $schema);
        }

        // Execute schema statements
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->exec($schema);

        // Store in session
        $_SESSION['installer_db'] = [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'prefix' => $prefix,
        ];

        return true;
    } catch (PDOException $e) {
        return 'Database Error: ' . $e->getMessage();
    }
}

function createAdmin($data) {
    $name = trim($data['admin_name'] ?? '');
    $email = trim($data['admin_email'] ?? '');
    $password = $data['admin_password'] ?? '';
    $confirmPassword = $data['admin_password_confirm'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        return 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid email address.';
    }

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        return 'Passwords do not match.';
    }

    $db = $_SESSION['installer_db'] ?? null;
    if (!$db) {
        return 'Database configuration not found. Please go back to step 3.';
    }

    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $prefix = $db['prefix'];
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );

        $stmt = $pdo->prepare("INSERT INTO {$prefix}users (uuid, name, email, password, role, status, email_verified_at, created_at) VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW(), NOW())");
        $stmt->execute([$uuid, $name, $email, $hashedPassword]);

        $_SESSION['installer_admin'] = ['name' => $name, 'email' => $email];
        return true;
    } catch (PDOException $e) {
        return 'Error creating admin: ' . $e->getMessage();
    }
}


function finalizeInstallation() {
    $db = $_SESSION['installer_db'] ?? null;
    if (!$db) {
        header('Location: ?step=3');
        exit;
    }

    // Generate .env file
    $appKey = bin2hex(random_bytes(16));
    $cronKey = bin2hex(random_bytes(16));
    
    // Auto-detect APP_URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $appUrl = rtrim($protocol . '://' . $host . $path, '/');

    $envContent = "# WaMark - Environment Configuration
# Generated during installation on " . date('Y-m-d H:i:s') . "

# Application Settings
APP_NAME=WaMark
APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}
APP_KEY={$appKey}
APP_TIMEZONE=UTC

# Database Configuration
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_NAME={$db['name']}
DB_USER={$db['user']}
DB_PASS={$db['pass']}
DB_PREFIX={$db['prefix']}

# Mail Configuration
MAIL_DRIVER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USER=
MAIL_PASS=
MAIL_ENCRYPTION=tls
MAIL_FROM_NAME=WaMark
MAIL_FROM_EMAIL=

# WhatsApp Configuration
WA_API_MODE=cloud
WA_CLOUD_API_TOKEN=
WA_WEBHOOK_VERIFY_TOKEN=
WA_PHONE_NUMBER_ID=
WA_BUSINESS_ACCOUNT_ID=

# Payment Gateways
STRIPE_KEY=
STRIPE_SECRET=
RAZORPAY_KEY=
RAZORPAY_SECRET=
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYU_MERCHANT_KEY=
PAYU_SALT=

# License
LICENSE_KEY=
LICENSE_TYPE=lifetime

# Cron Security Key
CRON_SECRET_KEY={$cronKey}
";

    $rootPath = dirname(__DIR__);
    
    // Write .env file
    file_put_contents($rootPath . '/.env', $envContent);
    
    // Create installed.lock
    file_put_contents($rootPath . '/config/installed.lock', json_encode([
        'version' => '1.0.0',
        'installed_at' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
    ]));

    // Clear session
    unset($_SESSION['installer_db']);
    unset($_SESSION['installer_admin']);

    // Redirect to admin
    header('Location: ../admin/login.php');
    exit;
}

$requirements = checkRequirements();
$allPassed = !in_array(false, array_column(
    array_filter($requirements, fn($r) => $r['required']),
    'status'
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WaMark - Installation Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1e1b4b;
            --gray: #6b7280;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .installer-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .installer-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .installer-header {
            background: var(--dark);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .installer-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        .installer-header p {
            margin: 8px 0 0;
            opacity: 0.8;
            font-size: 14px;
        }
        .installer-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            background: #e5e7eb;
            color: var(--gray);
            transition: all 0.3s;
        }
        .step-dot.active {
            background: var(--primary);
            color: #fff;
            transform: scale(1.1);
        }
        .step-dot.completed {
            background: var(--success);
            color: #fff;
        }
        .step-line {
            width: 30px;
            height: 2px;
            background: #e5e7eb;
            align-self: center;
        }
        .step-line.completed {
            background: var(--success);
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .check-item:last-child { border-bottom: none; }
        .check-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
        }
        .check-icon.pass { background: #d1fae5; color: var(--success); }
        .check-icon.fail { background: #fee2e2; color: var(--danger); }
        .check-icon.warn { background: #fef3c7; color: var(--warning); }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 16px;
            border: 1.5px solid #e5e7eb;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .alert { border-radius: 8px; }
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d1fae5;
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
<div class="installer-container">
    <div class="installer-card">
        <div class="installer-header">
            <h1><i class="bi bi-whatsapp"></i> WaMark</h1>
            <p>Installation Wizard v1.0.0</p>
        </div>
        
        <div class="step-indicator">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i > 1): ?>
                    <div class="step-line <?= $i <= $step ? 'completed' : '' ?>"></div>
                <?php endif; ?>
                <div class="step-dot <?= $i === $step ? 'active' : ($i < $step ? 'completed' : '') ?>">
                    <?= $i < $step ? '<i class="bi bi-check"></i>' : $i ?>
                </div>
            <?php endfor; ?>
        </div>

        <div class="installer-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php include __DIR__ . "/steps/step{$step}.php"; ?>
        </div>
    </div>
    
    <p class="text-center text-white mt-3 opacity-75">
        <small>&copy; <?= date('Y') ?> WaMark. All rights reserved.</small>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
