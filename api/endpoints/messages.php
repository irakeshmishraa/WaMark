<?php
/**
 * WaMark API - Messages Endpoint
 * GET /api/messages - List messages
 * POST /api/messages - Send a message (queue)
 * GET /api/messages/{id} - Get message status
 */

if (!$apiUser) json_response(['error' => 'Authentication required'], 401);
$userId = $apiUser['user_id'];

switch ($method) {
    case 'GET':
        if ($resourceId) {
            $message = $db->fetch(
                "SELECT id, phone, direction, message_type, message_body, status, wa_message_id, sent_at, delivered_at, read_at, created_at 
                 FROM " . $db->table('messages') . " WHERE id = ? AND user_id = ?",
                [(int)$resourceId, $userId]
            );
            if (!$message) json_response(['error' => 'Message not found'], 404);
            json_response(['data' => $message]);
        } else {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
            $status = $_GET['status'] ?? '';
            $direction = $_GET['direction'] ?? '';
            $phone = $_GET['phone'] ?? '';

            $where = 'user_id = ?';
            $params = [$userId];
            if ($status) { $where .= " AND status = ?"; $params[] = $status; }
            if ($direction) { $where .= " AND direction = ?"; $params[] = $direction; }
            if ($phone) { $where .= " AND phone = ?"; $params[] = clean_phone($phone); }

            $total = $db->fetchColumn("SELECT COUNT(*) FROM " . $db->table('messages') . " WHERE {$where}", $params);
            $offset = ($page - 1) * $perPage;

            $messages = $db->fetchAll(
                "SELECT id, phone, direction, message_type, message_body, status, wa_message_id, sent_at, delivered_at, read_at, created_at 
                 FROM " . $db->table('messages') . " WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params
            );

            json_response([
                'data' => $messages,
                'meta' => ['total' => (int)$total, 'page' => $page, 'per_page' => $perPage]
            ]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $phone = clean_phone($input['phone'] ?? '');
        $messageType = $input['type'] ?? 'text';
        $body = trim($input['body'] ?? $input['message'] ?? '');
        $mediaUrl = trim($input['media_url'] ?? '');
        $templateName = trim($input['template_name'] ?? '');
        $templateParams = $input['template_params'] ?? null;
        $waAccountId = (int)($input['whatsapp_account_id'] ?? 0);
        $scheduledAt = $input['scheduled_at'] ?? null;

        // Validation
        if (empty($phone)) json_response(['error' => 'Phone number is required'], 400);
        if ($messageType === 'text' && empty($body)) json_response(['error' => 'Message body is required'], 400);
        if ($messageType === 'template' && empty($templateName)) json_response(['error' => 'Template name is required'], 400);

        // Find contact
        $contact = $db->fetch("SELECT id FROM " . $db->table('contacts') . " WHERE user_id = ? AND phone = ?", [$userId, $phone]);
        
        // Auto-detect WA account if not provided
        if (!$waAccountId) {
            $waAccount = $db->fetch(
                "SELECT id FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? AND status = 'connected' LIMIT 1",
                [$userId]
            );
            $waAccountId = $waAccount ? $waAccount['id'] : null;
        }

        $messageId = $db->insert('messages', [
            'user_id' => $userId,
            'whatsapp_account_id' => $waAccountId,
            'contact_id' => $contact ? $contact['id'] : null,
            'phone' => $phone,
            'direction' => 'outgoing',
            'message_type' => $messageType,
            'message_body' => $body,
            'media_url' => $mediaUrl ?: null,
            'template_name' => $templateName ?: null,
            'template_params' => $templateParams ? json_encode($templateParams) : null,
            'status' => 'queued',
            'scheduled_at' => $scheduledAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        json_response([
            'data' => ['id' => $messageId, 'status' => 'queued', 'phone' => $phone],
            'message' => 'Message queued for delivery'
        ], 201);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
