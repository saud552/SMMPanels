<?php
// =============================================
// admin/login.php - تسجيل دخول لوحة الإدارة
// =============================================

session_start();
require_once '../config.php';

// إذا كان مسجل دخول مسبقاً، انتقل للوحة التحكم
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            // تحديث آخر تسجيل دخول
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $stmt->execute([$ip, $admin['id']]);

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SkyLink SMM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo p {
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }
        .alert {
            background: rgba(239,68,68,0.1);
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #dc2626;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            border: none;
            border-radius: 50px;
            color: white;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(99,102,241,0.4);
        }
        @media (max-width: 480px) {
            .login-container { padding: 28px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>SkyLink Admin</h1>
            <p>Login to manage your SMM Panel</p>
        </div>

        <?php if ($error): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" placeholder="admin" required>
            </div>
            <div class="input-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
</body>
</html>