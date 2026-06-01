<?php
// =============================================
// installSQL.php - تثبيت جميع جداول قاعدة البيانات
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html dir='ltr' lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Installation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: monospace; background: #0f172a; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 30px; color: #e2e8f0; }
        h1 { color: #4f46e5; margin-bottom: 20px; font-size: 24px; }
        h2 { color: #10b981; margin: 20px 0 10px; font-size: 18px; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #f59e0b; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; margin: 10px 0; font-size: 12px; }
        hr { border-color: #334155; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Database Installation</h1>
";

require_once 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'>Connected to database successfully</div>";

    // =============================================
    // 1. جدول المستخدمين (users)
    // =============================================
    echo "<h2>Creating table: users</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `password` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(255) DEFAULT NULL,
        `balance` DECIMAL(10,2) DEFAULT 0.00,
        `api_key` VARCHAR(255) DEFAULT NULL,
        `api_key_created_at` DATETIME DEFAULT NULL,
        `status` TINYINT(1) DEFAULT 1,
        `last_login` DATETIME DEFAULT NULL,
        `last_ip` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'users' created successfully</div>";

    // =============================================
    // 2. جدول المشرفين (admin_users)
    // =============================================
    echo "<h2>Creating table: admin_users</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `role` VARCHAR(50) DEFAULT 'admin',
        `last_login` DATETIME DEFAULT NULL,
        `last_ip` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'admin_users' created successfully</div>";

    // =============================================
    // 3. جدول المنصات (platforms)
    // =============================================
    echo "<h2>Creating table: platforms</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `platforms` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `icon` VARCHAR(100) DEFAULT NULL,
        `sort_order` INT(11) DEFAULT 0,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'platforms' created successfully</div>";

    // إضافة المنصات الافتراضية
    $check = $pdo->query("SELECT COUNT(*) FROM platforms")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("
            INSERT INTO `platforms` (`name`, `sort_order`) VALUES
            ('instagram', 1),
            ('tiktok', 2),
            ('youtube', 3),
            ('telegram', 4),
            ('twitter', 5),
            ('facebook', 6)
        ");
        echo "<div class='success'>Default platforms inserted</div>";
    }

    // =============================================
    // 4. جدول الفئات (categories)
    // =============================================
    echo "<h2>Creating table: categories</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `platform_id` INT(11) NOT NULL,
        `icon` VARCHAR(100) DEFAULT NULL,
        `sort_order` INT(11) DEFAULT 0,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `platform_id` (`platform_id`),
        FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'categories' created successfully</div>";

    // =============================================
    // 5. جدول الخدمات (services)
    // =============================================
    echo "<h2>Creating table: services</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `services` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `category_id` INT(11) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `min_qty` INT(11) DEFAULT 100,
        `max_qty` INT(11) DEFAULT 10000,
        `price_per_1000` DECIMAL(10,4) NOT NULL,
        `provider_id` INT(11) DEFAULT NULL,
        `api_service_id` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `sort_order` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `category_id` (`category_id`),
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'services' created successfully</div>";

    // =============================================
    // 6. جدول الطلبات (orders)
    // =============================================
    echo "<h2>Creating table: orders</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `service_id` INT(11) NOT NULL,
        `link` TEXT NOT NULL,
        `quantity` INT(11) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL,
        `status` ENUM('pending', 'processing', 'in_progress', 'completed', 'partial', 'failed', 'cancelled') DEFAULT 'pending',
        `api_order_id` VARCHAR(255) DEFAULT NULL,
        `api_response` TEXT DEFAULT NULL,
        `api_error` TEXT DEFAULT NULL,
        `start_counter` INT(11) DEFAULT 0,
        `remains` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `service_id` (`service_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'orders' created successfully</div>";

    // =============================================
    // 7. جدول الإيداعات (deposits)
    // =============================================
    echo "<h2>Creating table: deposits</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `deposits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `method` VARCHAR(50) NOT NULL,
        `invoice_id` VARCHAR(255) DEFAULT NULL,
        `payment_url` TEXT DEFAULT NULL,
        `status` ENUM('pending', 'paid', 'expired', 'failed') DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'deposits' created successfully</div>";

    // =============================================
    // 8. جدول مزودي API (api_providers)
    // =============================================
    echo "<h2>Creating table: api_providers</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `api_providers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `api_url` VARCHAR(255) NOT NULL,
        `api_key` VARCHAR(255) NOT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'api_providers' created successfully</div>";

    // =============================================
    // 9. جدول العملات (currencies)
    // =============================================
    echo "<h2>Creating table: currencies</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `currencies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(10) NOT NULL,
        `name` VARCHAR(50) NOT NULL,
        `symbol` VARCHAR(10) NOT NULL,
        `rate` DECIMAL(10,4) NOT NULL,
        `status` TINYINT(1) DEFAULT 1,
        `sort_order` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'currencies' created successfully</div>";

    // إضافة العملات الافتراضية
    $check = $pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("
            INSERT INTO `currencies` (`code`, `name`, `symbol`, `rate`, `sort_order`) VALUES
            ('USD', 'US Dollar', '$', 1.0000, 0),
            ('SAR', 'Saudi Riyal', '﷼', 3.7500, 1),
            ('AED', 'UAE Dirham', 'د.إ', 3.6725, 2),
            ('YER_OLD', 'Yemeni Riyal (Old)', '﷼', 540.0000, 3),
            ('YER_NEW', 'Yemeni Riyal (New)', '﷼', 1600.0000, 4)
        ");
        echo "<div class='success'>Default currencies inserted</div>";
    }

    // =============================================
    // 10. جدول المقالات (blog_posts)
    // =============================================
    echo "<h2>Creating table: blog_posts</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `blog_posts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `content` LONGTEXT NOT NULL,
        `excerpt` TEXT DEFAULT NULL,
        `featured_image` VARCHAR(500) DEFAULT NULL,
        `author_id` INT(11) NOT NULL,
        `status` ENUM('draft', 'published') DEFAULT 'draft',
        `views` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`),
        KEY `author_id` (`author_id`),
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'blog_posts' created successfully</div>";

    // =============================================
    // 11. جدول إعدادات الموقع (settings)
    // =============================================
    echo "<h2>Creating table: settings</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `key_name` VARCHAR(100) NOT NULL,
        `value` TEXT DEFAULT NULL,
        `type` ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        `group` VARCHAR(50) DEFAULT 'general',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key_name` (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'settings' created successfully</div>";

    // إضافة الإعدادات الافتراضية
    $check = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("
            INSERT INTO `settings` (`key_name`, `value`, `type`, `group`) VALUES
            ('site_title', 'Best SMM Panel - Cheap Social Media Services', 'text', 'seo'),
            ('site_description', 'Number one SMM panel in the world. Buy Instagram followers, TikTok likes, YouTube subscribers at cheapest prices with instant delivery.', 'text', 'seo'),
            ('site_keywords', 'SMM Panel, Instagram followers, TikTok likes, YouTube subscribers, social media marketing', 'text', 'seo'),
            ('og_title', 'SMM Panel - Best SMM Panel', 'text', 'seo'),
            ('og_description', 'Buy social media services at the cheapest prices. Fast delivery, 24/7 support.', 'text', 'seo'),
            ('footer_text', 'All Rights Reserved', 'text', 'general'),
            ('child_panel_price', '5.00', 'number', 'payment'),
            ('child_panel_service', 'active', 'text', 'general')
        ");
        echo "<div class='success'>Default settings inserted</div>";
    }

    // =============================================
    // 12. جدول البانلات الفرعية (child_panels)
    // =============================================
    echo "<h2>Creating table: child_panels</h2>";
    $sql = "
    CREATE TABLE IF NOT EXISTS `child_panels` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `domain` VARCHAR(255) NOT NULL,
        `admin_username` VARCHAR(100) DEFAULT 'admin',
        `admin_password` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
        `price` DECIMAL(10,2) DEFAULT 5.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `expiry_date` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
    echo "<div class='success'>Table 'child_panels' created successfully</div>";

    // =============================================
    // ملخص التثبيت
    // =============================================
    echo "<hr>";
    echo "<h2>Installation Summary</h2>";
    echo "<div class='success'>All tables have been created successfully</div>";
    echo "<div class='info'>Total tables: 12</div>";
    echo "<div class='info'>Tables created: users, admin_users, platforms, categories, services, orders, deposits, api_providers, currencies, blog_posts, settings, child_panels</div>";
    echo "<div class='warning'>Please delete this file (installSQL.php) after installation for security reasons.</div>";

} catch (PDOException $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>

</div>
</body>
</html>