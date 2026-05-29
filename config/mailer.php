<?php
/**
 * WaMark - Email Mailer Class
 * Simple SMTP mailer using PHP sockets (no external dependencies)
 */

class Mailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $fromName;
    private $fromEmail;
    private $socket;
    private $lastError = '';

    public function __construct() {
        $this->host = MAIL_HOST;
        $this->port = MAIL_PORT;
        $this->username = MAIL_USER;
        $this->password = MAIL_PASS;
        $this->encryption = MAIL_ENCRYPTION;
        $this->fromName = MAIL_FROM_NAME;
        $this->fromEmail = MAIL_FROM_EMAIL;
    }

    /**
     * Send email using SMTP
     */
    public function send($to, $subject, $body, $isHtml = true) {
        if (empty($this->host) || empty($this->fromEmail)) {
            $this->lastError = 'SMTP not configured';
            return false;
        }

        try {
            $headers = $this->buildHeaders($to, $subject, $isHtml);
            $message = $this->buildMessage($body, $isHtml);
            
            return $this->sendViaSMTP($to, $subject, $headers, $message);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Send email using template
     */
    public function sendTemplate($to, $templateSlug, $variables = []) {
        global $db;
        
        $template = $db->fetch(
            "SELECT * FROM " . $db->table('email_templates') . " WHERE slug = ? AND is_active = 1",
            [$templateSlug]
        );

        if (!$template) {
            $this->lastError = "Template '{$templateSlug}' not found";
            return false;
        }

        // Add global variables
        $variables['app_name'] = APP_NAME;
        $variables['app_url'] = BASE_URL;
        $variables['login_url'] = BASE_URL . '/admin/login.php';
        $variables['year'] = date('Y');

        // Parse template
        $subject = parse_template($template['subject'], $variables);
        $body = parse_template($template['body'], $variables);

        // Wrap in email layout
        $htmlBody = $this->wrapInLayout($body, $subject);

        return $this->send($to, $subject, $htmlBody);
    }

    /**
     * Wrap content in email HTML layout
     */
    private function wrapInLayout($content, $title = '') {
        $brandColor = get_setting('primary_color', '#6366f1');
        $appName = APP_NAME;
        $year = date('Y');

        return "<!DOCTYPE html>
<html>
<head><meta charset='utf-8'><title>{$title}</title></head>
<body style='margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f3f4f6;padding:40px 20px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
<tr><td style='background:{$brandColor};padding:30px;text-align:center;'>
<h1 style='color:#fff;margin:0;font-size:24px;'>{$appName}</h1>
</td></tr>
<tr><td style='padding:40px 30px;'>{$content}</td></tr>
<tr><td style='padding:20px 30px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;'>
<p style='color:#6b7280;font-size:12px;margin:0;'>&copy; {$year} {$appName}. All rights reserved.</p>
</td></tr>
</table>
</td></tr></table>
</body></html>";
    }

    /**
     * Build email headers
     */
    private function buildHeaders($to, $subject, $isHtml) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $headers .= "X-Mailer: WaMark/1.0\r\n";
        return $headers;
    }

    /**
     * Build message body
     */
    private function buildMessage($body, $isHtml) {
        return $body;
    }

    /**
     * Send via SMTP
     */
    private function sendViaSMTP($to, $subject, $headers, $message) {
        $prefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $this->socket = @fsockopen($prefix . $this->host, $this->port, $errno, $errstr, 30);
        
        if (!$this->socket) {
            // Fallback to PHP mail()
            return mail($to, $subject, $message, $headers);
        }

        $this->getResponse(); // Get greeting

        $this->sendCommand("EHLO " . gethostname());
        
        // Start TLS if needed
        if ($this->encryption === 'tls') {
            $this->sendCommand("STARTTLS");
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand("EHLO " . gethostname());
        }

        // Authenticate
        if ($this->username) {
            $this->sendCommand("AUTH LOGIN");
            $this->sendCommand(base64_encode($this->username));
            $this->sendCommand(base64_encode($this->password));
        }

        // Send email
        $this->sendCommand("MAIL FROM:<{$this->fromEmail}>");
        $this->sendCommand("RCPT TO:<{$to}>");
        $this->sendCommand("DATA");
        
        $data = "Subject: {$subject}\r\n";
        $data .= "To: {$to}\r\n";
        $data .= $headers;
        $data .= "\r\n{$message}\r\n.";
        $this->sendCommand($data);
        
        $this->sendCommand("QUIT");
        fclose($this->socket);

        return true;
    }

    private function sendCommand($command) {
        fputs($this->socket, $command . "\r\n");
        return $this->getResponse();
    }

    private function getResponse() {
        $response = '';
        while ($str = fgets($this->socket, 4096)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $response;
    }

    public function getLastError() {
        return $this->lastError;
    }
}
