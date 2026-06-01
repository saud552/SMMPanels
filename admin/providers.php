<?php
require_once 'header.php';
require_once '../config.php';

// إضافة مزود جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider'])) {
    $stmt = $pdo->prepare("INSERT INTO api_providers (name, api_url, api_key, status, priority) VALUES (?, ?, ?, 'active', ?)");
    $stmt->execute([
        $_POST['name'],
        rtrim($_POST['api_url'], '/') . '/api/v2',
        $_POST['api_key'],
        intval($_POST['priority'])
    ]);
    $success = "Provider added successfully";
}

// تحديث مزود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_provider'])) {
    $stmt = $pdo->prepare("UPDATE api_providers SET api_key = ?, status = ? WHERE id = ?");
    $stmt->execute([$_POST['api_key'], $_POST['status'], $_POST['provider_id']]);
    $success = "Provider updated successfully";
}

// حذف مزود
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM api_providers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: providers.php');
    exit;
}

// جلب المزودين
$providers = $pdo->query("SELECT * FROM api_providers ORDER BY priority")->fetchAll();
?>
<div class="page-title">
    <i class="fas fa-cloud-upload-alt"></i>
    <span>API Providers</span>
</div>

<?php if (isset($success)): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Add New Provider</div>
    <form method="POST">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div class="form-group">
                <label>Provider Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g., JustAnotherPanel" required>
            </div>
            <div class="form-group">
                <label>API URL</label>
                <input type="url" name="api_url" class="form-control" placeholder="https://example.com" required>
                <small style="color:#94a3b8;">Will automatically add /api/v2</small>
            </div>
            <div class="form-group">
                <label>API Key</label>
                <input type="text" name="api_key" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Priority (lower = higher)</label>
                <input type="number" name="priority" class="form-control" value="1">
            </div>
        </div>
        <button type="submit" name="add_provider" class="btn btn-primary" style="margin-top: 16px;">Add Provider</button>
    </form>
</div>

<div class="card">
    <div class="card-title">Existing Providers</div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>API URL</th><th>API Key</th><th>Status</th><th>Priority</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $provider): ?>
                <tr>
                    <td><?php echo $provider['id']; ?></td>
                    <td><?php echo htmlspecialchars($provider['name']); ?></td>
                    <td><code style="font-size: 11px;"><?php echo htmlspecialchars($provider['api_url']); ?></code></td>
                    <td><code style="font-size: 11px;"><?php echo substr($provider['api_key'], 0, 20); ?>...</code></td>
                    <td><span class="badge <?php echo $provider['status'] == 'active' ? 'badge-completed' : 'badge-failed'; ?>"><?php echo ucfirst($provider['status']); ?></span></td>
                    <td><?php echo $provider['priority']; ?></td>
                    <td>
                        <button class="btn btn-sm" onclick="showEditModal(<?php echo $provider['id']; ?>, '<?php echo htmlspecialchars($provider['api_key']); ?>', '<?php echo $provider['status']; ?>')" style="background:#6366f1; color:white;">Edit</button>
                        <a href="?delete=<?php echo $provider['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this provider?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal لتعديل المزود -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Provider</h3>
        <form method="POST">
            <input type="hidden" name="provider_id" id="edit_provider_id">
            <div class="form-group">
                <label>API Key</label>
                <input type="text" name="api_key" id="edit_api_key" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeEditModal()" style="background:#e2e8f0;">Cancel</button>
                <button type="submit" name="update_provider" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function showEditModal(id, apiKey, status) {
    document.getElementById('edit_provider_id').value = id;
    document.getElementById('edit_api_key').value = apiKey;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}
</script>

<?php require_once 'sidebar.php'; ?>