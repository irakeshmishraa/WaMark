<?php
/**
 * WaMark API - Campaigns Endpoint
 * GET /api/campaigns - List campaigns
 * GET /api/campaigns/{id} - Get campaign details with stats
 * POST /api/campaigns - Create campaign
 * POST /api/campaigns/{id}/start - Start campaign
 * POST /api/campaigns/{id}/pause - Pause campaign
 */

if (!$apiUser) json_response(['error' => 'Authentication required'], 401);
$userId = $apiUser['user_id'];

switch ($method) {
    case 'GET':
        if ($resourceId) {
            $campaign = $db->fetch(
                "SELECT * FROM " . $db->table('campaigns') . " WHERE id = ? AND user_id = ?",
                [(int)$resourceId, $userId]
            );
            if (!$campaign) json_response(['error' => 'Campaign not found'], 404);
            
            // Remove internal fields
            unset($campaign['settings']);
            json_response(['data' => $campaign]);
        } else {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 25)));
            $status = $_GET['status'] ?? '';

            $where = 'user_id = ?';
            $params = [$userId];
            if ($status) { $where .= " AND status = ?"; $params[] = $status; }

            $total = $db->count('campaigns', $where, $params);
            $offset = ($page - 1) * $perPage;

            $campaigns = $db->fetchAll(
                "SELECT id, name, type, status, message_type, total_recipients, sent_count, delivered_count, read_count, failed_count, scheduled_at, started_at, completed_at, created_at 
                 FROM " . $db->table('campaigns') . " WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params
            );

            json_response(['data' => $campaigns, 'meta' => ['total' => $total, 'page' => $page]]);
        }
        break;

    case 'POST':
        // Check for sub-actions: /campaigns/{id}/start or /campaigns/{id}/pause
        if ($resourceId && $subResource) {
            $campaign = $db->fetch("SELECT * FROM " . $db->table('campaigns') . " WHERE id = ? AND user_id = ?", [(int)$resourceId, $userId]);
            if (!$campaign) json_response(['error' => 'Campaign not found'], 404);

            if ($subResource === 'start') {
                $db->update('campaigns', ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$resourceId]);
                json_response(['message' => 'Campaign started']);
            } elseif ($subResource === 'pause') {
                $db->update('campaigns', ['status' => 'paused'], 'id = ?', [(int)$resourceId]);
                json_response(['message' => 'Campaign paused']);
            } elseif ($subResource === 'cancel') {
                $db->update('campaigns', ['status' => 'cancelled'], 'id = ?', [(int)$resourceId]);
                json_response(['message' => 'Campaign cancelled']);
            }
            json_response(['error' => 'Invalid action'], 400);
        }

        // Create campaign
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($input['name'] ?? '');
        $messageBody = trim($input['message'] ?? $input['message_body'] ?? '');

        if (empty($name) || empty($messageBody)) {
            json_response(['error' => 'Name and message are required'], 400);
        }

        $campaignId = $db->insert('campaigns', [
            'user_id' => $userId,
            'whatsapp_account_id' => (int)($input['whatsapp_account_id'] ?? 0) ?: null,
            'name' => $name,
            'type' => $input['type'] ?? 'broadcast',
            'status' => 'draft',
            'message_type' => $input['message_type'] ?? 'text',
            'message_body' => $messageBody,
            'target_type' => $input['target_type'] ?? 'all',
            'target_groups' => isset($input['target_groups']) ? json_encode($input['target_groups']) : null,
            'scheduled_at' => $input['scheduled_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        json_response(['data' => ['id' => $campaignId], 'message' => 'Campaign created'], 201);
        break;

    case 'DELETE':
        if (!$resourceId) json_response(['error' => 'Campaign ID required'], 400);
        $deleted = $db->delete('campaigns', 'id = ? AND user_id = ? AND status IN ("draft","cancelled")', [(int)$resourceId, $userId]);
        if (!$deleted) json_response(['error' => 'Cannot delete active campaign'], 400);
        json_response(['message' => 'Campaign deleted']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
