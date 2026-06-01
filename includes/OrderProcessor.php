<?php
// =============================================
// includes/OrderProcessor.php - معالجة الطلبات عبر API
// =============================================

require_once __DIR__ . '/ApiProvider.php';

class OrderProcessor {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * معالجة طلب جديد وإرساله إلى API المزود
     */
    public function processOrder($order_id) {
        // جلب تفاصيل الطلب
        $stmt = $this->pdo->prepare("
            SELECT o.*, s.provider_id, s.api_service_id
            FROM orders o
            JOIN services s ON o.service_id = s.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        // جلب بيانات المزود
        $stmt = $this->pdo->prepare("SELECT * FROM api_providers WHERE id = ? AND status = 'active'");
        $stmt->execute([$order['provider_id']]);
        $provider_data = $stmt->fetch();

        if (!$provider_data) {
            return ['success' => false, 'error' => 'No active provider found for this service'];
        }

        // إنشاء كائن API
        $api = new ApiProvider(
            $provider_data['id'],
            $provider_data['api_url'],
            $provider_data['api_key'],
            $provider_data['name']
        );

        // إرسال الطلب إلى API
        $result = $api->createOrder($order['api_service_id'], $order['link'], $order['quantity']);

        // حفظ استجابة API في قاعدة البيانات
        $response_json = json_encode($result);
        if ($result['success']) {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET api_order_id = ?, api_response = ?, status = 'processing', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['order_id'], $response_json, $order_id]);

            return ['success' => true, 'api_order_id' => $result['order_id']];
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET api_response = ?, api_error = ?, status = 'failed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$response_json, $result['error'], $order_id]);

            return ['success' => false, 'error' => $result['error']];
        }
    }

    /**
     * تحديث حالة الطلب من API
     */
    public function updateOrderStatus($order_id) {
        // جلب تفاصيل الطلب
        $stmt = $this->pdo->prepare("
            SELECT o.*, p.api_url, p.api_key
            FROM orders o
            JOIN api_providers p ON o.provider_id = p.id
            WHERE o.id = ? AND o.api_order_id IS NOT NULL
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order || !$order['api_order_id']) {
            return ['success' => false, 'error' => 'No API order ID found'];
        }

        // إنشاء كائن API
        $api = new ApiProvider(
            $order['provider_id'],
            $order['api_url'],
            $order['api_key']
        );

        // جلب الحالة من API
        $result = $api->getOrderStatus($order['api_order_id']);

        if ($result['success']) {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET status = ?, start_counter = ?, remains = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['status'], $result['start_count'], $result['remains'], $order_id]);

            return ['success' => true, 'status' => $result['status']];
        }

        return ['success' => false, 'error' => $result['error']];
    }

    /**
     * مزامنة الخدمات من API المزود
     */
    public function syncServicesFromProvider($provider_id) {
        // جلب بيانات المزود
        $stmt = $this->pdo->prepare("SELECT * FROM api_providers WHERE id = ? AND status = 'active'");
        $stmt->execute([$provider_id]);
        $provider_data = $stmt->fetch();

        if (!$provider_data) {
            return ['success' => false, 'error' => 'Provider not found or inactive'];
        }

        // إنشاء كائن API
        $api = new ApiProvider(
            $provider_data['id'],
            $provider_data['api_url'],
            $provider_data['api_key'],
            $provider_data['name']
        );

        // جلب الخدمات من API
        $result = $api->getServices();

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        $synced = 0;
        foreach ($result['services'] as $api_service) {
            // التحقق إذا كانت الخدمة موجودة مسبقاً
            $stmt = $this->pdo->prepare("
                SELECT id FROM services
                WHERE provider_id = ? AND api_service_id = ?
            ");
            $stmt->execute([$provider_id, $api_service['api_service_id']]);

            if (!$stmt->fetch()) {
                // إضافة خدمة جديدة (بدون category_id - سيتم تعيينه يدوياً)
                $stmt = $this->pdo->prepare("
                    INSERT INTO services (provider_id, api_service_id, name, min_qty, max_qty, price_per_1000, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'inactive', NOW())
                ");
                $stmt->execute([
                    $provider_id,
                    $api_service['api_service_id'],
                    $api_service['name'],
                    $api_service['min'],
                    $api_service['max'],
                    $api_service['price_per_1000']
                ]);
                $synced++;
            }
        }

        // تحديث وقت آخر مزامنة
        $stmt = $this->pdo->prepare("UPDATE api_providers SET last_sync = NOW() WHERE id = ?");
        $stmt->execute([$provider_id]);

        return ['success' => true, 'synced' => $synced];
    }
}
?>