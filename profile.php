<?php
// =============================================
// profile.php - صفحة الملف الشخصي مع API Key Management
// =============================================
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['balance'] = $user['balance'];
$_SESSION['username'] = $user['username'];

// =============================================
// معالجة تحديث الملف الشخصي
// =============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($full_name)) {
                $error = 'Full name is required';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } else {
                try {
                    if (!empty($email) && $email !== $user['email']) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $check->execute([$email, $_SESSION['user_id']]);
                        if ($check->fetch()) {
                            $error = 'Email already used by another account';
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
                        $success = 'Profile updated successfully';
                        $user['full_name'] = $full_name;
                        $user['email'] = $email;
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to update profile';
                }
            }
        }

        if ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill all password fields';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success = 'Password changed successfully';
            }
        }

        if ($action === 'generate_api_key') {
            $new_api_key = 'SKY_' . bin2hex(random_bytes(32)) . '_' . time();
            $stmt = $pdo->prepare("UPDATE users SET api_key = ?, api_key_created_at = NOW() WHERE id = ?");
            $stmt->execute([$new_api_key, $_SESSION['user_id']]);
            $user['api_key'] = $new_api_key;
            $user['api_key_created_at'] = date('Y-m-d H:i:s');
            $success = 'API Key generated successfully';
        }

        if ($action === 'delete_api_key') {
            $stmt = $pdo->prepare("UPDATE users SET api_key = NULL, api_key_created_at = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user['api_key'] = null;
            $user['api_key_created_at'] = null;
            $success = 'API Key deleted successfully';
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب إحصائيات المستخدم
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(price), 0) as total_spent
    FROM orders
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_orders
    FROM orders
    WHERE user_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$pending = $stmt->fetch();
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Profile - SkyLink SMM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --secondary: #ec4899;
            --success: #10b981; --danger: #ef4444; --warning: #f59e0b;
            --dark: #0f172a; --gray-50: #f8fafc; --gray-100: #f1f5f9;
            --gray-200: #e2e8f0; --gray-300: #cbd5e1; --gray-400: #94a3b8;
            --gray-500: #64748b; --gray-600: #475569; --white: #ffffff;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7fb 0%, #f0f2f5 100%); min-height: 100vh; color: var(--dark); }

        /* Header مثل dashboard */
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .balance-badge {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 6px 14px;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .balance-badge i { font-size: 12px; opacity: 0.8; }

        .menu-btn {
            width: 38px;
            height: 38px;
            background: var(--gray-100);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .menu-btn i { font-size: 18px; color: var(--gray-600); }
        .menu-btn:hover { background: var(--gray-200); }

        /* القائمة الجانبية مثل dashboard */
        .dropdown-menu {
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-200);
            z-index: 99;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
        }
        .dropdown-menu.open { max-height: 380px; }
        .dropdown-container {
            padding: 16px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .menu-card {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            border: 1px solid var(--gray-200);
        }
        .menu-card:hover { transform: translateY(-2px); border-color: var(--primary); background: white; }
        .menu-card.active { border-color: var(--primary); background: rgba(99,102,241,0.05); }
        .menu-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(236,72,153,0.1));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--primary);
        }
        .menu-card-content h4 { font-size: 13px; font-weight: 700; margin-bottom: 2px; color: var(--dark); }
        .menu-card-content p { font-size: 10px; color: var(--gray-400); }

        /* Main Content */
        .main { margin-top: 73px; padding: 20px; max-width: 1000px; margin-left: auto; margin-right: auto; }

        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--primary); }

        /* Stats Cards (مصغرة ومرتبة) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 14px;
            border: 1px solid var(--gray-200);
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-icon {
            width: 36px;
            height: 36px;
            background: rgba(99,102,241,0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .stat-icon i { font-size: 18px; color: var(--primary); }
        .stat-value { font-size: 20px; font-weight: 800; color: var(--dark); margin-bottom: 2px; }
        .stat-label { font-size: 10px; color: var(--gray-400); font-weight: 500; }

        /* Profile Cards */
        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }
        .card-title i { color: var(--primary); }

        /* Avatar */
        .avatar-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .avatar-info h3 { font-size: 18px; font-weight: 800; margin-bottom: 4px; }
        .avatar-info p { color: var(--gray-400); font-size: 12px; }
        .member-since {
            background: var(--gray-50);
            border-radius: 30px;
            padding: 4px 12px;
            font-size: 10px;
            color: var(--gray-500);
            display: inline-block;
        }

        /* Form Groups */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gray-500); margin-bottom: 5px; }
        .form-group label i { margin-right: 4px; color: var(--primary); }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 2px rgba(99,102,241,0.1);
        }
        .form-control:disabled { background: var(--gray-100); cursor: not-allowed; }

        .btn {
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: inherit;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
        .btn-outline { background: transparent; border: 1px solid var(--gray-200); color: var(--gray-600); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

        /* API Key Section */
        .api-key-box {
            background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(236,72,153,0.05));
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(99,102,241,0.2);
        }
        .api-key-display {
            background: #1e293b;
            border-radius: 10px;
            padding: 10px;
            font-family: monospace;
            font-size: 11px;
            word-break: break-all;
            color: #e2e8f0;
            margin: 12px 0;
        }
        .api-key-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            font-size: 10px;
            color: var(--gray-400);
        }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #059669; border-left: 3px solid #059669; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #dc2626; border-left: 3px solid #dc2626; }

        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(100px); background: var(--dark); color: white; padding: 8px 16px; border-radius: 40px; font-size: 12px; z-index: 200; transition: 0.3s; opacity: 0; }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        code { background: var(--gray-200); padding: 2px 6px; border-radius: 6px; font-size: 10px; }

        @media (max-width: 768px) {
            .main { padding: 16px; margin-top: 70px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .two-columns { grid-template-columns: 1fr; gap: 16px; }
            .avatar-section { justify-content: center; text-align: center; flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="header">
    <a href="dashboard.php" class="logo"><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?></a>
    <div class="header-right">
        <div class="balance-badge"><i class="fas fa-wallet"></i> $<?php echo number_format($user['balance'], 2); ?></div>
        <button class="menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    </div>
</div>

<div class="dropdown-menu" id="dropdownMenu">
    <div class="dropdown-container">
        <a href="dashboard.php" class="menu-card"><div class="menu-icon"><i class="fas fa-shopping-cart"></i></div><div class="menu-card-content"><h4>New Order</h4><p>Place order</p></div></a>
        <a href="orders.php" class="menu-card"><div class="menu-icon"><i class="fas fa-list-alt"></i></div><div class="menu-card-content"><h4>Orders</h4><p>View orders</p></div></a>
        <a href="addfunds.php" class="menu-card"><div class="menu-icon"><i class="fas fa-plus-circle"></i></div><div class="menu-card-content"><h4>Add Funds</h4><p>Deposit money</p></div></a>
        <a href="api.php" class="menu-card"><div class="menu-icon"><i class="fas fa-code"></i></div><div class="menu-card-content"><h4>API</h4><p>Documentation</p></div></a>
        <a href="profile.php" class="menu-card active"><div class="menu-icon"><i class="fas fa-user"></i></div><div class="menu-card-content"><h4>Profile</h4><p>Account settings</p></div></a>
        <a href="logout.php" class="menu-card"><div class="menu-icon"><i class="fas fa-sign-out-alt"></i></div><div class="menu-card-content"><h4>Logout</h4><p>Sign out</p></div></a>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title"><i class="fas fa-user-circle"></i><span>My Profile</span></div>
    </div>

    <!-- Stats Cards (مصغرة ومرتبة) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-value">$<?php echo number_format($user['balance'], 2); ?></div>
            <div class="stat-label">Balance</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-value">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo number_format($pending['pending_orders'] ?? 0); ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
    <?php endif; ?>

    <div class="two-columns">

        <!-- Left Column: Profile Info -->
        <div class="profile-card">
            <div class="card-title"><i class="fas fa-user"></i><span>Profile Information</span></div>

            <div class="avatar-section">
                <div class="avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                <div class="avatar-info">
                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="member-since"><i class="fas fa-calendar-alt"></i> Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                    <small style="color: var(--gray-400); font-size: 10px;">Leave blank if you don't want to add email</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?>" disabled>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>

        <!-- Right Column: Password & API Key -->
        <div>
            <!-- Change Password -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-lock"></i><span>Change Password</span></div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_password">

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
                </form>
            </div>

            <!-- API Key Management -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-code"></i><span>API Key Management</span></div>

                <div class="api-key-box">
                    <?php if (!empty($user['api_key'])): ?>
                        <div style="margin-bottom: 10px;"><strong><i class="fas fa-key"></i> Your API Key:</strong></div>
                        <div class="api-key-display" id="apiKeyDisplay">
                            <?php
                            $api_key = $user['api_key'];
                            $masked_key = substr($api_key, 0, 15) . '••••••••••••' . substr($api_key, -10);
                            echo htmlspecialchars($masked_key);
                            ?>
                        </div>
                        <button class="btn btn-outline" onclick="copyApiKey()" style="margin-right: 8px;"><i class="fas fa-copy"></i> Copy Full Key</button>
                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete your API Key?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete_api_key">
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                        <div class="api-key-info">
                            <span><i class="fas fa-calendar"></i> Created: <?php echo date('F d, Y \a\t h:i A', strtotime($user['api_key_created_at'])); ?></span>
                            <span><i class="fas fa-shield-alt"></i> Keep it secure</span>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-key" style="font-size: 40px; color: var(--gray-400); margin-bottom: 12px; display: block;"></i>
                            <p style="color: var(--gray-400); margin-bottom: 16px; font-size: 12px;">Generate an API key to access our API services.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="generate_api_key">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Generate API Key</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 12px; padding: 10px; background: var(--gray-50); border-radius: 10px; font-size: 10px; color: var(--gray-500);">
                    <i class="fas fa-info-circle"></i>
                    <strong>API Usage:</strong> Use your API key in the <code>X-API-Key</code> header. Keep your key confidential.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const fullApiKey = '<?php echo addslashes($user['api_key'] ?? ''); ?>';

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function copyApiKey() {
        if (!fullApiKey) {
            showToast('No API key to copy', 'error');
            return;
        }
        navigator.clipboard.writeText(fullApiKey).then(() => {
            showToast('API Key copied to clipboard!', 'success');
        }).catch(() => {
            showToast('Failed to copy API key', 'error');
        });
    }

    // Dropdown Menu
    const menuBtn = document.getElementById('menuBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('open');
        }
    });
</script>
</body>
</html>