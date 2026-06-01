<?php
// لا تضع session_start() هنا - موجود بالفعل في الملف الرئيسي
?>
<div class="admin-sidebar" id="sideMenu">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-crown"></i>
            <span>Admin<span>Panel</span></span>
        </div>
        <button class="close-sidebar" id="closeMenuBtn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-title">MAIN</div>
            <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="orders.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>
            <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="services.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Services</span>
            </a>
            <a href="providers.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'providers.php' ? 'active' : ''; ?>">
                <i class="fas fa-plug"></i>
                <span>Providers</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-title">CONTENT</div>
            <a href="manage_blog.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_blog.php' ? 'active' : ''; ?>">
                <i class="fas fa-blog"></i>
                <span>Blog Manager</span>
            </a>
            <a href="appearance.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'appearance.php' ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Appearance</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-title">FINANCE</div>
            <a href="payments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="currencies.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'currencies.php' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span>Currencies</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-title">EXTRA</div>
            <a href="child-panels.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'child-panels.php' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i>
                <span>Child Panels Manager</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-title">SETTINGS</div>
            <a href="seo.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'seo.php' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i>
                <span>SEO & Meta</span>
            </a>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>System Settings</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-title">ACCOUNT</div>
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Site</span>
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<div class="menu-overlay" id="menuOverlay"></div>

<!-- زر Menu لفتح/إغلاق السايد بار -->
<button class="menu-toggle-btn" id="menuToggleBtn">
    <i class="fas fa-bars"></i>
</button>

<style>
/* Sidebar Styles */
.admin-sidebar {
    width: 280px;
    background: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
    transition: all 0.3s ease;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
}

/* Sidebar Closed State (Collapsed) */
.admin-sidebar.collapsed {
    left: -280px;
}

.admin-sidebar::-webkit-scrollbar {
    width: 5px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: #1e293b;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: #4f46e5;
    border-radius: 10px;
}

.sidebar-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-header .logo {
    font-size: 20px;
    font-weight: 800;
}

.sidebar-header .logo i {
    color: #4f46e5;
    margin-right: 10px;
}

.sidebar-header .logo span {
    color: white;
}

.sidebar-header .logo span span {
    background: linear-gradient(135deg, #4f46e5, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.close-sidebar {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.close-sidebar:hover {
    background: rgba(255,255,255,0.2);
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-section {
    margin-bottom: 24px;
}

.nav-title {
    padding: 8px 24px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #64748b;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 24px;
    color: #94a3b8;
    text-decoration: none;
    transition: all 0.2s;
    margin: 4px 12px;
    border-radius: 12px;
}

.nav-item:hover {
    background: rgba(79,70,229,0.1);
    color: white;
}

.nav-item.active {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    box-shadow: 0 4px 15px rgba(79,70,229,0.3);
}

.nav-item i {
    width: 20px;
    font-size: 16px;
}

.nav-item span {
    font-size: 14px;
    font-weight: 500;
}

/* Menu Overlay for Mobile */
.menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none;
    backdrop-filter: blur(4px);
}

.menu-overlay.show {
    display: block;
}

/* زر Menu (للجهاز والكمبيوتر) */
.menu-toggle-btn {
    position: fixed;
    top: 20px;
    left: 20px;
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 20px;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 4px 15px rgba(79,70,229,0.3);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-toggle-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(79,70,229,0.4);
}

/* زر Menu عندما يكون السايد بار مفتوح */
.menu-toggle-btn.open {
    left: 300px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.menu-toggle-btn.open:hover {
    transform: scale(1.05);
}

/* Main Content Adjustment - عندما السايد بار مفتوح */
.admin-main {
    margin-left: 280px;
    padding: 24px;
    min-height: 100vh;
    transition: all 0.3s ease;
}

/* عندما السايد بار مغلق */
.admin-main.expanded {
    margin-left: 0;
}

/* Mobile Styles */
@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
        left: 0;
    }

    .admin-sidebar.open {
        transform: translateX(0);
    }

    .admin-sidebar.collapsed {
        transform: translateX(-100%);
        left: 0;
    }

    .close-sidebar {
        display: flex;
    }

    .menu-toggle-btn {
        display: flex;
    }

    .admin-main {
        margin-left: 0 !important;
        padding: 16px;
        padding-top: 80px;
    }

    .admin-main.expanded {
        margin-left: 0;
    }
}

