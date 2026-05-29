<?php
/**
 * WaMark - Admin/User Panel Header Template
 * Shared header with sidebar navigation
 */
if (!defined('WAMARK_VERSION')) exit;

$currentUser = Auth::user();
$currentRole = Auth::role();
$pageTitle = $pageTitle ?? 'Dashboard';
$bodyClass = $bodyClass ?? '';

// Get branding settings
$siteName = get_setting('site_name', APP_NAME);
$siteLogo = get_setting('site_logo', '');
$primaryColor = get_setting('primary_color', '#6366f1');
$themeMode = $_COOKIE['wm_theme'] ?? get_setting('theme_mode', 'light');

// Get unread notifications count
$notifCount = $db->count('notifications', 'user_id = ? AND is_read = 0', [Auth::id()]);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $themeMode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= sanitize($pageTitle) ?> - <?= sanitize($siteName) ?></title>
    
    <!-- Favicon -->
    <?php if ($siteLogo): ?>
    <link rel="icon" href="<?= $siteLogo ?>" type="image/png">
    <?php endif; ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/app.css" rel="stylesheet">
    
    <!-- Theme Color -->
    <style>
        :root {
            --wm-primary: <?= $primaryColor ?>;
            --wm-primary-rgb: <?= implode(',', sscanf($primaryColor, "#%02x%02x%02x")) ?>;
        }
    </style>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body class="<?= $bodyClass ?>" data-theme="<?= $themeMode ?>">

<div class="app-wrapper" id="appWrapper">
    <!-- Sidebar -->
    <aside class="app-sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= BASE_URL ?>" class="sidebar-brand">
                <?php if ($siteLogo): ?>
                    <img src="<?= $siteLogo ?>" alt="<?= $siteName ?>" class="brand-logo">
                <?php else: ?>
                    <i class="bi bi-whatsapp"></i>
                    <span class="brand-text"><?= $siteName ?></span>
                <?php endif; ?>
            </a>
            <button class="sidebar-close d-lg-none" onclick="toggleSidebar()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <?php include __DIR__ . '/nav_' . ($currentRole === 'client' ? 'user' : 'admin') . '.php'; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= sanitize($currentUser['name']) ?></span>
                    <span class="user-role"><?= ucfirst(str_replace('_', ' ', $currentRole)) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="app-main">
        <!-- Top Navbar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="btn-sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="page-title mb-0"><?= sanitize($pageTitle) ?></h5>
            </div>
            <div class="topbar-right">
                <!-- Theme Toggle -->
                <button class="topbar-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="bi bi-moon-fill theme-icon-dark"></i>
                    <i class="bi bi-sun-fill theme-icon-light"></i>
                </button>
                
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="topbar-btn position-relative" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="badge-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="dropdown-header d-flex justify-content-between">
                            <span>Notifications</span>
                            <?php if ($notifCount > 0): ?>
                            <span class="badge bg-primary"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <p class="text-muted text-center py-3 mb-0">Loading...</p>
                        </div>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="topbar-btn user-menu-btn" data-bs-toggle="dropdown">
                        <div class="user-avatar-sm">
                            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= sanitize($currentUser['name']) ?></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="<?= ($currentRole === 'client' ? USER_URL : ADMIN_URL) ?>/profile.php">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                        <a class="dropdown-item" href="<?= ($currentRole === 'client' ? USER_URL : ADMIN_URL) ?>/settings.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="<?= ADMIN_URL ?>/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php $flashMessages = get_flash_messages(); ?>
        <?php if (!empty($flashMessages)): ?>
        <div class="flash-container">
            <?php foreach ($flashMessages as $msg): ?>
            <div class="alert alert-<?= $msg['type'] === 'error' ? 'danger' : $msg['type'] ?> alert-dismissible fade show">
                <?= sanitize($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="app-content">
