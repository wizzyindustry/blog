-- ==============================================================
-- Upgrade v3: sharing user files on the Materials page
--
-- Adds to `user_files`:
--   title       Name shown on the Materials page (defaults to the file name)
--   visibility  'private' = only the owner (and admins) | 'public' = everyone
--   category    Optional materials category, linked to categories.name
--               (renaming a category updates files; deleting one clears it)
--
-- Safe on an existing database and safe to run twice (MariaDB 10.4+ / XAMPP).
-- Run sql/upgrade_v2_users.sql first if the user tables do not exist yet.
-- ==============================================================

USE `udom_blog`;

ALTER TABLE `user_files`
  ADD COLUMN IF NOT EXISTS `title` VARCHAR(200) DEFAULT NULL AFTER `original_name`,
  ADD COLUMN IF NOT EXISTS `visibility` ENUM('private','public') NOT NULL DEFAULT 'private' AFTER `description`,
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT NULL AFTER `visibility`;

ALTER TABLE `user_files`
  ADD INDEX IF NOT EXISTS `idx_user_files_public_created` (`visibility`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_user_files_public_category` (`visibility`, `category`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_user_files_public_ext` (`visibility`, `extension`, `created_at`);

ALTER TABLE `user_files`
  ADD CONSTRAINT `fk_user_files_category` FOREIGN KEY IF NOT EXISTS (`category`)
    REFERENCES `categories` (`name`) ON UPDATE CASCADE ON DELETE SET NULL;
