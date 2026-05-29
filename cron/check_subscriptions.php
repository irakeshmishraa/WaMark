<?php
/**
 * WaMark Cron - Check Subscriptions
 * Expires subscriptions, sends reminder emails
 */

$processed = 0;

// 1. Expire overdue subscriptions
$expired = $db->fetchAll(
    "SELECT u.*, p.name as plan_name FROM " . $db->table('users') . " u
     LEFT JOIN " . $db->table('plans') . " p ON u.plan_id = p.id
     WHERE u.subscription_expires_at IS NOT NULL 
     AND u.subscription_expires_at < NOW()
     AND u.status = 'active'
     AND u.role = 'client'"
);

foreach ($expired as $user) {
    // Update subscription status
    $db->update('subscriptions', ['status' => 'expired'], 'user_id = ? AND status = ?', [$user['id'], 'active']);
    
    // Downgrade to free plan or disable
    $freePlan = $db->fetch("SELECT id FROM " . $db->table('plans') . " WHERE type = 'free' AND is_active = 1 LIMIT 1");
    if ($freePlan) {
        $db->update('users', ['plan_id' => $freePlan['id']], 'id = ?', [$user['id']]);
    }

    // Send expiry notification
    $db->insert('notifications', [
        'user_id' => $user['id'],
        'type' => 'subscription_expired',
        'title' => 'Subscription Expired',
        'message' => 'Your ' . ($user['plan_name'] ?? 'subscription') . ' plan has expired. Renew to continue using all features.',
        'action_url' => '/user/subscription.php',
        'icon' => 'credit-card',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $processed++;
}

// 2. Send reminder emails for subscriptions expiring in 3 days
$expiringSoon = $db->fetchAll(
    "SELECT u.*, p.name as plan_name FROM " . $db->table('users') . " u
     LEFT JOIN " . $db->table('plans') . " p ON u.plan_id = p.id
     WHERE u.subscription_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
     AND u.status = 'active' AND u.role = 'client'"
);

foreach ($expiringSoon as $user) {
    // Check if we already notified
    $alreadyNotified = $db->exists('notifications',
        "user_id = ? AND type = 'subscription_expiring' AND DATE(created_at) = CURDATE()",
        [$user['id']]
    );

    if (!$alreadyNotified) {
        $db->insert('notifications', [
            'user_id' => $user['id'],
            'type' => 'subscription_expiring',
            'title' => 'Subscription Expiring Soon',
            'message' => 'Your plan expires on ' . date('M d, Y', strtotime($user['subscription_expires_at'])) . '. Renew now to avoid interruption.',
            'action_url' => '/user/subscription.php',
            'icon' => 'exclamation-triangle',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send email
        $mailer = new Mailer();
        $mailer->sendTemplate($user['email'], 'subscription_expiring', [
            'name' => $user['name'],
            'plan_name' => $user['plan_name'] ?? 'Current',
            'expiry_date' => date('M d, Y', strtotime($user['subscription_expires_at'])),
            'renew_url' => BASE_URL . '/user/subscription.php',
        ]);

        $processed++;
    }
}

// 3. Check license expiry
$expiringLicenses = $db->fetchAll(
    "SELECT * FROM " . $db->table('licenses') . " 
     WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()"
);

foreach ($expiringLicenses as $lic) {
    $db->update('licenses', ['status' => 'expired'], 'id = ?', [$lic['id']]);
    $processed++;
}

return $processed;
