<?php
// =============================================
// admin/import_services.php - استيراد الخدمات من API (متوافق مع التنسيق القياسي)
// =============================================
require_once 'header.php';
require_once '../config.php';

$import_message = '';
$import_error = '';

// جلب الفئات
$categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY platform_id, sort_order")->fetchAll();

// جلب المزودين
$providers = $pdo->query("SELECT * FROM api_providers WHERE status = 'active' ORDER BY priority")->fetchAll();

// =============================================
// معالجة الاستيراد
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_from_api'])) {
    $provider_id = intval($_POST['provider_id']);
    $target_category_id = intval($_POST['target_category_id'] ?? 0);

    if ($provider_id <= 0) {
        $import_error = "Please select a provider.";
    } elseif ($target_category_id <= 0) {
        $import_error = "Please select a category.";
    } else {
        // التحقق من وجود الفئة
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? AND status = 1");
        $stmt->execute([$target_category_id]);
        $category = $stmt->fetch();

        if (!$category) {
            $import_error = "Selected category does not exist.";
        } else {
            // جلب بيانات المزود
            $stmt = $pdo->prepare("SELECT * FROM api_providers WHERE id = ? AND status = 'active'");
            $stmt->execute([$provider_id]);
            $provider = $stmt->fetch();

            if ($provider) {
                // استدعاء API لجلب الخدمات (باستخدام التنسيق القياسي)
                $api_url = rtrim($provider['api_url'], '/');
                $api_key = $provider['api_key'];

                $post_data = [
                    'key' => $api_key,
                    'action' => 'services'
                ];

                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200 && $response) {
                    $services = json_decode($response, true);

                    if (is_array($services) && count($services) > 0) {
                        $synced = 0;
                        $updated = 0;

                        foreach ($services as $api_service) {
                            // استخراج البيانات من التنسيق القياسي
                            $service_id = $api_service['service'] ?? null;
                            $service_name = $api_service['name'] ?? 'Unknown Service';
                            $min_qty = intval($api_service['min'] ?? 100);
                            $max_qty = intval($api_service['max'] ?? 10000);
                            $price = floatval($api_service['rate'] ?? 0);

                            // التحقق من أن السعر لكل 1000 (إذا كان السعر صغير جداً، ربما هو السعر الإجمالي)
                            if ($price > 0 && $price < 1) {
                                // السعر بالفعل لكل 1000
                            } elseif ($price > 1 && $price < 100) {
                                // قد يكون السعر للوحدة الواحدة، نحتاج إلى ضربه في 1000
                                $price = $price;
                            }

                            if ($service_id) {
                                // التحقق من وجود الخدمة
                                $stmt = $pdo->prepare("SELECT id FROM services WHERE provider_id = ? AND api_service_id = ?");
                                $stmt->execute([$provider_id, $service_id]);
                                $existing = $stmt->fetch();

                                if ($existing) {
                                    // تحديث الخدمة الموجودة
                                    $stmt = $pdo->prepare("
                                        UPDATE services
                                        SET name = ?, min_qty = ?, max_qty = ?, price_per_1000 = ?,
                                            category_id = ?, updated_at = NOW()
                                        WHERE provider_id = ? AND api_service_id = ?
                                    ");
                                    $stmt->execute([
                                        $service_name,
                                        $min_qty,
                                        $max_qty,
                                        $price,
                                        $target_category_id,
                                        $provider_id,
                                        $service_id
                                    ]);
                                    $updated++;
                                } else {
                                    // إضافة خدمة جديدة
                                    $stmt = $pdo->prepare("
                                        INSERT INTO services (
                                            provider_id, api_service_id, name, min_qty, max_qty,
                                            price_per_1000, category_id, status, created_at
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive', NOW())
                                    ");
                                    $stmt->execute([
                                        $provider_id,
                                        $service_id,
                                        $service_name,
                                        $min_qty,
                                        $max_qty,
                                        $price,
                                        $target_category_id
                                    ]);
                                    $synced++;
                                }
                            }
                        }

                        // تحديث وقت آخر مزامنة
                        $stmt = $pdo->prepare("UPDATE api_providers SET last_sync = NOW() WHERE id = ?");
                        $stmt->execute([$provider_id]);

                        $import_message = "✅ Successfully imported from '{$provider['name']}' into category '{$category['name']}':<br>
                                          🆕 New services: $synced<br>
                                          🔄 Updated services: $updated";
                    } else {
                        $import_error = "No services found in API response. Response: " . substr($response, 0, 200);
                    }
                } else {
                    $import_error = "Failed to connect to API. HTTP Code: $http_code<br>
                                    URL: $api_url<br>
                                    Response: " . substr($response, 0, 200);
                }
            } else {
                $import_error = "Provider not found or inactive.";
            }
        }
    }
}
?>
<div class="page-title">
    <i class="fas fa-cloud-download-alt"></i>
    <span>Import Services from API</span>
</div>

<?php if ($import_message): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo $import_message; ?>
</div>
<?php endif; ?>

<?php if ($import_error): ?>
<div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo $import_error; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Import Services from API Provider</div>
    <form method="POST">
        <div class="form-group">
            <label><i class="fas fa-cloud"></i> Select API Provider</label>
            <select name="provider_id" class="form-control" required>
                <option value="">-- Select Provider --</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?php echo $provider['id']; ?>">
                        <?php echo htmlspecialchars($provider['name']); ?> - <?php echo htmlspecialchars($provider['api_url']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label><i class="fas fa-folder"></i> Select Category</label>
            <select name="target_category_id" class="form-control" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($providers)): ?>
            <div style="margin-top: 16px; padding: 12px; background: #fee2e2; border-radius: 12px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i> No API providers found.
                <a href="providers.php" style="color: #dc2626;">Add a provider first</a>
            </div>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
            <div style="margin-top: 16px; padding: 12px; background: #fee2e2; border-radius: 12px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i> No categories found.
                <a href="../categories.php" style="color: #dc2626;">Add a category first</a>
            </div>
        <?php endif; ?>

        <button type="submit" name="import_from_api" class="btn btn-primary" style="margin-top: 20px;" <?php echo (empty($providers) || empty($categories)) ? 'disabled' : ''; ?>>
            <i class="fas fa-sync-alt"></i> Import Services
        </button>
    </form>
</div>

<div class="card">
    <div class="card-title">API Response Format (Standard)</div>
    <div style="background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 12px; overflow-x: auto;">
        <pre style="color: #e2e8f0; margin: 0;">
[
    {
        "service": 1,
        "name": "Followers",
        "type": "Default",
        "category": "First Category",
        "rate": "0.90",
        "min": "50",
        "max": "10000",
        "refill": true,
        "cancel": true
    },
    {
        "service": 2,
        "name": "Comments",
        "type": "Custom Comments",
        "category": "Second Category",
        "rate": "8",
        "min": "10",
        "max": "1500",
        "refill": false,
        "cancel": true
    }
]</pre>
    </div>
    <div style="margin-top: 12px; font-size: 12px; color: #64748b;">
        <strong>Note:</strong> The API should return an array of services with at least:
        <code>service</code>, <code>name</code>, <code>rate</code>, <code>min</code>, <code>max</code>
    </div>
</div>

<?php require_once 'sidebar.php'; ?>