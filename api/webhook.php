<?php
/**
 * WaMark - WhatsApp Webhook Handler
 * Receives and processes webhooks from WhatsApp Cloud API
 */

// Webhook verification (GET request from Meta)
if ($method === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    $verifyToken = get_setting('wa_webhook_verify_token', env('WA_WEBHOOK_VERIFY_TOKEN', ''));
    
    if ($mode === 'subscribe' && $token === $verifyToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    
    json_response(['error' => 'Verification failed'], 403);
}

// Process incoming webhook (POST)
if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!$payload) {
        json_response(['error' => 'Invalid payload'], 400);
    }

    // Log webhook
    $webhookId = $db->insert('webhooks', [
        'source' => 'whatsapp',
        'event_type' => $payload['object'] ?? 'unknown',
        'payload' => json_encode($payload),
        'headers' => json_encode(getallheaders()),
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    try {
        processWhatsAppWebhook($payload);
        $db->update('webhooks', ['status' => 'processed', 'processed_at' => date('Y-m-d H:i:s')], 'id = ?', [$webhookId]);
    } catch (Exception $e) {
        $db->update('webhooks', ['status' => 'failed', 'error_message' => $e->getMessage()], 'id = ?', [$webhookId]);
    }

    json_response(['status' => 'received']);
}

/**
 * Process WhatsApp Cloud API webhook payload
 */
function processWhatsAppWebhook($payload) {
    global $db;

    if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
        return;
    }

    $entries = $payload['entry'] ?? [];
    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $field = $change['field'] ?? '';

            if ($field === 'messages') {
                // Handle incoming messages
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? '';

                foreach ($messages as $msg) {
                    processIncomingMessage($msg, $contacts, $phoneNumberId);
                }

                // Handle status updates
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    processStatusUpdate($status);
                }
            }
        }
    }
}

/**
 * Process incoming WhatsApp message
 */
function processIncomingMessage($msg, $contacts, $phoneNumberId) {
    global $db;

    $from = $msg['from'] ?? '';
    $msgId = $msg['id'] ?? '';
    $timestamp = $msg['timestamp'] ?? time();
    $type = $msg['type'] ?? 'text';
    $body = '';

    // Extract message body based on type
    switch ($type) {
        case 'text':
            $body = $msg['text']['body'] ?? '';
            break;
        case 'image':
            $body = $msg['image']['caption'] ?? '[Image]';
            break;
        case 'video':
            $body = $msg['video']['caption'] ?? '[Video]';
            break;
        case 'document':
            $body = $msg['document']['filename'] ?? '[Document]';
            break;
        case 'audio':
            $body = '[Audio Message]';
            break;
        case 'location':
            $body = '[Location]';
            break;
        case 'interactive':
            $body = $msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? '[Interactive]';
            break;
    }

    // Find WhatsApp account
    $waAccount = $db->fetch(
        "SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE phone_number_id = ?",
        [$phoneNumberId]
    );

    if (!$waAccount) return;

    $userId = $waAccount['user_id'];
    $phone = '+' . $from;

    // Find or create contact
    $contact = $db->fetch(
        "SELECT * FROM " . $db->table('contacts') . " WHERE user_id = ? AND phone = ?",
        [$userId, $phone]
    );

    if (!$contact) {
        $contactName = '';
        if (!empty($contacts)) {
            $contactName = $contacts[0]['profile']['name'] ?? '';
        }
        $contactId = $db->insert('contacts', [
            'user_id' => $userId,
            'phone' => $phone,
            'name' => $contactName ?: null,
            'status' => 'active',
            'source' => 'whatsapp',
            'last_message_at' => date('Y-m-d H:i:s', $timestamp),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } else {
        $contactId = $contact['id'];
        $db->update('contacts', ['last_message_at' => date('Y-m-d H:i:s', $timestamp)], 'id = ?', [$contactId]);
    }

    // Store incoming message
    $db->insert('messages', [
        'user_id' => $userId,
        'whatsapp_account_id' => $waAccount['id'],
        'contact_id' => $contactId,
        'phone' => $phone,
        'direction' => 'incoming',
        'message_type' => $type,
        'message_body' => $body,
        'wa_message_id' => $msgId,
        'status' => 'delivered',
        'delivered_at' => date('Y-m-d H:i:s', $timestamp),
        'created_at' => date('Y-m-d H:i:s', $timestamp),
    ]);

    // Check auto-reply rules
    processAutoReply($userId, $waAccount['id'], $phone, $body, $type);

    // Check automation triggers
    processAutomationTrigger($userId, $contactId, $body);
}

/**
 * Process message status updates (sent, delivered, read)
 */
function processStatusUpdate($status) {
    global $db;

    $msgId = $status['id'] ?? '';
    $statusType = $status['status'] ?? '';
    $timestamp = $status['timestamp'] ?? time();

    if (empty($msgId)) return;

    $updateData = ['status' => $statusType];
    switch ($statusType) {
        case 'sent':
            $updateData['sent_at'] = date('Y-m-d H:i:s', $timestamp);
            break;
        case 'delivered':
            $updateData['delivered_at'] = date('Y-m-d H:i:s', $timestamp);
            break;
        case 'read':
            $updateData['read_at'] = date('Y-m-d H:i:s', $timestamp);
            break;
        case 'failed':
            $errors = $status['errors'] ?? [];
            $updateData['error_message'] = !empty($errors) ? json_encode($errors) : 'Delivery failed';
            break;
    }

    $db->query(
        "UPDATE " . $db->table('messages') . " SET " . 
        implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($updateData))) . 
        " WHERE wa_message_id = ?",
        [...array_values($updateData), $msgId]
    );

    // Update campaign stats if applicable
    $message = $db->fetch("SELECT campaign_id FROM " . $db->table('messages') . " WHERE wa_message_id = ?", [$msgId]);
    if ($message && $message['campaign_id']) {
        $field = $statusType . '_count';
        if (in_array($statusType, ['delivered', 'read', 'failed'])) {
            $db->query("UPDATE " . $db->table('campaigns') . " SET {$field} = {$field} + 1 WHERE id = ?", [$message['campaign_id']]);
        }
    }
}

