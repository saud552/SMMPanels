<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Panel - SkyLink SMM</title>
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
            background: #f1f5f9;
            min-height: 100vh;
        }

        /* Header */
        .admin-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .logo h2 {
            font-size: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* زر القائمة (3 خطوط) */
        .menu-btn {
            background: none;
            border: none;
            font-size: 22px;
            color: #1e293b;
            cursor: pointer;
            padding: 10px;
            border-radius: 10px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .menu-btn:hover {
            background: #f1f5f9;
        }

        /* القائمة الجانبية المنزلقة */
        .side-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100%;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 200;
            transition: left 0.3s ease;
            overflow-y: auto;
        }
        .side-menu.open {
            left: 0;
        }
        .side-menu-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #6366f1, #ec4899);
        }
        .side-menu-header h3 {
            color: white;
            font-size: 18px;
        }
        .close-menu {
            background: rgba(255,255,255,0.2);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }
        .side-menu-items {
            list-style: none;
            padding: 16px 0;
        }
        .side-menu-item {
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .side-menu-item a {
            text-decoration: none;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .side-menu-item i {
            width: 22px;
            color: #6366f1;
            font-size: 16px;
        }
        .side-menu-item:hover {
            background: #f1f5f9;
        }
        .side-menu-item.active {
            background: rgba(99,102,241,0.1);
        }
        .side-menu-item.active a {
            color: #6366f1;
        }

        /* Overlay */
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 199;
            display: none;
        }
        .menu-overlay.show {
            display: block;
        }

        /* القائمة الجانبية العادية للشاشات الكبيرة */
        .admin-container {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-item {
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 12px;
        }
        .sidebar-item a {
            text-decoration: none;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-item i {
            width: 20px;
            color: #6366f1;
        }
        .sidebar-item:hover {
            background: #f1f5f9;
        }
        .sidebar-item.active {
            background: rgba(99,102,241,0.1);
        }
        .sidebar-item.active a {
            color: #6366f1;
        }

        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-x: auto;
        }
        .page-title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #ec4899);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }
        .btn-danger {
            background: rgba(239,68,68,0.1);
            color: #dc2626;
        }
        .btn-danger:hover {
            background: rgba(239,68,68,0.2);
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        th {
            color: #64748b;
            font-weight: 600;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pending { background: rgba(245,158,11,0.1); color: #d97706; }
        .badge-processing { background: rgba(59,130,246,0.1); color: #2563eb; }
        .badge-progress { background: rgba(99,102,241,0.1); color: #6366f1; }
        .badge-completed { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-failed { background: rgba(239,68,68,0.1); color: #dc2626; }
        .badge-canceled { background: rgba(107,114,128,0.1); color: #6b7280; }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1e293b;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #6366f1;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 13px;
            z-index: 1100;
            display: none;
        }
        .toast.show {
            display: block;
            animation: fadeInUp 0.3s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                padding: 16px;
            }
            table {
                min-width: 800px;
            }
        }
        @media (min-width: 769px) {
            .menu-btn {
                display: none;
            }
            .side-menu, .menu-overlay {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- القائمة الجانبية المنزلقة (للموبايل) -->
<div class="side-menu" id="sideMenu">
    <div class="side-menu-header">
        <h3><i class="fas fa-chart-line"></i> SkyLink Admin</h3>
        <button class="close-menu" id="closeMenuBtn"><i class="fas fa-times"></i></button>
    </div>
    <ul class="side-menu-items">
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        </li>
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
            <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        </li>
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
        </li>
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
            <a href="services.php"><i class="fas fa-cogs"></i> Services</a>
        </li>
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'providers.php' ? 'active' : ''; ?>">
            <a href="providers.php"><i class="fas fa-cloud-upload-alt"></i> API Providers</a>
        </li>
        <li class="side-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
            <a href="payments.php"><i class="fas fa-credit-card"></i> Payment Methods</a>
        </li>
        <li style="margin-top: 20px; border-top: 1px solid #e2e8f0; margin-left: 20px; margin-right: 20px;"></li>
        <li class="side-menu-item">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>
<div class="menu-overlay" id="menuOverlay"></div>

<div class="admin-header">
    <div class="logo">
        <h2>SkyLink Admin</h2>
    </div>
    <div style="display: flex; align-items: center; gap: 16px;">
        <span class="admin-name" style="font-size: 14px; font-weight: 600;">
            <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
        </span>
        <button class="menu-btn" id="menuBtn">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

<div class="admin-container">
    <!-- القائمة الجانبية العادية للشاشات الكبيرة -->
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            </li>
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
            </li>
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
            </li>
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                <a href="services.php"><i class="fas fa-cogs"></i> Services</a>
            </li>
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'providers.php' ? 'active' : ''; ?>">
                <a href="providers.php"><i class="fas fa-cloud-upload-alt"></i> API Providers</a>
            </li>
            <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <a href="payments.php"><i class="fas fa-credit-card"></i> Payment Methods</a>
            </li>
            <li style="margin-top: 20px; border-top: 1px solid #e2e8f0; margin: 20px 20px 0;"></li>
            <li class="sidebar-item">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </div>

    <div class="main-content">