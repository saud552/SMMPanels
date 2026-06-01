<?php
// =============================================
// blog.php - صفحة عرض المدونة للمستخدمين (مع زر القائمة)
// =============================================

session_start();
require_once 'config.php';

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Latest news, tips and updates from SkyLink SMM Panel - Best Social Media Marketing Services">
    <meta name="keywords" content="SMM Panel, Instagram, TikTok, YouTube, Blog, Social Media Marketing">
    <title>Blog - <?php echo htmlspecialchars($site_domain); ?></title>
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

        /* Header مثل index.php */
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-btn {
            width: 38px;
            height: 38px;
            background: #f1f5f9;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .menu-btn i {
            font-size: 18px;
            color: #475569;
        }

        .menu-btn:hover {
            background: #e2e8f0;
        }

        /* القائمة المنسدلة */
        .dropdown-menu {
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid #e2e8f0;
            z-index: 99;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        .dropdown-menu.open {
            max-height: 380px;
        }

        .dropdown-container {
            padding: 16px 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .menu-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .menu-card:hover {
            transform: translateY(-2px);
            border-color: #4f46e5;
            background: white;
        }

        .menu-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(236,72,153,0.1));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #4f46e5;
        }

        .menu-card-content h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
            color: #1a1a2e;
        }

        .menu-card-content p {
            font-size: 10px;
            color: #94a3b8;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        /* Blog Header */
        .blog-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .blog-header h1 {
            font-size: 38px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 10px;
        }

        .blog-header p {
            color: #64748b;
            font-size: 16px;
        }

        /* Posts Grid */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        /* Post Card */
        .post-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .post-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }

        .post-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .post-image-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #4f46e5, #ec4899);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .post-image-placeholder i {
            font-size: 50px;
            color: white;
            opacity: 0.8;
        }

        .post-content {
            padding: 20px;
        }

        .post-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a1a2e;
            line-height: 1.4;
        }

        .post-excerpt {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #94a3b8;
            padding-top: 12px;
            border-top: 1px solid #eef2f6;
        }

        .read-more {
            color: #4f46e5;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }

        /* Single Post Page */
        .single-post {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }

        .single-post img {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
        }

        .single-post h1 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #1a1a2e;
        }

        .single-post h2 {
            font-size: 24px;
            margin: 30px 0 15px;
            color: #1a1a2e;
        }

        .single-post h3 {
            font-size: 20px;
            margin: 25px 0 12px;
            color: #1a1a2e;
        }

        .single-post p {
            line-height: 1.8;
            color: #334155;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .single-post ul,
        .single-post ol {
            margin: 15px 0 15px 25px;
            color: #334155;
        }

        .single-post li {
            margin: 8px 0;
        }

        .single-post .post-meta-single {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eef2f6;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
        }

        .single-post .post-meta-single i {
            margin-right: 6px;
            color: #4f46e5;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4f46e5;
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-top: 30px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: #1a1a2e;
            color: #94a3b8;
            padding: 40px 20px;
            text-align: center;
            margin-top: 60px;
        }

        .footer p {
            font-size: 13px;
        }

        /* Not Found */
        .not-found {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }

        .not-found i {
            font-size: 60px;
            color: #94a3b8;
            margin-bottom: 20px;
        }

        .not-found h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .not-found p {
            color: #64748b;
            margin-bottom: 25px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            z-index: 200;
            transition: 0.3s;
            opacity: 0;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 30px 16px;
            }

            .blog-header h1 {
                font-size: 28px;
            }

            .posts-grid {
                grid-template-columns: 1fr;
            }

            .single-post {
                padding: 24px;
            }

            .single-post h1 {
                font-size: 24px;
            }

            .single-post h2 {
                font-size: 20px;
            }

            .dropdown-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Header مثل index.php -->
<div class="header">
    <a href="index.php" class="logo"><?php echo htmlspecialchars($site_domain); ?><span></span></a>
    <div class="header-right">
        <button class="menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    </div>
</div>

<!-- القائمة المنسدلة -->
<div class="dropdown-menu" id="dropdownMenu">
    <div class="dropdown-container">
        <a href="index.php" class="menu-card">
            <div class="menu-icon"><i class="fas fa-home"></i></div>
            <div class="menu-card-content">
                <h4>Home</h4>
                <p>Back to home</p>
            </div>
        </a>
        <a href="services.php" class="menu-card">
            <div class="menu-icon"><i class="fas fa-cogs"></i></div>
            <div class="menu-card-content">
                <h4>Services</h4>
                <p>Browse services</p>
            </div>
        </a>
        <a href="blog.php" class="menu-card">
            <div class="menu-icon"><i class="fas fa-blog"></i></div>
            <div class="menu-card-content">
                <h4>Blog</h4>
                <p>Read our articles</p>
            </div>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="menu-card">
                <div class="menu-icon"><i class="fas fa-tachometer-alt"></i></div>
                <div class="menu-card-content">
                    <h4>Dashboard</h4>
                    <p>Go to dashboard</p>
                </div>
            </a>
            <a href="logout.php" class="menu-card">
                <div class="menu-icon"><i class="fas fa-sign-out-alt"></i></div>
                <div class="menu-card-content">
                    <h4>Logout</h4>
                    <p>Sign out</p>
                </div>
            </a>
        <?php else: ?>
            <a href="login.php" class="menu-card">
                <div class="menu-icon"><i class="fas fa-sign-in-alt"></i></div>
                <div class="menu-card-content">
                    <h4>Login</h4>
                    <p>Sign in to account</p>
                </div>
            </a>
            <a href="register.php" class="menu-card">
                <div class="menu-icon"><i class="fas fa-user-plus"></i></div>
                <div class="menu-card-content">
                    <h4>Register</h4>
                    <p>Create new account</p>
                </div>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <?php
    // =============================================
    // عرض مقال واحد (متاح للجميع)
    // =============================================
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);

        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'published'");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if ($post):
            // زيادة عدد المشاهدات
            $update_stmt = $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
            $update_stmt->execute([$id]);
    ?>
        <article class="single-post">
            <?php if ($post['featured_image']): ?>
                <img src="<?php echo $post['featured_image']; ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
            <?php endif; ?>

            <h1><?php echo htmlspecialchars($post['title']); ?></h1>

            <div class="post-meta-single">
                <span><i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($post['created_at'])); ?></span>
                <span><i class="fas fa-eye"></i> <?php echo number_format($post['views']); ?> views</span>
            </div>

            <div class="post-body">
                <?php echo $post['content']; ?>
            </div>

            <a href="blog.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Blog
            </a>
        </article>
    <?php
        else:
    ?>
        <div class="not-found">
            <i class="fas fa-newspaper"></i>
            <h2>Post Not Found</h2>
            <p>The article you're looking for doesn't exist or hasn't been published yet.</p>
            <a href="blog.php" class="back-btn">Browse All Articles</a>
        </div>
    <?php
        endif;
    } else {
        // =============================================
        // عرض جميع المقالات (متاح للجميع)
        // =============================================
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC");
        $stmt->execute();
        $posts = $stmt->fetchAll();
    ?>
        <div class="blog-header">
            <h1>Our Blog</h1>
            <p>Latest news, tips and updates from our team</p>
        </div>

        <div class="posts-grid">
            <?php foreach ($posts as $post): ?>
            <a href="blog.php?id=<?php echo $post['id']; ?>" class="post-card">
                <?php if ($post['featured_image']): ?>
                    <img src="<?php echo $post['featured_image']; ?>" class="post-image" alt="<?php echo htmlspecialchars($post['title']); ?>">
                <?php else: ?>
                    <div class="post-image-placeholder">
                        <i class="fas fa-newspaper"></i>
                    </div>
                <?php endif; ?>
                <div class="post-content">
                    <h3 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p class="post-excerpt">
                        <?php
                            $excerpt = !empty($post['excerpt']) ? $post['excerpt'] : strip_tags($post['content']);
                            echo htmlspecialchars(substr($excerpt, 0, 110)) . '...';
                        ?>
                    </p>
                    <div class="post-meta">
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                        <span class="read-more">Read More <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>

            <?php if (empty($posts)): ?>
                <div class="not-found" style="grid-column: 1/-1;">
                    <i class="fas fa-blog"></i>
                    <h2>No Posts Yet</h2>
                    <p>Check back soon for new articles and updates!</p>
                </div>
            <?php endif; ?>
        </div>
    <?php } ?>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_domain); ?>. All rights reserved.</p>
    </div>
</footer>

<div class="toast" id="toast"></div>

<script>
    // القائمة المنسدلة
    const menuBtn = document.getElementById('menuBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('open');
        menuBtn.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('open');
            menuBtn.classList.remove('active');
        }
    });

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }
</script>

</body>
</html>