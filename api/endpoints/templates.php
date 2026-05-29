<?php
/**
 * WaMark API - Templates Endpoint
 * GET /api/templates - List message templates
 * POST /api/templates - Create template
 */

if (!$apiUser) json_response(['error' => 'Authentication required'], 401);
$userId = $apiUser['user_id'];

switch ($method) {
    case 'GET':
        if ($resourceId) {
            $template = $db->fetch(
                "SELECT id, name, category, language, header_type, header_content, body, footer, buttons, variables, status, created_at 
                 FROM " . $db->table('templates') . " WHERE id = ? AND user_id = ?",
                [(int)$resourceId, $userId]
            );
            if (!$template) json_response(['error' => 'Template not found'], 404);
            if ($template['buttons']) $template['buttons'] = json_decode($template['buttons'], true);
            if ($template['variables']) $template['variables'] = json_decode($template['variables'], true);
            json_response(['data' => $template]);
        } else {
            $templates = $db->fetchAll(
                "SELECT id, name, category, language, body, status, created_at FROM " . $db->table('templates') . " WHERE user_id = ? AND status = 'active' ORDER BY name",
                [$userId]
            );
            json_response(['data' => $templates]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $name = trim($input['name'] ?? '');
        $body = trim($input['body'] ?? '');

        if (empty($name) || empty($body)) json_response(['error' => 'Name and body are required'], 400);

        $id = $db->insert('templates', [
            'user_id' => $userId,
            'name' => $name,
            'category' => $input['category'] ?? 'marketing',
            'language' => $input['language'] ?? 'en',
            'header_type' => $input['header_type'] ?? 'none',
            'header_content' => $input['header_content'] ?? null,
            'body' => $body,
            'footer' => $input['footer'] ?? null,
            'buttons' => isset($input['buttons']) ? json_encode($input['buttons']) : null,
            'variables' => isset($input['variables']) ? json_encode($input['variables']) : null,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        json_response(['data' => ['id' => $id], 'message' => 'Template created'], 201);
        break;

    case 'DELETE':
        if (!$resourceId) json_response(['error' => 'Template ID required'], 400);
        $db->delete('templates', 'id = ? AND user_id = ?', [(int)$resourceId, $userId]);
        json_response(['message' => 'Template deleted']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
