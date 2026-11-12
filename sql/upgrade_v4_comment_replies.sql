-- ==============================================================
-- Upgrade v4: replies to comments + ownership (edit / delete own comments)
--
--   parent_id   the comment this one replies to (NULL = top-level comment)
--   user_id     the student account that wrote it (NULL = guest comment)
--   updated_at  set when the author edits the comment ("edited" label)
--   deleted_at  set when the author deletes a comment that has replies:
--               the text is removed but the replies of OTHER people stay
--
-- Run as MySQL root in phpMyAdmin (the site's own account cannot ALTER tables).
-- Safe on an existing database and safe to run twice (MariaDB 10.4+ / XAMPP).
-- ==============================================================

USE `udom_blog`;

ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `parent_id` INT DEFAULT NULL AFTER `post_id`,
  ADD COLUMN IF NOT EXISTS `user_id` INT UNSIGNED DEFAULT NULL AFTER `parent_id`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT NULL AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `comments`
  ADD INDEX IF NOT EXISTS `idx_comments_parent` (`parent_id`),
  ADD INDEX IF NOT EXISTS `idx_comments_user` (`user_id`);

-- Replies disappear with their parent only when the parent row is really deleted
-- (which the site does only when nobody has replied to it).
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_parent` FOREIGN KEY IF NOT EXISTS (`parent_id`)
    REFERENCES `comments` (`id`) ON DELETE CASCADE;

-- Deleting a student account keeps their comments, shown as written by a guest
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY IF NOT EXISTS (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL;
