-- Migration: Many-to-many user roles
-- Replaces single role_id FK on users with user_roles pivot table

-- Create user_roles pivot table
CREATE TABLE `user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing role assignments
INSERT INTO `user_roles` (`user_id`, `role_id`)
    SELECT `id`, `role_id` FROM `users` WHERE `role_id` IS NOT NULL;

-- Drop role_id FK and column from users
ALTER TABLE `users` DROP FOREIGN KEY `users_ibfk_1`;
ALTER TABLE `users` DROP COLUMN `role_id`;

-- Drop level column from roles
ALTER TABLE `roles` DROP COLUMN `level`;
