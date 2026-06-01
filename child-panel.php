<?php
// =============================================
// child-panel.php - صفحة Child Panel (مبسطة ومتناسقة)
// =============================================
session_start();
require_once 'config.php';  // تم تغيير المسار من ../config.php إلى config.php

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$site_domain = $_SERVER['HTTP_HOST'];

// =============================================
// جلب سعر الـ Child Panel من جدول settings
// =============================================
$child_price = 5.00;

try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'child_panel_price'");
    $stmt->execute();
    $price = $stmt->fetchColumn();
    if ($price !== false && $price !== null) {
        $child_price = floatval($price);
    }
} catch (PDOException $e) {
    // استخدام السعر الافتراضي
}

// جلب حالة الخدمة
$service_active = true;
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'child_panel_service'");
    $stmt->execute();
    $status = $stmt->fetchColumn();
    if ($status !== false && $status !== null) {
        $service_active = ($status === 'active');
    }
} catch (PDOException $e) {
    // الخدمة مفعلة افتراضياً
}

$message = '';
$error = '';

// معالجة طلب Child Panel جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_child_panel'])) {
    if (!$service_active) {
        $error = 'Child Panel service is currently disabled. Please try again later.';
    } else {
        $domain = trim($_POST['domain']);
        $admin_username = trim($_POST['admin_username']);
        $admin_password = $_POST['admin_password'];

        if (empty($domain)) {
            $error = 'Please enter a domain name';
        } elseif (empty($admin_username)) {
            $error = 'Please enter admin username';
        } elseif (empty($admin_password)) {
            $error = 'Please enter admin password';
        } elseif ($user['balance'] < $child_price) {
            $error = 'Insufficient balance. You need $' . number_format($child_price, 2);
        } else {
            try {
                $pdo->beginTransaction();

                // خصم الرصيد
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$child_price, $_SESSION['user_id']]);

                // تسجيل الطلب
                $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt = $pdo->prepare("
                    INSERT INTO child_panels (user_id, domain, admin_username, admin_password, status, price, expiry_date)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $domain,
                    $admin_username,
                    password_hash($admin_password, PASSWORD_DEFAULT),
                    $child_price,
                    $expiry_date
                ]);

                $pdo->commit();

                // تحديث الرصيد في الجلسة
                $_SESSION['balance'] = $user['balance'] - $child_price;

                $message = 'Child Panel request submitted successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to submit request. Please try again.';
            }
        }
    }
}

