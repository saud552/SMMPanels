<?php
session_start();
require_once '../config.php';
require_once 'includes/SEOManager.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// جلب بيانات الأدمن
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// تهيئة مدير SEO
$seo = new SEOManager($pdo);
$site_settings = $seo->getAll();

// معالجة حفظ إعدادات SEO
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo_settings'])) {
    $data = [
        'site_name' => $_POST['site_name'],
        'site_description' => $_POST['site_description'],
        'site_keywords' => $_POST['site_keywords'],
        'site_author' => $_POST['site_author'],
        'google_analytics' => $_POST['google_analytics'],
        'facebook_pixel' => $_POST['facebook_pixel'],
        'custom_header_code' => $_POST['custom_header_code'],
        'custom_footer_code' => $_POST['custom_footer_code']
    ];

    // معالجة رفع الشعار
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/settings/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_dir . $filename)) {
            $data['site_logo'] = 'uploads/settings/' . $filename;
        }
    }

    // معالجة رفع الفافيكون
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/settings/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION);
        $filename = 'favicon_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $upload_dir . $filename)) {
            $data['site_favicon'] = 'uploads/settings/' . $filename;
        }
    }

    // معالجة رفع صورة Open Graph
    if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/settings/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = pathinfo($_FILES['og_image']['name'], PATHINFO_EXTENSION);
        $filename = 'og_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['og_image']['tmp_name'], $upload_dir . $filename)) {
            $data['og_image'] = 'uploads/settings/' . $filename;
        }
    }

    if ($seo->update($data)) {
        $message = "SEO settings saved successfully!";
        $message_type = "success";
        $site_settings = $seo->getAll();
    } else {
        $message = "Failed to save settings!";
        $message_type = "error";
    }
}

// إحصائيات سريعة
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processing_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_deposits = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE status = 'paid'")->fetchColumn();
$total_balance = $pdo->query("SELECT COALESCE(SUM(balance), 0) FROM users")->fetchColumn();

