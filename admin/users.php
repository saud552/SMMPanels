<?php
require_once 'header.php';
require_once '../config.php';

// إضافة رصيد يدوياً
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);

    if ($user_id && $amount > 0) {
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        $success = "Added $$amount to user balance";
    }
}

// تحديث API Key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_api_key'])) {
    $user_id = intval($_POST['user_id']);
    $new_api_key = 'SKY_' . bin2hex(random_bytes(32)) . '_' . time();

    $stmt = $pdo->prepare("UPDATE users SET api_key = ?, api_key_created_at = NOW() WHERE id = ?");
    $stmt->execute([$new_api_key, $user_id]);
    $success = "API Key updated successfully";
}

// جلب جميع المستخدمين
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<div class="page-title">
    <i class="fas fa-users"></i>
    <span>Manage Users</span>
</div>

<?php if (isset($success)): ?>
<div style="background: #d1fae5; color: #059669; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">All Users</div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Balance</th><th>API Key</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>#<?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>$<?php echo number_format($user['balance'], 2); ?></td>
                    <td>
                        <?php if ($user['api_key']): ?>
                            <span style="font-family: monospace; font-size: 11px;"><?php echo substr($user['api_key'], 0, 15); ?>...</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">Not generated</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-sm" onclick="showUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['balance']; ?>)" style="background:#6366f1; color:white;">Add Balance</button>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Generate new API key for this user?');">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="update_api_key" class="btn btn-sm" style="background:#f59e0b; color:white;">Regen API</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal لإضافة رصيد -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-bottom: 20px;">Add Balance</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="form-group">
                <label>User</label>
                <input type="text" id="modal_username" class="form-control" disabled>
            </div>
            <div class="form-group">
                <label>Amount ($)</label>
                <input type="number" name="amount" step="0.01" min="0" class="form-control" required>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeUserModal()" style="background:#e2e8f0;">Cancel</button>
                <button type="submit" name="add_balance" class="btn btn-primary">Add Balance</button>
            </div>
        </form>
    </div>
</div>

<script>
function showUserModal(id, username, balance) {
    document.getElementById('modal_user_id').value = id;
    document.getElementById('modal_username').value = username + ' (Current: $' + balance + ')';
    document.getElementById('userModal').classList.add('show');
}
function closeUserModal() {
    document.getElementById('userModal').classList.remove('show');
}
</script>

<?php require_once 'sidebar.php'; ?>