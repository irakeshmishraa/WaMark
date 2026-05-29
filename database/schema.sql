-- ============================================================
-- WaMark - White Label WhatsApp Marketing Platform
-- Database Schema v1.0.0
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- TABLE: Users
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(36) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('super_admin','reseller','client') NOT NULL DEFAULT 'client',
  `status` ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `subscription_expires_at` DATETIME DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_secret` VARCHAR(64) DEFAULT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `last_ip` VARCHAR(45) DEFAULT NULL,
  `timezone` VARCHAR(50) DEFAULT 'UTC',
  `language` VARCHAR(10) DEFAULT 'en',
  `company_name` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Plans (Subscription Plans)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('free','monthly','quarterly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
  `max_contacts` INT NOT NULL DEFAULT 100,
  `max_messages_per_month` INT NOT NULL DEFAULT 1000,
  `max_campaigns` INT NOT NULL DEFAULT 5,
  `max_whatsapp_accounts` INT NOT NULL DEFAULT 1,
  `max_automation` INT NOT NULL DEFAULT 3,
  `max_templates` INT NOT NULL DEFAULT 10,
  `features` JSON DEFAULT NULL,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `trial_days` INT NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Subscriptions
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','expired','cancelled','pending') NOT NULL DEFAULT 'pending',
  `payment_gateway` VARCHAR(50) DEFAULT NULL,
  `gateway_subscription_id` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
  `starts_at` DATETIME NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Payments
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED DEFAULT NULL,
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `gateway` ENUM('stripe','razorpay','paypal','payu','manual') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
  `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `gateway_response` JSON DEFAULT NULL,
  `invoice_number` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_transaction` (`transaction_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: WhatsApp Accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_whatsapp_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20) DEFAULT NULL,
  `mode` ENUM('cloud_api','non_api') NOT NULL DEFAULT 'cloud_api',
  `status` ENUM('connected','disconnected','expired','banned') NOT NULL DEFAULT 'disconnected',
  `phone_number_id` VARCHAR(100) DEFAULT NULL,
  `business_account_id` VARCHAR(100) DEFAULT NULL,
  `access_token` TEXT DEFAULT NULL,
  `session_data` LONGTEXT DEFAULT NULL,
  `qr_code` TEXT DEFAULT NULL,
  `webhook_url` VARCHAR(500) DEFAULT NULL,
  `webhook_secret` VARCHAR(100) DEFAULT NULL,
  `last_connected_at` DATETIME DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_mode` (`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Contacts
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `company` VARCHAR(150) DEFAULT NULL,
  `country_code` VARCHAR(5) DEFAULT NULL,
  `tags` VARCHAR(500) DEFAULT NULL,
  `custom_fields` JSON DEFAULT NULL,
  `status` ENUM('active','unsubscribed','blocked','invalid') NOT NULL DEFAULT 'active',
  `opted_in` TINYINT(1) NOT NULL DEFAULT 1,
  `opted_in_at` DATETIME DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `source` VARCHAR(50) DEFAULT 'manual',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `idx_user_phone` (`user_id`, `phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Contact Groups
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_contact_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#007bff',
  `contact_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Contact Group Members
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_contact_group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `contact_id` INT UNSIGNED NOT NULL,
  `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_group_contact` (`group_id`, `contact_id`),
  KEY `idx_contact` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `whatsapp_account_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `type` ENUM('broadcast','scheduled','drip','trigger') NOT NULL DEFAULT 'broadcast',
  `status` ENUM('draft','scheduled','running','paused','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
  `message_type` ENUM('text','image','video','document','template','interactive') NOT NULL DEFAULT 'text',
  `message_body` TEXT DEFAULT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `target_type` ENUM('all','group','tag','custom') NOT NULL DEFAULT 'all',
  `target_groups` JSON DEFAULT NULL,
  `target_tags` VARCHAR(500) DEFAULT NULL,
  `total_recipients` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `delivered_count` INT NOT NULL DEFAULT 0,
  `read_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `replied_count` INT NOT NULL DEFAULT 0,
  `scheduled_at` DATETIME DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Messages (Queue & Log)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED DEFAULT NULL,
  `whatsapp_account_id` INT UNSIGNED DEFAULT NULL,
  `contact_id` INT UNSIGNED DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `direction` ENUM('outgoing','incoming') NOT NULL DEFAULT 'outgoing',
  `message_type` ENUM('text','image','video','document','audio','template','interactive','location','contact') NOT NULL DEFAULT 'text',
  `message_body` TEXT DEFAULT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `template_name` VARCHAR(100) DEFAULT NULL,
  `template_params` JSON DEFAULT NULL,
  `wa_message_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('queued','sending','sent','delivered','read','failed','rejected') NOT NULL DEFAULT 'queued',
  `error_message` TEXT DEFAULT NULL,
  `retry_count` TINYINT NOT NULL DEFAULT 0,
  `scheduled_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_contact` (`contact_id`),
  KEY `idx_status` (`status`),
  KEY `idx_phone` (`phone`),
  KEY `idx_wa_msg` (`wa_message_id`),
  KEY `idx_scheduled` (`scheduled_at`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Message Templates
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `category` ENUM('marketing','utility','authentication','service') NOT NULL DEFAULT 'marketing',
  `language` VARCHAR(10) NOT NULL DEFAULT 'en',
  `header_type` ENUM('none','text','image','video','document') NOT NULL DEFAULT 'none',
  `header_content` TEXT DEFAULT NULL,
  `body` TEXT NOT NULL,
  `footer` VARCHAR(60) DEFAULT NULL,
  `buttons` JSON DEFAULT NULL,
  `variables` JSON DEFAULT NULL,
  `wa_template_id` VARCHAR(100) DEFAULT NULL,
  `wa_status` ENUM('pending','approved','rejected','disabled') DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Automation Workflows
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_automations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `type` ENUM('welcome','follow_up','drip','birthday','anniversary','cart_recovery','trigger','lead_nurture') NOT NULL,
  `status` ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
  `trigger_type` ENUM('keyword','webhook','schedule','event','manual') NOT NULL DEFAULT 'keyword',
  `trigger_value` VARCHAR(500) DEFAULT NULL,
  `whatsapp_account_id` INT UNSIGNED DEFAULT NULL,
  `target_groups` JSON DEFAULT NULL,
  `total_enrolled` INT NOT NULL DEFAULT 0,
  `total_completed` INT NOT NULL DEFAULT 0,
  `settings` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Automation Steps
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_automation_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `automation_id` INT UNSIGNED NOT NULL,
  `step_order` INT NOT NULL DEFAULT 1,
  `action_type` ENUM('send_message','wait','condition','tag','notify','webhook') NOT NULL DEFAULT 'send_message',
  `message_type` ENUM('text','image','video','document','template') DEFAULT 'text',
  `message_body` TEXT DEFAULT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `delay_value` INT DEFAULT 0,
  `delay_unit` ENUM('minutes','hours','days') DEFAULT 'hours',
  `condition_field` VARCHAR(100) DEFAULT NULL,
  `condition_operator` VARCHAR(20) DEFAULT NULL,
  `condition_value` VARCHAR(255) DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_automation` (`automation_id`),
  KEY `idx_order` (`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Automation Enrollments
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_automation_enrollments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `automation_id` INT UNSIGNED NOT NULL,
  `contact_id` INT UNSIGNED NOT NULL,
  `current_step` INT NOT NULL DEFAULT 1,
  `status` ENUM('active','completed','paused','cancelled') NOT NULL DEFAULT 'active',
  `next_action_at` DATETIME DEFAULT NULL,
  `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_automation` (`automation_id`),
  KEY `idx_contact` (`contact_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_action` (`next_action_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Chatbot / Auto-Reply Rules
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_auto_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `whatsapp_account_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `trigger_type` ENUM('exact','contains','starts_with','regex','default') NOT NULL DEFAULT 'contains',
  `trigger_keyword` VARCHAR(255) DEFAULT NULL,
  `response_type` ENUM('text','image','video','document','interactive') NOT NULL DEFAULT 'text',
  `response_body` TEXT NOT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `buttons` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `priority` INT NOT NULL DEFAULT 0,
  `reply_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Licenses
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_key` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('lifetime','subscription','trial') NOT NULL DEFAULT 'subscription',
  `status` ENUM('active','expired','revoked','unused') NOT NULL DEFAULT 'unused',
  `domain` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `max_users` INT NOT NULL DEFAULT 1,
  `activated_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `last_verified_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key` (`license_key`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  `setting_group` VARCHAR(50) DEFAULT 'general',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: White Label Configurations
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_white_label` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `brand_name` VARCHAR(100) DEFAULT NULL,
  `logo_url` VARCHAR(500) DEFAULT NULL,
  `favicon_url` VARCHAR(500) DEFAULT NULL,
  `login_bg_url` VARCHAR(500) DEFAULT NULL,
  `primary_color` VARCHAR(7) DEFAULT '#6366f1',
  `secondary_color` VARCHAR(7) DEFAULT '#8b5cf6',
  `accent_color` VARCHAR(7) DEFAULT '#06b6d4',
  `custom_domain` VARCHAR(255) DEFAULT NULL,
  `custom_login_url` VARCHAR(255) DEFAULT NULL,
  `custom_css` TEXT DEFAULT NULL,
  `email_header` TEXT DEFAULT NULL,
  `email_footer` TEXT DEFAULT NULL,
  `footer_text` VARCHAR(255) DEFAULT NULL,
  `hide_branding` TINYINT(1) NOT NULL DEFAULT 0,
  `custom_smtp_host` VARCHAR(255) DEFAULT NULL,
  `custom_smtp_port` INT DEFAULT NULL,
  `custom_smtp_user` VARCHAR(255) DEFAULT NULL,
  `custom_smtp_pass` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_domain` (`custom_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Audit Logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `module` VARCHAR(50) DEFAULT 'system',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_module` (`module`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Login Attempts
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_attempted` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Remember Tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_remember_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: API Keys
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_api_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `api_key` VARCHAR(64) NOT NULL,
  `api_secret` VARCHAR(64) NOT NULL,
  `permissions` JSON DEFAULT NULL,
  `rate_limit` INT NOT NULL DEFAULT 60,
  `last_used_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_api_key` (`api_key`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Webhooks (Incoming)
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_webhooks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(100) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `headers` JSON DEFAULT NULL,
  `status` ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `processed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `action_url` VARCHAR(500) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT 'bell',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Scheduled Jobs
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_scheduled_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(50) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `priority` TINYINT NOT NULL DEFAULT 5,
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `run_at` DATETIME NOT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_run_at` (`run_at`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Chat Conversations
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `contact_id` INT UNSIGNED NOT NULL,
  `whatsapp_account_id` INT UNSIGNED NOT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `last_message_preview` VARCHAR(255) DEFAULT NULL,
  `unread_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('open','closed','archived') NOT NULL DEFAULT 'open',
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `tags` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_contact_wa` (`user_id`, `contact_id`, `whatsapp_account_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Media Files
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_url` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT NOT NULL DEFAULT 0,
  `type` ENUM('image','video','audio','document','other') NOT NULL DEFAULT 'other',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- TABLE: Email Templates
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `variables` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: System Backups
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_backups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` BIGINT NOT NULL DEFAULT 0,
  `type` ENUM('full','database','files') NOT NULL DEFAULT 'database',
  `status` ENUM('completed','failed','in_progress') NOT NULL DEFAULT 'in_progress',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: System Updates
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(20) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `changelog` LONGTEXT DEFAULT NULL,
  `file_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('available','downloaded','installed','failed') NOT NULL DEFAULT 'available',
  `installed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Cron Logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `wm_cron_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_name` VARCHAR(100) NOT NULL,
  `status` ENUM('success','failed') NOT NULL,
  `duration` FLOAT DEFAULT NULL,
  `records_processed` INT DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job` (`job_name`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- DEFAULT DATA: Initial Settings
-- ============================================================
INSERT INTO `wm_settings` (`setting_key`, `setting_value`, `setting_group`, `is_public`) VALUES
('site_name', 'WaMark', 'general', 1),
('site_tagline', 'White Label WhatsApp Marketing Platform', 'general', 1),
('site_description', 'Complete WhatsApp Marketing & Automation Solution', 'general', 1),
('site_logo', '', 'branding', 1),
('site_favicon', '', 'branding', 1),
('primary_color', '#6366f1', 'branding', 1),
('secondary_color', '#8b5cf6', 'branding', 1),
('theme_mode', 'light', 'branding', 1),
('registration_enabled', '1', 'general', 0),
('email_verification', '1', 'general', 0),
('default_plan', '1', 'billing', 0),
('currency', 'USD', 'billing', 0),
('currency_symbol', '$', 'billing', 0),
('tax_rate', '0', 'billing', 0),
('invoice_prefix', 'INV-', 'billing', 0),
('maintenance_mode', '0', 'general', 0),
('terms_url', '', 'legal', 1),
('privacy_url', '', 'legal', 1),
('support_email', '', 'general', 1),
('support_phone', '', 'general', 1),
('google_analytics', '', 'integrations', 0),
('facebook_pixel', '', 'integrations', 0),
('wa_default_delay', '2', 'whatsapp', 0),
('wa_batch_size', '50', 'whatsapp', 0),
('wa_max_retries', '3', 'whatsapp', 0),
('backup_enabled', '1', 'system', 0),
('backup_retention_days', '30', 'system', 0),
('system_version', '1.0.0', 'system', 0);

-- ============================================================
-- DEFAULT DATA: Plans
-- ============================================================
INSERT INTO `wm_plans` (`name`, `slug`, `description`, `type`, `price`, `max_contacts`, `max_messages_per_month`, `max_campaigns`, `max_whatsapp_accounts`, `max_automation`, `max_templates`, `trial_days`, `is_popular`, `sort_order`) VALUES
('Free Trial', 'free-trial', 'Try WaMark free for 7 days', 'free', 0.00, 100, 500, 3, 1, 2, 5, 7, 0, 1),
('Starter', 'starter', 'Perfect for small businesses', 'monthly', 29.00, 1000, 5000, 10, 2, 5, 20, 0, 0, 2),
('Professional', 'professional', 'For growing businesses', 'monthly', 79.00, 5000, 25000, 50, 5, 20, 50, 0, 1, 3),
('Enterprise', 'enterprise', 'For large organizations', 'monthly', 199.00, 50000, 100000, -1, 20, -1, -1, 0, 0, 4),
('Starter Yearly', 'starter-yearly', 'Starter plan billed yearly', 'yearly', 290.00, 1000, 5000, 10, 2, 5, 20, 0, 0, 5),
('Professional Yearly', 'professional-yearly', 'Professional plan billed yearly', 'yearly', 790.00, 5000, 25000, 50, 5, 20, 50, 0, 0, 6),
('Lifetime', 'lifetime', 'One-time payment, lifetime access', 'lifetime', 999.00, 100000, -1, -1, 50, -1, -1, 0, 0, 7);

-- ============================================================
-- DEFAULT DATA: Email Templates
-- ============================================================
INSERT INTO `wm_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('welcome', 'Welcome Email', 'Welcome to {app_name}!', '<h2>Welcome, {name}!</h2><p>Thank you for joining {app_name}. Your account is ready to use.</p><p>Get started by connecting your WhatsApp account and importing your contacts.</p><p><a href="{login_url}" style="background:#6366f1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;">Login to Dashboard</a></p>', '["name","app_name","login_url"]'),
('password_reset', 'Password Reset', 'Reset Your Password - {app_name}', '<h2>Password Reset Request</h2><p>Hi {name},</p><p>We received a request to reset your password. Click the button below:</p><p><a href="{reset_url}" style="background:#6366f1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;">Reset Password</a></p><p>This link expires in 60 minutes. If you did not request this, ignore this email.</p>', '["name","app_name","reset_url"]'),
('invoice', 'Invoice Email', 'Invoice #{invoice_number} - {app_name}', '<h2>Invoice #{invoice_number}</h2><p>Hi {name},</p><p>Thank you for your payment.</p><p><strong>Amount:</strong> {amount}<br><strong>Plan:</strong> {plan_name}<br><strong>Date:</strong> {date}</p>', '["name","app_name","invoice_number","amount","plan_name","date"]'),
('subscription_expiring', 'Subscription Expiring', 'Your subscription is expiring soon - {app_name}', '<h2>Subscription Expiring</h2><p>Hi {name},</p><p>Your {plan_name} subscription will expire on {expiry_date}. Renew now to avoid service interruption.</p><p><a href="{renew_url}" style="background:#6366f1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;">Renew Now</a></p>', '["name","app_name","plan_name","expiry_date","renew_url"]');

COMMIT;
