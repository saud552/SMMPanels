<?php
// =============================================
// services.php - صفحة عرض الخدمات للعملاء (بتصميم جديد)
// =============================================
session_start();
require_once 'config.php';
require_once 'themes/theme_loader.php';

// جلب جميع المنصات
$platforms = $pdo->query("SELECT * FROM platforms WHERE status = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

// المنصة المختارة
$selected_platform = isset($_GET['platform']) ? $_GET['platform'] : 'all';

// جلب جميع الخدمات النشطة مع الفئات والمنصات
if ($selected_platform !== 'all') {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as category_name, c.icon as category_icon,
               p.name as platform_name, p.icon as platform_icon
        FROM services s
        JOIN categories c ON s.category_id = c.id
        JOIN platforms p ON c.platform_id = p.id
        WHERE s.status = 'active' AND c.status = 1 AND p.name = ?
        ORDER BY p.sort_order, c.sort_order, s.price_per_1000
    ");
    $stmt->execute([$selected_platform]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $services = $pdo->query("
        SELECT s.*, c.name as category_name, c.icon as category_icon,
               p.name as platform_name, p.icon as platform_icon
        FROM services s
        JOIN categories c ON s.category_id = c.id
        JOIN platforms p ON c.platform_id = p.id
        WHERE s.status = 'active' AND c.status = 1
        ORDER BY p.sort_order, c.sort_order, s.price_per_1000
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// تجميع الخدمات حسب الفئة
$services_by_category = [];
foreach ($services as $service) {
    $cat_name = $service['category_name'];
    if (!isset($services_by_category[$cat_name])) {
        $services_by_category[$cat_name] = [
            'icon' => $service['category_icon'],
            'platform' => $service['platform_name'],
            'platform_icon' => $service['platform_icon'],
            'services' => []
        ];
    }
    $services_by_category[$cat_name]['services'][] = $service;
}

// إحصائيات سريعة
$total_services = count($services);
$total_categories = count($services_by_category);

// أيقونات المنصات
$platform_icons = [
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F'],
    'tiktok' => ['icon' => 'fab fa-tiktok', 'color' => '#000000'],
    'youtube' => ['icon' => 'fab fa-youtube', 'color' => '#FF0000'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088cc'],
    'twitter' => ['icon' => 'fab fa-twitter', 'color' => '#1DA1F2'],
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2'],
];

$site_domain = $_SERVER['HTTP_HOST'];
?>

$theme_settings = getThemeSettings($pdo);
$data = [
    'user' => $user ?? null,
    'site_title' => 'Services | SkyLink',
    'theme_settings' => $theme_settings
];

ob_start();
?>
<div class='services-content'>
    <h1>Services</h1>
    <div class='card'>
        <p>This is the Services page. Full content integration in progress.</p>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>