/* Desktop Styles */
@media (min-width: 769px) {
    .admin-main {
        margin-left: 280px;
    }

    .admin-main.expanded {
        margin-left: 0;
    }

    .menu-toggle-btn {
        display: flex;
    }
}
</style>

<script>
(function() {
    const sideMenu = document.getElementById('sideMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const closeMenuBtn = document.getElementById('closeMenuBtn');

    // التحقق من حالة السايد بار من localStorage
    const sidebarState = localStorage.getItem('admin_sidebar_collapsed');

    // تطبيق الحالة المخزنة (للكمبيوتر فقط)
    if (sidebarState === 'collapsed' && window.innerWidth > 768) {
        sideMenu.classList.add('collapsed');
        document.querySelector('.admin-main')?.classList.add('expanded');
        menuToggleBtn.classList.add('open');
        menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    } else {
        // التأكد من أن السايد بار مفتوح بشكل افتراضي
        if (window.innerWidth > 768) {
            sideMenu.classList.remove('collapsed');
            document.querySelector('.admin-main')?.classList.remove('expanded');
            menuToggleBtn.classList.remove('open');
            menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        }
    }

    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            // للجوال: use transform
            sideMenu.classList.toggle('open');
            menuOverlay.classList.toggle('show');
            if (sideMenu.classList.contains('open')) {
                menuToggleBtn.innerHTML = '<i class="fas fa-times"></i>';
                document.body.style.overflow = 'hidden';
            } else {
                menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.style.overflow = '';
            }
        } else {
            // للكمبيوتر: collapse/expand
            sideMenu.classList.toggle('collapsed');
            document.querySelector('.admin-main')?.classList.toggle('expanded');
            menuToggleBtn.classList.toggle('open');

            if (sideMenu.classList.contains('collapsed')) {
                menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                localStorage.setItem('admin_sidebar_collapsed', 'collapsed');
            } else {
                menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                localStorage.setItem('admin_sidebar_collapsed', 'expanded');
            }
        }
    }

    function closeSidebar() {
        if (window.innerWidth <= 768) {
            sideMenu.classList.remove('open');
            menuOverlay.classList.remove('show');
            menuToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.style.overflow = '';
        }
    }

    // Event Listeners
    if (menuToggleBtn) menuToggleBtn.addEventListener('click', toggleSidebar);
    if (closeMenuBtn) closeMenuBtn.addEventListener('click', closeSidebar);
    if (menuOverlay) menuOverlay.addEventListener('click', closeSidebar);

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (window.innerWidth <= 768 && sideMenu.classList.contains('open')) {
                closeSidebar();
            }
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                // Desktop
                if (sideMenu.classList.contains('open')) {
                    sideMenu.classList.remove('open');
                    menuOverlay.classList.remove('show');
                }
                document.body.style.overflow = '';

                // Apply saved state
                const savedState = localStorage.getItem('admin_sidebar_collapsed');
                if (savedState === 'collapsed') {
                    sideMenu.classList.add('collapsed');
                    document.querySelector('.admin-main')?.classList.add('expanded');
                    menuToggleBtn.classList.add('open');
                } else {
                    sideMenu.classList.remove('collapsed');
                    document.querySelector('.admin-main')?.classList.remove('expanded');
                    menuToggleBtn.classList.remove('open');
                }
            } else {
                // Mobile
                sideMenu.classList.remove('collapsed');
                document.querySelector('.admin-main')?.classList.remove('expanded');
                menuToggleBtn.classList.remove('open');
            }
        }, 100);
    });

    // Highlight current page
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) {
            item.classList.add('active');
        }
    });
})();
</script>