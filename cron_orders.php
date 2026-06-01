<?php
// =============================================
// cron_orders.php - تحديث حالة الطلبات (نسخة مصححة بالكامل)
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// تحديد المسار الصحيح لملف config.php
$possible_paths = [
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php',
    __DIR__ . '/../config.php',
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
    die("Error: Cannot find config.php file");
}

// تضمين كلاس ApiProvider
$api_provider_paths = [
    __DIR__ . '/includes/ApiProvider.php',
    dirname(__DIR__) . '/includes/ApiProvider.php',
    __DIR__ . '/../includes/ApiProvider.php',
    '/home/tigerspeed/public_html/includes/ApiProvider.php'
];

$api_loaded = false;
foreach ($api_provider_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $api_loaded = true;
        break;
    }
}

if (!$api_loaded) {
    die("Error: Cannot find ApiProvider.php file");
}

// دالة تسجيل
function logMessage($message, $type = 'INFO') {
    $logFile = __DIR__ . '/cron_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Orders Status</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 12px; padding: 20px; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        .refund { color: #8b5cf6; }
        .order-item { border-bottom: 1px solid #eee; padding: 12px 0; }
        .summary { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-canceled { background: #fee2e2; color: #991b1b; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-pending { background: #fed7aa; color: #92400e; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔄 تحديث حالة الطلبات</h1>";

logMessage("=== بدء تحديث حالة الطلبات ===");

// =============================================
// جلب جميع مزودي API النشطين
// =============================================
$stmt = $pdo->prepare("SELECT * FROM api_providers WHERE status = 'active'");
$stmt->execute();
$providers = $stmt->fetchAll();

// إنشاء مصفوفة ربط بين provider_id وكائن ApiProvider
$api_instances = [];
foreach ($providers as $provider) {
    $api_instances[$provider['id']] = new ApiProvider($provider['api_url'], $provider['api_key']);
    echo "<p class='info'>✅ تم تحميل مزود: {$provider['name']}</p>";
    logMessage("Loaded provider: {$provider['name']}");
}

// =============================================
// جلب جميع الطلبات غير المكتملة من جدول orders
// =============================================
$stmt = $pdo->prepare("
    SELECT o.*, s.name as service_name, s.provider_id, s.api_service_id
    FROM orders o
    LEFT JOIN services s ON o.service_id = s.id
    WHERE o.status NOT IN ('completed', 'canceled')
    AND o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY o.created_at ASC
    LIMIT 100
");
$stmt->execute();
$pending_orders = $stmt->fetchAll();

$total_orders = count($pending_orders);
echo "<p class='info'>📊 تم العثور على $total_orders طلب غير مكتمل</p>";
logMessage("Found $total_orders pending orders");

// إحصائيات
$updated_count = 0;
$error_count = 0;
$refunded_count = 0;
$refunded_total = 0;
$completed_count = 0;
$canceled_count = 0;

// =============================================
// معالجة كل طلب
// =============================================
foreach ($pending_orders as $order) {
    echo "<div class='order-item'>";
    echo "<strong>📋 الطلب #{$order['id']}</strong><br>";

    // حالة الطلب الحالية
    $old_status = $order['status'];
    echo "الحالة الحالية: " . ucfirst($old_status) . "<br>";

    // التحقق من وجود api_order_id
    if (empty($order['api_order_id'])) {
        echo "<span class='error'>⚠️ لا يوجد API Order ID لهذا الطلب</span><br>";
        echo "</div>";
        continue;
    }

    // التحقق من وجود provider_id
    if (empty($order['provider_id']) || !isset($api_instances[$order['provider_id']])) {
        echo "<span class='error'>⚠️ لا يوجد مزود API لهذا الطلب</span><br>";
        echo "</div>";
        continue;
    }

    echo "API Order ID: {$order['api_order_id']}<br>";

    // جلب الحالة من API
    $api = $api_instances[$order['provider_id']];
    $result = $api->getOrderStatus($order['api_order_id']);

    if ($result['success']) {
        $new_status = strtolower($result['status']);
        $start_count = intval($result['start_count'] ?? 0);
        $remains = intval($result['remains'] ?? 0);

        echo "الحالة الجديدة: " . ucfirst($new_status) . "<br>";
        echo "عدد البدء: " . number_format($start_count) . " | المتبقي: " . number_format($remains) . "<br>";

        // =============================================
        // تحديث قاعدة البيانات
        // =============================================

        if ($new_status === 'canceled' && $old_status !== 'canceled') {
            // =============================================
            // حالة ملغية - استرجاع الرصيد
            // =============================================
            echo "<span class='refund'>💰 تم استرجاع المبلغ: $" . number_format($order['price'], 2) . "</span><br>";

            // استرجاع الرصيد للمستخدم
            $refund_stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $refund_stmt->execute([$order['price'], $order['user_id']]);

            // تحديث الطلب (جعل السعر 0)
            $update_stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?,
                    start_counter = ?,
                    remains = ?,
                    price = 0
                WHERE id = ?
            ");
            $update_stmt->execute([$new_status, $start_count, $remains, $order['id']]);

            $refunded_count++;
            $refunded_total += $order['price'];
            $canceled_count++;
            $updated_count++;

        } elseif ($new_status !== $old_status) {
            // =============================================
            // تحديث عادي (تغيرت الحالة)
            // =============================================
            $update_stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?,
                    start_counter = ?,
                    remains = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$new_status, $start_count, $remains, $order['id']]);

            if ($new_status == 'completed') {
                $completed_count++;
                echo "<span class='success'>✅ تم إكمال الطلب بنجاح</span><br>";
            } else {
                echo "<span class='info'>🔄 تم تحديث الحالة إلى: " . ucfirst($new_status) . "</span><br>";
            }
            $updated_count++;

        } else {
            // =============================================
            // الحالة لم تتغير - تحديث الأعداد فقط
            // =============================================
            $update_stmt = $pdo->prepare("
                UPDATE orders
                SET start_counter = ?,
                    remains = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$start_count, $remains, $order['id']]);
            echo "<span class='info'>📊 تم تحديث الأعداد فقط (Start: $start_count, Remains: $remains)</span><br>";
        }

    } else {
        echo "<span class='error'>❌ فشل الاتصال بـ API: " . htmlspecialchars($result['error']) . "</span><br>";
        $error_count++;
    }

    echo "</div>";

    // تأخير بسيط
    usleep(200000);
}

// تحديث رصيد الجلسة للمستخدم الحالي
if ($refunded_count > 0 && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['balance'] = $stmt->fetchColumn();
}

// عرض الملخص
echo "<div class='summary'>
        <h3>📊 ملخص التحديث</h3>
        <p>✅ تم تحديث: <strong>$updated_count</strong> طلب</p>
        <p>✅ مكتمل: <strong>$completed_count</strong> طلب</p>
        <p>❌ ملغي: <strong>$canceled_count</strong> طلب</p>
        <p>💰 تم استرجاع: <strong>$refunded_count</strong> طلب (المبلغ الإجمالي: <strong>$" . number_format($refunded_total, 2) . "</strong>)</p>
        <p>❌ أخطاء: <strong>$error_count</strong> طلب</p>
        <p>🕐 وقت التشغيل: " . date('Y-m-d H:i:s') . "</p>
      </div>";

echo "<button onclick='location.reload()'>🔄 تشغيل مرة أخرى</button>";
echo "<button onclick='window.location.href=\"orders.php\"' style='background: #10b981; margin-left: 10px;'>← العودة للطلبات</button>";

echo "</div></body></html>";

logMessage("=== انتهى التحديث: Updated=$updated_count, Refunded=$refunded_count, Errors=$error_count ===");
?>