// آخر الطلبات
$recent_orders = $pdo->query("SELECT o.*, u.username, s.name as service_name FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN services s ON o.service_id = s.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();

// تحديد التبويب النشط
$active_tab = $_GET['tab'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($admin['username']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        /* تصميم التبويبات */
        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0;
        }

        .admin-tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            border-radius: 8px 8px 0 0;
        }

        .admin-tab:hover {
            color: #4f46e5;
            background: #f1f5f9;
        }

        .admin-tab.active {
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
            margin-bottom: -2px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* تصميم الإحصائيات المصغرة */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-mini {
            background: white;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .stat-mini:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .stat-mini-icon {
            width: 40px;
            height: 40px;
            background: rgba(79,70,229,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-mini-icon i {
            font-size: 18px;
            color: #4f46e5;
        }

        .stat-mini-info {
            flex: 1;
        }

        .stat-mini-number {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-mini-label {
            font-size: 11px;
            color: #64748b;
        }

        /* ترحيب مصغر */
        .welcome-mini {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .welcome-mini h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-mini p {
            font-size: 13px;
            opacity: 0.9;
        }

        .date-badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
        }

        /* أزرار سريعة مصغرة */
        .actions-mini {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .action-mini {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            color: #475569;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .action-mini:hover {
            border-color: #4f46e5;
            color: #4f46e5;
            transform: translateY(-2px);
        }

        /* جداول مصغرة */
        .table-mini {
            width: 100%;
            border-collapse: collapse;
        }

        .table-mini th,
        .table-mini td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .table-mini th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        /* نماذج SEO */
        .seo-form {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1e293b;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .image-preview {
            margin-top: 8px;
        }

        .image-preview img {
            width: 60px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fed7aa; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #f1f5f9; color: #475569; }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">

        <!-- تبويبات الأدمن -->
        <div class="admin-tabs">
            <button class="admin-tab <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" data-tab="dashboard">
                <i class="fas fa-chart-line"></i> Dashboard
            </button>
            <button class="admin-tab <?php echo $active_tab == 'seo' ? 'active' : ''; ?>" data-tab="seo">
                <i class="fas fa-search"></i> SEO & Meta
            </button>
        </div>

        <!-- ============================================ -->
        <!-- تبويب Dashboard -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" id="tab-dashboard">

            <!-- Welcome Mini -->
            <div class="welcome-mini">
                <div>
                    <h2><i class="fas fa-crown"></i> Welcome back, <?php echo htmlspecialchars($admin['username']); ?>!</h2>
                    <p>Your SMM panel at a glance</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <!-- Stats Mini -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#4f46e5;"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-mini-label">Total Orders</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#f59e0b;"><?php echo number_format($pending_orders); ?></div>
                        <div class="stat-mini-label">Pending</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-spinner"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#3b82f6;"><?php echo number_format($processing_orders); ?></div>
                        <div class="stat-mini-label">Processing</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#10b981;"><?php echo number_format($completed_orders); ?></div>
                        <div class="stat-mini-label">Completed</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#4f46e5;"><?php echo number_format($total_users); ?></div>
                        <div class="stat-mini-label">Total Users</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#10b981;">$<?php echo number_format($total_deposits, 2); ?></div>
                        <div class="stat-mini-label">Total Deposits</div>
                    </div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-mini-info">
                        <div class="stat-mini-number" style="color:#4f46e5;">$<?php echo number_format($total_balance, 2); ?></div>
                        <div class="stat-mini-label">Total Balance</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Mini -->
            <div class="section-title">
                <i class="fas fa-bolt" style="color: #4f46e5;"></i>
                <span>Quick Actions</span>
            </div>
            <div class="actions-mini">
                <a href="orders.php" class="action-mini"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="users.php" class="action-mini"><i class="fas fa-users"></i> Users</a>
                <a href="services.php" class="action-mini"><i class="fas fa-cogs"></i> Services</a>
                <a href="manage_blog.php" class="action-mini"><i class="fas fa-blog"></i> Blog</a>
                <a href="appearance.php" class="action-mini"><i class="fas fa-palette"></i> Theme</a>
                <a href="currencies.php" class="action-mini"><i class="fas fa-coins"></i> Currencies</a>
                <a href="#tab-seo" class="action-mini" onclick="switchTab('seo')"><i class="fas fa-search"></i> SEO</a>
            </div>

            <!-- Recent Orders Table -->
            <div class="section-title">
                <i class="fas fa-clock" style="color: #4f46e5;"></i>
                <span>Recent Orders</span>
            </div>
            <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="overflow-x: auto;">
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Service</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_orders) > 0): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr class="danger">
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['username'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($order['service_name'] ?? 'N/A', 0, 30)); ?></td>
                                    <td><?php echo number_format($order['quantity']); ?></td>
                                    <td>$<?php echo number_format($order['price'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td><?php echo date('M d, H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="danger">
                                    <td colspan="7" style="text-align: center; padding: 30px;">No orders found</span>
                                </span>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- تبويب SEO & Meta -->
        <!-- ============================================ -->
        <div class="tab-content <?php echo $active_tab == 'seo' ? 'active' : ''; ?>" id="tab-seo">

            <div class="section-title">
                <i class="fas fa-search" style="color: #4f46e5;"></i>
                <span>SEO & Meta Settings</span>
            </div>

            <?php if ($message): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="seo-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($site_settings['site_name'] ?? 'SMM Panel'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Site Author</label>
                        <input type="text" name="site_author" class="form-control" value="<?php echo htmlspecialchars($site_settings['site_author'] ?? 'SkyLink'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="site_description" class="form-control" rows="2"><?php echo htmlspecialchars($site_settings['site_description'] ?? ''); ?></textarea>
                    <small>Recommended: 150-160 characters</small>
                </div>

                <div class="form-group">
                    <label>Meta Keywords</label>
                    <input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars($site_settings['site_keywords'] ?? ''); ?>">
                    <small>Separate with commas</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Site Logo</label>
                        <input type="file" name="site_logo" accept="image/*" class="form-control">
                        <?php if (!empty($site_settings['site_logo']) && file_exists('../' . $site_settings['site_logo'])): ?>
                        <div class="image-preview">
                            <img src="../<?php echo $site_settings['site_logo']; ?>" alt="Logo">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Favicon</label>
                        <input type="file" name="site_favicon" accept="image/*" class="form-control">
                        <?php if (!empty($site_settings['site_favicon']) && file_exists('../' . $site_settings['site_favicon'])): ?>
                        <div class="image-preview">
                            <img src="../<?php echo $site_settings['site_favicon']; ?>" alt="Favicon" style="width:32px;">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>OG Image (Social Share)</label>
                        <input type="file" name="og_image" accept="image/*" class="form-control">
                        <small>1200x630px</small>
                        <?php if (!empty($site_settings['og_image']) && file_exists('../' . $site_settings['og_image'])): ?>
                        <div class="image-preview">
                            <img src="../<?php echo $site_settings['og_image']; ?>" alt="OG Image" style="width:80px;">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Google Analytics ID</label>
                        <input type="text" name="google_analytics" class="form-control" value="<?php echo htmlspecialchars($site_settings['google_analytics'] ?? ''); ?>" placeholder="G-XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Facebook Pixel ID</label>
                        <input type="text" name="facebook_pixel" class="form-control" value="<?php echo htmlspecialchars($site_settings['facebook_pixel'] ?? ''); ?>" placeholder="123456789012345">
                    </div>
                </div>

                <div class="form-group">
                    <label>Custom Header Code</label>
                    <textarea name="custom_header_code" class="form-control" rows="3" placeholder="<!-- Custom CSS, JS, or meta tags -->"><?php echo htmlspecialchars($site_settings['custom_header_code'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Custom Footer Code</label>
                    <textarea name="custom_footer_code" class="form-control" rows="3" placeholder="<!-- Custom scripts before closing body tag -->"><?php echo htmlspecialchars($site_settings['custom_footer_code'] ?? ''); ?></textarea>
                </div>

                <button type="submit" name="save_seo_settings" class="btn-primary">
                    <i class="fas fa-save"></i> Save SEO Settings
                </button>
            </form>
        </div>

    </div>
</div>

<script>
    // التبديل بين التبويبات
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.admin-tab').forEach(btn => {
            btn.classList.remove('active');
        });

        document.getElementById('tab-' + tabName).classList.add('active');
        document.querySelector(`.admin-tab[data-tab="${tabName}"]`).classList.add('active');

        // تحديث URL
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }

    // إضافة مستمعين للتبويبات
    document.querySelectorAll('.admin-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            switchTab(btn.dataset.tab);
        });
    });

    // التحقق من وجود تبويب في URL
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab && ['dashboard', 'seo'].includes(activeTab)) {
        switchTab(activeTab);
    }

    // Toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.admin-sidebar').classList.toggle('open');
    }
</script>
</body>
</html>