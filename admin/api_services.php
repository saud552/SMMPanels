<?php
// =============================================
// admin/api_services.php - عرض خدمات API مع إضافة Profit
// =============================================
require_once 'header.php';
require_once '../config.php';

$message = '';
$error = '';

// التحقق من وجود بيانات في الجلسة
if (!isset($_SESSION['temp_api_services']) || !isset($_SESSION['selected_api_categories'])) {
    header('Location: api_categories.php');
    exit;
}

$services = $_SESSION['temp_api_services'];
$selected_categories = $_SESSION['selected_api_categories'];
$provider_id = $_SESSION['temp_api_provider_id'];

// فلترة الخدمات حسب الفئات المختارة
$filtered_services = [];
foreach ($services as $service) {
    $category = $service['category'] ?? 'Other';
    if (in_array($category, $selected_categories)) {
        $filtered_services[] = $service;
    }
}

// جلب الفئات المحلية لعرضها في القائمة
$local_categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// معالجة إضافة الخدمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_selected_services'])) {
    $selected_service_ids = $_POST['selected_services'] ?? [];
    $profit_percentages = $_POST['profit_percentage'] ?? [];
    $target_categories = $_POST['target_category'] ?? [];

    if (empty($selected_service_ids)) {
        $error = "Please select at least one service to import.";
    } else {
        $imported = 0;
        $skipped = 0;

        foreach ($selected_service_ids as $service_id) {
            // البحث عن الخدمة في المصفوفة
            $service_data = null;
            foreach ($filtered_services as $svc) {
                if ($svc['service'] == $service_id) {
                    $service_data = $svc;
                    break;
                }
            }

            if ($service_data) {
                $api_price = floatval($service_data['rate'] ?? 0);
                $profit_percentage = floatval($profit_percentages[$service_id] ?? 0);

                // حساب السعر بعد إضافة الربح
                $final_price = $api_price * (1 + ($profit_percentage / 100));

                $target_category_id = intval($target_categories[$service_id] ?? 0);

                if ($target_category_id <= 0) {
                    $skipped++;
                    continue;
                }

                // التحقق من وجود الخدمة مسبقاً
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
                        $service_data['name'],
                        intval($service_data['min'] ?? 100),
                        intval($service_data['max'] ?? 10000),
                        $final_price,
                        $target_category_id,
                        $provider_id,
                        $service_id
                    ]);
                } else {
                    // إضافة خدمة جديدة
                    $stmt = $pdo->prepare("
                        INSERT INTO services (
                            provider_id, api_service_id, name, min_qty, max_qty,
                            price_per_1000, category_id, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $provider_id,
                        $service_id,
                        $service_data['name'],
                        intval($service_data['min'] ?? 100),
                        intval($service_data['max'] ?? 10000),
                        $final_price,
                        $target_category_id
                    ]);
                }
                $imported++;
            }
        }

        $message = "✅ Successfully imported/updated $imported services. Skipped: $skipped";

        // تنظيف الجلسة
        // unset($_SESSION['temp_api_services']);
        // unset($_SESSION['selected_api_categories']);
    }
}

// تجميع الخدمات حسب الفئة
$services_by_category = [];
foreach ($filtered_services as $service) {
    $category = $service['category'] ?? 'Other';
    if (!isset($services_by_category[$category])) {
        $services_by_category[$category] = [];
    }
    $services_by_category[$category][] = $service;
}
?>
<div class="page-title">
    <i class="fas fa-list-alt"></i>
    <span>Import Services from API</span>
</div>

<?php if ($message): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<form method="POST" id="importForm">
    <?php foreach ($services_by_category as $category_name => $category_services): ?>
    <div class="card">
        <div class="card-title" style="background: linear-gradient(135deg, #6366f1, #ec4899); color: white; margin: -24px -24px 20px -24px; padding: 15px 24px; border-radius: 20px 20px 0 0;">
            <i class="fas fa-folder"></i> Category: <?php echo htmlspecialchars($category_name); ?>
            <label style="float: right; color: white;">
                <input type="checkbox" class="select-all-category" data-category="<?php echo md5($category_name); ?>"> Select All
            </label>
        </div>

        <div style="overflow-x: auto;">
            <table class="api-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" class="select-all-global"></th>
                        <th>Service ID</th>
                        <th>Service Name</th>
                        <th>Type</th>
                        <th>API Price/1K</th>
                        <th>Profit %</th>
                        <th>Your Price/1K</th>
                        <th>Min/Max</th>
                        <th>Target Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_services as $service):
                        $api_price = floatval($service['rate'] ?? 0);
                        $service_id = $service['service'];
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_services[]" value="<?php echo $service_id; ?>" class="service-checkbox" data-category="<?php echo md5($category_name); ?>">
                        </td>
                        <td><?php echo $service_id; ?></td>
                        <td><?php echo htmlspecialchars($service['name']); ?></td>
                        <td><?php echo htmlspecialchars($service['type'] ?? 'Default'); ?></td>
                        <td>
                            $<?php echo number_format($api_price, 4); ?>
                            <input type="hidden" name="api_price[<?php echo $service_id; ?>]" value="<?php echo $api_price; ?>">
                        </td>
                        <td>
                            <input type="number" name="profit_percentage[<?php echo $service_id; ?>]"
                                   class="form-control profit-input" style="width: 80px;"
                                   value="0" step="1" min="0" max="500"
                                   data-service="<?php echo $service_id; ?>"
                                   data-api-price="<?php echo $api_price; ?>">
                        </td>
                        <td>
                            <span class="final-price" id="price_<?php echo $service_id; ?>">
                                $<?php echo number_format($api_price, 4); ?>
                            </span>
                        </td>
                        <td><?php echo $service['min'] ?? '?'; ?> / <?php echo $service['max'] ?? '?'; ?></td>
                        <td>
                            <select name="target_category[<?php echo $service_id; ?>]" class="form-control" style="min-width: 150px;" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($local_categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="card" style="text-align: center;">
        <button type="submit" name="import_selected_services" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px;">
            <i class="fas fa-cloud-upload-alt"></i> Import Selected Services
        </button>
        <a href="api_categories.php" class="btn" style="background: #e2e8f0; margin-left: 10px;">
            <i class="fas fa-arrow-left"></i> Back to Categories
        </a>
    </div>
</form>

<script>
// تحديث السعر النهائي عند تغيير نسبة الربح
document.querySelectorAll('.profit-input').forEach(input => {
    input.addEventListener('input', function() {
        const serviceId = this.dataset.service;
        const apiPrice = parseFloat(this.dataset.apiPrice);
        const profit = parseFloat(this.value) || 0;
        const finalPrice = apiPrice * (1 + (profit / 100));
        document.getElementById(`price_${serviceId}`).innerHTML = '$' + finalPrice.toFixed(4);
    });
});

// تحديد/إلغاء تحديد جميع الخدمات في الفئة
document.querySelectorAll('.select-all-category').forEach(btn => {
    btn.addEventListener('change', function() {
        const category = this.dataset.category;
        const checkboxes = document.querySelectorAll(`.service-checkbox[data-category="${category}"]`);
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
});

// تحديد/إلغاء تحديد جميع الخدمات في الصفحة
document.querySelector('.select-all-global')?.addEventListener('change', function() {
    document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

<style>
.api-table th, .api-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}
.api-table th {
    background: #f1f5f9;
    font-weight: 600;
}
.api-table tr:hover {
    background: #f8fafc;
}
</style>

<?php require_once 'sidebar.php'; ?>