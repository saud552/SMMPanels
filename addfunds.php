<?php
// =============================================
// addfunds.php - SMM Panel Add Funds Page (بتصميم جديد)
// =============================================

error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

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
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($site_domain); ?> | Add Funds</title>
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

        .main { margin-top: 73px; padding: 20px; max-width: 700px; margin-left: auto; margin-right: auto; }

        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .page-title i { color: var(--primary); }
        .page-subtitle { font-size: 13px; color: var(--gray-400); }

        .main-card { background: white; border-radius: 16px; padding: 24px; border: 1px solid var(--gray-200); margin-bottom: 24px; }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
        .card-subtitle { font-size: 12px; color: var(--gray-400); margin-bottom: 24px; }

        .select-group { margin-bottom: 20px; position: relative; }
        .select-label { display: block; font-size: 11px; font-weight: 600; color: var(--gray-500); margin-bottom: 5px; }
        .select-label i { margin-right: 4px; color: var(--primary); }
        .custom-select { position: relative; width: 100%; }
        .custom-select-trigger {
            width: 100%;
            padding: 10px 12px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            border-radius: 10px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 100;
        }
        .custom-select-dropdown.open { max-height: 200px; opacity: 1; visibility: visible; overflow-y: auto; }
        .custom-select-option { padding: 10px 12px; cursor: pointer; transition: all 0.2s; border-bottom: 1px solid var(--gray-100); font-size: 12px; }
        .custom-select-option:hover { background: var(--gray-50); }
        .custom-select-option.selected { background: rgba(99,102,241,0.1); color: var(--primary); }

        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gray-500); margin-bottom: 5px; }
        .input-group label i { margin-right: 4px; color: var(--primary); }
        .input-group input {
            width: 100%;
            padding: 10px 12px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 13px;
        }
        .input-group input:focus { outline: none; border-color: var(--primary); background: white; }
        .amount-range { font-size: 10px; color: var(--gray-400); margin-top: 4px; }

        .summary-card { background: var(--gray-50); border-radius: 12px; padding: 16px; margin-bottom: 20px; }
        .summary-title { font-size: 13px; font-weight: 700; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--gray-200); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; }
        .summary-row.total { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--gray-200); font-weight: 700; font-size: 14px; color: var(--primary); }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .success-box { background: linear-gradient(135deg, var(--success), #059669); border-radius: 14px; padding: 14px; margin-bottom: 20px; color: white; display: none; animation: slideIn 0.3s ease; }
        .success-box.show { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .success-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .success-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .success-close { background: rgba(255,255,255,0.2); border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; color: white; font-size: 12px; }
        .success-details { font-size: 11px; line-height: 1.5; }

        .recent-card { background: white; border-radius: 16px; padding: 20px; border: 1px solid var(--gray-200); }
        .recent-title { font-size: 14px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .recent-title i { color: var(--primary); }
        .deposits-table { width: 100%; border-collapse: collapse; }
        .deposits-table th, .deposits-table td { padding: 10px 6px; text-align: left; border-bottom: 1px solid var(--gray-100); font-size: 11px; }
        .deposits-table th { color: var(--gray-500); font-weight: 600; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 20px; font-size: 9px; font-weight: 600; }
        .status-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .status-paid { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .status-expired { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .binance-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .binance-modal.show { display: flex; }
        .binance-modal-content {
            background: white;
            border-radius: 24px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
            animation: modalSlideUp 0.3s ease;
        }
        @keyframes modalSlideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .binance-header { text-align: center; margin-bottom: 20px; }
        .binance-icon { width: 50px; height: 50px; background: linear-gradient(135deg, #F0B90B, #f3ba2f); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
        .binance-icon i { font-size: 26px; color: #1e1e1e; }
        .binance-title { font-size: 18px; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
        .binance-subtitle { font-size: 11px; color: var(--gray-400); }
        .binance-info { background: linear-gradient(135deg, rgba(240,185,11,0.1), rgba(243,186,47,0.05)); border-radius: 12px; padding: 12px; margin-bottom: 16px; text-align: center; }
        .binance-amount { font-size: 24px; font-weight: 800; color: #F0B90B; }
        .binance-address { background: var(--gray-50); border-radius: 10px; padding: 10px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .binance-address span { font-family: monospace; font-size: 11px; word-break: break-all; color: var(--dark); }
        .copy-btn { background: var(--primary); border: none; border-radius: 6px; padding: 6px 10px; color: white; cursor: pointer; font-size: 11px; }
        .binance-input-group { margin-bottom: 16px; }
        .binance-input-group label { display: block; font-size: 11px; font-weight: 600; margin-bottom: 6px; color: var(--gray-500); }
        .binance-input-group input { width: 100%; padding: 10px 12px; border: 1px solid var(--gray-200); border-radius: 10px; font-size: 13px; }
        .binance-input-group input:focus { outline: none; border-color: var(--primary); }
        .verify-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #F0B90B, #f3ba2f); border: none; border-radius: 30px; color: #1e1e1e; font-weight: 700; font-size: 13px; cursor: pointer; }
        .close-modal-btn { width: 100%; padding: 10px; background: var(--gray-100); border: none; border-radius: 30px; margin-top: 10px; cursor: pointer; color: var(--gray-600); font-size: 12px; }

        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3); backdrop-filter: blur(2px); z-index: 1000; display: flex; align-items: center; justify-content: center; display: none; }
        .loading-overlay.show { display: flex; }
        .spinner { width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.3); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(100px); background: var(--dark); color: white; padding: 8px 16px; border-radius: 40px; font-size: 12px; z-index: 200; transition: 0.3s; opacity: 0; }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        @media (max-width: 600px) {
            .main { padding: 16px; margin-top: 70px; }
            .deposits-table { min-width: 500px; }
            .binance-modal-content { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Header مثل dashboard -->
<div class="header">
    <a href="dashboard.php" class="logo"><?php echo htmlspecialchars($site_domain); ?></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<!-- القائمة المنسدلة الرئيسية مثل dashboard -->
<div class="main-dropdown" id="mainDropdown">
    <div class="dropdown-container">
        <a href="dashboard.php" class="menu-item"><i class="fas fa-shopping-cart"></i> New Order</a>
        <a href="orders.php" class="menu-item"><i class="fas fa-list-alt"></i> Orders</a>
        <a href="services.php" class="menu-item"><i class="fas fa-cogs"></i> Services</a>
        <a href="addfunds.php" class="menu-item active"><i class="fas fa-plus-circle"></i> Add Funds</a>
        <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
        <a href="child-panel.php" class="menu-item"><i class="fas fa-link"></i> Child Panel</a>
        <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
        <div class="menu-divider"></div>
        <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title"><i class="fas fa-plus-circle"></i><span>Add Funds</span></div>
        <div class="page-subtitle">Deposit funds into your account balance</div>
    </div>

    <div class="main-card">
        <div class="success-box" id="successBox">
            <div class="success-header"><div class="success-title"><i class="fas fa-check-circle"></i><span>Invoice Created!</span></div><button class="success-close" onclick="closeSuccessBox()"><i class="fas fa-times"></i></button></div>
            <div class="success-details" id="successDetails"></div>
        </div>

        <?php if (empty($payment_methods)): ?>
        <div style="background: #fee2e2; color: #dc2626; padding: 14px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 13px;">
            <i class="fas fa-exclamation-triangle"></i> No payment methods available. Contact support.
        </div>
        <?php else: ?>
        <div class="select-group">
            <label class="select-label"><i class="fas fa-credit-card"></i> Payment Method</label>
            <div class="custom-select" id="methodSelect">
                <div class="custom-select-trigger" id="methodTrigger">
                    <span id="selectedMethod"><?php echo htmlspecialchars($payment_methods[array_key_first($payment_methods)]['name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="custom-select-dropdown" id="methodDropdown">
                    <?php foreach ($payment_methods as $key => $method): ?>
                    <div class="custom-select-option <?php echo $key === array_key_first($payment_methods) ? 'selected' : ''; ?>" data-value="<?php echo $key; ?>">
                        <?php echo htmlspecialchars($method['name']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="input-group">
            <label><i class="fas fa-dollar-sign"></i> Amount (USD)</label>
            <input type="number" id="amount" step="0.01" min="1" max="10000" value="10">
            <div class="amount-range">Minimum: $1 | Maximum: $10,000</div>
        </div>

        <div class="summary-card">
            <div class="summary-title">Payment Summary</div>
            <div class="summary-row"><span>Amount:</span><span id="summaryAmount">$10.00</span></div>
            <div class="summary-row"><span>Method:</span><span id="summaryMethod"><?php echo htmlspecialchars($payment_methods[array_key_first($payment_methods)]['name']); ?></span></div>
            <div class="summary-row total"><span>Total to Pay:</span><span id="summaryTotal">$10.00</span></div>
        </div>

        <button class="submit-btn" id="payBtn"><i class="fas fa-paper-plane"></i> Pay Now</button>
    </div>

    <div class="recent-card">
        <div class="recent-title"><i class="fas fa-history"></i> Recent Deposits</div>
        <div style="overflow-x: auto;">
            <table class="deposits-table">
                <thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_deposits)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--gray-400);">No deposits yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_deposits as $deposit): ?>
                        <tr>
                            <td>#<?php echo $deposit['id']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($deposit['created_at'])); ?></span></td>
                            <td>$<?php echo number_format($deposit['amount'], 2); ?></span></td>
                            <td><?php echo ucfirst($deposit['method']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $deposit['status']; ?>"><?php echo ucfirst($deposit['status']); ?></span></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Binance Modal -->
<div class="binance-modal" id="binanceModal">
    <div class="binance-modal-content">
        <div class="binance-header">
            <div class="binance-icon"><i class="fab fa-binance"></i></div>
            <h3 class="binance-title">Binance Pay</h3>
            <p class="binance-subtitle">Complete your payment using Binance</p>
        </div>
        <div class="binance-info">
            <div style="font-size: 11px; margin-bottom: 6px;">Amount to Pay:</div>
            <div class="binance-amount" id="binanceAmount">$10.00</div>
        </div>
        <div class="binance-address">
            <span id="binanceWallet"><?php echo htmlspecialchars($binance_wallet ?: '833208397'); ?></span>
            <button class="copy-btn" onclick="copyWallet()"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div class="binance-input-group">
            <label>Transaction ID (Order ID)</label>
            <input type="text" id="transactionId" placeholder="Enter the Order ID from Binance">
        </div>
        <button class="verify-btn" id="verifyPaymentBtn" onclick="verifyBinancePayment()">Verify Payment</button>
        <button class="close-modal-btn" onclick="closeBinanceModal()">Cancel</button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    let currentMethod = '<?php echo array_key_first($payment_methods); ?>';
    let currentAmount = 10;
    const paymentMethods = <?php echo json_encode($payment_methods); ?>;

    const methodTrigger = document.getElementById('methodTrigger');
    const methodDropdown = document.getElementById('methodDropdown');
    const amountInput = document.getElementById('amount');
    const summaryAmount = document.getElementById('summaryAmount');
    const summaryMethod = document.getElementById('summaryMethod');
    const summaryTotal = document.getElementById('summaryTotal');
    const payBtn = document.getElementById('payBtn');
    const successBox = document.getElementById('successBox');
    const successDetails = document.getElementById('successDetails');
    const binanceModal = document.getElementById('binanceModal');
    const menuBtn = document.getElementById('menuBtn');
    const mainDropdown = document.getElementById('mainDropdown');

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function showLoading() { document.getElementById('loadingOverlay').classList.add('show'); }
    function hideLoading() { document.getElementById('loadingOverlay').classList.remove('show'); }
    function closeSuccessBox() { successBox.classList.remove('show'); }

    function closeAllDropdowns() {
        methodTrigger.classList.remove('active');
        methodDropdown.classList.remove('open');
    }

    methodTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (methodDropdown.classList.contains('open')) {
            methodTrigger.classList.remove('active');
            methodDropdown.classList.remove('open');
        } else {
            closeAllDropdowns();
            methodTrigger.classList.add('active');
            methodDropdown.classList.add('open');
        }
    });

    document.querySelectorAll('.custom-select-option').forEach(option => {
        option.addEventListener('click', () => {
            const value = option.dataset.value;
            const text = option.textContent;
            currentMethod = value;
            document.getElementById('selectedMethod').textContent = text;
            summaryMethod.textContent = text;
            document.querySelectorAll('.custom-select-option').forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            closeAllDropdowns();
        });
    });

    document.addEventListener('click', (e) => {
        if (!methodTrigger.contains(e.target) && !methodDropdown.contains(e.target)) {
            methodTrigger.classList.remove('active');
            methodDropdown.classList.remove('open');
        }
    });

    function updateAmount() {
        let amount = parseFloat(amountInput.value);
        if (isNaN(amount)) amount = 1;
        if (amount < 1) amount = 1;
        if (amount > 10000) amount = 10000;
        amountInput.value = amount;
        currentAmount = amount;
        summaryAmount.textContent = '$' + amount.toFixed(2);
        summaryTotal.textContent = '$' + amount.toFixed(2);
        document.getElementById('binanceAmount').innerHTML = '$' + amount.toFixed(2);
    }

    amountInput.addEventListener('input', updateAmount);
    amountInput.addEventListener('blur', updateAmount);

    function openBinanceModal() {
        binanceModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeBinanceModal() {
        binanceModal.classList.remove('show');
        document.body.style.overflow = '';
        document.getElementById('transactionId').value = '';
    }

    window.copyWallet = function() {
        const wallet = document.getElementById('binanceWallet').textContent;
        navigator.clipboard.writeText(wallet).then(() => {
            showToast('Wallet address copied!', 'success');
        });
    }

    window.verifyBinancePayment = async function() {
        const transactionId = document.getElementById('transactionId').value.trim();
        if (!transactionId) {
            showToast('Please enter Transaction ID', 'error');
            return;
        }
        showLoading();
        try {
            const formData = new FormData();
            formData.append('action', 'verify_binance_payment');
            formData.append('order_id', transactionId);
            formData.append('amount', currentAmount);
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                closeBinanceModal();
                successDetails.innerHTML = '<div><strong>Amount:</strong> $' + result.amount.toFixed(2) + '</div><div><strong>Payment Method:</strong> Binance Pay</div><div><strong>Status:</strong> Completed</div>';
                successBox.classList.add('show');
                showToast(result.message, 'success');
                setTimeout(() => { location.reload(); }, 2000);
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            showToast('Network error, please try again', 'error');
        } finally {
            hideLoading();
        }
    }

    async function createInvoice() {
        let amount = parseFloat(amountInput.value);
        if (isNaN(amount) || amount < 1) {
            showToast('Amount must be at least $1', 'error');
            return;
        }
        if (amount > 10000) {
            showToast('Amount cannot exceed $10,000', 'error');
            return;
        }

        if (currentMethod === 'binance') {
            openBinanceModal();
            return;
        }

        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        showLoading();

        try {
            const formData = new FormData();
            formData.append('action', 'create_invoice');
            formData.append('amount', amount);
            formData.append('method', currentMethod);
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                const methodName = result.method === 'heleket' ? 'Heleket' : 'Cryptomus';
                successDetails.innerHTML = '<div><strong>Amount:</strong> $' + result.amount.toFixed(2) + '</div><div><strong>Method:</strong> ' + methodName + '</div><div><strong>Invoice ID:</strong> ' + result.invoice_id + '</div><div><strong>Status:</strong> Pending</div><div style="margin-top: 8px;">Redirecting to payment page...</div>';
                successBox.classList.add('show');
                showToast(result.message, 'success');
                setTimeout(() => { window.location.href = result.payment_url; }, 1500);
            } else {
                showToast(result.error, 'error');
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Pay Now';
            }
        } catch (error) {
            showToast('Network error, please try again', 'error');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Pay Now';
        } finally {
            hideLoading();
        }
    }

    payBtn.addEventListener('click', createInvoice);

    // القائمة المنسدلة الرئيسية
    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        mainDropdown.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !mainDropdown.contains(e.target)) {
            mainDropdown.classList.remove('open');
        }
    });

    updateAmount();
</script>
</body>
</html>