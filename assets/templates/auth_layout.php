<?php
/**
 * WaMark - Auth Pages Layout (Login, Register, Forgot Password)
 */
$siteName = defined('IS_INSTALLED') && IS_INSTALLED ? get_setting('site_name', APP_NAME) : APP_NAME;
$siteLogo = defined('IS_INSTALLED') && IS_INSTALLED ? get_setting('site_logo', '') : '';
$primaryColor = defined('IS_INSTALLED') && IS_INSTALLED ? get_setting('primary_color', '#6366f1') : '#6366f1';
$authTitle = $authTitle ?? 'Login';
$authSubtitle = $authSubtitle ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $authTitle ?> - <?= $siteName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --wm-primary: <?= $primaryColor ?>; }
        body {
            min-height: 100vh;
            display: flex;
            background: #f1f5f9;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .auth-left {
            flex: 1;
            background: linear-gradient(135deg, var(--wm-primary) 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .auth-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 15s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .auth-left-content { position: relative; z-index: 1; text-align: center; }
        .auth-left-content h2 { font-size: 32px; font-weight: 700; margin-bottom: 16px; }
        .auth-left-content p { font-size: 16px; opacity: 0.9; }
        .auth-right {
            width: 500px;
            min-width: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        .auth-card {
            width: 100%;
            max-width: 400px;
        }
        .auth-brand {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-brand img { max-height: 48px; margin-bottom: 12px; }
        .auth-brand h3 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
        .auth-brand p { color: #64748b; font-size: 14px; margin-top: 4px; }
        .form-control {
            border-radius: 8px;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: var(--wm-primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .btn-primary {
            background: var(--wm-primary);
            border-color: var(--wm-primary);
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
        }
        .btn-primary:hover { filter: brightness(0.9); background: var(--wm-primary); border-color: var(--wm-primary); }
        @media (max-width: 992px) {
            .auth-left { display: none; }
            .auth-right { width: 100%; min-width: auto; }
        }
    </style>
</head>
<body>
    <div class="auth-left">
        <div class="auth-left-content">
            <i class="bi bi-whatsapp" style="font-size:60px;"></i>
            <h2><?= $siteName ?></h2>
            <p>White Label WhatsApp Marketing & Automation Platform</p>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-card">
            <div class="auth-brand">
                <?php if ($siteLogo): ?>
                    <img src="<?= $siteLogo ?>" alt="<?= $siteName ?>">
                <?php else: ?>
                    <h3><i class="bi bi-whatsapp text-success"></i> <?= $siteName ?></h3>
                <?php endif; ?>
                <p><?= $authSubtitle ?></p>
            </div>

            <?php $flashMessages = get_flash_messages(); ?>
            <?php foreach ($flashMessages as $msg): ?>
                <div class="alert alert-<?= $msg['type'] === 'error' ? 'danger' : $msg['type'] ?> py-2 small">
                    <?= sanitize($msg['message']) ?>
                </div>
            <?php endforeach; ?>
