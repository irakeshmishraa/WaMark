<?php
/**
 * WaMark - Quick Send Message
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Auth::requireRole(['client']);
Middleware::run();

$pageTitle = 'Send Message';
$userId = Auth::id();

// Handle send
if (request_method() === 'POST') {
    verify_csrf();
    
    $phones = trim($_POST['phones'] ?? '');
    $messageType = $_POST['message_type'] ?? 'text';
    $messageBody = trim($_POST['message_body'] ?? '');
    $waAccountId = (int)($_POST['whatsapp_account_id'] ?? 0);

    if (empty($phones) || empty($messageBody)) {
        flash('error', 'Phone number(s) and message are required.');
    } else {
        // Parse phone numbers (comma or newline separated)
        $phoneList = preg_split('/[\n,]+/', $phones);
        $phoneList = array_filter(array_map('trim', $phoneList));
        $queued = 0;

        foreach ($phoneList as $phone) {
            $phone = clean_phone($phone);
            if (empty($phone)) continue;

            // Find or create contact
            $contact = $db->fetch("SELECT id FROM " . $db->table('contacts') . " WHERE user_id = ? AND phone = ?", [$userId, $phone]);
            $contactId = $contact ? $contact['id'] : null;

            // Queue message
            $db->insert('messages', [
                'user_id' => $userId,
                'whatsapp_account_id' => $waAccountId ?: null,
                'contact_id' => $contactId,
                'phone' => $phone,
                'direction' => 'outgoing',
                'message_type' => $messageType,
                'message_body' => $messageBody,
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $queued++;
        }

        if ($queued > 0) {
            flash('success', "{$queued} message(s) queued for delivery.");
        } else {
            flash('error', 'No valid phone numbers found.');
        }
    }
    redirect(USER_URL . '/send.php');
}

$waAccounts = $db->fetchAll("SELECT * FROM " . $db->table('whatsapp_accounts') . " WHERE user_id = ? AND status = 'connected'", [$userId]);
$prefillPhone = $_GET['phone'] ?? '';

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold"><i class="bi bi-send text-success"></i> Send Message</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">WhatsApp Account</label>
                        <select class="form-select" name="whatsapp_account_id">
                            <?php foreach ($waAccounts as $wa): ?>
                            <option value="<?= $wa['id'] ?>"><?= sanitize($wa['name']) ?> (<?= $wa['phone_number'] ?>)</option>
                            <?php endforeach; ?>
                            <?php if (empty($waAccounts)): ?>
                            <option value="">No accounts connected</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recipient Phone Number(s) *</label>
                        <textarea class="form-control" name="phones" rows="3" placeholder="Enter phone numbers (one per line or comma-separated)&#10;+1234567890&#10;+0987654321" required><?= sanitize($prefillPhone) ?></textarea>
                        <small class="text-muted">Include country code. Example: +1234567890</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message Type</label>
                        <select class="form-select" name="message_type">
                            <option value="text">Text Message</option>
                            <option value="image">Image with Caption</option>
                            <option value="document">Document</option>
                            <option value="video">Video</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="message_body" rows="5" id="messageBody" placeholder="Type your message here...&#10;&#10;You can use variables like {name}, {company}" required></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Variables: {name}, {phone}, {email}, {company}</small>
                            <small id="charCount" class="text-muted">0 characters</small>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" <?= empty($waAccounts) ? 'disabled' : '' ?>>
                            <i class="bi bi-send"></i> Send Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Tips</h6></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li class="mb-2">Always include country code (+1, +91, etc.)</li>
                    <li class="mb-2">Use variables to personalize messages</li>
                    <li class="mb-2">Messages are queued and sent via cron</li>
                    <li class="mb-2">For bulk sending, use Campaigns instead</li>
                    <li>Check delivery status in Message Logs</li>
                </ul>
            </div>
        </div>

        <?php if (empty($waAccounts)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            <strong>No WhatsApp account connected.</strong><br>
            <a href="whatsapp.php">Connect an account</a> to start sending messages.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$extraScripts = '<script>
document.getElementById("messageBody").addEventListener("input", function() {
    document.getElementById("charCount").textContent = this.value.length + " characters";
});
</script>';
include ASSETS_PATH . 'templates/footer.php';
?>
