<?php
/**
 * WaMark Cron - Process Message Queue
 * Sends queued messages via WhatsApp Cloud API
 */

$batchSize = (int)get_setting('wa_batch_size', 50);
$delay = (int)get_setting('wa_default_delay', 2);
$maxRetries = (int)get_setting('wa_max_retries', 3);
$processed = 0;

// Get queued messages ready to send
$messages = $db->fetchAll(
    "SELECT m.*, wa.access_token, wa.phone_number_id, wa.mode 
     FROM " . $db->table('messages') . " m
     LEFT JOIN " . $db->table('whatsapp_accounts') . " wa ON m.whatsapp_account_id = wa.id
     WHERE m.status = 'queued' 
     AND m.direction = 'outgoing'
     AND (m.scheduled_at IS NULL OR m.scheduled_at <= NOW())
     AND m.retry_count < ?
     ORDER BY m.created_at ASC
     LIMIT ?",
    [$maxRetries, $batchSize]
);

foreach ($messages as $msg) {
    // Mark as sending
    $db->update('messages', ['status' => 'sending'], 'id = ?', [$msg['id']]);

    // Check if WA account is available
    if (empty($msg['access_token']) || empty($msg['phone_number_id'])) {
        $db->update('messages', [
            'status' => 'failed',
            'error_message' => 'No WhatsApp account configured or not connected',
        ], 'id = ?', [$msg['id']]);
        continue;
    }

    if ($msg['mode'] !== 'cloud_api') {
        // Non-API mode messages need different handling
        $db->update('messages', ['status' => 'queued'], 'id = ?', [$msg['id']]);
        continue;
    }

    // Parse template variables if contact exists
    $messageBody = $msg['message_body'];
    if ($msg['contact_id']) {
        $contact = $db->fetch("SELECT * FROM " . $db->table('contacts') . " WHERE id = ?", [$msg['contact_id']]);
        if ($contact) {
            $messageBody = parse_template($messageBody, [
                'name' => $contact['name'] ?? '',
                'phone' => $contact['phone'],
                'email' => $contact['email'] ?? '',
                'company' => $contact['company'] ?? '',
            ]);
        }
    }

    // Send via WhatsApp Cloud API
    $result = sendWhatsAppMessage(
        $msg['access_token'],
        $msg['phone_number_id'],
        $msg['phone'],
        $msg['message_type'],
        $messageBody,
        $msg['media_url'],
        $msg['template_name'],
        $msg['template_params'] ? json_decode($msg['template_params'], true) : null
    );

    if ($result['success']) {
        $db->update('messages', [
            'status' => 'sent',
            'wa_message_id' => $result['message_id'],
            'sent_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$msg['id']]);
        $processed++;
    } else {
        $retryCount = $msg['retry_count'] + 1;
        $newStatus = $retryCount >= $maxRetries ? 'failed' : 'queued';
        $db->update('messages', [
            'status' => $newStatus,
            'retry_count' => $retryCount,
            'error_message' => $result['error'],
        ], 'id = ?', [$msg['id']]);
    }

    // Rate limiting delay
    if ($delay > 0) usleep($delay * 1000000);
}

return $processed;

/**
 * Send message via WhatsApp Cloud API
 */
function sendWhatsAppMessage($token, $phoneNumberId, $to, $type, $body, $mediaUrl = null, $templateName = null, $templateParams = null) {
    $apiUrl = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
    $recipient = ltrim($to, '+');

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $recipient,
        'type' => $type,
    ];

    switch ($type) {
        case 'text':
            $payload['text'] = ['body' => $body, 'preview_url' => true];
            break;
        case 'image':
            $payload['image'] = array_filter(['link' => $mediaUrl, 'caption' => $body]);
            break;
        case 'video':
            $payload['video'] = array_filter(['link' => $mediaUrl, 'caption' => $body]);
            break;
        case 'document':
            $payload['document'] = array_filter(['link' => $mediaUrl, 'caption' => $body, 'filename' => basename($mediaUrl ?? 'document')]);
            break;
        case 'audio':
            $payload['audio'] = ['link' => $mediaUrl];
            break;
        case 'template':
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $templateName,
                'language' => ['code' => 'en'],
            ];
            if ($templateParams) {
                $payload['template']['components'] = $templateParams;
            }
            break;
        default:
            $payload['text'] = ['body' => $body];
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['messages'][0]['id'])) {
        return ['success' => true, 'message_id' => $data['messages'][0]['id']];
    }

    $error = $data['error']['message'] ?? $data['error']['error_data']['details'] ?? "HTTP {$httpCode}";
    return ['success' => false, 'error' => $error];
}
