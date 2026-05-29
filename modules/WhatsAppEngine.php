<?php
/**
 * WaMark - WhatsApp Engine Module
 * Handles all WhatsApp Cloud API interactions
 */

class WhatsAppEngine {
    private $accessToken;
    private $phoneNumberId;
    private $businessAccountId;
    private $apiVersion = 'v18.0';
    private $baseUrl = 'https://graph.facebook.com';
    private $lastError = '';

    public function __construct($accessToken = null, $phoneNumberId = null, $businessAccountId = null) {
        $this->accessToken = $accessToken;
        $this->phoneNumberId = $phoneNumberId;
        $this->businessAccountId = $businessAccountId;
    }

    /**
     * Initialize from WA account record
     */
    public static function fromAccount($account) {
        $engine = new self(
            $account['access_token'],
            $account['phone_number_id'],
            $account['business_account_id'] ?? null
        );
        return $engine;
    }

    /**
     * Send text message
     */
    public function sendText($to, $text, $previewUrl = true) {
        return $this->sendMessage($to, [
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => $previewUrl],
        ]);
    }

    /**
     * Send image message
     */
    public function sendImage($to, $imageUrl, $caption = '') {
        $payload = ['type' => 'image', 'image' => ['link' => $imageUrl]];
        if ($caption) $payload['image']['caption'] = $caption;
        return $this->sendMessage($to, $payload);
    }

    /**
     * Send video message
     */
    public function sendVideo($to, $videoUrl, $caption = '') {
        $payload = ['type' => 'video', 'video' => ['link' => $videoUrl]];
        if ($caption) $payload['video']['caption'] = $caption;
        return $this->sendMessage($to, $payload);
    }

    /**
     * Send document
     */
    public function sendDocument($to, $docUrl, $filename = 'document', $caption = '') {
        $payload = ['type' => 'document', 'document' => ['link' => $docUrl, 'filename' => $filename]];
        if ($caption) $payload['document']['caption'] = $caption;
        return $this->sendMessage($to, $payload);
    }

    /**
     * Send template message
     */
    public function sendTemplate($to, $templateName, $language = 'en', $components = []) {
        $payload = [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        return $this->sendMessage($to, $payload);
    }

    /**
     * Send interactive message with buttons
     */
    public function sendButtons($to, $bodyText, $buttons, $header = null, $footer = null) {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => []],
        ];
        if ($header) $interactive['header'] = ['type' => 'text', 'text' => $header];
        if ($footer) $interactive['footer'] = ['text' => $footer];

        foreach ($buttons as $i => $btn) {
            $interactive['action']['buttons'][] = [
                'type' => 'reply',
                'reply' => ['id' => $btn['id'] ?? 'btn_' . $i, 'title' => $btn['title']],
            ];
        }

        return $this->sendMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Send interactive list message
     */
    public function sendList($to, $bodyText, $buttonText, $sections, $header = null, $footer = null) {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => ['button' => $buttonText, 'sections' => $sections],
        ];
        if ($header) $interactive['header'] = ['type' => 'text', 'text' => $header];
        if ($footer) $interactive['footer'] = ['text' => $footer];

        return $this->sendMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Send location
     */
    public function sendLocation($to, $lat, $lng, $name = '', $address = '') {
        return $this->sendMessage($to, [
            'type' => 'location',
            'location' => array_filter(['latitude' => $lat, 'longitude' => $lng, 'name' => $name, 'address' => $address]),
        ]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead($messageId) {
        return $this->apiRequest('messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * Get business profile
     */
    public function getBusinessProfile() {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/whatsapp_business_profile?fields=about,address,description,email,profile_picture_url,websites,vertical";
        return $this->apiGet($url);
    }

    /**
     * Get message templates
     */
    public function getTemplates($limit = 100) {
        if (!$this->businessAccountId) return ['error' => 'Business Account ID required'];
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->businessAccountId}/message_templates?limit={$limit}";
        return $this->apiGet($url);
    }

    /**
     * Upload media
     */
    public function uploadMedia($filePath, $mimeType) {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/media";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'messaging_product' => 'whatsapp',
                'file' => new CURLFile($filePath, $mimeType),
                'type' => $mimeType,
            ],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Core: Send message to WhatsApp
     */
    private function sendMessage($to, $payload) {
        $to = ltrim($to, '+');
        $data = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $payload);

        $result = $this->apiRequest('messages', $data);
        
        if (isset($result['messages'][0]['id'])) {
            return ['success' => true, 'message_id' => $result['messages'][0]['id']];
        }

        $this->lastError = $result['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'error' => $this->lastError];
    }

    /**
     * Make POST API request
     */
    private function apiRequest($endpoint, $data) {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/{$endpoint}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return ['error' => ['message' => 'cURL: ' . $error]];
        return json_decode($response, true) ?: ['error' => ['message' => 'Invalid response']];
    }

    /**
     * Make GET API request
     */
    private function apiGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getLastError() { return $this->lastError; }
}
