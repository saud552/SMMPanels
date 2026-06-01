<?php
// =============================================
// services.php - صفحة عرض الخدمات للعملاء (بتصميم جديد)
// =============================================
session_start();
require_once 'config.php';

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
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($site_domain); ?> | Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7fb 0%, #f0f2f5 100%); min-height: 100vh; color: var(--dark); }

        /* Header مثل dashboard */
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--gray-200);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
        }
        .logo span { color: var(--primary); }

        .menu-btn {
            background: #f1f5f9;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .menu-btn:hover {
            background: #e2e8f0;
        }

        /* القائمة المنسدلة الرئيسية مثل dashboard */
        .main-dropdown {
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #eef2f6;
            z-index: 99;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
        }
        .main-dropdown.open {
            max-height: 500px;
        }
        .dropdown-container {
            padding: 16px 24px;
        }

        .menu-item {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .menu-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }
        .menu-item i {
            width: 24px;
            margin-right: 8px;
            color: var(--primary);
        }
        .menu-item.active {
            color: var(--primary);
            background: rgba(79,70,229,0.05);
        }

        .menu-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 8px 0;
        }

        .main { margin-top: 73px; padding: 20px; max-width: 1200px; margin-left: auto; margin-right: auto; }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 30px;
        }

        .hero h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .hero p {
            font-size: 14px;
            color: var(--gray-500);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats */
        .stats-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 12px 24px;
            text-align: center;
            border: 1px solid var(--gray-200);
            min-width: 120px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
        }
        .stat-label {
            font-size: 11px;
            color: var(--gray-500);
        }

        /* Platforms Filter */
        .platforms-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 30px;
        }

        .platform-filter-btn {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 40px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 13px;
            font-weight: 500;
        }

        .platform-filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .platform-filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-color: transparent;
            color: white;
        }

        /* Search Box */
        .search-container {
            margin-bottom: 30px;
        }

        .search-box {
            position: relative;
            max-width: 450px;
            margin: 0 auto;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 14px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 40px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79,70,229,0.1);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
        }

        .no-results i {
            font-size: 48px;
            color: var(--gray-400);
            margin-bottom: 16px;
            display: block;
        }
        .no-results h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .no-results p {
            color: var(--gray-500);
            font-size: 13px;
        }

        /* Category Section */
        .category-section {
            margin-bottom: 40px;
            display: none;
        }
        .category-section.show {
            display: block;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        .category-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .category-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        .category-badge {
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            color: var(--gray-500);
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        .service-card {
            background: white;
            border-radius: 14px;
            padding: 18px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .service-id {
            font-size: 10px;
            font-family: monospace;
            color: var(--gray-400);
            margin-bottom: 6px;
        }

        .service-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .service-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .service-price {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
        }

        .service-price small {
            font-size: 10px;
            font-weight: 400;
            color: var(--gray-400);
        }

        .service-range {
            font-size: 10px;
            color: var(--gray-400);
            background: var(--gray-50);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .service-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 12px 0;
        }

        .order-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.4);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 30px;
            border-top: 1px solid var(--gray-200);
            margin-top: 40px;
            color: var(--gray-400);
            font-size: 12px;
        }
        .footer a {
            color: var(--primary);
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .main { padding: 16px; margin-top: 70px; }
            .hero h1 { font-size: 22px; }
            .services-grid { grid-template-columns: 1fr; }
            .platforms-filter { gap: 8px; }
            .platform-filter-btn { padding: 6px 14px; font-size: 12px; }
            .stats-row { gap: 10px; }
            .stat-box { padding: 8px 16px; min-width: 100px; }
            .stat-value { font-size: 18px; }
            .dropdown-container { padding: 12px 16px; }
        }
    </style>
</head>
<body>

<!-- Header مثل dashboard -->
<div class="header">
    <a href="index.php" class="logo"><?php echo htmlspecialchars($site_domain); ?></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<!-- القائمة المنسدلة الرئيسية مثل dashboard -->
<div class="main-dropdown" id="mainDropdown">
    <div class="dropdown-container">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="menu-item"><i class="fas fa-shopping-cart"></i> New Order</a>
            <a href="orders.php" class="menu-item"><i class="fas fa-list-alt"></i> Orders</a>
            <a href="services.php" class="menu-item active"><i class="fas fa-cogs"></i> Services</a>
            <a href="addfunds.php" class="menu-item"><i class="fas fa-plus-circle"></i> Add Funds</a>
            <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
            <a href="child-panel.php" class="menu-item"><i class="fas fa-link"></i> Child Panel</a>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <div class="menu-divider"></div>
            <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="index.php" class="menu-item"><i class="fas fa-sign-in-alt"></i> Sign In</a>
            <a href="services.php" class="menu-item active"><i class="fas fa-cogs"></i> Services</a>
            <a href="blog.php" class="menu-item"><i class="fas fa-blog"></i> Blog</a>
            <a href="register.php" class="menu-item"><i class="fas fa-user-plus"></i> Sign Up</a>
            <div class="menu-divider"></div>
            <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
        <?php endif; ?>
    </div>
</div>

<div class="main">
    <!-- Hero Section -->
    <div class="hero">
        <h1>Our Services</h1>
        <p>High-quality social media marketing services to boost your online presence</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($total_services); ?></div>
            <div class="stat-label">Services</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format($total_categories); ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo number_format(count($platforms)); ?></div>
            <div class="stat-label">Platforms</div>
        </div>
    </div>

    <!-- Platforms Filter -->
    <div class="platforms-filter" id="platformsFilter">
        <a href="?platform=all" class="platform-filter-btn <?php echo $selected_platform == 'all' ? 'active' : ''; ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <?php foreach ($platforms as $platform):
            $icon_data = $platform_icons[$platform['name']] ?? ['icon' => 'fab fa-instagram', 'color' => '#4f46e5'];
        ?>
            <a href="?platform=<?php echo urlencode($platform['name']); ?>" class="platform-filter-btn <?php echo $selected_platform == $platform['name'] ? 'active' : ''; ?>">
                <i class="<?php echo $icon_data['icon']; ?>" style="color: <?php echo $selected_platform == $platform['name'] ? 'white' : $icon_data['color']; ?>"></i>
                <?php echo ucfirst($platform['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search Box -->
    <div class="search-container">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by service name or ID..." autocomplete="off">
        </div>
    </div>

    <!-- Services by Category -->
    <div id="servicesContainer">
        <?php if (empty($services_by_category)): ?>
        <div class="no-results">
            <i class="fas fa-folder-open"></i>
            <h3>No services found</h3>
            <p>Try selecting a different platform or check back later</p>
        </div>
        <?php else: ?>
            <?php foreach ($services_by_category as $category_name => $category_data): ?>
            <div class="category-section" data-category="<?php echo htmlspecialchars($category_name); ?>">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="<?php echo htmlspecialchars($category_data['icon'] ?? 'fas fa-tag'); ?>"></i>
                    </div>
                    <h2 class="category-title"><?php echo htmlspecialchars($category_name); ?></h2>
                    <span class="category-badge"><?php echo count($category_data['services']); ?> services</span>
                </div>

                <div class="services-grid">
                    <?php foreach ($category_data['services'] as $service): ?>
                    <div class="service-card" data-service-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>" data-service-id="<?php echo $service['id']; ?>">
                        <div class="service-id">ID: <?php echo $service['id']; ?></div>
                        <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                        <div class="service-details">
                            <div class="service-price">
                                $<?php echo number_format($service['price_per_1000'], 2); ?>
                                <small>/1000</small>
                            </div>
                            <div class="service-range">
                                Min: <?php echo number_format($service['min_qty']); ?> | Max: <?php echo number_format($service['max_qty']); ?>
                            </div>
                        </div>
                        <div class="service-divider"></div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="dashboard.php?service=<?php echo $service['id']; ?>" class="order-btn">
                                <i class="fas fa-cart-plus"></i> Order Now
                            </a>
                        <?php else: ?>
                            <a href="index.php" class="order-btn">
                                <i class="fas fa-sign-in-alt"></i> Login to Order
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="no-results" id="noResults" style="display: none;">
        <i class="fas fa-search"></i>
        <h3>No matching services found</h3>
        <p>Try searching with different keywords</p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_domain); ?>. All rights reserved.</p>
    </div>
</div>

<script>
    // البحث المباشر
    const searchInput = document.getElementById('searchInput');
    const categorySections = document.querySelectorAll('.category-section');
    const noResultsDiv = document.getElementById('noResults');
    let searchTimeout;

    function filterServices() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let hasVisibleServices = false;

        categorySections.forEach(section => {
            const services = section.querySelectorAll('.service-card');
            let sectionHasVisible = false;

            services.forEach(service => {
                const serviceName = service.dataset.serviceName || '';
                const serviceId = service.dataset.serviceId || '';
                const matches = serviceName.includes(searchTerm) || serviceId.includes(searchTerm);

                if (searchTerm === '') {
                    service.style.display = '';
                    sectionHasVisible = true;
                } else if (matches) {
                    service.style.display = '';
                    sectionHasVisible = true;
                } else {
                    service.style.display = 'none';
                }
            });

            if (sectionHasVisible && searchTerm !== '') {
                section.style.display = 'block';
                hasVisibleServices = true;
            } else if (searchTerm === '') {
                section.style.display = 'block';
                hasVisibleServices = true;
            } else {
                section.style.display = 'none';
            }
        });

        if (searchTerm !== '' && !hasVisibleServices) {
            noResultsDiv.style.display = 'block';
        } else {
            noResultsDiv.style.display = 'none';
        }
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterServices, 300);
    });

    // القائمة المنسدلة الرئيسية
    const menuBtn = document.getElementById('menuBtn');
    const mainDropdown = document.getElementById('mainDropdown');

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        mainDropdown.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !mainDropdown.contains(e.target)) {
            mainDropdown.classList.remove('open');
        }
    });

    // إظهار جميع الأقسام عند التحميل
    categorySections.forEach(section => {
        section.style.display = 'block';
    });
</script>
</body>
</html>