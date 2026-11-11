-- ==============================================================
-- Upgrade v2: user accounts, user file uploads, activity tracking
-- and indexes for large data sets (10,000+ users / 10,000+ rows).
--
-- Safe to run on an EXISTING udom_blog database: it only adds tables,
-- columns and indexes and never deletes data. Safe to run twice.
-- (Written for MariaDB 10.4+, the database bundled with XAMPP.)
--
-- A fresh install does not need this file: sql/database.sql already
-- contains everything below.
-- ==============================================================

USE `udom_blog`;

-- --------------------------------------------------------
-- Registered users (students). Separate from `admins`.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,           -- File in uploads/avatars/
  `bio` VARCHAR(300) DEFAULT NULL,
  `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
  `file_count` INT UNSIGNED NOT NULL DEFAULT 0,  -- Kept in sync on upload/delete (no COUNT(*) per page)
  `storage_used` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_seen_at` DATETIME DEFAULT NULL,          -- Updated at most once a minute while browsing
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_last_seen` (`last_seen_at`),
  KEY `idx_users_created` (`created_at`),
  KEY `idx_users_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Files uploaded by users (any type). Stored under a random
-- name in uploads/user_files/ (web access denied) and served
-- only through account/file.php.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_files` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(100) NOT NULL,
  `extension` VARCHAR(20) NOT NULL DEFAULT '',
  `mime_type` VARCHAR(150) NOT NULL DEFAULT 'application/octet-stream',
  `file_kind` VARCHAR(20) NOT NULL DEFAULT 'other', -- image, document, video, audio, archive, code, other
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(300) DEFAULT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_user_files_name` (`file_name`),
  KEY `idx_user_files_user_created` (`user_id`, `created_at`),
  KEY `idx_user_files_user_kind` (`user_id`, `file_kind`),
  KEY `idx_user_files_created` (`created_at`),
  KEY `idx_user_files_kind` (`file_kind`),
  CONSTRAINT `fk_user_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- One row per successful user login (login trend chart).
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_logins` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_logins_created` (`created_at`),
  KEY `idx_user_logins_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_user_logins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Failed-login table is now shared by admin and user logins.
-- `scope` keeps their lockouts separate.
-- --------------------------------------------------------
ALTER TABLE `login_attempts`
  ADD COLUMN IF NOT EXISTS `scope` VARCHAR(10) NOT NULL DEFAULT 'admin' AFTER `id`;
ALTER TABLE `login_attempts`
  ADD INDEX IF NOT EXISTS `idx_scope_ip_time` (`scope`, `ip_address`, `attempted_at`),
  ADD INDEX IF NOT EXISTS `idx_attempted_at` (`attempted_at`);

-- --------------------------------------------------------
-- Indexes for fast listing / sorting with many rows
-- --------------------------------------------------------
ALTER TABLE `posts`
  ADD INDEX IF NOT EXISTS `idx_posts_created` (`created_at`, `id`),
  ADD INDEX IF NOT EXISTS `idx_posts_category_created` (`category`, `created_at`);

ALTER TABLE `comments`
  ADD INDEX IF NOT EXISTS `idx_comments_post_created` (`post_id`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_comments_created` (`created_at`);

ALTER TABLE `attachments`
  ADD INDEX IF NOT EXISTS `idx_attachments_post` (`post_id`, `id`);
