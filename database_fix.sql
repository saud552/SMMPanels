-- Database Schema Fix for SMMPanels Theme Management System

-- 1. Create the theme_settings table if it doesn't exist
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

-- 2. Ensure all required columns exist (for cases where table existed but was incomplete)
-- Note: We use a multi-step approach for maximum compatibility

SET @dbname = DATABASE();

-- Function to safely add columns (Handled via PHP script for easier execution in this context)

-- 3. Insert default row if missing
INSERT INTO `theme_settings` (`id`, `active_theme`, `primary_color`, `secondary_color`)
SELECT 1, 'classic', '#4f46e5', '#ec4899'
FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `theme_settings` WHERE id = 1) LIMIT 1;
