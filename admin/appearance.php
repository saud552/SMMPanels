<?php
session_start();
require_once '../config.php';
require_once '../db_updater.php'; // Include the updater for safe execution

// Check login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    return;
}

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Ensure database is aligned before any operation
updateDatabase($pdo);

$stmt = $pdo->query('SELECT * FROM theme_settings WHERE id = 1');
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

        // Validate theme id
        $theme_ids = array_column($available_themes, 'id');
        if (in_array($active_theme, $theme_ids)) {
            $stmt = $pdo->prepare('UPDATE theme_settings SET active_theme = ? WHERE id = 1');
            $stmt->execute([$active_theme]);
            $message = 'Theme activated successfully!';
            $theme_settings['active_theme'] = $active_theme;
        } else {
            $message = 'Error: Invalid theme selection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Theme Management Architecture</title>
    <link rel='stylesheet' href='assets/admin-style.css'>
    <style>
        .themes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; padding: 20px; }
        .theme-card { background: white; border-radius: 16px; overflow: hidden; border: 3px solid transparent; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .theme-card.active { border-color: #4f46e5; transform: translateY(-5px); box-shadow: 0 15px 30px rgba(79,70,229,0.15); }
        .theme-preview { height: 140px; display: flex; align-items: center; justify-content: center; font-size: 50px; color: rgba(255,255,255,0.8); }
        .theme-info { padding: 20px; }
        .theme-name { font-weight: 800; font-size: 18px; margin-bottom: 8px; color: #1e293b; }
        .theme-description { font-size: 13px; color: #64748b; margin-bottom: 20px; line-height: 1.6; }
        .btn { cursor: pointer; padding: 12px 20px; border-radius: 10px; border: none; width: 100%; font-weight: 700; transition: 0.2s; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-primary:hover { background: #4338ca; }
        .btn-success { background: #10b981; color: white; cursor: default; }
    </style>
</head>
<body>
    <div style='display:flex;'>
        <?php include 'includes/sidebar.php'; ?>
        <div style='flex:1; padding: 40px; background: #f8fafc; min-height: 100vh;'>
            <h1 style='font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 10px;'>Theme Architecture</h1>
            <p style='color: #64748b; margin-bottom: 40px;'>Select a global layout architecture for your SMM Panel.</p>

            <?php if ($message): ?>
                <div style='padding:15px 20px; border-radius: 12px; background:#dcfce7; color:#166534; margin-bottom:30px; font-weight: 600; border-left: 5px solid #22c55e;'>
                    <i class='fas fa-check-circle'></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class='themes-grid'>
                <?php foreach ($available_themes as $theme): ?>
                <div class='theme-card <?php echo ($theme['id'] == ($theme_settings['active_theme'] ?? "")) ? "active" : ""; ?>'>
                    <div class='theme-preview' style='background: <?php echo $theme['preview_color']; ?>'>
                        <i class='fas fa-swatchbook'></i>
                    </div>
                    <div class='theme-info'>
                        <div class='theme-name'><?php echo $theme['name']; ?></div>
                        <div class='theme-description'><?php echo $theme['description']; ?></div>
                        <?php if ($theme['id'] == ($theme_settings['active_theme'] ?? "")): ?>
                            <button class='btn btn-success'><i class='fas fa-check-circle'></i> Active Architecture</button>
                        <?php else: ?>
                            <form method='POST'>
                                <input type='hidden' name='activate_theme' value='<?php echo $theme['id']; ?>'>
                                <button type='submit' class='btn btn-primary'>Activate Layout</button>
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