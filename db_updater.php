<?php
require_once 'config.php';

function updateDatabase($pdo) {
    echo "Starting database realignment...\n";

    try {
        // Create table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `theme_settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // List of columns and their definitions
        $columns = [
            'active_theme' => "VARCHAR(100) NOT NULL DEFAULT 'classic'",
            'primary_color' => "VARCHAR(20) DEFAULT '#4f46e5'",
            'secondary_color' => "VARCHAR(20) DEFAULT '#ec4899'",
            'dark_mode' => "TINYINT(1) DEFAULT 0",
            'custom_css' => "TEXT",
            'updated_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        // Check each column
        foreach ($columns as $col => $definition) {
            $check = $pdo->query("SHOW COLUMNS FROM `theme_settings` LIKE '$col'");
            if ($check->rowCount() == 0) {
                echo "Adding missing column: $col...\n";
                $pdo->exec("ALTER TABLE `theme_settings` ADD `$col` $definition");
            }
        }

        // Ensure default row exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM `theme_settings` WHERE id = 1");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO `theme_settings` (id, active_theme) VALUES (1, 'classic')");
        }

        echo "✅ Database realignment completed successfully.\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ Database Error: " . $e->getMessage() . "\n";
        return false;
    }
}

if (php_sapi_name() === 'cli') {
    updateDatabase($pdo);
}
?>
