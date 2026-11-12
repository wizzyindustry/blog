-- ==============================================================
-- Database Schema and Sample Data for UDOM Student Materials Blog
-- Database Name: udom_blog
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `udom_blog` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `udom_blog`;

-- Drop existing tables if re-importing (children first)
DROP TABLE IF EXISTS `user_logins`;
DROP TABLE IF EXISTS `user_files`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `attachments`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `login_attempts`;

-- --------------------------------------------------------
-- Table structure for table `admins`
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `admins`
-- Default credentials: username = admin, password = admin123
-- --------------------------------------------------------
INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$hJ9seZVxwYhTB6MZwnMsMu4a5ZM8W30Vvttz7i/FIa0lWLEXOJ41W');

-- --------------------------------------------------------
-- Table structure for table `categories`
-- Managed by the admin in admin/categories.php.
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(300) DEFAULT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-folder',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `categories`
-- --------------------------------------------------------
INSERT INTO `categories` (`id`, `name`, `description`, `icon`) VALUES
(1, 'UDOM News', 'Official announcements, registration schedules, exam timetables and campus updates from the University of Dodoma.', 'fa-bullhorn'),
(2, 'Design', 'Notes on graphic, architectural and software design: principles, design systems, drawing and visual hierarchy.', 'fa-compass-drafting'),
(3, 'History', 'Articles on Tanzanian and East African history, the growth of education in Tanzania and the story of UDOM.', 'fa-landmark');

