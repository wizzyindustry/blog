-- ==============================================================
-- Upgrade v6: Sign in with Google
--
--   users.google_sub         Google's permanent account ID ("sub" claim). Unique: one site account per Google account.
--   users.email_verified_at  When Google confirmed the email address (Google sign-ups only).
--   users.has_password       0 for accounts created with Google that never set a password
--                            (their password column holds an unusable random hash).
--
-- No Google access or refresh tokens are stored.
-- Run as MySQL root in phpMyAdmin. Safe on an existing database and safe to run twice.
-- ==============================================================

USE `udom_blog`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `google_sub` VARCHAR(64) DEFAULT NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `has_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `google_sub`,
  ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME DEFAULT NULL AFTER `has_password`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_users_google_sub` (`google_sub`);