// جلب طلبات المستخدم
$stmt = $pdo->prepare("SELECT * FROM child_panels WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$my_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Child Panel - <?php echo htmlspecialchars($site_domain); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7fb 0%, #f0f2f5 100%); min-height: 100vh; color: var(--dark); }

        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--gray-200);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
        }
        .logo span { color: var(--primary); }

        .menu-btn {
            background: #f1f5f9;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .menu-btn:hover { background: #e2e8f0; }

        .main-dropdown {
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #eef2f6;
            z-index: 99;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
        }
        .main-dropdown.open { max-height: 500px; }
        .dropdown-container { padding: 16px 24px; }

        .menu-item {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .menu-item:hover { background: var(--gray-50); color: var(--primary); }
        .menu-item i { width: 24px; margin-right: 8px; color: var(--primary); }
        .menu-item.active { color: var(--primary); background: rgba(79,70,229,0.05); }
        .menu-divider { height: 1px; background: var(--gray-200); margin: 8px 0; }

        .main { margin-top: 73px; padding: 20px; max-width: 1000px; margin-left: auto; margin-right: auto; }

        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .page-title i { color: var(--primary); }
        .page-subtitle { font-size: 13px; color: var(--gray-400); }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 3px solid #ef4444; }

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
        .form-group label i { margin-right: 6px; color: var(--primary); }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(99,102,241,0.4); }
        .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .summary-box {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .summary-row.total {
            padding-top: 8px;
            border-top: 1px solid var(--gray-200);
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0;
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

        .admin-btn {
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .admin-btn:hover { background: var(--primary-dark); }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray-400);
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        @media (max-width: 768px) {
            .main { padding: 16px; margin-top: 70px; }
            .page-title { font-size: 20px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="dashboard.php" class="logo"><?php echo htmlspecialchars($site_domain); ?></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<div class="main-dropdown" id="mainDropdown">
    <div class="dropdown-container">
        <a href="dashboard.php" class="menu-item"><i class="fas fa-shopping-cart"></i> New Order</a>
        <a href="orders.php" class="menu-item"><i class="fas fa-list-alt"></i> Orders</a>
        <a href="services.php" class="menu-item"><i class="fas fa-cogs"></i> Services</a>
        <a href="addfunds.php" class="menu-item"><i class="fas fa-plus-circle"></i> Add Funds</a>
        <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
        <a href="child-panel.php" class="menu-item active"><i class="fas fa-link"></i> Child Panel</a>
        <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
        <div class="menu-divider"></div>
        <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title">
            <i class="fas fa-link"></i>
            <span>Child Panel Service</span>
        </div>
        <div class="page-subtitle">Get your own branded SMM panel with full admin access</div>
    </div>

    <?php if (!$service_active): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Child Panel service is currently disabled. Please check back later.</span>
    </div>
    <?php endif; ?>

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

    <!-- Request Form -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-shopping-cart"></i>
            <span>Order Child Panel</span>
        </div>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-globe"></i> Your Domain</label>
                <input type="text" name="domain" class="form-control" placeholder="yourdomain.com" required <?php echo !$service_active ? 'disabled' : ''; ?>>
                <small style="color: var(--gray-400); font-size: 11px; display: block; margin-top: 4px;">
                    Point your domain nameservers to: ns1.cloudflare.com, ns2.cloudflare.com
                </small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user-shield"></i> Admin Username</label>
                <input type="text" name="admin_username" class="form-control" placeholder="admin" value="admin" required <?php echo !$service_active ? 'disabled' : ''; ?>>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Admin Password</label>
                <input type="password" name="admin_password" class="form-control" placeholder="Choose a strong password" required <?php echo !$service_active ? 'disabled' : ''; ?>>
            </div>

            <div class="summary-box">
                <div class="summary-row">
                    <span>Child Panel Price:</span>
                    <span>$<?php echo number_format($child_price, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Your Balance:</span>
                    <span>$<?php echo number_format($user['balance'], 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Amount to Deduct:</span>
                    <span>$<?php echo number_format($child_price, 2); ?></span>
                </div>
            </div>

            <button type="submit" name="request_child_panel" class="submit-btn" <?php echo ($user['balance'] < $child_price || !$service_active) ? 'disabled' : ''; ?>>
                <i class="fas fa-paper-plane"></i> Request Child Panel
            </button>
        </form>
    </div>

    <!-- My Requests -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-history"></i>
            <span>My Child Panel Requests</span>
        </div>

        <?php if (empty($my_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                No child panel requests yet
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Created</th>
                            <th>Expiry</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_requests as $req): ?>
                        <tr>
                            <td>#<?php echo $req['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($req['domain']); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($req['price'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            <td>
                                <?php echo $req['expiry_date'] ? date('M d, Y', strtotime($req['expiry_date'])) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] == 'active'): ?>
                                    <a href="https://<?php echo htmlspecialchars($req['domain']); ?>/admin" target="_blank" class="admin-btn">
                                        <i class="fas fa-external-link-alt"></i> Admin Area
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 11px;">Pending activation</span>
                                <?php endif; ?>
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
    const menuBtn = document.getElementById('menuBtn');
    const mainDropdown = document.getElementById('mainDropdown');

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        mainDropdown.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !mainDropdown.contains(e.target)) {
            mainDropdown.classList.remove('open');
        }
    });
</script>
</body>
</html>