<?php
session_start();
require_once '../config.php';

// Check login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    return;
}

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Ensure table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS theme_settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    active_theme VARCHAR(100) NOT NULL DEFAULT "classic",
    primary_color VARCHAR(20) DEFAULT "#4f46e5",
    secondary_color VARCHAR(20) DEFAULT "#ec4899",
    dark_mode TINYINT(1) DEFAULT 0,
    custom_css TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$stmt = $pdo->query('SELECT COUNT(*) FROM theme_settings');
if ($stmt->fetchColumn() == 0) {
    $pdo->prepare('INSERT INTO theme_settings (active_theme) VALUES (?)')->execute(['classic']);
}

$stmt = $pdo->query('SELECT * FROM theme_settings LIMIT 1');
$theme_settings = $stmt->fetch(PDO::FETCH_ASSOC);

$available_themes = [
    ['id' => 'classic', 'name' => 'Classic Dashboard', 'description' => 'Standard professional layout, sidebar navigation, structured grid cards.', 'preview_color' => '#4f46e5'],
    ['id' => 'dark_asymmetric', 'name' => 'Dark Asymmetric', 'description' => 'Sleek dark mode, unconventional asymmetric layout, and high-contrast accents.', 'preview_color' => '#0f172a'],
    ['id' => 'premium_3d', 'name' => 'Premium 3D Metal', 'description' => 'Luxury 3D transformed cards, glassmorphism, and metallic gradients.', 'preview_color' => '#111827'],
    ['id' => 'minimalist', 'name' => 'Minimalist Flow', 'description' => 'Ultra-clean white interface, absolute minimal borders, and masonry layout.', 'preview_color' => '#ffffff'],
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['activate_theme'])) {
        $active_theme = $_POST['activate_theme'];
        $stmt = $pdo->prepare('UPDATE theme_settings SET active_theme = ? WHERE id = 1');
        $stmt->execute([$active_theme]);
        $message = 'Theme activated successfully!';
        $theme_settings['active_theme'] = $active_theme;
    }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Appearance Architecture</title>
    <link rel='stylesheet' href='assets/admin-style.css'>
    <style>
        .themes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; padding: 20px; }
        .theme-card { background: white; border-radius: 12px; overflow: hidden; border: 2px solid transparent; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .theme-card.active { border-color: #4f46e5; }
        .theme-preview { height: 100px; }
        .theme-info { padding: 15px; }
        .theme-name { font-weight: bold; margin-bottom: 5px; }
        .theme-description { font-size: 12px; color: #666; margin-bottom: 15px; }
        .btn { cursor: pointer; padding: 8px 16px; border-radius: 6px; border: none; width: 100%; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-success { background: #10b981; color: white; }
    </style>
</head>
<body>
    <div style='display:flex;'>
        <?php include 'includes/sidebar.php'; ?>
        <div style='flex:1; padding: 20px;'>
            <h1>Theme Management</h1>
            <?php if ($message): ?><div style='padding:10px; background:#dcfce7; color:#166534; margin-bottom:20px;'><?php echo $message; ?></div><?php endif; ?>
            <div class='themes-grid'>
                <?php foreach ($available_themes as $theme): ?>
                <div class='theme-card <?php echo ($theme['id'] == $theme_settings['active_theme']) ? "active" : ""; ?>'>
                    <div class='theme-preview' style='background: <?php echo $theme['preview_color']; ?>'></div>
                    <div class='theme-info'>
                        <div class='theme-name'><?php echo $theme['name']; ?></div>
                        <div class='theme-description'><?php echo $theme['description']; ?></div>
                        <?php if ($theme['id'] == $theme_settings['active_theme']): ?>
                            <button class='btn btn-success' disabled>Active</button>
                        <?php else: ?>
                            <form method='POST'>
                                <input type='hidden' name='activate_theme' value='<?php echo $theme['id']; ?>'>
                                <button type='submit' class='btn btn-primary'>Activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>