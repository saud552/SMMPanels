<?php
// =============================================
// admin/child-panels.php - إدارة Child Panels (للمشرف فقط)
// =============================================
session_start();
require_once '../config.php';

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// =============================================
// تحديث الإعدادات العامة
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $price = floatval($_POST['price']);
    $service_status = $_POST['service_status'] ?? 'active';

    if ($price > 0) {
        // تحديث السعر في جدول settings (باستخدام key_name و value)
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value, type, `group`) VALUES ('child_panel_price', ?, 'number', 'payment')
                                ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$price, $price]);

        // تحديث حالة الخدمة
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value, type, `group`) VALUES ('child_panel_service', ?, 'text', 'general')
                                ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$service_status, $service_status]);

        $message = "Settings updated successfully!";
    } else {
        $error = "Invalid price value";
    }
}

// =============================================
// تحديث حالة طلب فردي
// =============================================
if (isset($_GET['update_status']) && isset($_GET['id'])) {
    $status = $_GET['update_status'];
    $id = intval($_GET['id']);

    $allowed_status = ['pending', 'active', 'expired', 'cancelled'];
    if (in_array($status, $allowed_status)) {
        $stmt = $pdo->prepare("UPDATE child_panels SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $message = "Request #$id status updated to " . ucfirst($status);
    }
}

// =============================================
// حذف طلب
// =============================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM child_panels WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Request #$id has been deleted";
}

// =============================================
// جلب الإعدادات الحالية (باستخدام key_name و value)
// =============================================
$current_price = 5.00;
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'child_panel_price'");
$stmt->execute();
$price_val = $stmt->fetchColumn();
if ($price_val) $current_price = floatval($price_val);

$service_status = 'active';
$stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'child_panel_service'");
$stmt->execute();
$status_val = $stmt->fetchColumn();
if ($status_val) $service_status = $status_val;

// =============================================
// جلب جميع الطلبات
// =============================================
$stmt = $pdo->prepare("
    SELECT cp.*, u.username as user_name, u.email as user_email
    FROM child_panels cp
    LEFT JOIN users u ON cp.user_id = u.id
    ORDER BY cp.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$total_requests = count($requests);
$pending_count = 0;
$active_count = 0;
foreach ($requests as $req) {
    if ($req['status'] == 'pending') $pending_count++;
    if ($req['status'] == 'active') $active_count++;
}
$total_earnings = array_sum(array_column($requests, 'price'));
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Child Panels - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        .admin-header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
        }
        .logo span { color: var(--primary); }
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logout-btn {
            background: #fee2e2;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .logout-btn:hover { background: #fecaca; }

        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }

        .page-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title i { color: var(--primary); }
        .page-subtitle { color: var(--gray-500); font-size: 14px; margin-bottom: 24px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
        }
        .stat-label {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 6px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }
        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }
        .card-title i { color: var(--primary); }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 13px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 6px 12px; font-size: 11px; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fed7aa; color: #92400e; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-expired { background: #f1f5f9; color: #475569; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .service-toggle {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: 40px;
        }
        .service-toggle-btn {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: transparent;
        }
        .service-toggle-btn.active {
            background: var(--primary);
            color: white;
        }

        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13px;
        }
        .data-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-600);
        }
        .data-table tr:hover { background: var(--gray-50); }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 3px solid #ef4444; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 20px;
        }
        .back-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <a href="index.php" class="logo">SkyLink<span>Admin</span></a>
    <div class="user-info">
        <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin['username']); ?></span>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="page-title">
        <i class="fas fa-link"></i>
        <span>Child Panels Management</span>
    </div>
    <div class="page-subtitle">Manage child panel requests, prices, and service status</div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_requests; ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $active_count; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($total_earnings, 2); ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>
    </div>

    <!-- Settings Card -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-cog"></i>
            <span>Service Settings</span>
        </div>

        <form method="POST">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign"></i> Price per Child Panel (USD)</label>
                    <input type="number" name="price" step="0.01" class="form-control" value="<?php echo $current_price; ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Service Status</label>
                    <div class="service-toggle">
                        <button type="button" class="service-toggle-btn <?php echo $service_status == 'active' ? 'active' : ''; ?>" data-value="active">Active</button>
                        <button type="button" class="service-toggle-btn <?php echo $service_status == 'inactive' ? 'active' : ''; ?>" data-value="inactive">Inactive</button>
                    </div>
                    <input type="hidden" name="service_status" id="service_status" value="<?php echo $service_status; ?>">
                </div>
            </div>
            <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>

    <!-- Requests List -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-list"></i>
            <span>Child Panel Requests</span>
        </div>

        <?php if (empty($requests)): ?>
            <div style="text-align: center; padding: 40px; color: var(--gray-400);">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                No child panel requests yet
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Domain</th>
                            <th>Admin</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>#<?php echo $req['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($req['user_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($req['user_email']); ?></small>
                            </td>
                            <td><strong><?php echo htmlspecialchars($req['domain']); ?></strong></td>
                            <td><?php echo htmlspecialchars($req['admin_username']); ?></td>
                            <td>$<?php echo number_format($req['price'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            <td>
                                <?php echo $req['expiry_date'] ? date('M d, Y', strtotime($req['expiry_date'])) : '-'; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($req['status'] != 'active'): ?>
                                    <a href="?update_status=active&id=<?php echo $req['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Activate this child panel?')">
                                        <i class="fas fa-check"></i> Activate
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($req['status'] != 'cancelled'): ?>
                                    <a href="?update_status=cancelled&id=<?php echo $req['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Cancel this child panel?')">
                                        <i class="fas fa-ban"></i> Cancel
                                    </a>
                                    <?php endif; ?>

                                    <?php if ($req['status'] != 'expired'): ?>
                                    <a href="?update_status=expired&id=<?php echo $req['id']; ?>" class="btn btn-secondary btn-sm" style="background:#64748b;color:white;" onclick="return confirm('Mark as expired?')">
                                        <i class="fas fa-clock"></i> Expire
                                    </a>
                                    <?php endif; ?>

                                    <a href="?delete=1&id=<?php echo $req['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this request? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Service toggle buttons
    const toggleBtns = document.querySelectorAll('.service-toggle-btn');
    const serviceStatusInput = document.getElementById('service_status');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            serviceStatusInput.value = value;

            toggleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });
</script>
</body>
</html>