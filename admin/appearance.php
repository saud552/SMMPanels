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

// Fetch settings from theme_settings table (Matches database.sql)
$stmt = $pdo->query('SELECT * FROM theme_settings WHERE id = 1');
$theme_settings = $stmt->fetch(PDO::FETCH_ASSOC);

$available_themes = [
    ['id' => 'classic', 'name' => 'Classic Dashboard', 'description' => 'Standard professional layout, sidebar navigation, structured grid cards.', 'preview_color' => '#4f46e5'],
    ['id' => 'dark_asymmetric', 'name' => 'Dark Asymmetric', 'description' => 'Sleek dark mode, unconventional asymmetric layout, and high-contrast accents.', 'preview_color' => '#0f172a'],
    ['id' => 'premium_3d', 'name' => 'Premium 3D Metal', 'description' => 'Luxury 3D transformed cards, glassmorphism, and metallic gradients.', 'preview_color' => '#111827'],
    ['id' => 'minimalist', 'name' => 'Minimalist Flow', 'description' => 'Ultra-clean white interface, absolute minimal borders, and masonry layout.', 'preview_color' => '#ffffff'],
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_theme'])) {
    $active_theme = $_POST['activate_theme'];

    // Safety check against available themes
    if (in_array($active_theme, array_column($available_themes, 'id'))) {
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
    <title>Theme Management - Admin</title>
    <link rel='stylesheet' href='assets/admin-style.css'>
    <style>
        .themes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; padding: 20px; }
        .theme-card { background: white; border-radius: 16px; overflow: hidden; border: 3px solid transparent; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .theme-card.active { border-color: #4f46e5; }
        .theme-preview { height: 120px; }
        .theme-info { padding: 20px; }
        .btn { padding: 10px; width: 100%; cursor: pointer; border-radius: 8px; border: none; font-weight: bold; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-success { background: #10b981; color: white; }
    </style>
</head>
<body>
    <div style='display:flex;'>
        <?php include 'includes/sidebar.php'; ?>
        <div style='flex:1; padding: 40px;'>
            <h1>Theme Architecture</h1>
            <?php if ($message): ?><div style='background:#dcfce7; color:#166534; padding:15px; margin-bottom:20px;'><?php echo $message; ?></div><?php endif; ?>
            <div class='themes-grid'>
                <?php foreach ($available_themes as $theme): ?>
                <div class='theme-card <?php echo ($theme['id'] == ($theme_settings['active_theme'] ?? "")) ? "active" : ""; ?>'>
                    <div class='theme-preview' style='background: <?php echo $theme['preview_color']; ?>'></div>
                    <div class='theme-info'>
                        <h3><?php echo $theme['name']; ?></h3>
                        <p style='font-size: 13px; color: #666; margin-bottom: 15px;'><?php echo $theme['description']; ?></p>
                        <?php if ($theme['id'] == ($theme_settings['active_theme'] ?? "")): ?>
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