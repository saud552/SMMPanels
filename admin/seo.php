<?php
// =============================================
// admin/seo.php - إدارة SEO و Meta Tags للموقع
// =============================================
session_start();
require_once '../config.php';

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// =============================================
// حفظ الإعدادات
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo'])) {
    $settings = [
        'site_title' => trim($_POST['site_title']),
        'site_description' => trim($_POST['site_description']),
        'site_keywords' => trim($_POST['site_keywords']),
        'og_title' => trim($_POST['og_title']),
        'og_description' => trim($_POST['og_description']),
        'og_image' => trim($_POST['og_image']),
        'twitter_title' => trim($_POST['twitter_title']),
        'twitter_description' => trim($_POST['twitter_description']),
        'footer_text' => trim($_POST['footer_text']),
        'robots_txt' => trim($_POST['robots_txt'])
    ];

    try {
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, value, type, `group`) VALUES (?, ?, 'text', 'seo')
                                    ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $message = "SEO settings saved successfully!";
    } catch (PDOException $e) {
        $error = "Failed to save settings";
    }
}

// =============================================
// جلب الإعدادات الحالية
// =============================================
$settings = [];
$stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE `group` = 'seo' OR `group` = 'general'");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key_name']] = $row['value'];
}

// الإعدادات الافتراضية
$defaults = [
    'site_title' => 'Best SMM Panel - Cheap Social Media Services',
    'site_description' => 'Number one SMM panel in the world. Buy Instagram followers, TikTok likes, YouTube subscribers at cheapest prices with instant delivery.',
    'site_keywords' => 'SMM Panel, Instagram followers, TikTok likes, YouTube subscribers, social media marketing',
    'og_title' => 'SkyLink SMM - Best SMM Panel',
    'og_description' => 'Buy social media services at the cheapest prices. Fast delivery, 24/7 support, and money-back guarantee.',
    'og_image' => '',
    'twitter_title' => 'SkyLink SMM - Best SMM Panel',
    'twitter_description' => 'Buy social media services at the cheapest prices. Fast delivery, 24/7 support.',
    'footer_text' => 'All Rights Reserved',
    'robots_txt' => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /login.php\nSitemap: https://tigerspeed.store/sitemap.xml"
];

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO & Meta - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-600: #475569;
            --white: #ffffff;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        .admin-header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
        }
        .logo span { color: var(--primary); }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .logout-btn {
            background: #fee2e2;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .logout-btn:hover { background: #fecaca; }

        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }

        .page-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title i { color: var(--primary); }
        .page-subtitle { color: var(--gray-500); font-size: 14px; margin-bottom: 24px; }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 3px solid #ef4444; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }
        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }
        .card-title i { color: var(--primary); }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .form-group label i { margin-right: 6px; color: var(--primary); }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79,70,229,0.1);
        }
        textarea.form-control { resize: vertical; min-height: 100px; }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .preview-box {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            border: 1px solid var(--gray-200);
        }
        .preview-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-500);
            margin-bottom: 8px;
        }
        .preview-content {
            font-size: 13px;
            color: var(--dark);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 20px;
        }
        .back-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <a href="index.php" class="logo">SkyLink<span>Admin</span></a>
    <div class="user-info">
        <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin['username']); ?></span>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="page-title">
        <i class="fas fa-search"></i>
        <span>SEO & Meta Settings</span>
    </div>
    <div class="page-subtitle">Manage your website's SEO, meta tags, and social media sharing settings</div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="grid-2">
            <!-- Left Column -->
            <div>
                <!-- Basic SEO Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-chart-line"></i>
                        <span>Basic SEO</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Site Title</label>
                        <input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars($settings['site_title'] ?? $defaults['site_title']); ?>">
                        <small style="color: var(--gray-400); font-size: 11px;">Appears in browser tab and search results</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Meta Description</label>
                        <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? $defaults['site_description']); ?></textarea>
                        <small style="color: var(--gray-400); font-size: 11px;">150-160 characters recommended</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> Meta Keywords</label>
                        <textarea name="site_keywords" class="form-control" rows="2"><?php echo htmlspecialchars($settings['site_keywords'] ?? $defaults['site_keywords']); ?></textarea>
                        <small style="color: var(--gray-400); font-size: 11px;">Comma separated keywords</small>
                    </div>
                </div>

                <!-- Open Graph Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fab fa-facebook"></i>
                        <span>Open Graph (Facebook, WhatsApp, Telegram)</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> OG Title</label>
                        <input type="text" name="og_title" class="form-control" value="<?php echo htmlspecialchars($settings['og_title'] ?? $defaults['og_title']); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> OG Description</label>
                        <textarea name="og_description" class="form-control" rows="2"><?php echo htmlspecialchars($settings['og_description'] ?? $defaults['og_description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-image"></i> OG Image URL</label>
                        <input type="text" name="og_image" class="form-control" placeholder="https://yourdomain.com/og-image.jpg" value="<?php echo htmlspecialchars($settings['og_image'] ?? $defaults['og_image']); ?>">
                        <small style="color: var(--gray-400); font-size: 11px;">Recommended size: 1200x630 pixels</small>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Twitter Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fab fa-twitter"></i>
                        <span>Twitter Card</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> Twitter Title</label>
                        <input type="text" name="twitter_title" class="form-control" value="<?php echo htmlspecialchars($settings['twitter_title'] ?? $defaults['twitter_title']); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Twitter Description</label>
                        <textarea name="twitter_description" class="form-control" rows="2"><?php echo htmlspecialchars($settings['twitter_description'] ?? $defaults['twitter_description']); ?></textarea>
                    </div>
                </div>

                <!-- Footer & Robots -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-copyright"></i>
                        <span>Footer & Robots</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-copyright"></i> Footer Text</label>
                        <input type="text" name="footer_text" class="form-control" value="<?php echo htmlspecialchars($settings['footer_text'] ?? $defaults['footer_text']); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-robot"></i> robots.txt Content</label>
                        <textarea name="robots_txt" class="form-control" rows="6"><?php echo htmlspecialchars($settings['robots_txt'] ?? $defaults['robots_txt']); ?></textarea>
                        <small style="color: var(--gray-400); font-size: 11px;">This will be used to generate /robots.txt file</small>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-eye"></i>
                        <span>Preview</span>
                    </div>

                    <div class="preview-box">
                        <div class="preview-title">Search Result Preview</div>
                        <div class="preview-content">
                            <div style="color: #1a0dab; font-size: 18px; margin-bottom: 4px;"><?php echo htmlspecialchars($settings['site_title'] ?? $defaults['site_title']); ?></div>
                            <div style="color: #006621; font-size: 14px; margin-bottom: 4px;">https://<?php echo $site_domain; ?></div>
                            <div style="color: #545454; font-size: 13px;"><?php echo htmlspecialchars(substr($settings['site_description'] ?? $defaults['site_description'], 0, 150)); ?>...</div>
                        </div>
                    </div>

                    <div class="preview-box" style="margin-top: 16px;">
                        <div class="preview-title">Social Media Preview (Facebook/WhatsApp)</div>
                        <div class="preview-content">
                            <div style="background: #f2f3f5; border-radius: 12px; overflow: hidden;">
                                <?php if (!empty($settings['og_image'])): ?>
                                <div style="height: 150px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                    <i class="fas fa-image" style="font-size: 40px;"></i>
                                </div>
                                <?php endif; ?>
                                <div style="padding: 12px;">
                                    <div style="color: #606770; font-size: 12px; text-transform: uppercase;"><?php echo $site_domain; ?></div>
                                    <div style="color: #1d2129; font-size: 16px; font-weight: 600;"><?php echo htmlspecialchars($settings['og_title'] ?? $defaults['og_title']); ?></div>
                                    <div style="color: #606770; font-size: 13px; margin-top: 4px;"><?php echo htmlspecialchars(substr($settings['og_description'] ?? $defaults['og_description'], 0, 100)); ?>...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
            <button type="submit" name="save_seo" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Settings
            </button>
        </div>
    </form>
</div>

</body>
</html>