<?php
// =============================================
// cryptomus_webhook.php - معالج إشعارات الدفع من Cryptomus
// =============================================

// إلغاء حد الوقت التنفيذي
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// =============================================
// إعدادات Cryptomus
// =============================================
define('CRYPTOMUS_MERCHANT_ID', '50e41469-5b3b-4ce3-b39f-710dc6765c46');
define('CRYPTOMUS_API_KEY', 'ixjenfEEeKYXtm5rAx0GfvNhLcmoWt5WIJ5DHGOdzUIpV8oeIwiIznJ0s4O4vXaC02wNoMJCARQsIO3mqK4L55naISWHbCwXTW0qc65m9H4OAFmb5iiFGOTPjbzUvT8O');

// سجل الأخطاء
function logWebhook($message, $type = 'info') {
    $logFile = __DIR__ . '/logs/cryptomus_webhook.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] " . print_r($message, true) . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// =============================================
// الاتصال بقاعدة البيانات
// =============================================
require_once 'config.php';

if (!isset($pdo)) {
    logWebhook("Database connection failed", "error");
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// =============================================
// دالة تحديث رصيد المستخدم
// =============================================
function updateUserBalance($pdo, $user_id, $amount, $invoice_id, $method) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $current_balance = $stmt->fetchColumn();

        if ($current_balance === false) {
            throw new Exception("User not found: $user_id");
        }

        $new_balance = $current_balance + $amount;

        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);

        $stmt = $pdo->prepare("
            UPDATE deposits
            SET status = 'paid', updated_at = NOW()
            WHERE invoice_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$invoice_id, $user_id]);

        $pdo->commit();

        logWebhook("Balance updated: User $user_id, Amount: $$amount, New Balance: $$new_balance");

        return [
            'success' => true,
            'old_balance' => $current_balance,
            'new_balance' => $new_balance
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        logWebhook("Error updating balance: " . $e->getMessage(), "error");
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// =============================================
// التحقق من توقيع Cryptomus
// =============================================
function verifyCryptomusSignature($data, $sign, $apiKey) {
    $expectedSign = md5(base64_encode(json_encode($data)) . $apiKey);
    return hash_equals($expectedSign, $sign);
}

// =============================================
// معالجة Webhook
// =============================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

logWebhook("Received webhook data: " . $input);

if (!$data) {
    logWebhook("Invalid JSON received", "error");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// التحقق من التوقيع
$receivedSign = $_SERVER['HTTP_SIGN'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!verifyCryptomusSignature($data, $receivedSign, CRYPTOMUS_API_KEY)) {
    logWebhook("Invalid signature", "error");
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// معالجة الدفع
$result = $data['result'] ?? $data;
$status = $result['status'] ?? '';

if ($status === 'paid' || $status === 'success') {

    $invoice_uuid = $result['uuid'] ?? $result['order_id'] ?? '';
    $amount = floatval($result['amount'] ?? $result['payment_amount'] ?? 0);
    $order_id = $result['order_id'] ?? '';

    if (empty($invoice_uuid)) {
        logWebhook("Missing invoice UUID", "error");
        http_response_code(400);
        echo json_encode(['error' => 'Missing invoice UUID']);
        exit;
    }

    // البحث عن الإيداع
    $stmt = $pdo->prepare("SELECT * FROM deposits WHERE invoice_id = ? AND status = 'pending'");
    $stmt->execute([$invoice_uuid]);
    $deposit = $stmt->fetch();

    if (!$deposit) {
        logWebhook("Deposit not found or already processed: $invoice_uuid", "warning");
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    // تحديث الرصيد
    $result = updateUserBalance($pdo, $deposit['user_id'], $amount, $invoice_uuid, 'cryptomus');

    if ($result['success']) {
        logWebhook("Payment processed successfully: Invoice $invoice_uuid, User {$deposit['user_id']}, Amount $$amount");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed']);
    } else {
        logWebhook("Failed to process payment: " . ($result['error'] ?? 'Unknown error'), "error");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process payment']);
    }
} else {
    logWebhook("Payment status: $status", "info");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'payment_status' => $status]);
}

exit;
?>