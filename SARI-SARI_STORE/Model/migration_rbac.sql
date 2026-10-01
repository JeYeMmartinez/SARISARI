-- RBAC User Table Migration
-- Run this in phpMyAdmin for your single shared database

-- Add role ENUM if it doesn't exist
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('customer', 'employee', 'admin', 'cashier') NOT NULL DEFAULT 'customer';

-- Add is_active column for blocking users
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1;

-- If you don't have a users table yet, here is the full schema:
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `gmail` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'employee', 'admin', 'cashier') NOT NULL DEFAULT 'customer',
    `is_active` TINYINT(1) DEFAULT 1,
    `status` VARCHAR(20) DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
