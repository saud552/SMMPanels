-- SMM Panel - Theme Management System Schema
-- Single Source of Truth for Visual Architecture

-- 1. Table Structure for Theme Settings
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `active_theme` VARCHAR(100) NOT NULL DEFAULT 'classic',
    `primary_color` VARCHAR(20) DEFAULT '#4f46e5',
    `secondary_color` VARCHAR(20) DEFAULT '#ec4899',
    `dark_mode` TINYINT(1) DEFAULT 0,
    `custom_css` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Initial Configuration Data
-- Ensures the system has a valid default state immediately after creation
INSERT IGNORE INTO `theme_settings`
    (`id`, `active_theme`, `primary_color`, `secondary_color`, `dark_mode`, `custom_css`)
VALUES
    (1, 'classic', '#4f46e5', '#ec4899', 0, '');

-- 3. Verification Query
-- SELECT * FROM theme_settings WHERE id = 1;