-- --------------------------------------------------------
-- Table structure for table `posts`
-- `category` stores the category name and is linked to categories.name:
--   ON UPDATE CASCADE  -> renaming a category updates all its posts
--   ON DELETE RESTRICT -> a category with posts cannot be deleted until
--                         its posts are moved (admin/categories.php does this)
-- --------------------------------------------------------
CREATE TABLE `posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) UNIQUE,
  `category` VARCHAR(100),
  `excerpt` TEXT,
  `content` LONGTEXT,
  `image` VARCHAR(255),
  -- How the image is framed on the site: 'auto' = its own shape, or a fixed ratio
  -- chosen by the admin (16:9, 4:3, 1:1, 4:5, 3:4, 9:16). The full image is always shown.
  `image_ratio` VARCHAR(10) NOT NULL DEFAULT 'auto',
  `image_width` INT UNSIGNED DEFAULT NULL,   -- Real pixel size, read when uploaded
  `image_height` INT UNSIGNED DEFAULT NULL,
  `allow_image_download` TINYINT(1) NOT NULL DEFAULT 0, -- 1 = show a Download Image button
  `author` VARCHAR(100) DEFAULT 'UDOM Student Materials',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_posts_category` FOREIGN KEY (`category`) REFERENCES `categories` (`name`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `posts`
-- --------------------------------------------------------
INSERT INTO `posts` (`id`, `title`, `slug`, `category`, `excerpt`, `content`, `image`, `author`, `created_at`) VALUES
(1, 'UDOM Semester Registration Guidelines and Schedule', 'udom-semester-registration-guidelines-and-schedule', 'UDOM News', 'Complete instructions for course selection, fee clearance, and verification via the student portal for the new academic semester.', '<p>The University of Dodoma (UDOM) Directorate of Undergraduate Studies has officially announced the commencement of the course registration exercise for the upcoming semester. All continuing and newly admitted students are required to complete both financial clearance and academic registration within the stipulated timeframe.</p><h3>Key Registration Deadlines</h3><p>Online course registration will remain open for three consecutive weeks. Students are strongly advised to finalize their subject selections early to prevent system congestion during the final hours of registration.</p><h3>Step-by-Step Procedure</h3><ol><li>Log in to the official UDOM Student Information System (SIS) using your designated registration number and secure password.</li><li>Verify your financial standing under the Accounts module. Ensure all mandatory institutional contributions and tuition payments are validated.</li><li>Proceed to the Academic module and select registered core and elective modules according to your departmental prospectus.</li><li>Submit your course selection for departmental approval and print three copies of your registration slip for Dean endorsement.</li></ol><p>Students encountering registration difficulties should visit their respective College ICT Help Desks or contact academic advisors promptly.</p>', 'udom-registration.svg', 'UDOM Student Materials', '2026-10-15 09:30:00'),

(2, 'Principles of Graphic and System Design for College Students', 'principles-of-graphic-and-system-design-for-college-students', 'Design', 'An analytical overview of visual balance, typography hierarchy, contrast ratios, and structural system design concepts.', '<p>Design is not merely visual aesthetics; it represents a systematic methodology for solving complex communication problems. For university students across computer science, multimedia, and engineering faculties, mastering foundational design principles is essential for creating accessible, user-friendly digital products.</p><h3>Core Principles of Visual Design</h3><p>Every effective interface or presentation rests on four fundamental tenets:</p><ul><li><strong>Contrast:</strong> Directing the user attention through intentional shifts in scale, value, and color tone.</li><li><strong>Hierarchy:</strong> Structuring information so readers instinctively grasp the order of importance, starting from primary headings down to descriptive copy.</li><li><strong>Alignment:</strong> Establishing invisible grid lines that bind disparate elements into a unified visual block.</li><li><strong>Proximity:</strong> Grouping logically related elements together to reduce cognitive load.</li></ul><h3>Systematic Consistency</h3><p>When engineering interfaces, adopting standardized token systems for spacing, typography, and color palettes ensures consistency across multiple platforms and team members.</p>', 'design-principles.svg', 'UDOM Student Materials', '2026-10-18 11:15:00'),

(3, 'The Evolution and History of Higher Education in Tanzania', 'the-evolution-and-history-of-higher-education-in-tanzania', 'History', 'Exploring the structural evolution of tertiary education in Tanzania from post-independence policy to modern research institutions.', '<p>The foundation of university education in Tanzania is deeply intertwined with national development strategies initiated after the attainment of independence in 1961. From early vocational training colleges to comprehensive research institutions like the University of Dodoma, the tertiary landscape has undergone substantial structural transformation.</p><h3>Post-Independence Policy</h3><p>In the initial post-independence period, national manpower planning necessitated the rapid training of administrators, educators, healthcare professionals, and engineers. The establishment of dedicated university colleges addressed critical personnel shortages across both public and private sectors.</p><h3>Expansion and Regional Decentralization</h3><p>In 2007, the establishment of the University of Dodoma marked a pivotal milestone in educational decentralization. Designed to accommodate up to 40,000 students across distinct specialized colleges, UDOM demonstrated a major leap toward making higher education widely accessible to students from every geographical region of Tanzania.</p>', 'history-education.svg', 'UDOM Student Materials', '2026-10-22 14:00:00'),

(4, 'Mastering Engineering Drawing and Architectural Design Concepts', 'mastering-engineering-drawing-and-architectural-design-concepts', 'Design', 'Essential drafting standards, orthographic projections, isometric views, and CAD fundamentals for engineering disciplines.', '<p>Engineering drawing serves as the universal technical language among engineers, architects, and construction supervisors. Precision in geometric representation, dimensioning conventions, and projection standards ensures that physical structures match theoretical specifications.</p><h3>Orthographic and Isometric Projections</h3><p>Understanding multi-view orthographic representation allows draftsmen to capture three-dimensional objects across horizontal, frontal, and profile planes without perceptual distortion. Isometric sketching provides an immediate spatial appreciation essential during preliminary concept reviews.</p><h3>Standard Compliance</h3><p>Adherence to International Organization for Standardization (ISO) guidelines regarding line weights, arrowheads, section lining, and tolerance annotations ensures blueprints can be interpreted universally across international construction projects.</p>', 'architectural-design.svg', 'UDOM Student Materials', '2026-10-25 08:45:00'),

(5, 'UDOM Main Campus Library Digital Resources and Research Access', 'udom-main-campus-library-digital-resources-and-research-access', 'UDOM News', 'How to access peer-reviewed journals, global academic repositories, and electronic citation databases from campus networks.', '<p>Academic research requires access to authenticated, peer-reviewed literature. The University of Dodoma Library System offers comprehensive electronic access to leading international databases, conference proceedings, and institutional dissertations.</p><h3>Available Research Repositories</h3><p>Through national consortia memberships, registered UDOM students can search and download complete papers from major publication repositories including IEEE Xplore, ScienceDirect, JSTOR, Research4Life, and SpringerLink.</p><h3>Remote Off-Campus Access</h3><p>Students conducting fieldwork or studying remotely during breaks can authenticate via the library EZproxy gateway. Using your institutional credentials, secure proxy channels provide seamless access to full-text PDF documents anywhere with an internet connection.</p>', 'library-resources.svg', 'UDOM Student Materials', '2026-10-29 16:20:00'),

(6, 'A Comprehensive History of Dodoma as the Capital City of Tanzania', 'a-comprehensive-history-of-dodoma-as-the-capital-city-of-tanzania', 'History', 'The strategic decision, urban planning milestones, and historical legacy of establishing Dodoma as the national headquarters.', '<p>In 1973, following nationwide party consultations, the government of Tanzania made the historic decision to transfer the national capital from the coastal port of Dar es Salaam to the centrally located inland city of Dodoma. This historic transition was designed to foster balanced regional socio-economic development.</p><h3>Strategic Geographic Advantage</h3><p>Dodoma central positioning within Tanzania provided direct transit links to northern, southern, western, and eastern regions. Its dry, temperate climate and vast expanses of land offered ideal physical conditions for sustainable civic planning and infrastructural expansion.</p><h3>Modern Institutional Maturation</h3><p>Over five decades of deliberate urban development, Dodoma has matured into the operational heart of Tanzanian governance, hosting Parliament (Bunge), core government ministries, military commands, and premier educational centers such as the University of Dodoma.</p>', 'dodoma-history.svg', 'UDOM Student Materials', '2026-11-01 10:10:00'),

(7, 'Essential Study Techniques for UDOM University Examination Success', 'essential-study-techniques-for-udom-university-examination-success', 'UDOM News', 'Evidence-based cognitive study habits, active recall, spaced repetition, and past paper analytical methods for university students.', '<p>University assessments demand higher-order analytical thinking rather than surface-level memorization. Achieving outstanding academic marks requires structured cognitive workflows that reinforce memory retention and problem-solving velocity.</p><h3>Active Recall versus Passive Review</h3><p>Re-reading textbooks or highlighting printed lecture notes creates an illusion of competence. In contrast, active recall—closing books and drafting summaries or testing concepts from memory—stimulates neural pathways and solidifies long-term understanding.</p><h3>Past Paper Analysis</h3><p>Reviewing past university examinations from preceding academic sessions reveals recurring inquiry themes, scoring rubrics, and time-allocation benchmarks. Formulating collaborative study cohorts to dissect complex tutorial questions builds collective competence.</p>', 'exam-techniques.svg', 'UDOM Student Materials', '2026-11-04 13:30:00'),

(8, 'Design Systems in Modern Web and Mobile Software Applications', 'design-systems-in-modern-web-and-mobile-software-applications', 'Design', 'How design tokens, accessible components, and pattern libraries accelerate scalable digital application development.', '<p>In contemporary enterprise software engineering, engineering teams and product designers rely on unified design systems to maintain visual coherence and operational efficiency across large codebases.</p><h3>Tokenization of UI Assets</h3><p>A mature design system abstracts hardcoded values into semantic tokens—such as primary brand colors, typography scales, elevation shadows, and spacing increments. Modifying a central token automatically propagates updates across web and native mobile client interfaces.</p><h3>Accessibility and Usability Standards</h3><p>Integrating Web Content Accessibility Guidelines (WCAG) standards directly into reusable component libraries ensures that color contrast, keyboard navigation, and screen reader labels comply with modern software engineering expectations.</p>', 'design-systems.svg', 'UDOM Student Materials', '2026-11-07 15:40:00');

-- The bundled sample images are 800 x 450 pixels
UPDATE `posts` SET `image_width` = 800, `image_height` = 450;

-- --------------------------------------------------------
-- Table structure for table `comments`
-- --------------------------------------------------------
CREATE TABLE `comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `comments`
-- --------------------------------------------------------
INSERT INTO `comments` (`id`, `post_id`, `name`, `comment`, `created_at`) VALUES
(1, 1, 'Baraka Mwita', 'This registration schedule clarification is very helpful. The step-by-step guidance resolved my issue with elective module verification.', '2026-10-15 14:20:00'),
(2, 1, 'Neema Joseph', 'Thank you for reminding us about the ICT Help Desk hours. The process was smooth this morning.', '2026-10-16 08:45:00'),
(3, 2, 'Kelvin Massawe', 'Visual hierarchy and alignment are fundamental concepts. Looking forward to more computer science design tutorials.', '2026-10-19 09:10:00'),
(4, 3, 'Grace Makau', 'Fascinating overview of higher education policy. The history of UDOM decentralization is inspiring.', '2026-10-23 11:30:00'),
(5, 5, 'Emmanuel Shija', 'The EZproxy remote access instructions worked smoothly. I was able to access IEEE journals from home without issues.', '2026-10-30 18:05:00'),
(6, 7, 'Zainab Rashid', 'Active recall transformed how I prepare for continuous assessment tests. Excellent academic advice.', '2026-11-05 16:40:00');

-- --------------------------------------------------------
-- Table structure for table `attachments`
-- Documents (PDF, Word, PowerPoint) attached to a post by the admin.
-- Files live in uploads/documents/ under a random name and are only
-- served through download.php. Rows are removed with their post.
-- --------------------------------------------------------
CREATE TABLE `attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,   -- File name shown to visitors
  `file_name` VARCHAR(255) NOT NULL UNIQUE, -- Random name on disk
  `file_type` VARCHAR(10) NOT NULL,        -- pdf, doc, docx, ppt, pptx
  `file_size` INT UNSIGNED NOT NULL,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `login_attempts`
-- Records failed admin logins so admin/login.php can block
-- brute-force attacks (5 failures per IP in 15 minutes).
-- --------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(100),
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- User accounts, user uploads, activity tracking and scaling indexes
-- (same as sql/upgrade_v2_users.sql, which upgrades an existing database)
-- ==============================================================


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

-- ==============================================================
-- Sharing user files on the Materials page
-- (same as sql/upgrade_v3_sharing.sql)
-- ==============================================================


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

-- ==============================================================
-- Comment replies and ownership (same as sql/upgrade_v4_comment_replies.sql)
-- ==============================================================


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

-- ==============================================================
-- Terms acceptance + compressed storage (same as sql/upgrade_v5_legal_compression.sql)
-- ==============================================================


ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `terms_accepted_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `terms_version` VARCHAR(20) DEFAULT NULL AFTER `terms_accepted_at`;

ALTER TABLE `user_files`
  ADD COLUMN IF NOT EXISTS `stored_size` BIGINT UNSIGNED DEFAULT NULL AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `is_compressed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stored_size`;
