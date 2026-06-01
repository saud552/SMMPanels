<?php
// =============================================
// addfunds.php - SMM Panel Add Funds Page (بتصميم جديد)
// =============================================

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'themes/theme_loader.php';

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

// إنشاء CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================
// جلب إعدادات طرق الدفع من قاعدة البيانات
// =============================================

// جلب إعدادات Heleket
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'heleket_merchant_id'");
$stmt->execute();
$heleket_merchant_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'heleket_api_key'");
$stmt->execute();
$heleket_api_key = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'heleket_status'");
$stmt->execute();
$heleket_status = $stmt->fetchColumn();
if ($heleket_status === null) $heleket_status = 'active';

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'heleket_display_name'");
$stmt->execute();
$heleket_display_name = $stmt->fetchColumn();
if (!$heleket_display_name) $heleket_display_name = 'Heleket (Cryptocurrency)';

// جلب إعدادات Cryptomus
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cryptomus_merchant_id'");
$stmt->execute();
$cryptomus_merchant_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cryptomus_api_key'");
$stmt->execute();
$cryptomus_api_key = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cryptomus_status'");
$stmt->execute();
$cryptomus_status = $stmt->fetchColumn();
if ($cryptomus_status === null) $cryptomus_status = 'active';

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'cryptomus_display_name'");
$stmt->execute();
$cryptomus_display_name = $stmt->fetchColumn();
if (!$cryptomus_display_name) $cryptomus_display_name = 'Cryptomus (USDT)';

// جلب إعدادات Binance
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'binance_api_key'");
$stmt->execute();
$binance_api_key = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'binance_secret_key'");
$stmt->execute();
$binance_secret_key = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'binance_wallet'");
$stmt->execute();
$binance_wallet = $stmt->fetchColumn();
if (!$binance_wallet) $binance_wallet = '833208397';

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'binance_status'");
$stmt->execute();
$binance_status = $stmt->fetchColumn();
if ($binance_status === null) $binance_status = 'active';

$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'binance_display_name'");
$stmt->execute();
$binance_display_name = $stmt->fetchColumn();
if (!$binance_display_name) $binance_display_name = 'Binance Pay (USDT)';

// =============================================
// تكوين طرق الدفع النشطة
// =============================================
$payment_methods = [];

if ($heleket_status === 'active' && !empty($heleket_api_key) && !empty($heleket_merchant_id)) {
    $payment_methods['heleket'] = [
        'name' => $heleket_display_name,
        'type' => 'redirect',
        'status' => 'active'
    ];
}

if ($cryptomus_status === 'active' && !empty($cryptomus_api_key) && !empty($cryptomus_merchant_id)) {
    $payment_methods['cryptomus'] = [
        'name' => $cryptomus_display_name,
        'type' => 'redirect',
        'status' => 'active'
    ];
}

if ($binance_status === 'active' && !empty($binance_api_key) && !empty($binance_secret_key)) {
    $payment_methods['binance'] = [
        'name' => $binance_display_name,
        'type' => 'modal',
        'status' => 'active'
    ];
}

// إعدادات Binance
define('BINANCE_API_KEY', $binance_api_key ?: 'SQ5GbiruogJi4RuRhkqKinFH3YWBwScfe38HJ5pXuc4rTAQELamrzyl7qw0d73Za');
define('BINANCE_SECRET_KEY', $binance_secret_key ?: 'we0xu0kXrWJPzpzQtxRVO076CoEgGAcCyEwBNswDzWSwtDafCdEzbqonlHG7RuUn');
define('BINANCE_BOT_USERNAME', 'Y5_5C_BOT');

$usedFile = __DIR__ . '/data/used_transactions.json';
if (!file_exists(dirname($usedFile))) @mkdir(dirname($usedFile), 0755, true);

// =============================================
// دوال Binance API
// =============================================
function callBinanceAPI($endpoint, $params = []) {
    $base_url = 'https://api.binance.com';
    $timestamp = round(microtime(true) * 1000);
    $params['timestamp'] = $timestamp;
    $params['recvWindow'] = 60000;
    ksort($params);
    $query_string = http_build_query($params);
    $signature = hash_hmac('sha256', $query_string, BINANCE_SECRET_KEY);
    $url = $base_url . $endpoint . '?' . $query_string . '&signature=' . $signature;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-MBX-APIKEY: ' . BINANCE_API_KEY],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function transactionUsed($transaction_id) {
    global $usedFile;
    if (!file_exists($usedFile)) return false;
    $used = json_decode(file_get_contents($usedFile), true);
    return is_array($used) && in_array($transaction_id, $used);
}

