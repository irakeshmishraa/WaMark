<?php
/**
 * WaMark Cron - Process Active Campaigns
 * Queues messages for running campaigns
 */

$processed = 0;

// Get running campaigns that need processing
$campaigns = $db->fetchAll(
    "SELECT * FROM " . $db->table('campaigns') . " 
     WHERE status = 'running' 
     AND sent_count < total_recipients
     ORDER BY started_at ASC LIMIT 5"
);

// Also process scheduled campaigns
$scheduled = $db->fetchAll(
    "SELECT * FROM " . $db->table('campaigns') . " 
     WHERE status = 'scheduled' AND scheduled_at <= NOW()
     ORDER BY scheduled_at ASC LIMIT 5"
);

// Activate scheduled campaigns
foreach ($scheduled as $camp) {
    $db->update('campaigns', [
        'status' => 'running',
        'started_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$camp['id']]);
    $campaigns[] = $camp;
}

$batchSize = (int)get_setting('wa_batch_size', 50);

foreach ($campaigns as $campaign) {
    $userId = $campaign['user_id'];
    $campaignId = $campaign['id'];

    // Get contacts to message (not yet queued for this campaign)
    $contactQuery = "";
    $contactParams = [];

    if ($campaign['target_type'] === 'all') {
        $contactQuery = "SELECT c.id, c.phone, c.name, c.email, c.company 
                         FROM " . $db->table('contacts') . " c 
                         WHERE c.user_id = ? AND c.status = 'active'
                         AND c.id NOT IN (SELECT contact_id FROM " . $db->table('messages') . " WHERE campaign_id = ? AND contact_id IS NOT NULL)
                         LIMIT ?";
        $contactParams = [$userId, $campaignId, $batchSize];
    } elseif ($campaign['target_type'] === 'group') {
        $groupIds = json_decode($campaign['target_groups'] ?? '[]', true);
        if (empty($groupIds)) continue;
        
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $contactQuery = "SELECT c.id, c.phone, c.name, c.email, c.company 
                         FROM " . $db->table('contacts') . " c 
                         JOIN " . $db->table('contact_group_members') . " gm ON c.id = gm.contact_id
                         WHERE c.user_id = ? AND c.status = 'active' AND gm.group_id IN ({$placeholders})
                         AND c.id NOT IN (SELECT contact_id FROM " . $db->table('messages') . " WHERE campaign_id = ? AND contact_id IS NOT NULL)
                         GROUP BY c.id LIMIT ?";
        $contactParams = array_merge([$userId], $groupIds, [$campaignId, $batchSize]);
    }

    if (empty($contactQuery)) continue;

    $contacts = $db->fetchAll($contactQuery, $contactParams);

    if (empty($contacts)) {
        // Campaign complete
        $db->update('campaigns', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$campaignId]);
        continue;
    }

    // Queue messages for batch
    foreach ($contacts as $contact) {
        // Parse template variables
        $messageBody = parse_template($campaign['message_body'], [
            'name' => $contact['name'] ?? '',
            'phone' => $contact['phone'],
            'email' => $contact['email'] ?? '',
            'company' => $contact['company'] ?? '',
        ]);

        $db->insert('messages', [
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'whatsapp_account_id' => $campaign['whatsapp_account_id'],
            'contact_id' => $contact['id'],
            'phone' => $contact['phone'],
            'direction' => 'outgoing',
            'message_type' => $campaign['message_type'],
            'message_body' => $messageBody,
            'media_url' => $campaign['media_url'],
            'status' => 'queued',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $processed++;
    }

    // Update campaign sent count
    $totalQueued = $db->fetchColumn(
        "SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE campaign_id = ?", [$campaignId]
    );
    $db->update('campaigns', ['sent_count' => (int)$totalQueued], 'id = ?', [$campaignId]);
}

return $processed;
