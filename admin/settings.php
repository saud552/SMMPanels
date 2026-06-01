<?php
// =============================================
// admin/settings.php - إدارة إعدادات الموقع (SEO)
// =============================================

session_start();
require_once '../config.php';

// التحقق من تسجيل دخول المشرف
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    $success = "Settings saved successfully!";
}

// جلب جميع الإعدادات
$stmt = $pdo->query("SELECT * FROM site_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 30px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #1e293b; }
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #4f46e5; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .btn { padding: 12px 24px; background: #4f46e5; color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #4338ca; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #4f46e5; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>

        <div class="card">
            <div class="card-title">SEO & Site Settings</div>

            <?php if ($success): ?>
            <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Site Title (SEO)</label>
                    <input type="text" name="settings[site_title]" class="form-control" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Best SMM Panel'); ?>">
                </div>

                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="settings[site_description]" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Meta Keywords</label>
                    <textarea name="settings[site_keywords]" class="form-control" rows="2"><?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>OG Title (Facebook/WhatsApp Share)</label>
                    <input type="text" name="settings[og_title]" class="form-control" value="<?php echo htmlspecialchars($settings['og_title'] ?? 'SkyLink SMM - Best SMM Panel'); ?>">
                </div>

                <div class="form-group">
                    <label>OG Description</label>
                    <textarea name="settings[og_description]" class="form-control" rows="2"><?php echo htmlspecialchars($settings['og_description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Footer Text</label>
                    <input type="text" name="settings[footer_text]" class="form-control" value="<?php echo htmlspecialchars($settings['footer_text'] ?? 'All Rights Reserved'); ?>">
                </div>

                <button type="submit" class="btn">Save Settings</button>
            </form>
        </div>
    </div>
</body>
</html>