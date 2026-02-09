-- Migration: Roles and Permissions
-- Run against existing database to add RBAC support

-- Roles table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `level` INT UNSIGNED NOT NULL DEFAULT 0,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role-Permission pivot table
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add columns to users table
ALTER TABLE `users`
    ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`,
    ADD COLUMN `name` VARCHAR(255) NULL AFTER `email`,
    ADD COLUMN `role_id` INT UNSIGNED NULL AFTER `name`,
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role_id`,
    ADD COLUMN `last_login_at` TIMESTAMP NULL AFTER `is_active`,
    ADD FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL;

-- Add user_id to remember_tokens, drop username
ALTER TABLE `remember_tokens`
    ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`,
    ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

UPDATE `remember_tokens` rt
    JOIN `users` u ON u.username = rt.username
    SET rt.user_id = u.id;

ALTER TABLE `remember_tokens` DROP COLUMN `username`;

-- Seed roles
INSERT INTO `roles` (`name`, `slug`, `level`, `description`) VALUES
    ('Admin', 'admin', 100, 'Full system access'),
    ('Editor', 'editor', 50, 'Can edit content'),
    ('Viewer', 'viewer', 10, 'Read-only access');

-- Seed permissions
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    ('View Users', 'users.view', 'Can view user list'),
    ('Create Users', 'users.create', 'Can create new users'),
    ('Edit Users', 'users.edit', 'Can edit existing users'),
    ('Delete Users', 'users.delete', 'Can delete users');

-- Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
    SELECT r.id, p.id FROM `roles` r, `permissions` p WHERE r.slug = 'admin';

-- Editor and Viewer get users.view
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
    SELECT r.id, p.id FROM `roles` r, `permissions` p
    WHERE r.slug IN ('editor', 'viewer') AND p.slug = 'users.view';

-- Update existing admin user with admin role
UPDATE `users` u
    JOIN `roles` r ON r.slug = 'admin'
    SET u.role_id = r.id, u.email = 'admin@example.com', u.name = 'Administrator'
    WHERE u.username = 'admin';
