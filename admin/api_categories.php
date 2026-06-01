<?php
// =============================================
// admin/api_categories.php - جلب الفئات من API
// =============================================
require_once 'header.php';
require_once '../config.php';

$categories_list = [];
$message = '';
$error = '';

// جلب المزودين
$providers = $pdo->query("SELECT * FROM api_providers WHERE status = 'active' ORDER BY priority")->fetchAll();

// جلب الفئات من API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_categories'])) {
    $provider_id = intval($_POST['provider_id']);

    if ($provider_id <= 0) {
        $error = "Please select a provider.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM api_providers WHERE id = ? AND status = 'active'");
        $stmt->execute([$provider_id]);
        $provider = $stmt->fetch();

        if ($provider) {
            $api_url = rtrim($provider['api_url'], '/');
            $api_key = $provider['api_key'];

            $post_data = ['key' => $api_key, 'action' => 'services'];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            curl_close($ch);

            $services = json_decode($response, true);

            if (is_array($services) && count($services) > 0) {
                // استخراج الفئات الفريدة
                $unique_categories = [];
                foreach ($services as $service) {
                    $category = $service['category'] ?? 'Other';
                    if (!in_array($category, $unique_categories)) {
                        $unique_categories[] = $category;
                    }
                }

                // حفظ الفئات في الجلسة لاستخدامها لاحقاً
                $_SESSION['temp_api_services'] = $services;
                $_SESSION['temp_api_provider_id'] = $provider_id;

                $categories_list = $unique_categories;
                $message = "Found " . count($unique_categories) . " categories from API.";
            } else {
                $error = "No services found in API response.";
            }
        } else {
            $error = "Provider not found.";
        }
    }
}

// حفظ الفئات المختارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected_categories'])) {
    $selected_categories = $_POST['selected_categories'] ?? [];
    $_SESSION['selected_api_categories'] = $selected_categories;
    header('Location: api_services.php');
    exit;
}
?>
<div class="page-title">
    <i class="fas fa-folder-tree"></i>
    <span>Import Categories from API</span>
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

<div class="card">
    <div class="card-title">Step 1: Select API Provider</div>
    <form method="POST">
        <div class="form-group">
            <label>API Provider</label>
            <select name="provider_id" class="form-control" required>
                <option value="">-- Select Provider --</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?php echo $provider['id']; ?>">
                        <?php echo htmlspecialchars($provider['name']); ?> - <?php echo htmlspecialchars($provider['api_url']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" name="fetch_categories" class="btn btn-primary">
            <i class="fas fa-search"></i> Fetch Categories
        </button>
    </form>
</div>

<?php if (!empty($categories_list)): ?>
<div class="card">
    <div class="card-title">Step 2: Select Categories to Import</div>
    <form method="POST">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <?php foreach ($categories_list as $category): ?>
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8fafc; border-radius: 10px;">
                    <input type="checkbox" name="selected_categories[]" value="<?php echo htmlspecialchars($category); ?>">
                    <span><?php echo htmlspecialchars($category); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" name="save_selected_categories" class="btn btn-primary">
            <i class="fas fa-arrow-right"></i> Next: Select Services
        </button>
    </form>
</div>
<?php endif; ?>

<?php require_once 'sidebar.php'; ?>