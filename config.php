<?php
// =============================================
// ملف الاتصال بقاعدة البيانات - SkyLink iOS
// الإصدار: 2.0.0
// =============================================

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== إعدادات قاعدة البيانات =====
// غير هذه القيم حسب إعدادات الاستضافة الخاصة بك
$db_host     = 'localhost';      // host (غالباً localhost)
$db_name     = '';   // اسم قاعدة البيانات
$db_user     = '';   // اسم المستخدم لقاعدة البيانات
$db_pass     = '';  // كلمة المرور لقاعدة البيانات

// ===== الاتصال بقاعدة البيانات =====
try {
    // إنشاء اتصال PDO
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // تسجيل نجاح الاتصال (للتطوير)
    error_log("✅ اتصال قاعدة البيانات ناجح - " . date('Y-m-d H:i:s'));

} catch (PDOException $e) {
    // تسجيل الخطأ
    error_log("❌ فشل اتصال قاعدة البيانات: " . $e->getMessage() . " - " . date('Y-m-d H:i:s'));

    // رسالة خطأ للمستخدم
    die("
        <div style='direction: rtl; font-family: system-ui; padding: 20px; background: #ffe5e5; border-radius: 12px; margin: 20px; border-right: 4px solid #ff3b30;'>
            <h3 style='color: #ff3b30; margin-bottom: 10px;'>⚠️ عذراً، حدث خطأ في الاتصال بقاعدة البيانات</h3>
            <p style='color: #1c1c1e; margin-bottom: 5px;'>الرجاء المحاولة مرة أخرى لاحقاً.</p>
            <p style='color: #8e8e93; font-size: 14px; margin-top: 10px;'>إذا استمرت المشكلة، تواصل مع الدعم الفني.</p>
        </div>
    ");
}

// =============================================
// دوال المساعدة (Helper Functions)
// =============================================

/**
 * التحقق من تسجيل دخول المستخدم
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * التحقق من تسجيل دخول الأدمن
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * إعادة توجيه المستخدم
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * تنظيف المدخلات
 * @param string $input
 * @return string
 */
function cleanInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * توليد كود عشوائي للكرت
 * @param string $prefix
 * @return string
 */
function generateCardNumber(string $prefix = ''): string {
    $numbers = '';
    for ($i = 0; $i < 10; $i++) {
        $numbers .= rand(0, 9);
    }
    return $prefix . $numbers;
}

/**
 * الحصول على إعدادات الموقع
 * @param PDO $pdo
 * @param string $key
 * @return string
 */
function getSetting(PDO $pdo, string $key): string {
    static $settings = [];

    if (empty($settings)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settings[$key] ?? '';
}
?>