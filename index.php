<?php
/**
 * WaMark - Main Entry Point
 * Detects installation status and routes accordingly
 */

// Load configuration
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/app.php';

// If not installed, redirect to installer
if (!IS_INSTALLED) {
    header('Location: installer/');
    exit;
}

// If accessing root, redirect to appropriate dashboard
if (Auth::check()) {
    $role = Auth::role();
    switch ($role) {
        case 'super_admin':
            header('Location: admin/');
            break;
        case 'reseller':
            header('Location: admin/');
            break;
        case 'client':
            header('Location: user/');
            break;
        default:
            header('Location: admin/login.php');
    }
} else {
    header('Location: admin/login.php');
}
exit;
