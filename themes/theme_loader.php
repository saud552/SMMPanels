<?php
// Theme Loader Helper - Version 2.2 (Fully Synchronized with database.sql)
require_once __DIR__ . '/../config.php';

/**
 * Fetches theme settings from the database.
 * Matches schema defined in database.sql
 */
function getThemeSettings($pdo) {
    $default_settings = [
        'active_theme' => 'classic',
        'primary_color' => '#4f46e5',
        'secondary_color' => '#ec4899',
        'dark_mode' => 0,
        'custom_css' => ''
    ];

    try {
        // Query the theme_settings table (Matches database.sql)
        $stmt = $pdo->query("SELECT * FROM `theme_settings` WHERE `id` = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || !is_array($settings)) {
            return $default_settings;
        }

        // Return merged settings to ensure all keys exist
        return array_merge($default_settings, $settings);

    } catch (Throwable $e) {
        // Fallback to default if table or columns are missing
        error_log("Theme Loader Error: " . $e->getMessage());
        return $default_settings;
    }
}

/**
 * Renders a theme partial (header, footer, layout, etc.)
 */
function renderThemePart($part, $pdo, $data = []) {
    $theme_settings = getThemeSettings($pdo);
    $active_theme = $theme_settings['active_theme'] ?? 'classic';

    if (empty($active_theme)) $active_theme = 'classic';

    // Extract data for local use in template files
    if (is_array($data)) {
        extract($data);
    }

    $theme_path = __DIR__ . '/' . $active_theme . '/' . $part . '.php';
    $fallback_path = __DIR__ . '/classic/' . $part . '.php';

    if (file_exists($theme_path)) {
        include $theme_path;
    } elseif (file_exists($fallback_path)) {
        include $fallback_path;
    }
}
?>
