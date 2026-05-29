<?php
/**
 * WaMark - User/Client Sidebar Navigation
 */
if (!defined('WAMARK_VERSION')) exit;

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$userNav = [
    ['title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => USER_URL . '/index.php', 'page' => 'index'],
    ['title' => 'MESSAGING', 'type' => 'heading'],
    ['title' => 'Campaigns', 'icon' => 'bi-megaphone', 'url' => USER_URL . '/campaigns.php', 'page' => 'campaigns'],
    ['title' => 'Send Message', 'icon' => 'bi-send', 'url' => USER_URL . '/send.php', 'page' => 'send'],
    ['title' => 'Templates', 'icon' => 'bi-file-text', 'url' => USER_URL . '/templates.php', 'page' => 'templates'],
    ['title' => 'Scheduler', 'icon' => 'bi-calendar-event', 'url' => USER_URL . '/scheduler.php', 'page' => 'scheduler'],
    ['title' => 'AUTOMATION', 'type' => 'heading'],
    ['title' => 'Workflows', 'icon' => 'bi-robot', 'url' => USER_URL . '/automation.php', 'page' => 'automation'],
    ['title' => 'Auto Reply', 'icon' => 'bi-reply', 'url' => USER_URL . '/auto-reply.php', 'page' => 'auto-reply'],
    ['title' => 'CONTACTS', 'type' => 'heading'],
    ['title' => 'All Contacts', 'icon' => 'bi-people', 'url' => USER_URL . '/contacts.php', 'page' => 'contacts'],
    ['title' => 'Groups', 'icon' => 'bi-collection', 'url' => USER_URL . '/groups.php', 'page' => 'groups'],
    ['title' => 'Import', 'icon' => 'bi-upload', 'url' => USER_URL . '/import.php', 'page' => 'import'],
    ['title' => 'WHATSAPP', 'type' => 'heading'],
    ['title' => 'My Accounts', 'icon' => 'bi-whatsapp', 'url' => USER_URL . '/whatsapp.php', 'page' => 'whatsapp'],
    ['title' => 'Chat Inbox', 'icon' => 'bi-inbox', 'url' => USER_URL . '/inbox.php', 'page' => 'inbox'],
    ['title' => 'REPORTS', 'type' => 'heading'],
    ['title' => 'Analytics', 'icon' => 'bi-graph-up', 'url' => USER_URL . '/analytics.php', 'page' => 'analytics'],
    ['title' => 'Reports', 'icon' => 'bi-bar-chart', 'url' => USER_URL . '/reports.php', 'page' => 'reports'],
    ['title' => 'ACCOUNT', 'type' => 'heading'],
    ['title' => 'Subscription', 'icon' => 'bi-credit-card', 'url' => USER_URL . '/subscription.php', 'page' => 'subscription'],
    ['title' => 'Profile', 'icon' => 'bi-person', 'url' => USER_URL . '/profile.php', 'page' => 'profile'],
    ['title' => 'API Access', 'icon' => 'bi-code-square', 'url' => USER_URL . '/api-access.php', 'page' => 'api-access'],
];
?>

<ul class="nav-list">
    <?php foreach ($userNav as $item): ?>
        <?php if (isset($item['type']) && $item['type'] === 'heading'): ?>
            <li class="nav-heading"><?= $item['title'] ?></li>
        <?php else: ?>
            <li class="nav-item <?= $currentPage === $item['page'] ? 'active' : '' ?>">
                <a href="<?= $item['url'] ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= $item['title'] ?></span>
                </a>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
