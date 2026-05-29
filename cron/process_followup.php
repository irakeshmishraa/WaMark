<?php
/**
 * WaMark Cron - Process Follow-Up Messages
 * Handles scheduled messages and follow-up sequences
 */

$processed = 0;

// Process scheduled messages that are due
$scheduled = $db->fetchAll(
    "SELECT * FROM " . $db->table('messages') . " 
     WHERE status = 'queued' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()
     ORDER BY scheduled_at ASC LIMIT 100"
);

foreach ($scheduled as $msg) {
    // Just ensure they're in queue (process_queue.php handles sending)
    // But update scheduled_at to null so they get picked up
    $db->update('messages', ['scheduled_at' => null], 'id = ?', [$msg['id']]);
    $processed++;
}

// Process birthday automations
$birthdayAutomations = $db->fetchAll(
    "SELECT a.* FROM " . $db->table('automations') . " a 
     WHERE a.type = 'birthday' AND a.status = 'active'"
);

foreach ($birthdayAutomations as $auto) {
    // Find contacts with birthdays today (stored in custom_fields)
    $contacts = $db->fetchAll(
        "SELECT * FROM " . $db->table('contacts') . " c 
         WHERE c.user_id = ? AND c.status = 'active' 
         AND JSON_EXTRACT(c.custom_fields, '$.birthday') IS NOT NULL
         AND DATE_FORMAT(JSON_EXTRACT(c.custom_fields, '$.birthday'), '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')",
        [$auto['user_id']]
    );

    foreach ($contacts as $contact) {
        // Check if not already enrolled today
        $alreadyEnrolled = $db->exists('automation_enrollments',
            'automation_id = ? AND contact_id = ? AND DATE(enrolled_at) = CURDATE()',
            [$auto['id'], $contact['id']]
        );

        if (!$alreadyEnrolled) {
            $db->insert('automation_enrollments', [
                'automation_id' => $auto['id'],
                'contact_id' => $contact['id'],
                'current_step' => 1,
                'status' => 'active',
                'next_action_at' => date('Y-m-d H:i:s'),
                'enrolled_at' => date('Y-m-d H:i:s'),
            ]);
            $processed++;
        }
    }
}

// Process anniversary automations (similar logic)
$anniversaryAutomations = $db->fetchAll(
    "SELECT a.* FROM " . $db->table('automations') . " a 
     WHERE a.type = 'anniversary' AND a.status = 'active'"
);

foreach ($anniversaryAutomations as $auto) {
    $contacts = $db->fetchAll(
        "SELECT * FROM " . $db->table('contacts') . " c 
         WHERE c.user_id = ? AND c.status = 'active'
         AND JSON_EXTRACT(c.custom_fields, '$.anniversary') IS NOT NULL
         AND DATE_FORMAT(JSON_EXTRACT(c.custom_fields, '$.anniversary'), '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')",
        [$auto['user_id']]
    );

    foreach ($contacts as $contact) {
        $alreadyEnrolled = $db->exists('automation_enrollments',
            'automation_id = ? AND contact_id = ? AND DATE(enrolled_at) = CURDATE()',
            [$auto['id'], $contact['id']]
        );

        if (!$alreadyEnrolled) {
            $db->insert('automation_enrollments', [
                'automation_id' => $auto['id'],
                'contact_id' => $contact['id'],
                'current_step' => 1,
                'status' => 'active',
                'next_action_at' => date('Y-m-d H:i:s'),
                'enrolled_at' => date('Y-m-d H:i:s'),
            ]);
            $processed++;
        }
    }
}

return $processed;
