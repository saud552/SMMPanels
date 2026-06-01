<?php
require_once 'header.php';
require_once '../config.php';
require_once '../includes/OrderProcessor.php';

$processor = new OrderProcessor($pdo);
$message = '';
$error = '';

// جلب المزودين
$providers = $pdo->query("SELECT * FROM api_providers ORDER BY priority")->fetchAll();

// مزامنة الخدمات من مزود معين
if (isset($_GET['sync']) && isset($_GET['provider_id'])) {
    $provider_id = intval($_GET['provider_id']);
    $result = $processor->syncServicesFromProvider($provider_id);

    if ($result['success']) {
        $message = "Successfully synced {$result['synced']} new services from provider";
    } else {
        $error = $result['error'];
    }
}
?>
<div class="page-title">
    <i class="fas fa-sync-alt"></i>
    <span>Sync Services from API Providers</span>
</div>

<?php if ($message): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">API Providers</div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>API URL</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $provider): ?>
                <tr>
                    <td><?php echo $provider['id']; ?></td>
                    <td><?php echo htmlspecialchars($provider['name']); ?></td>
                    <td><code style="font-size: 11px;"><?php echo htmlspecialchars($provider['api_url']); ?></code></td>
                    <td><span class="badge <?php echo $provider['status'] == 'active' ? 'badge-completed' : 'badge-failed'; ?>"><?php echo ucfirst($provider['status']); ?></span></td>
                    <td>
                        <a href="?sync=1&provider_id=<?php echo $provider['id']; ?>" class="btn btn-sm btn-primary" onclick="return confirm('Sync services from this provider?')">
                            <i class="fas fa-sync-alt"></i> Sync Services
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-title">How to Use API Providers</div>
    <div style="font-size: 13px; color: #475569; line-height: 1.6;">
        <ol style="margin-left: 20px;">
            <li>Add a new provider from the <a href="providers.php">API Providers</a> page</li>
            <li>Click "Sync Services" to import services from the provider</li>
            <li>The imported services will appear in the <a href="services.php">Services</a> page</li>
            <li>Customers can then order these services from the dashboard</li>
            <li>When an order is placed, it will be sent to the provider's API automatically</li>
        </ol>
        <hr style="margin: 16px 0;">
        <p><strong>Note:</strong> Each service needs to have <code>api_service_id</code> (the service ID from the provider) and <code>provider_id</code> to work correctly.</p>
    </div>
</div>

<?php require_once 'sidebar.php'; ?>