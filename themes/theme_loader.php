<?php
// Theme Loader Helper - Version 2.1 (Defensive & Robust)
require_once __DIR__ . '/../config.php';

/**
 * Robustly fetch theme settings with safe defaults.
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
        // Safe check for table existence before querying
        $stmt = $pdo->query("SHOW TABLES LIKE 'theme_settings'");
        if ($stmt->rowCount() == 0) {
            return $default_settings;
        }

        $stmt = $pdo->query("SELECT * FROM theme_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || !is_array($settings)) {
            return $default_settings;
        }

        // Merge with defaults to handle missing columns
        return array_merge($default_settings, $settings);

    } catch (Throwable $e) {
        error_log("Theme System Error: " . $e->getMessage());
        return $default_settings;
    }
}

/**
 * Renders a theme partial with guaranteed fallback.
 */
function renderThemePart($part, $pdo, $data = []) {
    $theme_settings = getThemeSettings($pdo);
    $active_theme = $theme_settings['active_theme'] ?? 'classic';

    // Safety check for active_theme value
    if (empty($active_theme)) $active_theme = 'classic';

    // Extract data for use in templates
    if (is_array($data)) {
        extract($data);
    }

    $theme_path = __DIR__ . '/' . $active_theme . '/' . $part . '.php';
    $fallback_path = __DIR__ . '/classic/' . $part . '.php';

    if (file_exists($theme_path)) {
        include $theme_path;
    } elseif (file_exists($fallback_path)) {
        include $fallback_path;
    } else {
        error_log("Theme Error: Missing partial [$part] in both active and fallback themes.");
    }
}

/**
 * Returns a theme asset path safely.
 */
function getThemeAsset($pdo, $asset) {
    $theme_settings = getThemeSettings($pdo);
    $active_theme = $theme_settings['active_theme'] ?? 'classic';
    if (empty($active_theme)) $active_theme = 'classic';

    return "themes/{$active_theme}/{$asset}";
}
?>
