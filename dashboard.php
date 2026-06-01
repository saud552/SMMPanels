<?php
// =============================================
// dashboard.php - SMM Panel Premium (نسخة محسنة بالكامل)
// =============================================
session_start();
require_once 'config.php';
require_once 'includes/ApiProvider.php';
require_once 'includes/OrderProcessor.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['balance'] = $user['balance'];
$_SESSION['username'] = $user['username'];

// =============================================
// نظام العملات
// =============================================

try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `currencies` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `code` VARCHAR(10) NOT NULL,
        `name` VARCHAR(50) NOT NULL,
        `symbol` VARCHAR(10) NOT NULL,
        `rate` DECIMAL(10,4) NOT NULL,
        `status` TINYINT(1) DEFAULT 1,
        `sort_order` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM currencies");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $default_currencies = [
            ['USD', 'US Dollar', '$', 1.0000, 1, 0],
            ['SAR', 'Saudi Riyal', '﷼', 3.7500, 1, 1],
            ['AED', 'UAE Dirham', 'د.إ', 3.6725, 1, 2],
            ['YER_OLD', 'Yemeni Riyal (Old)', '﷼', 540.0000, 1, 3],
            ['YER_NEW', 'Yemeni Riyal (New)', '﷼', 1600.0000, 1, 4]
        ];
        $insert = $pdo->prepare("INSERT INTO currencies (code, name, symbol, rate, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($default_currencies as $cur) {
            $insert->execute($cur);
        }
    }
} catch (PDOException $e) {}

