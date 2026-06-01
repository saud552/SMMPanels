<?php
// =============================================
// heleket_webhook.php - معالج إشعارات الدفع من Heleket
// =============================================

// إلغاء حد الوقت التنفيذي
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// =============================================
// إعدادات Heleket
// =============================================
define('HELEKET_MERCHANT_ID', '75867280-43be-49fb-835e-56125a7db2bb');
define('HELEKET_API_KEY', 'uGY6miwJibV0yThGq3NaQ7Aqx5wvDDjCY0K7Q6yc9aKk0asZu6gxDTzWccIRujxMmUWmMkdTiVadGd63FhE2AGjmhzoHsDQOIoM9BpLYHnSX9Yyb8fPQns1cIfSs7YkZ');

// سجل الأخطاء
function logWebhook($message, $type = 'info') {
    $logFile = __DIR__ . '/logs/heleket_webhook.log';
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

        // قفل الصف لمنع تضارب البيانات
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $current_balance = $stmt->fetchColumn();

        if ($current_balance === false) {
            throw new Exception("User not found: $user_id");
        }

        $new_balance = $current_balance + $amount;

        // تحديث الرصيد
        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);

        // تحديث حالة الإيداع
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
// التحقق من توقيع Heleket (Signature)
// =============================================
function verifyHeleketSignature($data, $signature, $apiKey) {
    $expectedSign = md5(base64_encode(json_encode($data)) . $apiKey);
    return hash_equals($expectedSign, $signature);
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
if (!verifyHeleketSignature($data, $receivedSign, HELEKET_API_KEY)) {
    logWebhook("Invalid signature", "error");
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// معالجة البيانات حسب الحدث
$event = $data['event'] ?? $data['state'] ?? '';
$result = $data['result'] ?? $data;

if ($event === 'payment.success' || ($result['status'] ?? '') === 'paid') {

    $invoice_uuid = $result['uuid'] ?? $result['invoice_uuid'] ?? '';
    $amount = floatval($result['amount'] ?? $result['payment_amount'] ?? 0);
    $order_id = $result['order_id'] ?? '';

    if (empty($invoice_uuid)) {
        logWebhook("Missing invoice UUID", "error");
        http_response_code(400);
        echo json_encode(['error' => 'Missing invoice UUID']);
        exit;
    }

    // البحث عن الإيداع في قاعدة البيانات
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
    $result = updateUserBalance($pdo, $deposit['user_id'], $amount, $invoice_uuid, 'heleket');

    if ($result['success']) {
        logWebhook("Payment processed successfully: Invoice $invoice_uuid, User {$deposit['user_id']}, Amount $$amount");

        // إرسال إشعار للمستخدم (اختياري - يمكن تفعيل Telegram bot)
        // sendTelegramNotification($deposit['user_id'], $amount, $invoice_uuid);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed']);
    } else {
        logWebhook("Failed to process payment: " . ($result['error'] ?? 'Unknown error'), "error");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process payment']);
    }
} else {
    logWebhook("Unhandled event: $event", "warning");
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
}

exit;
?>