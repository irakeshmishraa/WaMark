<?php
/**
 * WaMark - Logout
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::logout();
flash('success', 'You have been logged out successfully.');
redirect(ADMIN_URL . '/login.php');
