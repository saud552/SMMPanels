<?php
// =============================================
// cron/update_prices.php - تحديث أسعار API تلقائياً (نسخة خفيفة)
// يحدث فقط الخدمات الموجودة في الموقع
// =============================================

// تحديد المسار الصحيح
$possible_paths = [
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php',
    '/home/tigerspeed/public_html/config.php'
];

$config_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die("Error: Cannot load config.php");
}

// =============================================
// إعدادات التحديث
// =============================================
$log_file = __DIR__ . '/price_sync.log';
$max_services = 50; // حد أقصى للخدمات في كل مرة

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    echo $message . PHP_EOL;
}

logMessage("========== Starting price sync ==========");

// =============================================
// جلب الخدمات التي لها Provider فقط (الموجودة في الموقع)
// =============================================
$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.price_per_1000 as current_price,
           s.api_service_id, p.api_url, p.api_key, p.name as provider_name
    FROM services s
    JOIN api_providers p ON s.provider_id = p.id
    WHERE p.status = 'active'
    AND s.api_service_id IS NOT NULL
    AND s.api_service_id != ''
    LIMIT ?
");
$stmt->execute([$max_services]);
$services = $stmt->fetchAll();

logMessage("Found " . count($services) . " services with API providers");

$updated = 0;
$failed = 0;
$no_change = 0;

foreach ($services as $service) {
    $api_url = rtrim($service['api_url'], '/');
    $api_key = $service['api_key'];

    // استدعاء API لجلب الخدمات
    $post_data = ['key' => $api_key, 'action' => 'services'];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || !$response) {
        logMessage("❌ Failed to fetch from {$service['provider_name']} for service ID {$service['id']}");
        $failed++;
        continue;
    }

    $services_list = json_decode($response, true);

    if (!is_array($services_list)) {
        logMessage("❌ Invalid response from {$service['provider_name']}");
        $failed++;
        continue;
    }

    // البحث عن الخدمة في القائمة
    $found = false;
    foreach ($services_list as $api_service) {
        $api_service_id = $api_service['service'] ?? null;
        if ($api_service_id && (string)$api_service_id === (string)$service['api_service_id']) {
            $api_price = floatval($api_service['rate'] ?? 0);

            // تحويل السعر إذا لزم الأمر
            if ($api_price > 100) {
                $api_price = $api_price / 1000;
            }

            if ($api_price > 0) {
                $current_price = floatval($service['current_price']);
                $difference = abs($api_price - $current_price);
                $percent_diff = ($difference / max($current_price, 0.01)) * 100;

                // فقط قم بالتحديث إذا كان الفرق أكبر من 5%
                if ($percent_diff > 5) {
                    $update = $pdo->prepare("UPDATE services SET price_per_1000 = ? WHERE id = ?");
                    $update->execute([$api_price, $service['id']]);
                    $updated++;
                    logMessage("✅ Updated: {$service['name']} | Old: \${$current_price} | New: \${$api_price} | Change: " . round($percent_diff, 2) . "%");
                } else {
                    $no_change++;
                    logMessage("⏭️ No change: {$service['name']} | Price: \${$current_price} | API: \${$api_price} | Diff: " . round($percent_diff, 2) . "%");
                }
            } else {
                logMessage("⚠️ Invalid price for {$service['name']}: \${$api_price}");
                $failed++;
            }
            $found = true;
            break;
        }
    }

    if (!$found) {
        logMessage("⚠️ Service ID {$service['api_service_id']} not found in API response for {$service['provider_name']}");
        $failed++;
    }

    // تأخير بسيط لتجنب إغراق الـ API
    usleep(200000);
}

// =============================================
// الملخص
// =============================================
logMessage("========== Summary ==========");
logMessage("✅ Updated: $updated services");
logMessage("⏭️ No change: $no_change services");
logMessage("❌ Failed: $failed services");
logMessage("========== Sync completed ==========");

// =============================================
// عرض النتيجة إذا تم التشغيل يدوياً
// =============================================
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Price Sync</title><meta charset='UTF-8'></head>
    <body style='font-family: Arial; padding: 20px;'>
        <h1>🔄 Price Sync Results</h1>
        <p>✅ Updated: <strong>$updated</strong> services</p>
        <p>⏭️ No change: <strong>$no_change</strong> services</p>
        <p>❌ Failed: <strong>$failed</strong> services</p>
        <hr>
        <a href='services.php'>← Back to Services</a>
    </body>
    </html>";
}
?>