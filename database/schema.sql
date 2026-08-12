-- Renewly Database Schema Definition
-- Subscription & Renewal Management System

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `reminder_logs`;
DROP TABLE IF EXISTS `renewals`;
DROP TABLE IF EXISTS `payment_schedules`;
DROP TABLE IF EXISTS `subscription_adjustments`;
DROP TABLE IF EXISTS `contract_items`;
DROP TABLE IF EXISTS `contracts`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `app_settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Roles Table
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Permissions Registry Table
CREATE TABLE `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `permission_key` VARCHAR(100) NOT NULL UNIQUE,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Role Permissions Pivot Table
CREATE TABLE `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Users Table
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Clients Table
CREATE TABLE `clients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL,
  `account_manager_id` INT UNSIGNED DEFAULT NULL,
  `primary_contact_name` VARCHAR(255) DEFAULT NULL,
  `primary_contact_email` VARCHAR(255) DEFAULT NULL,
  `primary_contact_phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_clients_am` FOREIGN KEY (`account_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Vendors Catalog Table
CREATE TABLE `vendors` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `vendor_name` VARCHAR(255) NOT NULL UNIQUE,
  `website` VARCHAR(255) DEFAULT NULL,
  `support_email` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Products Catalog Table
CREATE TABLE `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `pricing_model` ENUM('flat_rate', 'per_seat') NOT NULL DEFAULT 'per_seat',
  `default_unit_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `currency` CHAR(3) DEFAULT 'USD',
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Contracts Master Table
CREATE TABLE `contracts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_reference` VARCHAR(100) NOT NULL UNIQUE,
  `client_id` INT UNSIGNED NOT NULL,
  `account_manager_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `exchange_rate` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
  `billing_cycle` ENUM('monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
  `total_contract_value` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `status` ENUM('draft', 'active', 'expiring', 'renewed', 'lapsed', 'cancelled') DEFAULT 'active',
  `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
  `auto_renew` TINYINT(1) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_contracts_expiry` (`expiry_date`),
  INDEX `idx_contracts_status` (`status`),
  CONSTRAINT `fk_contracts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contracts_am` FOREIGN KEY (`account_manager_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_contracts_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Contract Items Table (Price Snapshots)
CREATE TABLE `contract_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `pricing_model` ENUM('flat_rate', 'per_seat') NOT NULL,
  `current_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `queued_quantity` INT UNSIGNED DEFAULT NULL,
  `unit_price` DECIMAL(15,4) NOT NULL,
  `line_total` DECIMAL(15,4) NOT NULL,
  `queued_effective_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'pending_reduction', 'cancelled') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_items_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Subscription Adjustments Audit Table
CREATE TABLE `subscription_adjustments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_item_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `adjustment_type` ENUM('addition_immediate', 'reduction_queued', 'reduction_activated') NOT NULL,
  `previous_quantity` INT UNSIGNED NOT NULL,
  `new_quantity` INT UNSIGNED NOT NULL,
  `prorated_charge_amount` DECIMAL(15,4) DEFAULT 0.0000,
  `requested_date` DATE NOT NULL,
  `effective_date` DATE NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_adj_item` FOREIGN KEY (`contract_item_id`) REFERENCES `contract_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adj_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payment Schedules Table
CREATE TABLE `payment_schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_id` INT UNSIGNED NOT NULL,
  `installment_number` INT UNSIGNED NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(15,4) NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `exchange_rate` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
  `status` ENUM('pending', 'due', 'overdue', 'partially_paid', 'paid', 'cancelled') DEFAULT 'pending',
  `payment_date` DATE DEFAULT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_payments_duedate` (`due_date`),
  INDEX `idx_payments_status` (`status`),
  CONSTRAINT `fk_pay_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Renewals Workflow Table
CREATE TABLE `renewals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_id` INT UNSIGNED NOT NULL,
  `account_manager_id` INT UNSIGNED NOT NULL,
  `renewal_stage` ENUM('upcoming', 'preparation', 'proposal_sent', 'awaiting_client_approval', 'approved', 'awaiting_payment', 'payment_received', 'renewed', 'churned') DEFAULT 'upcoming',
  `current_contract_value` DECIMAL(15,4) NOT NULL,
  `estimated_renewal_value` DECIMAL(15,4) NOT NULL,
  `target_renewal_date` DATE NOT NULL,
  `next_action` VARCHAR(255) DEFAULT NULL,
  `next_action_due_date` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_renewals_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_renewals_am` FOREIGN KEY (`account_manager_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Reminder Logs Table (Idempotent Scanning)
CREATE TABLE `reminder_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contract_id` INT UNSIGNED NOT NULL,
  `reminder_stage` ENUM('30_days', '14_days', '7_days', '3_days', '1_day', 'expired') NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('sent', 'failed') DEFAULT 'sent',
  `error_message` TEXT DEFAULT NULL,
  UNIQUE KEY `idx_contract_stage_recipient` (`contract_id`, `reminder_stage`, `recipient_email`),
  CONSTRAINT `fk_reminders_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Audit Logs Table
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `before_state` JSON DEFAULT NULL,
  `after_state` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. App Settings Table
CREATE TABLE `app_settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
