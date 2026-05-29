<?php
/**
 * WaMark API - Contacts Endpoint
 * GET /api/contacts - List contacts
 * GET /api/contacts/{id} - Get single contact
 * POST /api/contacts - Create contact
 * PUT /api/contacts/{id} - Update contact
 * DELETE /api/contacts/{id} - Delete contact
 */

if (!$apiUser) json_response(['error' => 'Authentication required. Pass X-API-Key header.'], 401);
$userId = $apiUser['user_id'];

switch ($method) {
    case 'GET':
        if ($resourceId) {
            // Get single contact
            $contact = $db->fetch(
                "SELECT id, phone, name, email, company, tags, status, source, created_at FROM " . $db->table('contacts') . " WHERE id = ? AND user_id = ?",
                [(int)$resourceId, $userId]
            );
            if (!$contact) json_response(['error' => 'Contact not found'], 404);
            json_response(['data' => $contact]);
        } else {
            // List contacts
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = 'user_id = ?';
            $params = [$userId];
            if ($search) { $where .= " AND (name LIKE ? OR phone LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
            if ($status) { $where .= " AND status = ?"; $params[] = $status; }

            $total = $db->count('contacts', $where, $params);
            $offset = ($page - 1) * $perPage;

            $contacts = $db->fetchAll(
                "SELECT id, phone, name, email, company, tags, status, source, created_at FROM " . $db->table('contacts') . " WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params
            );

            json_response([
                'data' => $contacts,
                'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => ceil($total / $perPage)]
            ]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $phone = clean_phone($input['phone'] ?? '');
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');

        if (empty($phone)) json_response(['error' => 'Phone number is required'], 400);
        if ($db->exists('contacts', 'user_id = ? AND phone = ?', [$userId, $phone])) {
            json_response(['error' => 'Contact already exists'], 409);
        }

        $id = $db->insert('contacts', [
            'user_id' => $userId,
            'phone' => $phone,
            'name' => $name ?: null,
            'email' => $email ?: null,
            'company' => trim($input['company'] ?? '') ?: null,
            'tags' => trim($input['tags'] ?? '') ?: null,
            'status' => 'active',
            'source' => 'api',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        json_response(['data' => ['id' => $id, 'phone' => $phone], 'message' => 'Contact created'], 201);
        break;

    case 'PUT':
        if (!$resourceId) json_response(['error' => 'Contact ID required'], 400);
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) json_response(['error' => 'Invalid JSON body'], 400);

        $contact = $db->fetch("SELECT id FROM " . $db->table('contacts') . " WHERE id = ? AND user_id = ?", [(int)$resourceId, $userId]);
        if (!$contact) json_response(['error' => 'Contact not found'], 404);

        $updateData = [];
        if (isset($input['name'])) $updateData['name'] = trim($input['name']);
        if (isset($input['email'])) $updateData['email'] = trim($input['email']);
        if (isset($input['company'])) $updateData['company'] = trim($input['company']);
        if (isset($input['tags'])) $updateData['tags'] = trim($input['tags']);
        if (isset($input['status'])) $updateData['status'] = $input['status'];

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $db->update('contacts', $updateData, 'id = ?', [(int)$resourceId]);
        }

        json_response(['message' => 'Contact updated']);
        break;

    case 'DELETE':
        if (!$resourceId) json_response(['error' => 'Contact ID required'], 400);
        $deleted = $db->delete('contacts', 'id = ? AND user_id = ?', [(int)$resourceId, $userId]);
        if (!$deleted) json_response(['error' => 'Contact not found'], 404);
        json_response(['message' => 'Contact deleted']);
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
