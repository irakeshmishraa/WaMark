<?php
/**
 * WaMark API - WhatsApp Endpoint
 * GET /api/whatsapp - List connected accounts
 * POST /api/whatsapp/send - Direct send via Cloud API (immediate, not queued)
 */

if (!$apiUser) json_response(['error' => 'Authentication required'], 401);
$userId = $apiUser['user_id'];

switch ($method) {
    case 'GET':
        $accounts = $db->fetchAll(
            "SELECT id, name, phone_number, mode, status, last_connected_at, created_at 
             FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
        json_response(['data' => $accounts]);
        break;

    case 'POST':
        // Direct send action
        if ($resourceId === 'send') {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $phone = clean_phone($input['phone'] ?? '');
            $type = $input['type'] ?? 'text';
            $body = trim($input['body'] ?? $input['message'] ?? '');
            $accountId = (int)($input['account_id'] ?? 0);

            if (empty($phone)) json_response(['error' => 'Phone required'], 400);
            if ($type === 'text' && empty($body)) json_response(['error' => 'Message body required'], 400);

            // Get WA account
            $where = 'user_id = ? AND status = ?';
            $params = [$userId, 'connected'];
            if ($accountId) { $where .= ' AND id = ?'; $params[] = $accountId; }
            
            $account = $db->fetch("SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE {$where} LIMIT 1", $params);
            if (!$account) json_response(['error' => 'No connected WhatsApp account'], 400);

            // Send via Cloud API
            if ($account['mode'] === 'cloud_api') {
                $result = sendCloudApiMessage($account, $phone, $type, $body, $input);
                
                if ($result['success']) {
                    // Log the message
                    $db->insert('messages', [
                        'user_id' => $userId,
                        'whatsapp_account_id' => $account['id'],
                        'phone' => $phone,
                        'direction' => 'outgoing',
                        'message_type' => $type,
                        'message_body' => $body,
                        'wa_message_id' => $result['message_id'] ?? null,
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    json_response(['success' => true, 'message_id' => $result['message_id'] ?? null]);
                } else {
                    json_response(['error' => $result['error'] ?? 'Send failed'], 500);
                }
            } else {
                // Non-API: queue for background processing
                $msgId = $db->insert('messages', [
                    'user_id' => $userId,
                    'whatsapp_account_id' => $account['id'],
                    'phone' => $phone,
                    'direction' => 'outgoing',
                    'message_type' => $type,
                    'message_body' => $body,
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                json_response(['success' => true, 'message_id' => $msgId, 'status' => 'queued']);
            }
        }
        json_response(['error' => 'Invalid action. Use /api/whatsapp/send'], 400);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}

/**
 * Send message via WhatsApp Cloud API
 */
function sendCloudApiMessage($account, $phone, $type, $body, $params = []) {
    $token = $account['access_token'];
    $phoneNumberId = $account['phone_number_id'];
    $apiUrl = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";

    // Remove leading + from phone
    $recipientPhone = ltrim($phone, '+');

    // Build payload based on message type
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $recipientPhone,
        'type' => $type,
    ];

    switch ($type) {
        case 'text':
            $payload['text'] = ['body' => $body, 'preview_url' => true];
            break;
        case 'image':
            $payload['image'] = ['link' => $params['media_url'] ?? '', 'caption' => $body];
            break;
        case 'video':
            $payload['video'] = ['link' => $params['media_url'] ?? '', 'caption' => $body];
            break;
        case 'document':
            $payload['document'] = ['link' => $params['media_url'] ?? '', 'caption' => $body, 'filename' => $params['filename'] ?? 'document'];
            break;
        case 'template':
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $params['template_name'] ?? '',
                'language' => ['code' => $params['language'] ?? 'en'],
            ];
            if (!empty($params['template_params'])) {
                $payload['template']['components'] = $params['template_params'];
            }
            break;
    }

    // Send HTTP request
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['messages'][0]['id'])) {
        return ['success' => true, 'message_id' => $responseData['messages'][0]['id']];
    }

    $error = $responseData['error']['message'] ?? ($responseData['error']['error_data']['details'] ?? 'Unknown error');
    return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
}
