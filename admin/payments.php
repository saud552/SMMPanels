<?php
// =============================================
// admin/payments.php - إدارة طرق الدفع
// =============================================
require_once 'header.php';
require_once '../config.php';

// =============================================
// إنشاء جدول settings إذا لم يكن موجوداً
// =============================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `key_name` VARCHAR(100) NOT NULL,
        `value` TEXT,
        `type` ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        `group` VARCHAR(50) DEFAULT 'payment',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key_name` (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// =============================================
// إضافة الإعدادات الافتراضية إذا لم تكن موجودة
// =============================================
$default_settings = [
    // Heleket
    ['heleket_merchant_id', '', 'text', 'payment'],
    ['heleket_api_key', '', 'text', 'payment'],
    ['heleket_status', 'inactive', 'text', 'payment'],
    ['heleket_display_name', 'Heleket (Cryptocurrency)', 'text', 'payment'],
    // Cryptomus
    ['cryptomus_merchant_id', '', 'text', 'payment'],
    ['cryptomus_api_key', '', 'text', 'payment'],
    ['cryptomus_status', 'inactive', 'text', 'payment'],
    ['cryptomus_display_name', 'Cryptomus (USDT)', 'text', 'payment'],
    // Binance
    ['binance_api_key', '', 'text', 'payment'],
    ['binance_secret_key', '', 'text', 'payment'],
    ['binance_wallet', '833208397', 'text', 'payment'],
    ['binance_status', 'inactive', 'text', 'payment'],
    ['binance_display_name', 'Binance Pay (USDT)', 'text', 'payment'],
];

foreach ($default_settings as $setting) {
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE key_name = ?");
    $stmt->execute([$setting[0]]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO settings (key_name, value, type, `group`) VALUES (?, ?, ?, ?)");
        $insert->execute($setting);
    }
}

// =============================================
// معالجة تحديث الإعدادات
// =============================================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';

    if ($payment_method === 'heleket') {
        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'heleket_merchant_id'");
        $stmt->execute([$_POST['merchant_id'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'heleket_api_key'");
        $stmt->execute([$_POST['api_key'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'heleket_status'");
        $stmt->execute([$_POST['status'] ?? 'inactive']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'heleket_display_name'");
        $stmt->execute([$_POST['display_name'] ?? 'Heleket (Cryptocurrency)']);

        $success = "Heleket settings updated successfully";

    } elseif ($payment_method === 'cryptomus') {
        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'cryptomus_merchant_id'");
        $stmt->execute([$_POST['merchant_id'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'cryptomus_api_key'");
        $stmt->execute([$_POST['api_key'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'cryptomus_status'");
        $stmt->execute([$_POST['status'] ?? 'inactive']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'cryptomus_display_name'");
        $stmt->execute([$_POST['display_name'] ?? 'Cryptomus (USDT)']);

        $success = "Cryptomus settings updated successfully";

    } elseif ($payment_method === 'binance') {
        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'binance_api_key'");
        $stmt->execute([$_POST['api_key'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'binance_secret_key'");
        $stmt->execute([$_POST['secret_key'] ?? '']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'binance_wallet'");
        $stmt->execute([$_POST['wallet'] ?? '833208397']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'binance_status'");
        $stmt->execute([$_POST['status'] ?? 'inactive']);

        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'binance_display_name'");
        $stmt->execute([$_POST['display_name'] ?? 'Binance Pay (USDT)']);

        $success = "Binance Pay settings updated successfully";
    }
}

// =============================================
// جلب الإعدادات الحالية
// =============================================
$settings = [];
$stmt = $pdo->query("SELECT key_name, value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key_name']] = $row['value'];
}
?>
<div class="page-title">
    <i class="fas fa-credit-card"></i>
    <span>Payment Methods</span>
</div>

<?php if ($success): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px;">

    <!-- ============================================= -->
    <!-- Heleket Payment Method -->
    <!-- ============================================= -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-coins"></i> Heleket (Cryptocurrency)</span>
            <span class="badge <?php echo ($settings['heleket_status'] ?? 'inactive') == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                <?php echo ucfirst($settings['heleket_status'] ?? 'inactive'); ?>
            </span>
        </div>
        <form method="POST">
            <input type="hidden" name="payment_method" value="heleket">

            <div class="form-group">
                <label><i class="fas fa-building"></i> Merchant ID</label>
                <input type="text" name="merchant_id" class="form-control"
                       value="<?php echo htmlspecialchars($settings['heleket_merchant_id'] ?? ''); ?>"
                       placeholder="Enter merchant ID">
            </div>

            <div class="form-group">
                <label><i class="fas fa-key"></i> API Key</label>
                <input type="text" name="api_key" class="form-control"
                       value="<?php echo htmlspecialchars($settings['heleket_api_key'] ?? ''); ?>"
                       placeholder="Enter API key">
            </div>

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Display Name</label>
                <input type="text" name="display_name" class="form-control"
                       value="<?php echo htmlspecialchars($settings['heleket_display_name'] ?? 'Heleket (Cryptocurrency)'); ?>">
                <small>Name shown to customers on add funds page</small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-power-off"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo ($settings['heleket_status'] ?? 'inactive') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($settings['heleket_status'] ?? 'inactive') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <small>When inactive, this method will not appear to customers</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                <i class="fas fa-save"></i> Save Heleket Settings
            </button>
        </form>
    </div>

    <!-- ============================================= -->
    <!-- Cryptomus Payment Method -->
    <!-- ============================================= -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-lock"></i> Cryptomus (USDT)</span>
            <span class="badge <?php echo ($settings['cryptomus_status'] ?? 'inactive') == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                <?php echo ucfirst($settings['cryptomus_status'] ?? 'inactive'); ?>
            </span>
        </div>
        <form method="POST">
            <input type="hidden" name="payment_method" value="cryptomus">

            <div class="form-group">
                <label><i class="fas fa-building"></i> Merchant ID</label>
                <input type="text" name="merchant_id" class="form-control"
                       value="<?php echo htmlspecialchars($settings['cryptomus_merchant_id'] ?? ''); ?>"
                       placeholder="Enter merchant ID">
            </div>

            <div class="form-group">
                <label><i class="fas fa-key"></i> API Key</label>
                <input type="text" name="api_key" class="form-control"
                       value="<?php echo htmlspecialchars($settings['cryptomus_api_key'] ?? ''); ?>"
                       placeholder="Enter API key">
            </div>

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Display Name</label>
                <input type="text" name="display_name" class="form-control"
                       value="<?php echo htmlspecialchars($settings['cryptomus_display_name'] ?? 'Cryptomus (USDT)'); ?>">
                <small>Name shown to customers on add funds page</small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-power-off"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo ($settings['cryptomus_status'] ?? 'inactive') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($settings['cryptomus_status'] ?? 'inactive') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <small>When inactive, this method will not appear to customers</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                <i class="fas fa-save"></i> Save Cryptomus Settings
            </button>
        </form>
    </div>

    <!-- ============================================= -->
    <!-- Binance Pay Payment Method -->
    <!-- ============================================= -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fab fa-binance"></i> Binance Pay (USDT)</span>
            <span class="badge <?php echo ($settings['binance_status'] ?? 'inactive') == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                <?php echo ucfirst($settings['binance_status'] ?? 'inactive'); ?>
            </span>
        </div>
        <form method="POST">
            <input type="hidden" name="payment_method" value="binance">

            <div class="form-group">
                <label><i class="fas fa-key"></i> API Key</label>
                <input type="text" name="api_key" class="form-control"
                       value="<?php echo htmlspecialchars($settings['binance_api_key'] ?? ''); ?>"
                       placeholder="Enter API key">
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Secret Key</label>
                <input type="password" name="secret_key" class="form-control"
                       value="<?php echo htmlspecialchars($settings['binance_secret_key'] ?? ''); ?>"
                       placeholder="Enter secret key">
                <small>Keep this key secure</small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-wallet"></i> Wallet Address / Merchant ID</label>
                <input type="text" name="wallet" class="form-control"
                       value="<?php echo htmlspecialchars($settings['binance_wallet'] ?? '833208397'); ?>"
                       placeholder="Enter wallet address">
            </div>

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Display Name</label>
                <input type="text" name="display_name" class="form-control"
                       value="<?php echo htmlspecialchars($settings['binance_display_name'] ?? 'Binance Pay (USDT)'); ?>">
                <small>Name shown to customers on add funds page</small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-power-off"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo ($settings['binance_status'] ?? 'inactive') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($settings['binance_status'] ?? 'inactive') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <small>When inactive, this method will not appear to customers</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                <i class="fas fa-save"></i> Save Binance Settings
            </button>
        </form>
    </div>
</div>

<!-- ============================================= -->
<!-- Active Payment Methods Summary -->
<!-- ============================================= -->
<div class="card" style="margin-top: 24px;">
    <div class="card-title">
        <span><i class="fas fa-chart-line"></i> Active Payment Methods</span>
    </div>
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <?php
        $active_methods = [];
        if (($settings['heleket_status'] ?? 'inactive') == 'active' && !empty($settings['heleket_api_key'])) {
            $active_methods[] = ['name' => $settings['heleket_display_name'] ?? 'Heleket', 'status' => 'active'];
        }
        if (($settings['cryptomus_status'] ?? 'inactive') == 'active' && !empty($settings['cryptomus_api_key'])) {
            $active_methods[] = ['name' => $settings['cryptomus_display_name'] ?? 'Cryptomus', 'status' => 'active'];
        }
        if (($settings['binance_status'] ?? 'inactive') == 'active' && !empty($settings['binance_api_key'])) {
            $active_methods[] = ['name' => $settings['binance_display_name'] ?? 'Binance Pay', 'status' => 'active'];
        }
        ?>
        <?php if (empty($active_methods)): ?>
            <div style="padding: 20px; text-align: center; width: 100%;">
                <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: var(--warning); margin-bottom: 12px; display: block;"></i>
                <p>No payment methods are currently active.</p>
                <p style="font-size: 12px; color: var(--gray-400);">Please configure and activate at least one payment method above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($active_methods as $method): ?>
                <div style="background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(99,102,241,0.05)); border-radius: 12px; padding: 12px 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <span><strong><?php echo htmlspecialchars($method['name']); ?></strong> is active</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div style="margin-top: 16px; padding: 12px; background: var(--gray-50); border-radius: 12px; font-size: 12px; color: var(--gray-500);">
        <i class="fas fa-info-circle"></i>
        <strong>Note:</strong> Only active payment methods with valid API credentials will appear on the Add Funds page for customers.
    </div>
</div>

<style>
    .badge-active {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .badge-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .form-group small {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: var(--gray-400);
    }
</style>

<?php require_once 'sidebar.php'; ?>