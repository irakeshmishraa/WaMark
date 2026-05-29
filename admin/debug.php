<?php
/**
 * WaMark - Debug Helper (DELETE THIS FILE AFTER USE)
 * Helps diagnose installation issues
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>WaMark Debug Info</h2>";
echo "<pre>";

// Check .env file
$envFile = dirname(__DIR__) . '/.env';
echo "=== .env File ===\n";
if (file_exists($envFile)) {
    echo "EXISTS: Yes\n";
    echo "Size: " . filesize($envFile) . " bytes\n";
    // Show contents (hide password partially)
    $content = file_get_contents($envFile);
    $content = preg_replace('/DB_PASS=(.{3})(.*)/', 'DB_PASS=$1***', $content);
    echo "Content:\n" . htmlspecialchars($content) . "\n";
} else {
    echo "EXISTS: NO - This is the problem! .env was never created.\n";
}

echo "\n=== installed.lock ===\n";
$lockFile = dirname(__DIR__) . '/config/installed.lock';
if (file_exists($lockFile)) {
    echo "EXISTS: Yes\n";
    echo "Content: " . file_get_contents($lockFile) . "\n";
} else {
    echo "EXISTS: NO\n";
}

echo "\n=== DB Temp File ===\n";
$tempFile = dirname(__DIR__) . '/config/.db_temp.php';
if (file_exists($tempFile)) {
    echo "EXISTS: Yes\n";
    echo "(Contains DB credentials from installation)\n";
} else {
    echo "EXISTS: NO\n";
}

echo "\n=== PHP Session ===\n";
session_start();
echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";

echo "\n=== Test DB Connection ===\n";
// Try to connect using .env values
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        $pos = strpos($line, '=');
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    
    $host = $env['DB_HOST'] ?? 'localhost';
    $port = $env['DB_PORT'] ?? '3306';
    $name = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    
    echo "Host: $host\n";
    echo "DB: $name\n";
    echo "User: $user\n";
    echo "Pass: " . substr($pass, 0, 3) . "***\n";
    
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass);
        echo "Connection: SUCCESS!\n";
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables: " . count($tables) . "\n";
        echo implode(', ', $tables) . "\n";
    } catch (PDOException $e) {
        echo "Connection FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "Cannot test - no .env file\n";
}

echo "\n=== Directory Permissions ===\n";
$dirs = ['config', 'storage', 'storage/logs', 'uploads'];
foreach ($dirs as $dir) {
    $path = dirname(__DIR__) . '/' . $dir;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    echo "$dir: " . ($exists ? 'exists' : 'MISSING') . " | " . ($writable ? 'writable' : 'NOT WRITABLE') . "\n";
}

echo "</pre>";
echo "<p><strong>DELETE THIS FILE (admin/debug.php) after troubleshooting!</strong></p>";
