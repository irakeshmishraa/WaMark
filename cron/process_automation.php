<?php
/**
 * WaMark Cron - Process Automation Steps
 * Executes pending automation actions for enrolled contacts
 */

$processed = 0;

// Get enrollments ready for next action
$enrollments = $db->fetchAll(
    "SELECT ae.*, a.user_id, a.whatsapp_account_id, a.name as automation_name
     FROM " . $db->table('automation_enrollments') . " ae
     JOIN " . $db->table('automations') . " a ON ae.automation_id = a.id
     WHERE ae.status = 'active' 
     AND ae.next_action_at <= NOW()
     AND a.status = 'active'
     ORDER BY ae.next_action_at ASC
     LIMIT 100"
);

foreach ($enrollments as $enrollment) {
    $automationId = $enrollment['automation_id'];
    $contactId = $enrollment['contact_id'];
    $currentStep = $enrollment['current_step'];

    // Get the current step definition
    $step = $db->fetch(
        "SELECT * FROM " . $db->table('automation_steps') . " 
         WHERE automation_id = ? AND step_order = ?",
        [$automationId, $currentStep]
    );

    if (!$step) {
        // No more steps - mark as completed
        $db->update('automation_enrollments', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$enrollment['id']]);
        
        $db->query("UPDATE " . $db->table('automations') . " SET total_completed = total_completed + 1 WHERE id = ?", [$automationId]);
        continue;
    }

    // Get contact info
    $contact = $db->fetch("SELECT * FROM " . $db->table('contacts') . " WHERE id = ?", [$contactId]);
    if (!$contact || $contact['status'] !== 'active') {
        $db->update('automation_enrollments', ['status' => 'cancelled'], 'id = ?', [$enrollment['id']]);
        continue;
    }

    // Execute step action
    switch ($step['action_type']) {
        case 'send_message':
            $messageBody = parse_template($step['message_body'] ?? '', [
                'name' => $contact['name'] ?? '',
                'phone' => $contact['phone'],
                'email' => $contact['email'] ?? '',
                'company' => $contact['company'] ?? '',
            ]);

            $db->insert('messages', [
                'user_id' => $enrollment['user_id'],
                'whatsapp_account_id' => $enrollment['whatsapp_account_id'],
                'contact_id' => $contactId,
                'phone' => $contact['phone'],
                'direction' => 'outgoing',
                'message_type' => $step['message_type'] ?? 'text',
                'message_body' => $messageBody,
                'media_url' => $step['media_url'],
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $processed++;
            break;

        case 'wait':
            // Just advance to next step with delay
            break;

        case 'condition':
            // Check condition (basic implementation)
            $field = $step['condition_field'] ?? '';
            $operator = $step['condition_operator'] ?? '=';
            $value = $step['condition_value'] ?? '';
            
            $contactValue = $contact[$field] ?? '';
            $conditionMet = match($operator) {
                '=' => $contactValue == $value,
                '!=' => $contactValue != $value,
                'contains' => stripos($contactValue, $value) !== false,
                'empty' => empty($contactValue),
                'not_empty' => !empty($contactValue),
                default => true,
            };
            
            if (!$conditionMet) {
                // Skip to next step or end
                // For now, just advance
            }
            break;

        case 'tag':
            // Add tag to contact
            $currentTags = $contact['tags'] ?? '';
            $newTag = $step['condition_value'] ?? '';
            if ($newTag && stripos($currentTags, $newTag) === false) {
                $updatedTags = $currentTags ? $currentTags . ',' . $newTag : $newTag;
                $db->update('contacts', ['tags' => $updatedTags], 'id = ?', [$contactId]);
            }
            break;

        case 'webhook':
            // Fire webhook (async would be better, but for shared hosting)
            $webhookUrl = $step['media_url'] ?? '';
            if ($webhookUrl) {
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['contact' => $contact, 'automation' => $enrollment['automation_name']]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
            break;
    }

    // Advance to next step
    $nextStep = $currentStep + 1;
    $nextStepDef = $db->fetch(
        "SELECT * FROM " . $db->table('automation_steps') . " WHERE automation_id = ? AND step_order = ?",
        [$automationId, $nextStep]
    );

    if ($nextStepDef) {
        // Calculate next action time
        $delayValue = $nextStepDef['delay_value'] ?? 0;
        $delayUnit = $nextStepDef['delay_unit'] ?? 'hours';
        $nextActionAt = date('Y-m-d H:i:s', strtotime("+{$delayValue} {$delayUnit}"));

        $db->update('automation_enrollments', [
            'current_step' => $nextStep,
            'next_action_at' => $nextActionAt,
        ], 'id = ?', [$enrollment['id']]);
    } else {
        // Automation complete
        $db->update('automation_enrollments', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$enrollment['id']]);
        
        $db->query("UPDATE " . $db->table('automations') . " SET total_completed = total_completed + 1 WHERE id = ?", [$automationId]);
    }
}

return $processed;
