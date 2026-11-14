-- ==============================================================
-- Upgrade v7: Password reset by email
--
--   password_resets          One row per reset link. Only a SHA-256 HASH of the token is stored, so a
--                            database leak cannot be used to reset passwords. Links expire (30 minutes)
--                            and work once; requesting a new link cancels the older ones.
--   users.password_changed_at  Sign-ins made before this moment are ended (other devices are signed
--                            out after a reset or password change).
--
-- Run as MySQL root in phpMyAdmin. Safe on an existing database and safe to run twice.
-- ==============================================================

USE `udom_blog`;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,                  -- hex SHA-256 of the token in the emailed link
  `ip_address` VARCHAR(45) DEFAULT NULL,           -- who asked for it
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`, `used_at`),
  KEY `idx_password_resets_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME DEFAULT NULL AFTER `email_verified_at`;