/**
 * Process auto-reply for incoming message
 */
function processAutoReply($userId, $waAccountId, $phone, $body, $type) {
    global $db;

    if ($type !== 'text' || empty($body)) return;

    $rules = $db->fetchAll(
        "SELECT * FROM " . $db->table('auto_replies') . " WHERE user_id = ? AND is_active = 1 AND (whatsapp_account_id = ? OR whatsapp_account_id IS NULL) ORDER BY priority DESC",
        [$userId, $waAccountId]
    );

    foreach ($rules as $rule) {
        $matched = false;
        $keyword = $rule['trigger_keyword'];

        switch ($rule['trigger_type']) {
            case 'exact': $matched = (strtolower($body) === strtolower($keyword)); break;
            case 'contains': $matched = (stripos($body, $keyword) !== false); break;
            case 'starts_with': $matched = (stripos($body, $keyword) === 0); break;
            case 'regex': $matched = @preg_match('/' . $keyword . '/i', $body); break;
            case 'default': $matched = true; break;
        }

        if ($matched) {
            // Queue auto-reply message
            $db->insert('messages', [
                'user_id' => $userId,
                'whatsapp_account_id' => $waAccountId,
                'phone' => $phone,
                'direction' => 'outgoing',
                'message_type' => $rule['response_type'],
                'message_body' => $rule['response_body'],
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update reply count
            $db->query("UPDATE " . $db->table('auto_replies') . " SET reply_count = reply_count + 1 WHERE id = ?", [$rule['id']]);
            break; // Only first matching rule
        }
    }
}

/**
 * Process automation triggers
 */
function processAutomationTrigger($userId, $contactId, $body) {
    global $db;

    $automations = $db->fetchAll(
        "SELECT * FROM " . $db->table('automations') . " WHERE user_id = ? AND status = 'active' AND trigger_type = 'keyword'",
        [$userId]
    );

    foreach ($automations as $auto) {
        if (stripos($body, $auto['trigger_value']) !== false) {
            // Check if contact already enrolled
            $enrolled = $db->exists('automation_enrollments', 'automation_id = ? AND contact_id = ? AND status = ?', [$auto['id'], $contactId, 'active']);
            if (!$enrolled) {
                $db->insert('automation_enrollments', [
                    'automation_id' => $auto['id'],
                    'contact_id' => $contactId,
                    'current_step' => 1,
                    'status' => 'active',
                    'next_action_at' => date('Y-m-d H:i:s'),
                    'enrolled_at' => date('Y-m-d H:i:s'),
                ]);
                $db->query("UPDATE " . $db->table('automations') . " SET total_enrolled = total_enrolled + 1 WHERE id = ?", [$auto['id']]);
            }
        }
    }
}
