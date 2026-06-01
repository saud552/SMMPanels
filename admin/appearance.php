<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// التأكد من وجود جدول theme_settings
$pdo->exec("CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `active_theme` VARCHAR(100) NOT NULL DEFAULT 'default',
    `primary_color` VARCHAR(20) DEFAULT '#4f46e5',
    `secondary_color` VARCHAR(20) DEFAULT '#ec4899',
    `dark_mode` TINYINT(1) DEFAULT 0,
    `custom_css` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// التأكد من وجود سجل
$stmt = $pdo->query("SELECT COUNT(*) FROM theme_settings");
if ($stmt->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO theme_settings (active_theme, primary_color, secondary_color) VALUES (?, ?, ?)")->execute(['default', '#4f46e5', '#ec4899']);
}

// جلب الإعدادات - المفتاح: نستخدم fetch() بدلاً من fetchColumn()
$stmt = $pdo->query("SELECT * FROM theme_settings LIMIT 1");
$theme_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// إذا لم يتم العثور على إعدادات، ننشئ مصفوفة افتراضية
if (!$theme_settings) {
    $theme_settings = [
        'active_theme' => 'default',
        'primary_color' => '#4f46e5',
        'secondary_color' => '#ec4899',
        'dark_mode' => 0,
        'custom_css' => ''
    ];
}

// الثيمات المتاحة
$available_themes = [
    ['id' => 'default', 'name' => 'Default Theme', 'description' => 'Clean and modern design', 'preview_color' => '#4f46e5'],
    ['id' => 'dark', 'name' => 'Dark Theme', 'description' => 'Dark mode for night browsing', 'preview_color' => '#1e293b'],
    ['id' => 'neon', 'name' => 'Neon Theme', 'description' => 'Vibrant neon colors', 'preview_color' => '#00f2fe'],
    ['id' => 'minimal', 'name' => 'Minimal Theme', 'description' => 'Simple and elegant', 'preview_color' => '#f59e0b'],
];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['activate_theme'])) {
        $active_theme = $_POST['active_theme'];
        $stmt = $pdo->prepare("UPDATE theme_settings SET active_theme = ? WHERE id = 1");
        $stmt->execute([$active_theme]);
        $message = "Theme activated successfully!";
        $message_type = "success";

        // تحديث المتغير
        $theme_settings['active_theme'] = $active_theme;
    }

    if (isset($_POST['save_theme_settings'])) {
        $primary_color = $_POST['primary_color'];
        $secondary_color = $_POST['secondary_color'];
        $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE theme_settings SET primary_color = ?, secondary_color = ?, dark_mode = ? WHERE id = 1");
        $stmt->execute([$primary_color, $secondary_color, $dark_mode]);

        $theme_settings['primary_color'] = $primary_color;
        $theme_settings['secondary_color'] = $secondary_color;
        $theme_settings['dark_mode'] = $dark_mode;

        $message = "Theme colors saved!";
        $message_type = "success";
    }

    if (isset($_POST['save_custom_css'])) {
        $custom_css = $_POST['custom_css'];
        $stmt = $pdo->prepare("UPDATE theme_settings SET custom_css = ? WHERE id = 1");
        $stmt->execute([$custom_css]);
        $theme_settings['custom_css'] = $custom_css;
        $message = "Custom CSS saved!";
        $message_type = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appearance - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        .CodeMirror {
            height: 400px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-input-group input[type="color"] {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
        }

        .color-input-group input[type="text"] {
            flex: 1;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">
        <div class="admin-header">
            <h1><i class="fas fa-palette"></i> Appearance & Themes</h1>
            <div class="admin-user">
                <i class="fas fa-bell"></i>
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Themes Grid -->
        <div class="themes-grid">
            <?php foreach ($available_themes as $theme): ?>
            <div class="theme-card <?php echo ($theme['id'] == $theme_settings['active_theme']) ? 'active' : ''; ?>">
                <div class="theme-preview" style="background: <?php echo $theme['preview_color']; ?>;">
                    <i class="fas fa-paintbrush"></i>
                </div>
                <div class="theme-info">
                    <div class="theme-name"><?php echo $theme['name']; ?></div>
                    <div class="theme-description"><?php echo $theme['description']; ?></div>
                    <?php if ($theme['id'] == $theme_settings['active_theme']): ?>
                    <span class="badge badge-active"><i class="fas fa-check"></i> Active</span>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="active_theme" value="<?php echo $theme['id']; ?>">
                        <button type="submit" name="activate_theme" class="btn btn-primary btn-sm" style="width: 100%; margin-top: 10px;">
                            Activate
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Color Customization -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-fill-drip"></i> Color Customization</h3>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-input-group">
                            <input type="color" name="primary_color" value="<?php echo $theme_settings['primary_color']; ?>">
                            <input type="text" class="form-control" value="<?php echo $theme_settings['primary_color']; ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Secondary Color</label>
                        <div class="color-input-group">
                            <input type="color" name="secondary_color" value="<?php echo $theme_settings['secondary_color']; ?>">
                            <input type="text" class="form-control" value="<?php echo $theme_settings['secondary_color']; ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="dark_mode" value="1" <?php echo $theme_settings['dark_mode'] ? 'checked' : ''; ?>>
                        Enable Dark Mode
                    </label>
                </div>
                <button type="submit" name="save_theme_settings" class="btn btn-primary">Save Colors</button>
            </form>
        </div>

        <!-- Custom CSS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fab fa-css3"></i> Custom CSS</h3>
            </div>
            <form method="POST">
                <textarea name="custom_css" id="cssEditor"><?php echo htmlspecialchars($theme_settings['custom_css'] ?? ''); ?></textarea>
                <button type="submit" name="save_custom_css" class="btn btn-primary" style="margin-top: 16px;">Save CSS</button>
            </form>
        </div>

        <!-- Live Preview -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-eye"></i> Live Preview</h3>
                <button class="btn btn-secondary btn-sm" onclick="refreshPreview()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            <iframe src="../index.php" class="live-preview-frame" id="livePreviewFrame"></iframe>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
<script>
    // CSS Editor
    var cssEditor = document.getElementById('cssEditor');
    if (cssEditor) {
        CodeMirror.fromTextArea(cssEditor, {
            lineNumbers: true,
            mode: 'css',
            theme: 'default',
            extraKeys: { 'Ctrl-Space': 'autocomplete' }
        });
    }

    function refreshPreview() {
        var iframe = document.getElementById('livePreviewFrame');
        if (iframe) {
            iframe.src = iframe.src;
        }
    }
</script>
</body>
</html>