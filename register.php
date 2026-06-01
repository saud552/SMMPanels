<?php
// =============================================
// register.php - صفحة إنشاء حساب جديد (مع زر Menu)
// =============================================

session_start();
require_once 'config.php';

// إذا كان المستخدم مسجل دخوله، نحوله للوحة التحكم
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// ============================================
// معالجة إنشاء الحساب
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // التحقق من الحقول الإجبارية
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields';
    }
    // التحقق من اسم المستخدم
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 characters (letters, numbers, underscore only)';
    }
    // التحقق من البريد الإلكتروني
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    }
    // التحقق من كلمة المرور
    elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    }
    elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    }
    else {
        try {
            // التحقق من عدم وجود اسم مستخدم مكرر
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already taken. Please choose another.';
            }
            // التحقق من عدم وجود بريد إلكتروني مكرر
            else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered. Please login or use another email.';
                }
            }

            if (empty($error)) {
                // تشفير كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // إدراج المستخدم مباشرة في قاعدة البيانات
                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password, balance, status, created_at)
                                      VALUES (?, ?, ?, ?, 0.00, 1, NOW())");
                $stmt->execute([
                    $username,
                    $email,
                    $full_name,
                    $hashed_password
                ]);

                $success = 'Account created successfully! You can now login.';

                // تخزين البريد في الجلسة لتعبئته تلقائياً في صفحة login
                $_SESSION['registered_email'] = $email;

                // التوجيه إلى صفحة تسجيل الدخول (index.php) بعد 2 ثانية
                header("refresh:2;url=index.php");
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'System error occurred. Please try again later.';
        }
    }
}

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Account - <?php echo htmlspecialchars($site_domain); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fc;
            color: #1a1a2e;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Header مثل index.php */
        .header {
            background: white;
            border-bottom: 1px solid #eef2f6;
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
            color: #1a1a2e;
            text-decoration: none;
        }

        .logo span {
            color: #4f46e5;
        }

        /* زر القائمة */
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

        .menu-btn:hover {
            background: #e2e8f0;
        }

        /* القائمة المنسدلة */
        .dropdown-menu {
            position: absolute;
            top: 55px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #eef2f6;
            min-width: 180px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #f8f9fc;
            color: #4f46e5;
        }

        .container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            margin-top: 80px;
        }

        /* Register Box */
        .register-box {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #eef2f6;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
        }

        .register-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .alert.error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 3px solid #dc2626;
        }

        .alert.success {
            background: #d1fae5;
            color: #059669;
            border-left: 3px solid #059669;
        }

        .input-field {
            margin-bottom: 16px;
        }

        .input-field input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .input-field input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        .input-field input::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }

        .form-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
            margin-bottom: 16px;
        }

        .register-btn {
            width: 100%;
            padding: 14px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .register-btn:hover {
            background: #4338ca;
        }

        .login-link {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
        }

        .login-link p {
            color: #64748b;
            font-size: 13px;
        }

        .login-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
            margin-left: 6px;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .register-box {
                padding: 25px;
            }
            .register-header h1 {
                font-size: 24px;
            }
            .dropdown-menu {
                right: 10px;
                left: 10px;
                width: auto;
            }
        }
    </style>
</head>
<body>

<!-- Header مثل index.php -->
<div class="header">
    <a href="index.php" class="logo"><?php echo htmlspecialchars($site_domain); ?><span></span></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<!-- القائمة المنسدلة -->
<div class="dropdown-menu" id="dropdownMenu">
    <a href="index.php"><i class="fas fa-sign-in-alt"></i> Sign in</a>
    <a href="blog.php"><i class="fas fa-blog"></i> Blog</a>
    <a href="api.php"><i class="fas fa-code"></i> API</a>
    <a href="services.php"><i class="fas fa-cogs"></i> Services</a>
    <a href="register.php"><i class="fas fa-user-plus"></i> Sign up</a>
</div>

<div class="container">
    <!-- Register Box -->
    <div class="register-box">
        <div class="register-header">
            <h1>Create Account</h1>
            <p>Join us and start growing your social media</p>
        </div>

        <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?> Redirecting to login...</span>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-field">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-hint">3-30 characters (letters, numbers, underscore only)</div>

            <div class="input-field">
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-field">
                <input type="text" name="full_name" placeholder="Full Name" required>
            </div>

            <div class="input-field">
                <input type="password" name="password" placeholder="Password (min. 6 characters)" required>
            </div>

            <div class="input-field">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="register-btn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="login-link">
            <p>Already have an account?
                <a href="index.php">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            </p>
        </div>
    </div>
</div>

<script>
    // تنسيق اسم المستخدم (منع المسافات والأحرف الخاصة)
    const usernameInput = document.querySelector('input[name="username"]');
    if (usernameInput) {
        usernameInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^a-zA-Z0-9_]/g, '');
            if (value.length > 30) value = value.slice(0, 30);
            e.target.value = value;
        });
    }

    // التحقق من تطابق كلمة المرور
    const passwordInput = document.querySelector('input[name="password"]');
    const confirmInput = document.querySelector('input[name="confirm_password"]');

    if (passwordInput && confirmInput) {
        function checkPasswords() {
            if (confirmInput.value.length > 0) {
                if (passwordInput.value !== confirmInput.value) {
                    confirmInput.style.borderColor = '#ef4444';
                    confirmInput.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
                } else {
                    confirmInput.style.borderColor = '#10b981';
                    confirmInput.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
                }
            } else {
                confirmInput.style.borderColor = '#e2e8f0';
                confirmInput.style.backgroundColor = '';
            }
        }

        passwordInput.addEventListener('input', checkPasswords);
        confirmInput.addEventListener('input', checkPasswords);
    }

    // القائمة المنسدلة
    const menuBtn = document.getElementById('menuBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (menuBtn && dropdownMenu) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    }
</script>

</body>
</html>