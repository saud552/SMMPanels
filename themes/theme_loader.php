<?php
// Theme Loader Helper - Version 2.0
require_once __DIR__ . '/../config.php';

function getThemeSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM theme_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return [
                'active_theme' => 'classic',
                'primary_color' => '#4f46e5',
                'secondary_color' => '#ec4899',
                'dark_mode' => 0,
                'custom_css' => ''
            ];
        }
        return $settings;
    } catch (PDOException $e) {
        return [
            'active_theme' => 'classic',
            'primary_color' => '#4f46e5',
            'secondary_color' => '#ec4899',
            'dark_mode' => 0,
            'custom_css' => ''
        ];
    }
}

function renderThemePart($part, $pdo, $data = []) {
    $theme_settings = getThemeSettings($pdo);
    $active_theme = $theme_settings['active_theme'];

    // Extract data for use in templates
    extract($data);

    $theme_path = __DIR__ . '/' . $active_theme . '/' . $part . '.php';
    $fallback_path = __DIR__ . '/classic/' . $part . '.php';

    if (file_exists($theme_path)) {
        include $theme_path;
    } elseif (file_exists($fallback_path)) {
        include $fallback_path;
    }
}

function getThemeAsset($pdo, $asset) {
    $theme_settings = getThemeSettings($pdo);
    $active_theme = $theme_settings['active_theme'];
    return "themes/{$active_theme}/{$asset}";
}
?>