function markTransactionUsed($transaction_id) {
    global $usedFile;
    $used = file_exists($usedFile) ? json_decode(file_get_contents($usedFile), true) : [];
    if (!is_array($used)) $used = [];
    $used[] = $transaction_id;
    @file_put_contents($usedFile, json_encode(array_unique($used), JSON_PRETTY_PRINT));
}

function getTransaction($orderId) {
    $data = callBinanceAPI('/sapi/v1/pay/transactions', ['limit'=>100]);
    $list = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    foreach ($list as $tx) {
        if ((isset($tx['orderId']) && (string)$tx['orderId'] === (string)$orderId) ||
            (isset($tx['merchantTradeNo']) && (string)$tx['merchantTradeNo'] === (string)$orderId) ||
            (isset($tx['transactionId']) && (string)$tx['transactionId'] === (string)$orderId)) {
            return $tx;
        }
    }
    return false;
}

// =============================================
// تكامل Heleket API
// =============================================
function generateHeleketSign($data, $apiKey) {
    $jsonData = json_encode($data);
    return md5(base64_encode($jsonData) . $apiKey);
}

function createHeleketInvoice($amount, $orderId, $apiKey, $merchantId, $currency = 'USD', $lifetime = 3600) {
    $apiUrl = 'https://api.heleket.com/v1/payment';

    $data = [
        'amount' => (string)$amount,
        'currency' => $currency,
        'order_id' => (string)$orderId,
        'lifetime' => $lifetime,
        'url_return' => "https://" . $_SERVER['HTTP_HOST'] . "/addfunds.php?success=1",
        'url_callback' => "https://" . $_SERVER['HTTP_HOST'] . "/heleket_webhook.php"
    ];

    $sign = generateHeleketSign($data, $apiKey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'merchant: ' . $merchantId,
        'sign: ' . $sign
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Connection error'];
    }

    $result = json_decode($response, true);

    if (isset($result['state']) && $result['state'] === 0 && isset($result['result']['url'])) {
        return [
            'success' => true,
            'uuid' => $result['result']['uuid'],
            'order_id' => $result['result']['order_id'],
            'amount' => $result['result']['amount'],
            'currency' => $result['result']['currency'],
            'url' => $result['result']['url'],
            'expired_at' => $result['result']['expired_at'] ?? null
        ];
    }

    return ['success' => false, 'error' => $result['message'] ?? 'Failed to create invoice'];
}

// =============================================
// تكامل Cryptomus API
// =============================================
function createCryptomusInvoice($amount, $orderId, $apiKey, $merchantId, $currency = 'USD', $lifetime = 3600) {
    $apiUrl = 'https://api.cryptomus.com/v1/payment';

    $data = [
        'amount' => (string)$amount,
        'currency' => $currency,
        'order_id' => (string)$orderId,
        'lifetime' => $lifetime,
        'url_return' => "https://" . $_SERVER['HTTP_HOST'] . "/addfunds.php?success=1",
        'url_callback' => "https://" . $_SERVER['HTTP_HOST'] . "/cryptomus_webhook.php"
    ];

    $sign = md5(base64_encode(json_encode($data)) . $apiKey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'merchant: ' . $merchantId,
        'sign: ' . $sign
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['state']) && $result['state'] === 0 && isset($result['result']['url'])) {
        return [
            'success' => true,
            'uuid' => $result['result']['uuid'],
            'order_id' => $result['result']['order_id'],
            'amount' => $result['result']['amount'],
            'currency' => $result['result']['currency'],
            'url' => $result['result']['url'],
            'expired_at' => $result['result']['expired_at'] ?? null
        ];
    }

    return ['success' => false, 'error' => $result['message'] ?? 'Failed to create invoice'];
}

