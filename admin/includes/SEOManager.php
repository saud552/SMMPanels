<?php
class SEOManager {
    private $pdo;
    private $settings;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        // التأكد من وجود الجدول
        $this->ensureTableExists();

        $stmt = $this->pdo->query("SELECT * FROM site_settings WHERE id = 1");
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->settings) {
            $this->createDefaultSettings();
        }
    }

    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS `site_settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `site_name` VARCHAR(255) NOT NULL DEFAULT 'SMM Panel',
            `site_description` TEXT,
            `site_keywords` TEXT,
            `site_author` VARCHAR(255) DEFAULT 'SkyLink',
            `site_logo` VARCHAR(500),
            `site_favicon` VARCHAR(500),
            `og_image` VARCHAR(500),
            `google_analytics` VARCHAR(100),
            `facebook_pixel` VARCHAR(100),
            `custom_header_code` TEXT,
            `custom_footer_code` TEXT,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    private function createDefaultSettings() {
        $stmt = $this->pdo->prepare("INSERT INTO site_settings (site_name, site_description, site_keywords) VALUES (?, ?, ?)");
        $stmt->execute(['SkyLink SMM', 'Best SMM Panel Provider', 'smm,panel,social media,instagram,tiktok,youtube']);
        $this->loadSettings();
    }

    public function get($key) {
        return $this->settings[$key] ?? '';
    }

    public function getAll() {
        return $this->settings;
    }

    public function update($data) {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['site_name', 'site_description', 'site_keywords', 'site_author',
                                 'site_logo', 'site_favicon', 'og_image', 'google_analytics',
                                 'facebook_pixel', 'custom_header_code', 'custom_footer_code'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE site_settings SET " . implode(', ', $fields) . " WHERE id = 1";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($values);
            if ($result) {
                $this->loadSettings();
            }
            return $result;
        }
        return false;
    }

    public function renderMetaTags() {
        ?>
        <title><?php echo htmlspecialchars($this->get('site_name')); ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="<?php echo htmlspecialchars($this->get('site_description')); ?>">
        <meta name="keywords" content="<?php echo htmlspecialchars($this->get('site_keywords')); ?>">
        <meta name="author" content="<?php echo htmlspecialchars($this->get('site_author')); ?>">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
        <meta property="og:title" content="<?php echo htmlspecialchars($this->get('site_name')); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($this->get('site_description')); ?>">
        <?php if ($this->get('og_image')): ?>
        <meta property="og:image" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $this->get('og_image'); ?>">
        <?php endif; ?>

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
        <meta property="twitter:title" content="<?php echo htmlspecialchars($this->get('site_name')); ?>">
        <meta property="twitter:description" content="<?php echo htmlspecialchars($this->get('site_description')); ?>">
        <?php if ($this->get('og_image')): ?>
        <meta property="twitter:image" content="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/' . $this->get('og_image'); ?>">
        <?php endif; ?>

        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">

        <!-- Favicon -->
        <?php if ($this->get('site_favicon')): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo $this->get('site_favicon'); ?>">
        <?php endif; ?>

        <?php if ($this->get('google_analytics')): ?>
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $this->get('google_analytics'); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo $this->get('google_analytics'); ?>');
        </script>
        <?php endif; ?>

        <?php if ($this->get('facebook_pixel')): ?>
        <!-- Facebook Pixel -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo $this->get('facebook_pixel'); ?>');
            fbq('track', 'PageView');
        </script>
        <?php endif; ?>

        <?php if ($this->get('custom_header_code')): ?>
        <!-- Custom Header Code -->
        <?php echo $this->get('custom_header_code'); ?>
        <?php endif; ?>
        <?php
    }

    public function renderFooterCode() {
        if ($this->get('custom_footer_code')) {
            echo $this->get('custom_footer_code');
        }
    }
}
?>