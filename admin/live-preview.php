<?php
require_once '../config.php';
require_once 'includes/SEOManager.php';
require_once 'includes/ThemeManager.php';

session_start();

$seo = new SEOManager($pdo);
$theme = new ThemeManager($pdo);

$active_theme = $theme->getActiveTheme();
$custom_css = $theme->getCustomCSS();
$theme_css = $theme->getThemeStyle();
$theme_config = $theme->getThemeConfig();

// دالة للحصول على النص المخصص من قاعدة البيانات
function getSiteText($key, $default = '', $page = 'global') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM site_texts WHERE `key` = ? AND `page` = ?");
    $stmt->execute([$key, $page]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Preview - <?php echo htmlspecialchars($seo->get('site_name')); ?></title>

    <!-- Theme Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=<?php echo $theme_config['fonts']['heading'] ?? 'Inter'; ?>:wght@400;500;600;700&family=<?php echo $theme_config['fonts']['body'] ?? 'Inter'; ?>:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Theme Styles -->
    <style>
        /* Theme CSS */
        <?php echo $theme_css; ?>

        /* Custom CSS */
        <?php echo $custom_css; ?>

        /* Live Preview Overrides */
        :root {
            --preview-primary: <?php echo $theme_config['colors']['primary'] ?? '#4f46e5'; ?>;
            --preview-secondary: <?php echo $theme_config['colors']['secondary'] ?? '#ec4899'; ?>;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .live-preview-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10000;
            font-size: 13px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .live-preview-content {
            margin-top: 60px;
        }

        .preview-badge {
            background: #4f46e5;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .close-preview {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 20px;
            cursor: pointer;
        }

        .close-preview:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="live-preview-toolbar">
        <div>
            <i class="fas fa-eye"></i> Live Preview Mode
            <span class="preview-badge">Theme: <?php echo ucfirst($active_theme); ?></span>
        </div>
        <div>
            <button class="close-preview" onclick="window.close()">
                <i class="fas fa-times"></i> Close Preview
            </button>
        </div>
    </div>

    <div class="live-preview-content">
        <?php
        // عرض رأس الثيم
        $theme->renderThemeHeader();
        ?>

        <!-- Hero Section Preview -->
        <section class="hero-section" style="padding: 80px 0; text-align: center; background: linear-gradient(135deg, var(--preview-primary), var(--preview-secondary)); color: white;">
            <div class="container">
                <h1 style="font-size: 48px; margin-bottom: 20px;"><?php echo getSiteText('hero_title', 'Welcome to SkyLink SMM', 'index'); ?></h1>
                <p style="font-size: 20px; margin-bottom: 30px; opacity: 0.9;"><?php echo getSiteText('hero_subtitle', 'Best SMM Panel Provider', 'index'); ?></p>
                <button class="btn btn-primary" style="background: white; color: var(--preview-primary);">
                    <?php echo getSiteText('hero_button_text', 'Get Started', 'index'); ?>
                </button>
            </div>
        </section>

        <!-- Services Preview -->
        <section class="services-section" style="padding: 60px 0; background: #f8fafc;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 40px;">Our Services</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                    <?php for($i = 1; $i <= 3; $i++): ?>
                    <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fab fa-instagram" style="font-size: 48px; color: var(--preview-primary); margin-bottom: 15px;"></i>
                        <h3>Service <?php echo $i; ?></h3>
                        <p style="color: #64748b; margin: 15px 0;">High quality social media services</p>
                        <button class="btn btn-primary">Order Now</button>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- Footer Preview -->
        <?php $theme->renderThemeFooter(); ?>
    </div>

    <script>
        // Listen for color updates from parent window
        window.addEventListener('message', function(event) {
            if (event.data.type === 'updateColors') {
                const colors = event.data.colors;
                document.documentElement.style.setProperty('--preview-primary', colors.primary);
                document.documentElement.style.setProperty('--preview-secondary', colors.secondary);

                // Update hero section gradient
                const heroSection = document.querySelector('.hero-section');
                if (heroSection) {
                    heroSection.style.background = `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`;
                }

                // Update primary buttons
                document.querySelectorAll('.btn-primary').forEach(btn => {
                    if (btn.closest('.hero-section')) {
                        btn.style.background = 'white';
                        btn.style.color = colors.primary;
                    } else {
                        btn.style.background = colors.primary;
                    }
                });
            }
        });

        // Send ready message to parent
        window.parent.postMessage({ type: 'previewReady' }, '*');
    </script>
</body>
</html>