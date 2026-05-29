<?php
/**
 * WaMark - Admin Sidebar Navigation
 */
if (!defined('WAMARK_VERSION')) exit;

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isReseller = Auth::isReseller();

$adminNav = [
    ['title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => ADMIN_URL . '/index.php', 'page' => 'index'],
    ['title' => 'MESSAGING', 'type' => 'heading'],
    ['title' => 'Campaigns', 'icon' => 'bi-megaphone', 'url' => ADMIN_URL . '/campaigns.php', 'page' => 'campaigns'],
    ['title' => 'Messages', 'icon' => 'bi-chat-dots', 'url' => ADMIN_URL . '/messages.php', 'page' => 'messages'],
    ['title' => 'Templates', 'icon' => 'bi-file-text', 'url' => ADMIN_URL . '/templates.php', 'page' => 'templates'],
    ['title' => 'Automation', 'icon' => 'bi-robot', 'url' => ADMIN_URL . '/automation.php', 'page' => 'automation'],
    ['title' => 'CONTACTS', 'type' => 'heading'],
    ['title' => 'All Contacts', 'icon' => 'bi-people', 'url' => ADMIN_URL . '/contacts.php', 'page' => 'contacts'],
    ['title' => 'Groups', 'icon' => 'bi-collection', 'url' => ADMIN_URL . '/groups.php', 'page' => 'groups'],
    ['title' => 'WHATSAPP', 'type' => 'heading'],
    ['title' => 'Accounts', 'icon' => 'bi-whatsapp', 'url' => ADMIN_URL . '/whatsapp.php', 'page' => 'whatsapp'],
    ['title' => 'Chat Inbox', 'icon' => 'bi-inbox', 'url' => ADMIN_URL . '/inbox.php', 'page' => 'inbox'],
];

// Admin-only items
if (Auth::isAdmin()) {
    $adminNav = array_merge($adminNav, [
        ['title' => 'MANAGEMENT', 'type' => 'heading'],
        ['title' => 'Users', 'icon' => 'bi-person-badge', 'url' => ADMIN_URL . '/users.php', 'page' => 'users'],
        ['title' => 'Plans', 'icon' => 'bi-credit-card', 'url' => ADMIN_URL . '/plans.php', 'page' => 'plans'],
        ['title' => 'Payments', 'icon' => 'bi-receipt', 'url' => ADMIN_URL . '/payments.php', 'page' => 'payments'],
        ['title' => 'Licenses', 'icon' => 'bi-key', 'url' => ADMIN_URL . '/licenses.php', 'page' => 'licenses'],
        ['title' => 'SYSTEM', 'type' => 'heading'],
        ['title' => 'White Label', 'icon' => 'bi-palette', 'url' => ADMIN_URL . '/white-label.php', 'page' => 'white-label'],
        ['title' => 'Settings', 'icon' => 'bi-gear', 'url' => ADMIN_URL . '/settings.php', 'page' => 'settings'],
        ['title' => 'API Keys', 'icon' => 'bi-code-square', 'url' => ADMIN_URL . '/api-keys.php', 'page' => 'api-keys'],
        ['title' => 'Audit Logs', 'icon' => 'bi-journal-text', 'url' => ADMIN_URL . '/audit-logs.php', 'page' => 'audit-logs'],
        ['title' => 'Backups', 'icon' => 'bi-cloud-download', 'url' => ADMIN_URL . '/backups.php', 'page' => 'backups'],
        ['title' => 'Updates', 'icon' => 'bi-arrow-repeat', 'url' => ADMIN_URL . '/updates.php', 'page' => 'updates'],
    ]);
}
?>

<ul class="nav-list">
    <?php foreach ($adminNav as $item): ?>
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
