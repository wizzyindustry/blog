-- ==============================================================
-- Upgrade v5: Terms acceptance record + compressed file storage
--
--   users.terms_accepted_at / terms_version
--       When the student accepted the Terms & Conditions and Privacy Policy, and which version.
--   user_files.stored_size / is_compressed
--       Size on disk after compression, and whether the file is stored gzip-compressed
--       (it is decompressed automatically when downloaded).
--
-- Run as MySQL root in phpMyAdmin. Safe on an existing database and safe to run twice.
-- ==============================================================

USE `udom_blog`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `terms_accepted_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `terms_version` VARCHAR(20) DEFAULT NULL AFTER `terms_accepted_at`;

ALTER TABLE `user_files`
  ADD COLUMN IF NOT EXISTS `stored_size` BIGINT UNSIGNED DEFAULT NULL AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `is_compressed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stored_size`;
