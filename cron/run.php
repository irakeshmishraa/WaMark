<?php
/**
 * WaMark - Cron Runner
 * Master cron script - runs all scheduled jobs
 * 
 * Add to crontab:
 * * * * * * php /path/to/WaMark/cron/run.php >> /dev/null 2>&1
 * 
 * Or call via URL with secret key:
 * https://domain.com/WaMark/cron/run.php?key=YOUR_CRON_SECRET
 */

// Security check
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // Web access requires secret key
    $providedKey = $_GET['key'] ?? '';
    require_once dirname(__DIR__) . '/config/constants.php';
    require_once dirname(__DIR__) . '/config/app.php';
    
    $cronKey = env('CRON_SECRET_KEY', '');
    if (empty($cronKey) || $providedKey !== $cronKey) {
        http_response_code(403);
        die('Unauthorized');
    }
} else {
    require_once dirname(__DIR__) . '/config/constants.php';
    require_once dirname(__DIR__) . '/config/app.php';
}

if (!IS_INSTALLED) {
    die('Not installed');
}

// Define jobs and their intervals (in seconds)
$jobs = [
    'message_queue' => ['file' => 'process_queue.php', 'interval' => 30],
    'campaign_processor' => ['file' => 'process_campaigns.php', 'interval' => 60],
    'automation_engine' => ['file' => 'process_automation.php', 'interval' => 60],
    'follow_up' => ['file' => 'process_followup.php', 'interval' => 300],
    'cleanup' => ['file' => 'cleanup.php', 'interval' => 86400],
    'backup' => ['file' => 'backup.php', 'interval' => 86400],
    'subscription_check' => ['file' => 'check_subscriptions.php', 'interval' => 3600],
];

$startTime = microtime(true);
$results = [];

foreach ($jobs as $name => $job) {
    // Check if job should run based on interval
    if (shouldRunJob($name, $job['interval'])) {
        $jobStart = microtime(true);
        $success = true;
        $error = null;
        $processed = 0;

        try {
            $jobFile = __DIR__ . '/' . $job['file'];
            if (file_exists($jobFile)) {
                ob_start();
                $processed = include $jobFile;
                ob_end_clean();
                if (!is_int($processed)) $processed = 0;
            }
        } catch (Exception $e) {
            $success = false;
            $error = $e->getMessage();
        }

        $duration = round(microtime(true) - $jobStart, 3);

        // Log cron execution
        $db->insert('cron_logs', [
            'job_name' => $name,
            'status' => $success ? 'success' : 'failed',
            'duration' => $duration,
            'records_processed' => $processed,
            'error_message' => $error,
            'started_at' => date('Y-m-d H:i:s', (int)$jobStart),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $results[$name] = ['status' => $success ? 'ok' : 'failed', 'duration' => $duration, 'processed' => $processed];
    }
}

// Output results (for CLI or web)
if ($isCli) {
    foreach ($results as $name => $r) {
        echo "[" . date('H:i:s') . "] {$name}: {$r['status']} ({$r['processed']} processed, {$r['duration']}s)\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'jobs' => $results, 'total_time' => round(microtime(true) - $startTime, 3)]);
}

/**
 * Check if a job should run based on its interval
 */
function shouldRunJob($name, $interval) {
    global $db;
    
    $lastRun = $db->fetchColumn(
        "SELECT MAX(started_at) FROM " . $db->table('cron_logs') . " WHERE job_name = ? AND status = 'success'",
        [$name]
    );

    if (!$lastRun) return true;
    return (time() - strtotime($lastRun)) >= $interval;
}
