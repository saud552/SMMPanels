<?php
class ThemeManager {
    private $pdo;
    private $active_theme;
    private $themes_path;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->themes_path = dirname(__DIR__) . '/appearance/themes/';
        $this->loadActiveTheme();
    }

    private function loadActiveTheme() {
        $stmt = $this->pdo->query("SELECT active_theme FROM theme_settings WHERE id = 1");
        $result = $stmt->fetch();
        $this->active_theme = $result ? $result['active_theme'] : 'default';
    }

    public function getActiveTheme() {
        return $this->active_theme;
    }

    public function getAllThemes() {
        $themes = [];
        if (is_dir($this->themes_path)) {
            foreach (scandir($this->themes_path) as $theme) {
                if ($theme !== '.' && $theme !== '..' && is_dir($this->themes_path . $theme)) {
                    $config_file = $this->themes_path . $theme . '/config.json';
                    if (file_exists($config_file)) {
                        $config = json_decode(file_get_contents($config_file), true);
                        $config['name'] = $theme;
                        $themes[] = $config;
                    }
                }
            }
        }
        return $themes;
    }

    public function activateTheme($theme_name) {
        $theme_path = $this->themes_path . $theme_name;
        if (!is_dir($theme_path)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE theme_settings SET active_theme = ? WHERE id = 1");
        $stmt->execute([$theme_name]);
        $this->active_theme = $theme_name;
        return true;
    }

    public function getThemeConfig($theme_name = null) {
        $theme = $theme_name ?: $this->active_theme;
        $config_file = $this->themes_path . $theme . '/config.json';

        if (file_exists($config_file)) {
            return json_decode(file_get_contents($config_file), true);
        }
        return [];
    }

    public function updateThemeConfig($theme_name, $config) {
        $config_file = $this->themes_path . $theme_name . '/config.json';
        return file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    }

    public function getThemeStyle($theme_name = null) {
        $theme = $theme_name ?: $this->active_theme;
        $style_file = $this->themes_path . $theme . '/style.css';

        if (file_exists($style_file)) {
            return file_get_contents($style_file);
        }
        return '';
    }

    public function updateThemeStyle($theme_name, $css) {
        $style_file = $this->themes_path . $theme_name . '/style.css';
        return file_put_contents($style_file, $css);
    }

    public function getCustomCSS() {
        $stmt = $this->pdo->query("SELECT custom_css FROM theme_settings WHERE id = 1");
        $result = $stmt->fetch();
        return $result ? $result['custom_css'] : '';
    }

    public function updateCustomCSS($css) {
        $stmt = $this->pdo->prepare("UPDATE theme_settings SET custom_css = ? WHERE id = 1");
        return $stmt->execute([$css]);
    }

    public function renderThemeHeader() {
        $theme = $this->active_theme;
        $template_file = $this->themes_path . $theme . '/header.php';
        if (file_exists($template_file)) {
            include $template_file;
        }
    }

    public function renderThemeFooter() {
        $theme = $this->active_theme;
        $template_file = $this->themes_path . $theme . '/footer.php';
        if (file_exists($template_file)) {
            include $template_file;
        }
    }
}
?>