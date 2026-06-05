-- COMPLETE THEME SYSTEM SETUP & REPAIR SQL
-- Run this in your MySQL database (phpMyAdmin / Command Line)

-- 1. Create/Update theme_settings table
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `active_theme` VARCHAR(100) NOT NULL DEFAULT 'classic',
    `primary_color` VARCHAR(20) DEFAULT '#4f46e5',
    `secondary_color` VARCHAR(20) DEFAULT '#ec4899',
    `dark_mode` TINYINT(1) DEFAULT 0,
    `custom_css` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Repair missing columns if table already existed
-- We do this using standard SQL that works across most MySQL versions
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `active_theme` VARCHAR(100) NOT NULL DEFAULT 'classic' AFTER `id`;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `primary_color` VARCHAR(20) DEFAULT '#4f46e5' AFTER `active_theme`;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `secondary_color` VARCHAR(20) DEFAULT '#ec4899' AFTER `primary_color`;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `dark_mode` TINYINT(1) DEFAULT 0 AFTER `secondary_color`;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `custom_css` TEXT AFTER `dark_mode`;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `custom_css`;

-- 3. Ensure the default configuration row exists
INSERT IGNORE INTO `theme_settings` (`id`, `active_theme`, `primary_color`, `secondary_color`)
VALUES (1, 'classic', '#4f46e5', '#ec4899');

-- 4. Force reset to a valid theme if currently invalid
UPDATE `theme_settings`
SET `active_theme` = 'classic'
WHERE `active_theme` NOT IN ('classic', 'dark_asymmetric', 'premium_3d', 'minimalist')
OR `active_theme` IS NULL;

-- 5. Final check
SELECT * FROM `theme_settings` WHERE id = 1;
