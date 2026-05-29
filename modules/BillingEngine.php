<?php
/**
 * WaMark - Billing Engine Module
 * Handles subscription management and payment processing
 */

class BillingEngine {
    private $db;
    private $userId;

    public function __construct($userId = null) {
        global $db;
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Create a new subscription
     */
    public function subscribe($userId, $planId, $gateway = 'manual', $transactionId = null) {
        $plan = $this->db->fetch("SELECT * FROM " . $this->db->table('plans') . " WHERE id = ?", [$planId]);
        if (!$plan) return ['error' => 'Plan not found'];

        // Calculate expiry
        $expiresAt = $this->calculateExpiry($plan['type']);

        // Create subscription
        $subId = $this->db->insert('subscriptions', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => 'active',
            'payment_gateway' => $gateway,
            'gateway_subscription_id' => $transactionId,
            'amount' => $plan['price'],
            'currency' => $plan['currency'],
            'starts_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update user
        $this->db->update('users', [
            'plan_id' => $planId,
            'subscription_expires_at' => $expiresAt,
        ], 'id = ?', [$userId]);

        // Cancel old subscriptions
        $this->db->query(
            "UPDATE " . $this->db->table('subscriptions') . " SET status = 'cancelled', cancelled_at = NOW() WHERE user_id = ? AND id != ? AND status = 'active'",
            [$userId, $subId]
        );

        return ['success' => true, 'subscription_id' => $subId, 'expires_at' => $expiresAt];
    }

    /**
     * Record payment
     */
    public function recordPayment($userId, $amount, $gateway, $transactionId = null, $subscriptionId = null) {
        $invoiceNumber = get_setting('invoice_prefix', 'INV-') . strtoupper(substr(uniqid(), -8));

        $paymentId = $this->db->insert('payments', [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'transaction_id' => $transactionId,
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => get_setting('currency', 'USD'),
            'status' => 'completed',
            'invoice_number' => $invoiceNumber,
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'payment_id' => $paymentId, 'invoice_number' => $invoiceNumber];
    }

    /**
     * Process Stripe payment
     */
    public function processStripe($userId, $planId, $paymentMethodId) {
        $plan = $this->db->fetch("SELECT * FROM " . $this->db->table('plans') . " WHERE id = ?", [$planId]);
        if (!$plan) return ['error' => 'Plan not found'];

        $stripeSecret = get_setting('stripe_secret', '');
        if (empty($stripeSecret)) return ['error' => 'Stripe not configured'];

        // Create payment intent
        $ch = curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'amount' => (int)($plan['price'] * 100),
                'currency' => strtolower($plan['currency']),
                'payment_method' => $paymentMethodId,
                'confirm' => 'true',
                'metadata[user_id]' => $userId,
                'metadata[plan_id]' => $planId,
            ]),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $stripeSecret],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['id']) && $response['status'] === 'succeeded') {
            $payment = $this->recordPayment($userId, $plan['price'], 'stripe', $response['id']);
            $subscription = $this->subscribe($userId, $planId, 'stripe', $response['id']);
            return ['success' => true, 'payment' => $payment, 'subscription' => $subscription];
        }

        return ['error' => $response['error']['message'] ?? 'Payment failed'];
    }

    /**
     * Process Razorpay payment verification
     */
    public function processRazorpay($userId, $planId, $paymentId, $orderId, $signature) {
        $plan = $this->db->fetch("SELECT * FROM " . $this->db->table('plans') . " WHERE id = ?", [$planId]);
        if (!$plan) return ['error' => 'Plan not found'];

        $razorpaySecret = get_setting('razorpay_secret', '');
        if (empty($razorpaySecret)) return ['error' => 'Razorpay not configured'];

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $razorpaySecret);
        if (!hash_equals($expectedSignature, $signature)) {
            return ['error' => 'Invalid payment signature'];
        }

        $payment = $this->recordPayment($userId, $plan['price'], 'razorpay', $paymentId);
        $subscription = $this->subscribe($userId, $planId, 'razorpay', $paymentId);
        return ['success' => true, 'payment' => $payment, 'subscription' => $subscription];
    }

    /**
     * Cancel subscription
     */
    public function cancel($userId) {
        $this->db->query(
            "UPDATE " . $this->db->table('subscriptions') . " SET status = 'cancelled', cancelled_at = NOW() WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
        return ['success' => true];
    }

    /**
     * Check if user has active subscription
     */
    public function isActive($userId) {
        $user = $this->db->fetch("SELECT subscription_expires_at FROM " . $this->db->table('users') . " WHERE id = ?", [$userId]);
        if (!$user) return false;
        if (!$user['subscription_expires_at']) return false;
        return strtotime($user['subscription_expires_at']) > time();
    }

    /**
     * Get usage stats for user
     */
    public function getUsage($userId) {
        $user = $this->db->fetch("SELECT plan_id FROM " . $this->db->table('users') . " WHERE id = ?", [$userId]);
        $plan = $this->db->fetch("SELECT * FROM " . $this->db->table('plans') . " WHERE id = ?", [$user['plan_id'] ?? 0]);

        $contacts = $this->db->count('contacts', 'user_id = ?', [$userId]);
        $messagesThisMonth = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM " . $this->db->table('messages') . " WHERE user_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND direction = 'outgoing'",
            [$userId]
        );
        $campaigns = $this->db->count('campaigns', 'user_id = ?', [$userId]);
        $automations = $this->db->count('automations', 'user_id = ?', [$userId]);

        return [
            'contacts' => ['used' => $contacts, 'limit' => $plan['max_contacts'] ?? 0],
            'messages' => ['used' => (int)$messagesThisMonth, 'limit' => $plan['max_messages_per_month'] ?? 0],
            'campaigns' => ['used' => $campaigns, 'limit' => $plan['max_campaigns'] ?? 0],
            'automations' => ['used' => $automations, 'limit' => $plan['max_automation'] ?? 0],
        ];
    }

    /**
     * Calculate subscription expiry based on plan type
     */
    private function calculateExpiry($type) {
        return match($type) {
            'free' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'quarterly' => date('Y-m-d H:i:s', strtotime('+3 months')),
            'yearly' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'lifetime' => date('Y-m-d H:i:s', strtotime('+100 years')),
            default => date('Y-m-d H:i:s', strtotime('+1 month')),
        };
    }
}