$stmt = $pdo->prepare("SELECT * FROM currencies WHERE status = 1 ORDER BY sort_order");
$stmt->execute();
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تغيير العملة عبر AJAX
if (isset($_GET['change_currency_ajax'])) {
    header('Content-Type: application/json');
    $currency_code = $_GET['change_currency_ajax'];
    $stmt = $pdo->prepare("SELECT * FROM currencies WHERE code = ? AND status = 1");
    $stmt->execute([$currency_code]);
    $currency = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($currency) {
        $_SESSION['selected_currency'] = $currency_code;
        setcookie('selected_currency', $currency_code, time() + (86400 * 30), "/");
        echo json_encode(['success' => true, 'currency' => $currency]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if (!isset($_SESSION['selected_currency']) && isset($_COOKIE['selected_currency'])) {
    $_SESSION['selected_currency'] = $_COOKIE['selected_currency'];
}
if (!isset($_SESSION['selected_currency'])) {
    $_SESSION['selected_currency'] = 'USD';
}

$current_currency = null;
foreach ($currencies as $cur) {
    if ($cur['code'] == $_SESSION['selected_currency']) {
        $current_currency = $cur;
        break;
    }
}
if (!$current_currency) {
    $current_currency = $currencies[0];
    $_SESSION['selected_currency'] = $current_currency['code'];
}

function convertPrice($usd_price, $rate) {
    return round($usd_price * $rate, 2);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$site_domain = $_SERVER['HTTP_HOST'];

// =============================================
// معالجة AJAX
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'get_categories') {
        $platform_name = $_POST['platform'] ?? 'everything';

        $sql = "SELECT c.*, p.name as platform_name
                FROM categories c
                JOIN platforms p ON c.platform_id = p.id
                WHERE c.status = 1";

        if ($platform_name !== 'everything') {
            $sql .= " AND p.name = ?";
            $params = [$platform_name];
        } else {
            $params = [];
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }

    if ($action === 'get_services') {
        $category_id = intval($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            echo json_encode(['success' => true, 'services' => []]);
            exit;
        }

        $current_rate = $current_currency['rate'];
        $current_symbol = $current_currency['symbol'];

        $stmt = $pdo->prepare("
            SELECT s.*, c.name as category_name, p.name as platform_name
            FROM services s
            JOIN categories c ON s.category_id = c.id
            JOIN platforms p ON c.platform_id = p.id
            WHERE s.category_id = ? AND s.status = 'active'
            ORDER BY s.id DESC
        ");
        $stmt->execute([$category_id]);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_services = [];
        foreach ($services as $service) {
            $price_usd = floatval($service['price_per_1000']);
            $price_converted = round($price_usd * $current_rate, 2);

            $formatted_services[] = [
                'id' => $service['id'],
                'name' => $service['name'],
                'category_id' => $service['category_id'],
                'category_name' => $service['category_name'],
                'platform_name' => $service['platform_name'],
                'min' => $service['min_qty'] ?? 100,
                'max' => $service['max_qty'] ?? 10000,
                'price_per_1000' => $price_usd,
                'price_per_1000_converted' => $price_converted,
                'currency_symbol' => $current_symbol,
                'status' => $service['status'],
                'description' => trim(preg_replace('/\s+/', ' ', $service['description'] ?? ''))
            ];
        }

        echo json_encode(['success' => true, 'services' => $formatted_services]);
        exit;
    }

    if ($action === 'search_services') {
        $query = trim($_POST['query'] ?? '');
        $current_rate = $current_currency['rate'];
        $current_symbol = $current_currency['symbol'];

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'services' => []]);
            exit;
        }

        $sql = "SELECT s.*, c.name as category_name, c.id as category_id, p.name as platform_name
                FROM services s
                JOIN categories c ON s.category_id = c.id
                JOIN platforms p ON c.platform_id = p.id
                WHERE s.status = 'active'";

        $params = [];

        if (is_numeric($query)) {
            $sql .= " AND (s.id = ? OR s.name LIKE ?)";
            $params[] = intval($query);
            $params[] = "%$query%";
        } else {
            $sql .= " AND s.name LIKE ?";
            $params[] = "%$query%";
        }

        $sql .= " ORDER BY s.id DESC LIMIT 30";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_results = [];
        foreach ($search_results as $service) {
            $price_usd = floatval($service['price_per_1000']);
            $price_converted = round($price_usd * $current_rate, 2);

            $formatted_results[] = [
                'id' => $service['id'],
                'name' => $service['name'],
                'category_id' => $service['category_id'],
                'category_name' => $service['category_name'],
                'platform_name' => $service['platform_name'],
                'min' => $service['min_qty'] ?? 100,
                'max' => $service['max_qty'] ?? 10000,
                'price_per_1000' => $price_usd,
                'price_per_1000_converted' => $price_converted,
                'currency_symbol' => $current_symbol,
                'description' => trim(preg_replace('/\s+/', ' ', $service['description'] ?? ''))
            ];
        }

        echo json_encode(['success' => true, 'services' => $formatted_results]);
        exit;
    }

    if ($action === 'place_order') {
        $service_id = intval($_POST['service_id']);
        $link = trim($_POST['link']);
        $quantity = intval($_POST['quantity']);
        $request_token = $_POST['request_token'] ?? '';

        if (isset($_SESSION['last_order_token']) && $_SESSION['last_order_token'] === $request_token) {
            echo json_encode(['success' => false, 'error' => 'Duplicate request detected. Please wait.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        $stmt->execute([$_SESSION['user_id']]);
        $orders_last_seconds = $stmt->fetchColumn();

        if ($orders_last_seconds > 1) {
            echo json_encode(['success' => false, 'error' => 'Please wait a few seconds before placing another order.']);
            exit;
        }

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            echo json_encode(['success' => false, 'error' => 'Invalid service']);
            exit;
        }

        $min_qty = $service['min_qty'] ?? 100;
        $max_qty = $service['max_qty'] ?? 10000;

        if ($quantity < $min_qty) {
            echo json_encode(['success' => false, 'error' => 'Minimum quantity is ' . $min_qty]);
            exit;
        }
        if ($quantity > $max_qty) {
            echo json_encode(['success' => false, 'error' => 'Maximum quantity is ' . $max_qty]);
            exit;
        }

        $price_usd = round(($quantity / 1000) * floatval($service['price_per_1000']), 2);
        $price_converted = round($price_usd * $current_currency['rate'], 2);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$_SESSION['user_id']]);
            $current_balance = $stmt->fetchColumn();

            if ($current_balance < $price_usd) {
                echo json_encode(['success' => false, 'error' => 'Insufficient balance. Your balance: $' . number_format($current_balance, 2)]);
                $pdo->rollBack();
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$price_usd, $_SESSION['user_id']]);

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, service_id, link, quantity, price, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$_SESSION['user_id'], $service_id, $link, $quantity, $price_usd]);

            $order_id = $pdo->lastInsertId();

            $api_order_id = null;
            if (!empty($service['provider_id']) && !empty($service['api_service_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM api_providers WHERE id = ? AND status = 'active'");
                $stmt->execute([$service['provider_id']]);
                $provider = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($provider) {
                    $api = new ApiProvider($provider['api_url'], $provider['api_key']);
                    $api_result = $api->createOrder($service['api_service_id'], $link, $quantity);

                    if ($api_result['success']) {
                        $api_order_id = $api_result['order_id'];
                        $stmt = $pdo->prepare("UPDATE orders SET api_order_id = ?, status = 'processing' WHERE id = ?");
                        $stmt->execute([$api_order_id, $order_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE orders SET api_response = ? WHERE id = ?");
                        $stmt->execute([json_encode($api_result), $order_id]);
                    }
                }
            }

            $pdo->commit();
            $new_balance = $current_balance - $price_usd;
            $new_balance_converted = round($new_balance * $current_currency['rate'], 2);

            $_SESSION['last_order_token'] = $request_token;

            echo json_encode([
                'success' => true,
                'order_id' => $order_id,
                'new_balance' => $new_balance,
                'new_balance_converted' => $new_balance_converted,
                'service_name' => $service['name'],
                'link' => $link,
                'quantity' => $quantity,
                'price_usd' => $price_usd,
                'price_converted' => $price_converted,
                'currency_symbol' => $current_currency['symbol'],
                'api_order_id' => $api_order_id,
                'message' => 'Order placed successfully!'
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Error processing order: ' . $e->getMessage()]);
            exit;
        }
    }
}

// جلب المنصات
$platforms = $pdo->query("SELECT * FROM platforms WHERE status = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

$everything_platform = [
    'id' => 0,
    'name' => 'everything',
    'status' => 1,
    'sort_order' => -1,
    'icon' => null
];
array_unshift($platforms, $everything_platform);

$platform_icons = [
    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F'],
    'tiktok' => ['icon' => 'fab fa-tiktok', 'color' => '#000000'],
    'youtube' => ['icon' => 'fab fa-youtube', 'color' => '#FF0000'],
    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088cc'],
    'twitter' => ['icon' => 'fab fa-twitter', 'color' => '#1DA1F2'],
    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2'],
    'discord' => ['icon' => 'fab fa-discord', 'color' => '#5865F2'],
    'snapchat' => ['icon' => 'fab fa-snapchat', 'color' => '#FFFC00'],
    'everything' => ['icon' => 'fas fa-bars', 'color' => '#4f46e5'],
];

$converted_balance = convertPrice($user['balance'], $current_currency['rate']);
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - SkyLink SMM</title>
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
        .logo { font-size: 18px; font-weight: 700; color: var(--dark); text-decoration: none; }
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
        .menu-btn:hover { background: #e2e8f0; }

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
        .main-dropdown.open { max-height: 500px; }
        .dropdown-container { padding: 16px 24px; }

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
        .menu-item:hover { background: var(--gray-50); color: var(--primary); }
        .menu-item i { width: 24px; margin-right: 8px; color: var(--primary); }

        .menu-balance-item {
            border-radius: 10px;
            margin-bottom: 8px;
            background: var(--gray-50);
        }
        .menu-balance-btn {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .menu-balance-btn:hover { background: var(--gray-100); border-radius: 10px; }
        .menu-balance-btn i { width: 24px; margin-right: 8px; color: var(--primary); }
        .menu-balance-btn .chevron { float: right; transition: transform 0.2s; }

        .currency-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--gray-100);
            border-radius: 10px;
            margin-top: 4px;
        }
        .currency-submenu.open { max-height: 300px; }
        .currency-submenu a {
            display: block;
            padding: 10px 16px 10px 48px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 13px;
            transition: all 0.2s;
            border-radius: 8px;
        }
        .currency-submenu a:hover { background: rgba(79,70,229,0.1); color: var(--primary); }

        .menu-divider { height: 1px; background: var(--gray-200); margin: 8px 0; }

        .main { margin-top: 73px; padding: 20px; max-width: 700px; margin-left: auto; margin-right: auto; }

        .platforms-grid {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .platform-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 8px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            min-width: 65px;
        }
        .platform-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .platform-card.active { border-color: var(--primary); background: rgba(99,102,241,0.05); }
        .platform-card i { font-size: 20px; }
        .platform-card span { font-size: 9px; font-weight: 500; color: var(--gray-600); }
        .platform-card.active span { color: var(--primary); font-weight: 600; }

        .select-group { margin-bottom: 16px; position: relative; }
        .select-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .select-label i { margin-right: 6px; color: var(--primary); font-size: 12px; }

        .custom-select { position: relative; width: 100%; }
        .custom-select-trigger {
            width: 100%;
            padding: 12px 14px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        .custom-select-trigger:hover { border-color: var(--primary); }
        .custom-select-trigger.active { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99,102,241,0.1); }
        .custom-select-trigger i { color: var(--gray-400); transition: transform 0.2s; }
        .custom-select-trigger.active i { transform: rotate(180deg); }

        .custom-select-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 100;
        }
        .custom-select-dropdown.open {
            max-height: 280px;
            opacity: 1;
            visibility: visible;
            overflow-y: auto;
        }
        .custom-select-option {
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13px;
        }
        .custom-select-option:last-child { border-bottom: none; }
        .custom-select-option:hover { background: var(--gray-50); }
        .custom-select-option.selected { background: rgba(99,102,241,0.1); color: var(--primary); }

        /* تنسيق الخدمات المحسن */
        .service-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
        }
        .service-id {
            font-family: monospace;
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            color: var(--gray-500);
            white-space: nowrap;
        }
        .service-name {
            font-weight: 500;
            font-size: 13px;
            flex: 1;
            color: var(--dark);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .service-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 12px;
            background: rgba(79,70,229,0.1);
            padding: 4px 12px;
            border-radius: 20px;
            white-space: nowrap;
        }

        /* ============================================ */
        /* صندوق الوصف - مثل Perfect Panel (بدون ارتفاع ثابت) */
        /* ============================================ */
        .description-section {
            margin-bottom: 20px;
        }
        .description-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .description-label i {
            margin-right: 6px;
            color: var(--primary);
            font-size: 12px;
        }
        .description-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            /* إزالة أي ارتفاع ثابت */
            height: auto;
            min-height: auto;
            max-height: none;
            overflow: visible;
            display: none;
        }
        .description-box.show {
            display: block;
        }
        #descriptionContent {
            margin: 0;
            padding: 0;
            height: auto;
            min-height: auto;
            line-height: 1.5;
            font-size: 13px;
            color: var(--gray-700);
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        #descriptionContent p {
            margin: 0 0 4px 0;
        }
        #descriptionContent p:last-child {
            margin-bottom: 0;
        }

        /* نموذج الطلب */
        .order-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 20px;
        }
        .order-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }
        .order-title i { color: var(--primary); font-size: 18px; }

        .input-group { margin-bottom: 18px; }
        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .input-group label i {
            margin-right: 6px;
            color: var(--primary);
            font-size: 12px;
        }
        .input-group input {
            width: 100%;
            padding: 12px 14px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 2px rgba(79,70,229,0.1);
        }
        .input-group input::placeholder {
            color: var(--gray-400);
            font-size: 13px;
        }

        .quantity-range {
            font-size: 11px;
            color: var(--gray-400);
            margin-top: 6px;
        }

        .price-preview {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 12px 14px;
            margin: 16px 0;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .price-row.total {
            padding-top: 8px;
            border-top: 1px solid var(--gray-200);
            font-weight: 700;
            font-size: 14px;
            color: var(--primary);
            margin-bottom: 0;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }

        .about-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 20px;
            text-align: center;
        }
        .about-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 12px; color: var(--dark); }
        .about-card p { font-size: 12px; color: var(--gray-500); line-height: 1.6; margin-bottom: 16px; }
        .telegram-support {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #0088cc, #00a9e6);
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .telegram-support:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,136,204,0.3); }

        .search-premium { margin-bottom: 20px; position: relative; }
        .search-premium-container { position: relative; }
        .search-premium-input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            font-size: 14px;
        }
        .search-premium-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99,102,241,0.1); }
        .search-premium-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 14px; }
        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            margin-top: 6px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid var(--gray-200);
        }
        .search-results-dropdown.show { display: block; }
        .search-result-item {
            padding: 12px 14px;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
        }
        .search-result-item:hover { background: var(--gray-50); }
        .search-result-id {
            font-size: 10px;
            font-family: monospace;
            background: var(--gray-100);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .search-result-name { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .search-result-price { font-size: 12px; color: var(--primary); font-weight: 600; }

        .success-box {
            background: linear-gradient(135deg, var(--success), #059669);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            color: white;
            display: none;
            animation: slideIn 0.3s ease;
        }
        .success-box.show { display: block; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .success-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .success-close {
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            color: white;
            font-size: 12px;
        }
        .success-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            font-size: 11px;
        }
        .success-detail { background: rgba(255,255,255,0.1); border-radius: 8px; padding: 6px 8px; }

        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--dark);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            z-index: 200;
            transition: 0.3s;
            opacity: 0;
        }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        @media (max-width: 500px) {
            .platforms-grid { gap: 8px; }
            .platform-card { padding: 6px 8px; min-width: 55px; }
            .platform-card i { font-size: 16px; }
            .dropdown-container { padding: 12px 16px; }
            .service-name { white-space: normal; line-height: 1.4; }
            .service-option { gap: 6px; }
            .main { padding: 16px; }
            .order-card { padding: 16px; }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="dashboard.php" class="logo"><?php echo htmlspecialchars($site_domain); ?></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<div class="main-dropdown" id="mainDropdown">
    <div class="dropdown-container">
        <div class="menu-balance-item">
            <div class="menu-balance-btn" id="balanceMenuBtn">
                <i class="fas fa-wallet"></i> Balance: <?php echo $current_currency['symbol'] . ' ' . number_format($converted_balance, 2); ?> <?php echo $current_currency['code']; ?>
                <i class="fas fa-chevron-down chevron" id="balanceChevron"></i>
            </div>
            <div class="currency-submenu" id="currencySubmenu">
                <?php foreach ($currencies as $cur): ?>
                <a href="#" data-currency="<?php echo $cur['code']; ?>" class="currency-option">
                    <?php echo $cur['code']; ?> - <?php echo htmlspecialchars($cur['name']); ?> (1 USD = <?php echo number_format($cur['rate'], 2); ?>)
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="menu-divider"></div>
        <a href="dashboard.php" class="menu-item"><i class="fas fa-shopping-cart"></i> New Order</a>
        <a href="orders.php" class="menu-item"><i class="fas fa-list-alt"></i> Orders</a>
        <a href="services.php" class="menu-item"><i class="fas fa-cogs"></i> Services</a>
        <a href="addfunds.php" class="menu-item"><i class="fas fa-plus-circle"></i> Add Funds</a>
        <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
        <a href="child-panel.php" class="menu-item"><i class="fas fa-link"></i> Child Panel</a>
        <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
        <div class="menu-divider"></div>
        <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="platforms-grid" id="platformsContainer"></div>

    <div class="success-box" id="successBox">
        <div class="success-header"><div class="success-title"><i class="fas fa-check-circle"></i><span>Order Received!</span></div><button class="success-close" onclick="closeSuccessBox()"><i class="fas fa-times"></i></button></div>
        <div class="success-details" id="successDetails"></div>
    </div>

    <div class="search-premium">
        <div class="search-premium-container">
            <i class="fas fa-search search-premium-icon"></i>
            <input type="text" class="search-premium-input" id="searchInput" placeholder="Search by Service ID or Name..." autocomplete="off">
            <div class="search-results-dropdown" id="searchResults"></div>
        </div>
    </div>

    <!-- Category -->
    <div class="select-group">
        <label class="select-label"><i class="fas fa-tags"></i> Category</label>
        <div class="custom-select" id="categorySelect">
            <div class="custom-select-trigger" id="categoryTrigger">
                <span id="selectedCategoryName">Loading categories...</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="custom-select-dropdown" id="categoryDropdown"></div>
        </div>
    </div>

    <!-- Service -->
    <div class="select-group">
        <label class="select-label"><i class="fas fa-cogs"></i> Service</label>
        <div class="custom-select" id="serviceSelect">
            <div class="custom-select-trigger" id="serviceTrigger">
                <span id="selectedServiceName">Select a category first</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="custom-select-dropdown" id="serviceDropdown"></div>
        </div>
    </div>

    <!-- Description - مثل Perfect Panel -->
    <div class="description-section">
        <div class="description-label"><i class="fas fa-info-circle"></i> Description</div>
        <div class="description-box" id="serviceDescriptionBox">
            <div id="descriptionContent"></div>
        </div>
    </div>

    <!-- Order Form -->
    <div class="order-card">
        <div class="order-title"><i class="fas fa-cart-plus"></i> New Order</div>

        <div class="input-group">
            <label><i class="fas fa-link"></i> Link</label>
            <input type="text" id="orderLink" placeholder="https://instagram.com/username">
        </div>

        <div class="input-group">
            <label><i class="fas fa-sort-amount-up"></i> Quantity</label>
            <input type="number" id="orderQuantity" placeholder="Enter quantity">
            <div class="quantity-range" id="quantityRange"></div>
        </div>

        <div class="price-preview">
            <div class="price-row"><span>Price per 1000:</span><span id="pricePer1000">$0.00</span></div>
            <div class="price-row"><span>Quantity:</span><span id="displayQuantity">0</span></div>
            <div class="price-row total"><span>Total Price:</span><span id="totalPrice">$0.00</span></div>
        </div>

        <button class="submit-btn" id="submitBtn"><i class="fas fa-paper-plane"></i> Submit Order</button>
    </div>

    <div class="about-card">
        <h3><i class="fas fa-info-circle"></i> About Us</h3>
        <p>SkyLink is one of the largest and most trusted SMM panel provider in online world. Buy affordable SMM services to quickly grow your social media by real and active accounts. We are the fastest wholesale SMM panel in The World for Instagram, YouTube, TikTok, Facebook, Spotify, Telegram, Twitter(X) and LinkedIn.</p>
        <a href="https://t.me/<?php echo BOT_USERNAME ?? 'SkyLinkSupport'; ?>" class="telegram-support" target="_blank">
            <i class="fab fa-telegram"></i> Telegram Support
        </a>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const platformsData = <?php echo json_encode($platforms); ?>;
    const platformIcons = <?php echo json_encode($platform_icons); ?>;
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    let currentCurrency = <?php echo json_encode($current_currency); ?>;
    let usdBalance = <?php echo $user['balance']; ?>;

    let currentPlatform = 'everything';
    let currentCategoryId = 0;
    let currentService = null;
    let categoriesCache = new Map();
    let servicesCache = new Map();
    let searchDebounceTimer = null;
    let isSubmitting = false;

    // عناصر DOM
    const platformsContainer = document.getElementById('platformsContainer');
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const categoryTrigger = document.getElementById('categoryTrigger');
    const categoryDropdown = document.getElementById('categoryDropdown');
    const selectedCategoryName = document.getElementById('selectedCategoryName');
    const serviceTrigger = document.getElementById('serviceTrigger');
    const serviceDropdown = document.getElementById('serviceDropdown');
    const selectedServiceName = document.getElementById('selectedServiceName');
    const serviceDescriptionBox = document.getElementById('serviceDescriptionBox');
    const descriptionContent = document.getElementById('descriptionContent');
    const orderLink = document.getElementById('orderLink');
    const orderQuantity = document.getElementById('orderQuantity');
    const quantityRange = document.getElementById('quantityRange');
    const pricePer1000Span = document.getElementById('pricePer1000');
    const displayQuantitySpan = document.getElementById('displayQuantity');
    const totalPriceSpan = document.getElementById('totalPrice');
    const submitBtn = document.getElementById('submitBtn');
    const successBox = document.getElementById('successBox');
    const successDetails = document.getElementById('successDetails');
    const menuBtn = document.getElementById('menuBtn');
    const mainDropdown = document.getElementById('mainDropdown');
    const balanceMenuBtn = document.getElementById('balanceMenuBtn');
    const currencySubmenu = document.getElementById('currencySubmenu');

    // دوال مساعدة
    function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;'); }
    function showToast(message, type = 'success') { const toast = document.getElementById('toast'); toast.textContent = message; toast.className = `toast ${type} show`; setTimeout(() => toast.classList.remove('show'), 2500); }
    function closeSuccessBox() { successBox.classList.remove('show'); }
    function convertPrice(usdPrice) { return usdPrice * currentCurrency.rate; }

    // تنظيف النص من المسافات والأسطر الفارغة الزائدة
    function cleanText(text) {
        if (!text) return '';
        // إزالة المسافات من البداية والنهاية
        let cleaned = text.trim();
        // إزالة الأسطر الفارغة المتعددة واستبدالها بسطر واحد
        cleaned = cleaned.replace(/\n\s*\n/g, '\n');
        // إزالة المسافات المتعددة
        cleaned = cleaned.replace(/\s+/g, ' ').trim();
        return cleaned;
    }

    // تحديث وصف الخدمة - مثل Perfect Panel
    function updateServiceDescription(description) {
        let cleanedDesc = cleanText(description || '');
        if (cleanedDesc !== '') {
            let formattedDesc = escapeHtml(cleanedDesc).replace(/\n/g, '<br>');
            descriptionContent.innerHTML = formattedDesc;
            serviceDescriptionBox.classList.add('show');
        } else {
            serviceDescriptionBox.classList.remove('show');
            descriptionContent.innerHTML = '';
        }
    }

    // تحديث نموذج الطلب
    function updateOrderForm(service) {
        if (!service) {
            pricePer1000Span.textContent = `${currentCurrency.symbol} 0.00`;
            quantityRange.innerHTML = '';
            orderQuantity.disabled = true;
            submitBtn.disabled = true;
            orderQuantity.placeholder = 'Select service first';
            orderQuantity.value = '';
            displayQuantitySpan.textContent = '0';
            totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;
            return;
        }
        orderQuantity.disabled = false;
        submitBtn.disabled = false;
        orderQuantity.placeholder = 'Enter quantity';
        orderQuantity.value = '';
        displayQuantitySpan.textContent = '0';
        totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;
        quantityRange.innerHTML = `Min: ${service.min.toLocaleString()} | Max: ${service.max.toLocaleString()}`;
        pricePer1000Span.textContent = `${currentCurrency.symbol} ${service.price_per_1000_converted.toFixed(2)}`;
    }

    // تحديث السعر (بدون تحقق)
    function updatePrice() {
        if (!currentService) return;
        let qty = parseInt(orderQuantity.value);
        if (isNaN(qty) || qty === 0) {
            displayQuantitySpan.textContent = '0';
            totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;
            return;
        }
        let totalConverted = (qty / 1000) * currentService.price_per_1000_converted;
        displayQuantitySpan.textContent = qty.toLocaleString();
        totalPriceSpan.textContent = `${currentCurrency.symbol} ${totalConverted.toFixed(2)}`;
    }

    // تحميل الفئات
    async function loadCategories(platform) {
        if (categoriesCache.has(platform)) {
            renderCategoriesDropdown(categoriesCache.get(platform));
            return;
        }

        categoryDropdown.innerHTML = '<div style="padding: 12px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading categories...</div>';

        try {
            const formData = new FormData();
            formData.append('action', 'get_categories');
            formData.append('platform', platform);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();

            if (result.success && result.categories.length > 0) {
                categoriesCache.set(platform, result.categories);
                renderCategoriesDropdown(result.categories);
                // اختيار أحدث فئة (أول عنصر)
                const firstCategory = result.categories[0];
                selectCategory(firstCategory.id, firstCategory.name);
            } else {
                categoryDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">No categories found</div>';
                selectedCategoryName.textContent = 'No categories';
                serviceTrigger.classList.remove('active');
                serviceDropdown.classList.remove('open');
                selectedServiceName.textContent = 'Select a category first';
                currentService = null;
                updateOrderForm(null);
                updateServiceDescription('');
            }
        } catch (error) {
            console.error(error);
            categoryDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">Failed to load categories</div>';
        }
    }

    // عرض الفئات في القائمة
    function renderCategoriesDropdown(categories) {
        if (categories.length === 0) {
            categoryDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">No categories</div>';
            return;
        }
        categoryDropdown.innerHTML = categories.map(cat => `
            <div class="custom-select-option" data-id="${cat.id}" data-name="${escapeHtml(cat.name)}">
                ${escapeHtml(cat.name)}
            </div>
        `).join('');

        categoryDropdown.querySelectorAll('.custom-select-option').forEach(el => {
            el.addEventListener('click', () => {
                selectCategory(parseInt(el.dataset.id), el.dataset.name);
                closeCategoryDropdown();
            });
        });
    }

    // اختيار فئة
    function selectCategory(categoryId, categoryName) {
        currentCategoryId = categoryId;
        selectedCategoryName.textContent = categoryName;
        currentService = null;
        selectedServiceName.textContent = 'Loading services...';
        updateOrderForm(null);
        updateServiceDescription('');
        orderQuantity.value = '';
        displayQuantitySpan.textContent = '0';
        totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;

        // تمييز الفئة المختارة
        document.querySelectorAll('#categoryDropdown .custom-select-option').forEach(el => {
            el.classList.remove('selected');
            if (parseInt(el.dataset.id) === categoryId) {
                el.classList.add('selected');
            }
        });

        loadServices(categoryId);
    }

    // تحميل الخدمات
    async function loadServices(categoryId) {
        if (servicesCache.has(categoryId)) {
            const services = servicesCache.get(categoryId);
            renderServicesDropdown(services);
            if (services.length > 0) {
                const firstService = services[0];
                selectService(firstService);
            } else {
                selectedServiceName.textContent = 'No services available';
                currentService = null;
                updateOrderForm(null);
                updateServiceDescription('');
            }
            return;
        }

        serviceDropdown.innerHTML = '<div style="padding: 12px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>';

        try {
            const formData = new FormData();
            formData.append('action', 'get_services');
            formData.append('category_id', categoryId);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();

            if (result.success && result.services.length > 0) {
                servicesCache.set(categoryId, result.services);
                renderServicesDropdown(result.services);
                const firstService = result.services[0];
                selectService(firstService);
            } else {
                serviceDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">No services in this category</div>';
                selectedServiceName.textContent = 'No services';
                currentService = null;
                updateOrderForm(null);
                updateServiceDescription('');
            }
        } catch (error) {
            console.error(error);
            serviceDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">Failed to load services</div>';
        }
    }

    // عرض الخدمات في القائمة
    function renderServicesDropdown(services) {
        if (services.length === 0) {
            serviceDropdown.innerHTML = '<div style="padding: 12px; text-align: center;">No services</div>';
            return;
        }

        serviceDropdown.innerHTML = services.map(s => `
            <div class="custom-select-option" data-id="${s.id}" data-service='${JSON.stringify(s)}'>
                <div class="service-option">
                    <span class="service-id">ID: ${s.id}</span>
                    <span class="service-name" title="${escapeHtml(s.name)}">${escapeHtml(s.name)}</span>
                    <span class="service-price">${currentCurrency.symbol} ${Number(s.price_per_1000_converted).toFixed(2)} / 1000</span>
                </div>
            </div>
        `).join('');

        serviceDropdown.querySelectorAll('.custom-select-option').forEach(el => {
            el.addEventListener('click', () => {
                selectService(JSON.parse(el.dataset.service));
                closeServiceDropdown();
            });
        });
    }

    // اختيار خدمة
    function selectService(service) {
        currentService = {
            id: service.id,
            name: service.name,
            min: service.min,
            max: service.max,
            price_per_1000: parseFloat(service.price_per_1000),
            price_per_1000_converted: parseFloat(service.price_per_1000_converted),
            description: service.description || ''
        };

        selectedServiceName.textContent = `ID: ${service.id} • ${service.name.substring(0, 45)}${service.name.length > 45 ? '...' : ''}`;
        updateServiceDescription(currentService.description);
        updateOrderForm(currentService);

        // تمييز الخدمة المختارة
        document.querySelectorAll('#serviceDropdown .custom-select-option').forEach(el => {
            el.classList.remove('selected');
            if (parseInt(el.dataset.id) === service.id) {
                el.classList.add('selected');
            }
        });
    }

    // تغيير العملة
    async function changeCurrency(currencyCode) {
        try {
            const response = await fetch(`?change_currency_ajax=${currencyCode}`);
            const result = await response.json();
            if (result.success) {
                currentCurrency = result.currency;
                currentCurrency.rate = parseFloat(currentCurrency.rate);

                // تحديث الرصيد المعروض
                const convertedBalance = convertPrice(usdBalance);
                const balanceElement = document.querySelector('.menu-balance-btn');
                if (balanceElement) {
                    balanceElement.innerHTML = `<i class="fas fa-wallet"></i> Balance: ${currentCurrency.symbol} ${convertedBalance.toFixed(2)} ${currentCurrency.code} <i class="fas fa-chevron-down chevron" id="balanceChevron"></i>`;
                }

                // تحديث الأسعار إذا كانت هناك خدمة محددة
                if (currentService) {
                    currentService.price_per_1000_converted = convertPrice(currentService.price_per_1000);
                    updateOrderForm(currentService);
                    if (orderQuantity.value && !isNaN(parseInt(orderQuantity.value))) {
                        updatePrice();
                    }
                }

                // تحديث قائمة الخدمات إذا كانت الفئة محددة
                if (currentCategoryId > 0) {
                    servicesCache.delete(currentCategoryId);
                    loadServices(currentCategoryId);
                }

                showToast(`Currency changed to ${currentCurrency.code}`, 'success');
            } else {
                showToast('Failed to change currency', 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('Network error', 'error');
        }
    }

    // البحث عن الخدمات
    async function performSearch(query) {
        if (!query || query.length < 2) {
            searchResults.classList.remove('show');
            return;
        }

        searchResults.innerHTML = '<div style="padding: 12px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        searchResults.classList.add('show');

        try {
            const formData = new FormData();
            formData.append('action', 'search_services');
            formData.append('query', query);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();

            if (result.success && result.services.length > 0) {
                searchResults.innerHTML = result.services.map(s => `
                    <div class="search-result-item" data-service='${JSON.stringify(s)}' data-category-id="${s.category_id}">
                        <div class="search-result-id">ID: ${s.id}</div>
                        <div class="search-result-name">${escapeHtml(s.name)}</div>
                        <div class="search-result-price">${currentCurrency.symbol} ${Number(s.price_per_1000_converted).toFixed(2)} / 1000</div>
                        <div style="font-size: 9px; color: #94a3b8; margin-top: 2px;">Min: ${s.min} | Max: ${s.max}</div>
                    </div>
                `).join('');

                document.querySelectorAll('.search-result-item').forEach(el => {
                    el.addEventListener('click', () => {
                        const service = JSON.parse(el.dataset.service);
                        const categoryId = parseInt(el.dataset.categoryId);

                        // إذا كانت الفئة مختلفة، قم بتحديثها
                        if (categoryId !== currentCategoryId) {
                            currentCategoryId = categoryId;
                            // تحديث الفئة المختارة في القائمة
                            const categoryOption = document.querySelector(`#categoryDropdown .custom-select-option[data-id="${categoryId}"]`);
                            if (categoryOption) {
                                selectedCategoryName.textContent = categoryOption.dataset.name;
                                document.querySelectorAll('#categoryDropdown .custom-select-option').forEach(opt => opt.classList.remove('selected'));
                                categoryOption.classList.add('selected');
                            }
                            // إعادة تحميل الخدمات ثم اختيار الخدمة
                            servicesCache.delete(categoryId);
                            loadServices(categoryId).then(() => {
                                selectService(service);
                            });
                        } else {
                            selectService(service);
                        }

                        searchInput.value = '';
                        searchResults.classList.remove('show');
                        closeServiceDropdown();
                        closeCategoryDropdown();
                    });
                });
            } else {
                searchResults.innerHTML = '<div style="padding: 20px; text-align: center;">No services found</div>';
            }
        } catch (error) {
            console.error(error);
            searchResults.innerHTML = '<div style="padding: 20px; text-align: center;">Search failed</div>';
        }
    }

    // إرسال الطلب
    async function submitOrder() {
        if (isSubmitting) {
            showToast('Please wait...', 'error');
            return;
        }
        if (!currentService) {
            showToast('Please select a service', 'error');
            return;
        }

        const link = orderLink.value.trim();
        if (!link) {
            showToast('Please enter a link', 'error');
            return;
        }
        try {
            new URL(link);
        } catch(e) {
            showToast('Invalid URL', 'error');
            return;
        }

        let quantity = parseInt(orderQuantity.value);

        if (isNaN(quantity) || quantity === 0) {
            showToast(`Please enter a valid quantity. Minimum: ${currentService.min}`, 'error');
            return;
        }
        if (quantity < currentService.min) {
            showToast(`Minimum quantity is ${currentService.min}`, 'error');
            return;
        }
        if (quantity > currentService.max) {
            showToast(`Maximum quantity is ${currentService.max}`, 'error');
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const requestToken = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const formData = new FormData();
            formData.append('action', 'place_order');
            formData.append('service_id', currentService.id);
            formData.append('link', link);
            formData.append('quantity', quantity);
            formData.append('request_token', requestToken);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                orderLink.value = '';
                orderQuantity.value = '';
                displayQuantitySpan.textContent = '0';
                totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;
                usdBalance = result.new_balance;

                // تحديث الرصيد المعروض
                const convertedBalance = convertPrice(usdBalance);
                const balanceElement = document.querySelector('.menu-balance-btn');
                if (balanceElement) {
                    balanceElement.innerHTML = `<i class="fas fa-wallet"></i> Balance: ${currentCurrency.symbol} ${convertedBalance.toFixed(2)} ${currentCurrency.code} <i class="fas fa-chevron-down chevron" id="balanceChevron"></i>`;
                }

                successDetails.innerHTML = `
                    <div class="success-detail"><div class="success-detail-label">Service</div><div class="success-detail-value">${escapeHtml(result.service_name)}</div></div>
                    <div class="success-detail"><div class="success-detail-label">Quantity</div><div class="success-detail-value">${result.quantity.toLocaleString()}</div></div>
                    <div class="success-detail"><div class="success-detail-label">Price</div><div class="success-detail-value">${result.currency_symbol} ${Number(result.price_converted).toFixed(2)}</div></div>
                    <div class="success-detail"><div class="success-detail-label">Order ID</div><div class="success-detail-value">#${result.order_id}</div></div>
                `;
                successBox.classList.add('show');
                setTimeout(() => successBox.classList.remove('show'), 4000);
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            showToast('Network error', 'error');
        } finally {
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Order';
        }
    }

    // إغلاق القوائم
    function closeCategoryDropdown() {
        categoryTrigger.classList.remove('active');
        categoryDropdown.classList.remove('open');
    }

    function closeServiceDropdown() {
        serviceTrigger.classList.remove('active');
        serviceDropdown.classList.remove('open');
    }

    // عرض المنصات
    function renderPlatforms() {
        platformsContainer.innerHTML = platformsData.map(p => `
            <div class="platform-card ${currentPlatform === p.name ? 'active' : ''}" data-platform="${p.name}">
                <i class="${platformIcons[p.name]?.icon || 'fab fa-instagram'}" style="color: ${platformIcons[p.name]?.color || '#4f46e5'}"></i>
                <span>${p.name === 'everything' ? 'All' : p.name.charAt(0).toUpperCase() + p.name.slice(1)}</span>
            </div>
        `).join('');

        document.querySelectorAll('.platform-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const platform = card.dataset.platform;
                if (platform && platform !== currentPlatform) {
                    currentPlatform = platform;
                    renderPlatforms();
                    // إعادة تحميل الفئات للمنصة الجديدة
                    categoriesCache.clear();
                    servicesCache.clear();
                    loadCategories(platform);
                }
            });
        });
    }

    // أحداث القوائم
    categoryTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (serviceDropdown.classList.contains('open')) {
            serviceTrigger.classList.remove('active');
            serviceDropdown.classList.remove('open');
        }
        if (categoryDropdown.classList.contains('open')) {
            categoryTrigger.classList.remove('active');
            categoryDropdown.classList.remove('open');
        } else {
            categoryTrigger.classList.add('active');
            categoryDropdown.classList.add('open');
        }
    });

    serviceTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!currentCategoryId) {
            showToast('Please select a category first', 'error');
            return;
        }
        if (categoryDropdown.classList.contains('open')) {
            categoryTrigger.classList.remove('active');
            categoryDropdown.classList.remove('open');
        }
        if (serviceDropdown.classList.contains('open')) {
            serviceTrigger.classList.remove('active');
            serviceDropdown.classList.remove('open');
        } else {
            serviceTrigger.classList.add('active');
            serviceDropdown.classList.add('open');
        }
    });

    // أحداث القائمة الرئيسية
    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        mainDropdown.classList.toggle('open');
    });

    if (balanceMenuBtn) {
        balanceMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            currencySubmenu.classList.toggle('open');
            const chevron = balanceMenuBtn.querySelector('.chevron');
            if (chevron) {
                chevron.style.transform = currencySubmenu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        });
    }

    document.querySelectorAll('.currency-option').forEach(option => {
        option.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const currencyCode = option.dataset.currency;
            changeCurrency(currencyCode);
            currencySubmenu.classList.remove('open');
            const chevron = balanceMenuBtn.querySelector('.chevron');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        });
    });

    // إغلاق القوائم عند الضغط خارجها
    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !mainDropdown.contains(e.target)) {
            mainDropdown.classList.remove('open');
        }
        if (!categoryTrigger.contains(e.target) && !categoryDropdown.contains(e.target)) {
            categoryTrigger.classList.remove('active');
            categoryDropdown.classList.remove('open');
        }
        if (!serviceTrigger.contains(e.target) && !serviceDropdown.contains(e.target)) {
            serviceTrigger.classList.remove('active');
            serviceDropdown.classList.remove('open');
        }
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('show');
        }
    });

    // أحداث الإدخال
    orderQuantity.addEventListener('input', function(e) {
        if (this.value === '') {
            displayQuantitySpan.textContent = '0';
            totalPriceSpan.textContent = `${currentCurrency.symbol} 0.00`;
            return;
        }
        if (!/^\d*$/.test(this.value)) {
            this.value = this.value.replace(/[^\d]/g, '');
        }
        updatePrice();
    });

    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => performSearch(e.target.value), 300);
    });

    submitBtn.addEventListener('click', submitOrder);

    // بدء التشغيل
    renderPlatforms();
    loadCategories('everything');
</script>
</body>
</html>