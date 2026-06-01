<?php
// =============================================
// index.php - صفحة الهبوط (مع SEO متكامل)
// =============================================

session_start();
require_once 'config.php';

// جلب إعدادات الموقع (SEO)
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
$site_settings = [];
while ($row = $stmt->fetch()) {
    $site_settings[$row['setting_key']] = $row['setting_value'];
}

// إعدادات افتراضية إذا كانت فارغة
$site_title = $site_settings['site_title'] ?? 'Best SMM Panel - Cheap Social Media Services';
$site_description = $site_settings['site_description'] ?? 'Number one SMM panel in the world. Buy Instagram followers, TikTok likes, YouTube subscribers at cheapest prices with instant delivery.';
$site_keywords = $site_settings['site_keywords'] ?? 'SMM Panel, Instagram followers, TikTok likes, YouTube subscribers, social media marketing';
$og_title = $site_settings['og_title'] ?? 'SkyLink SMM - Best SMM Panel';
$og_description = $site_settings['og_description'] ?? 'Buy social media services at the cheapest prices. Fast delivery, 24/7 support.';
$footer_text = $site_settings['footer_text'] ?? 'All Rights Reserved';

// إذا كان المستخدم مسجل دخوله، نحوله للوحة التحكم
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please enter email/username and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['balance'] = $user['balance'];
                $_SESSION['full_name'] = $user['full_name'];

                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                $stmt->execute([$ip, $user['id']]);

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email/username or password';
            }
        } catch (PDOException $e) {
            $error = 'System error, please try again';
        }
    }
}

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_domain); ?> | <?php echo htmlspecialchars($site_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_keywords); ?>">

    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://<?php echo htmlspecialchars($site_domain); ?>">

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
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            padding: 20px 0;
            border-bottom: 1px solid #eef2f6;
            background: white;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
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
            padding: 10px 18px;
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
            top: 70px;
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

        .dropdown-menu a.active {
            color: #4f46e5;
            background: #eef2ff;
        }

        /* Hero Section */
        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            padding: 60px 0;
            align-items: center;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-title span {
            color: #4f46e5;
        }

        .hero-subtitle {
            color: #64748b;
            font-size: 18px;
            margin-bottom: 30px;
        }

        /* Login Form */
        .login-box {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #eef2f6;
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center;
        }

        .input-field {
            margin-bottom: 20px;
        }

        .input-field input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .input-field input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #4338ca;
        }

        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }

        .forgot-link {
            display: block;
            text-align: right;
            margin: 15px 0;
            color: #4f46e5;
            text-decoration: none;
            font-size: 13px;
        }

        .signup-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
        }

        .signup-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }

        /* Features Section */
        .features {
            padding: 60px 0;
            background: white;
        }

        .section-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .section-subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 50px;
            font-size: 16px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .feature-card {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fc;
            border-radius: 16px;
            transition: transform 0.2s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: #eef2ff;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .feature-icon i {
            font-size: 28px;
            color: #4f46e5;
        }

        .feature-card h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #64748b;
            font-size: 14px;
        }

        /* How it works */
        .how-it-works {
            padding: 60px 0;
            background: #f8f9fc;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .step {
            text-align: center;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin: 0 auto 20px;
        }

        .step h4 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .step p {
            color: #64748b;
            font-size: 14px;
        }

        /* Testimonials */
        .testimonials {
            padding: 60px 0;
            background: white;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .testimonial-card {
            background: #f8f9fc;
            padding: 25px;
            border-radius: 16px;
        }

        .testimonial-text {
            font-size: 14px;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .author-avatar {
            width: 45px;
            height: 45px;
            background: #eef2ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .author-avatar i {
            font-size: 20px;
            color: #4f46e5;
        }

        .author-name {
            font-weight: 600;
            font-size: 14px;
        }

        .author-title {
            font-size: 12px;
            color: #64748b;
        }

        .stars {
            color: #fbbf24;
            font-size: 12px;
            margin-top: 5px;
        }

        /* FAQ Section */
        .faq {
            padding: 60px 0;
            background: #f8f9fc;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .faq-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eef2f6;
        }

        .faq-question {
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            cursor: pointer;
        }

        .faq-answer {
            color: #64748b;
            font-size: 14px;
            display: none;
        }

        .faq-answer.show {
            display: block;
        }

        /* Footer */
        .footer {
            background: #1a1a2e;
            color: #94a3b8;
            padding: 40px 0;
            text-align: center;
        }

        .footer p {
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .features-grid,
            .steps-grid,
            .testimonials-grid,
            .faq-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .hero-title {
                font-size: 32px;
            }

            .features-grid,
            .steps-grid,
            .testimonials-grid,
            .faq-grid {
                grid-template-columns: 1fr;
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

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header-inner">
            <a href="index.php" class="logo"><?php echo htmlspecialchars($site_domain); ?><span></span></a>
            <button class="menu-btn" id="menuBtn">
                <i class="fas fa-bars"></i> Menu
            </button>
        </div>
    </div>
</header>

<!-- القائمة المنسدلة -->
<div class="dropdown-menu" id="dropdownMenu">
    <a href="index.php" class="active"><i class="fas fa-sign-in-alt"></i> Sign in</a>
    <a href="blog.php"><i class="fas fa-blog"></i> Blog</a>
    <a href="api.php"><i class="fas fa-code"></i> API</a>
    <a href="services.php"><i class="fas fa-cogs"></i> Services</a>
    <a href="register.php"><i class="fas fa-user-plus"></i> Sign up</a>
</div>

<main>
    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <div>
                <h1 class="hero-title">Best Quality <span>Cheapest Price</span></h1>
                <p class="hero-subtitle">Number one SMM panel in the world</p>
                <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                    <div>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <span style="margin-left: 8px;">Top-quality services</span>
                    </div>
                    <div>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        <span style="margin-left: 8px;">Very quick delivery</span>
                    </div>
                </div>
            </div>

            <!-- Login Box -->
            <div class="login-box">
                <h2 class="login-title">Sign in</h2>

                <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-field">
                        <input type="text" name="email" placeholder="Username / Email" required>
                    </div>
                    <div class="input-field">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" name="login" class="login-btn">Sign in</button>
                </form>

                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>

                <div class="signup-link">
                    Don't have an account? <a href="register.php">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Here are reasons why you should choose us</h2>
            <p class="section-subtitle">Learn why using our panel is the best & cheapest way to get popular online.</p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-star"></i></div>
                    <h3>Top-quality services</h3>
                    <p>You will be pleasantly surprised at the results.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                    <h3>Diverse payment systems</h3>
                    <p>You can add funds via any payment option we provide.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-tag"></i></div>
                    <h3>Low prices</h3>
                    <p>Enjoy the cheapest SMM services you can find online!</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Very quick delivery</h3>
                    <p>Customer orders on our panel are processed very fast.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <h2 class="section-title">How does it work?</h2>
            <p class="section-subtitle">Check out the step-by-step tutorial on how to get started on our SMM panel.</p>

            <div class="steps-grid">
                <div class="step">
                    <div class="step-number">1</div>
                    <h4>Sign up</h4>
                    <p>The first thing to do is to create an account and log in.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h4>Make a deposit</h4>
                    <p>Add funds to your account using a preferred payment method.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h4>Place your order</h4>
                    <p>Check out the list of SMM services that we offer and place your orders.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h4>Superb results</h4>
                    <p>It's that easy! Now all you need to do is wait a little until your order is ready.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <h2 class="section-title">What do our customers say?</h2>
            <p class="section-subtitle">Check out our customers' testimonials to learn more about the benefits of using our panel.</p>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "Best SMM panel I've ever used! Very fast delivery and great customer support. Highly recommended!"
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="author-name">Alex Johnson</div>
                            <div class="author-title">Social Media Manager</div>
                            <div class="stars">★★★★★</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "Amazing prices and quality. My Instagram growth has been incredible since I started using this panel."
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="author-name">Sarah Williams</div>
                            <div class="author-title">Influencer</div>
                            <div class="stars">★★★★★</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "The API integration is seamless. As a reseller, this panel has been a game-changer for my business."
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="author-name">Michael Chen</div>
                            <div class="author-title">Reseller</div>
                            <div class="stars">★★★★★</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="faq" id="faq">
        <div class="container">
            <h2 class="section-title">Popular questions on our panel</h2>
            <p class="section-subtitle">Our staff chose some of the most popular questions about SMM panels and replied to them.</p>

            <div class="faq-grid">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        An SMM panel — what is it?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        An SMM panel is a web-based platform that allows users to purchase social media marketing services such as followers, likes, views, and comments for various social media platforms.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What types of SMM services can I buy on your panel?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        We offer services for Instagram, TikTok, YouTube, Telegram, Twitter, Facebook including followers, likes, views, comments, shares, and much more.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        Are your SMM services safe to use?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, all our services are 100% safe. We use high-quality accounts and methods that comply with social media platforms' terms of service.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        What does the mass order feature do?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        The mass order feature allows you to place multiple orders at once using a CSV file, saving time when ordering multiple services or for multiple links.
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_domain); ?>. <?php echo htmlspecialchars($footer_text); ?></p>
    </div>
</footer>

<script>
    // القائمة المنسدلة
    const menuBtn = document.getElementById('menuBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    });

    // FAQ toggle
    function toggleFAQ(element) {
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
        const icon = element.querySelector('i');
        if (answer.classList.contains('show')) {
            icon.style.transform = 'rotate(180deg)';
        } else {
            icon.style.transform = 'rotate(0deg)';
        }
    }
</script>

</body>
</html>