// =============================================
// إنشاء جدول deposits إذا لم يكن موجوداً
// =============================================
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `deposits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `method` VARCHAR(50) NOT NULL,
        `invoice_id` VARCHAR(255) DEFAULT NULL,
        `payment_url` TEXT DEFAULT NULL,
        `status` ENUM('pending', 'paid', 'expired', 'failed') DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {}

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

    if ($action === 'verify_binance_payment') {
        $orderId = trim($_POST['order_id'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);

        if (empty($orderId)) {
            echo json_encode(['success' => false, 'error' => 'Please enter Order ID']);
            exit;
        }

        if (transactionUsed($orderId)) {
            echo json_encode(['success' => false, 'error' => 'This transaction has already been used']);
            exit;
        }

        $tx = getTransaction($orderId);

        if (!$tx) {
            echo json_encode(['success' => false, 'error' => 'Transaction not found. Please check the Order ID and try again.']);
            exit;
        }

        $paidAmount = floatval($tx['totalAmount'] ?? $tx['amount'] ?? 0);

        if ($paidAmount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }

        $minAmount = $amount * 0.99;
        $maxAmount = $amount * 1.01;

        if ($paidAmount < $minAmount || $paidAmount > $maxAmount) {
            echo json_encode([
                'success' => false,
                'error' => "Amount mismatch. Expected: $$amount, Received: $$paidAmount"
            ]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$_SESSION['user_id']]);
            $current_balance = $stmt->fetchColumn();

            $new_balance = $current_balance + $paidAmount;

            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $_SESSION['user_id']]);

            $stmt = $pdo->prepare("
                INSERT INTO deposits (user_id, amount, currency, method, invoice_id, payment_url, status, created_at)
                VALUES (?, ?, 'USD', 'binance', ?, NULL, 'paid', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $paidAmount,
                $orderId
            ]);

            markTransactionUsed($orderId);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'new_balance' => $new_balance,
                'amount' => $paidAmount,
                'message' => 'Payment verified successfully!'
            ]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to update balance']);
            exit;
        }
    }

    if ($action === 'create_invoice') {
        $amount = floatval($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? 'heleket';

        if ($amount < 1) {
            echo json_encode(['success' => false, 'error' => 'Minimum amount is $1']);
            exit;
        }

        if ($amount > 10000) {
            echo json_encode(['success' => false, 'error' => 'Maximum amount is $10,000']);
            exit;
        }

        try {
            $orderId = 'SKY_' . time() . '_' . $_SESSION['user_id'] . '_' . rand(1000, 9999);
            $invoice = null;

            if ($method === 'heleket') {
                $invoice = createHeleketInvoice($amount, $orderId, $heleket_api_key, $heleket_merchant_id, 'USD', 3600);
            } elseif ($method === 'cryptomus') {
                $invoice = createCryptomusInvoice($amount, $orderId, $cryptomus_api_key, $cryptomus_merchant_id, 'USD', 3600);
            } elseif ($method === 'binance') {
                echo json_encode([
                    'success' => true,
                    'method' => 'binance',
                    'amount' => $amount,
                    'message' => 'Please complete the payment using Binance Pay'
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
                exit;
            }

            if ($invoice['success']) {
                $stmt = $pdo->prepare("
                    INSERT INTO deposits (user_id, amount, currency, method, invoice_id, payment_url, status, created_at)
                    VALUES (?, ?, 'USD', ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $amount,
                    $method,
                    $invoice['uuid'],
                    $invoice['url']
                ]);

                echo json_encode([
                    'success' => true,
                    'invoice_id' => $invoice['uuid'],
                    'payment_url' => $invoice['url'],
                    'amount' => $amount,
                    'method' => $method,
                    'message' => 'Invoice created successfully!'
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => $invoice['error']]);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to create invoice. Please try again.']);
            exit;
        }
    }
}

// جلب آخر الإيداعات
$stmt = $pdo->prepare("
    SELECT * FROM deposits
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_deposits = $stmt->fetchAll();

$site_domain = $_SERVER['HTTP_HOST'];
?>

$theme_settings = getThemeSettings($pdo);
$data = [
    'user' => $user ?? null,
    'site_title' => 'Addfunds | SkyLink',
    'theme_settings' => $theme_settings
];

ob_start();
?>
<div class='addfunds-content'>
    <h1>Addfunds</h1>
    <div class='card'>
        <p>This is the Addfunds page. Full content integration in progress.</p>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>