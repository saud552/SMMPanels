<?php
// =============================================
// orders.php - SMM Panel Orders Page (مع تصميم جديد مثل dashboard)
// =============================================
session_start();
require_once 'config.php';

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

$_SESSION['balance'] = $user['balance'];
$_SESSION['username'] = $user['username'];

// =============================================
// إنشاء CSRF token
// =============================================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================
// معالجة AJAX requests
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'get_orders') {
        $status_filter = $_POST['status'] ?? 'all';

        $sql = "SELECT o.*, s.name as service_name
                FROM orders o
                LEFT JOIN services s ON o.service_id = s.id
                WHERE o.user_id = ?";
        $params = [$_SESSION['user_id']];

        if ($status_filter !== 'all') {
            $sql .= " AND o.status = ?";
            $params[] = $status_filter;
        }

        $sql .= " ORDER BY o.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $orders_data = [];
        foreach ($orders as $order) {
            $orders_data[] = [
                'id' => $order['id'],
                'service_name' => $order['service_name'] ?? 'N/A',
                'link' => $order['link'],
                'quantity' => $order['quantity'],
                'price' => $order['price'],
                'status' => $order['status'],
                'created_at' => $order['created_at'],
                'start_counter' => $order['start_counter'] ?? 0,
                'remains' => $order['remains'] ?? 0
            ];
        }

        $html = renderOrdersTable($orders_data);
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }
}

// دالة عرض جدول الطلبات
function renderOrdersTable($orders) {
    if (empty($orders)) {
        return '<div style="text-align: center; padding: 60px 20px;"><i class="fas fa-inbox" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px; display: block;"></i><p style="color: #64748b;">No orders found</p></div>';
    }

    $status_badges = [
        'pending' => '<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>',
        'processing' => '<span class="badge badge-processing"><i class="fas fa-spinner fa-spin"></i> Processing</span>',
        'in_progress' => '<span class="badge badge-progress"><i class="fas fa-chart-line"></i> In Progress</span>',
        'completed' => '<span class="badge badge-completed"><i class="fas fa-check-circle"></i> Completed</span>',
        'partial' => '<span class="badge badge-partial"><i class="fas fa-chart-simple"></i> Partial</span>',
        'canceled' => '<span class="badge badge-canceled"><i class="fas fa-ban"></i> Canceled</span>',
        'cancelled' => '<span class="badge badge-canceled"><i class="fas fa-ban"></i> Canceled</span>'
    ];

    $html = '<div class="orders-table-wrapper"><table class="orders-table"><thead>';
    $html .= '<th>ID</th><th>Date</th><th>Link</th><th>Charge</th><th>Start Count</th><th>Quantity</th><th>Service</th><th>Status</th><th>Remains</th>';
    $html .= '</thead><tbody>';

    foreach ($orders as $order) {
        $display_status = $order['status'];
        $badge = $status_badges[$display_status] ?? $status_badges['pending'];
        $date = date('M d, Y H:i', strtotime($order['created_at']));
        $link = htmlspecialchars($order['link']);
        $service_name = htmlspecialchars($order['service_name']);

        // اختصار الرابط إذا كان طويلاً
        $short_link = strlen($link) > 50 ? substr($link, 0, 47) . '...' : $link;
        $full_link_display = '<a href="' . $link . '" target="_blank" class="order-link" title="' . $link . '">' . $short_link . '</a>';

        $display_price = ($display_status === 'canceled' || $display_status === 'cancelled') ? 0 : $order['price'];
        $price_display = '<span class="order-price">$' . number_format($display_price, 2) . '</span>';

        $html .= '<tr>';
        $html .= '<td data-label="ID"><span class="order-id">#' . $order['id'] . '</span></td>';
        $html .= '<td data-label="Date"><span class="order-date">' . $date . '</span></td>';
        $html .= '<td data-label="Link" class="link-cell">' . $full_link_display . '</td>';
        $html .= '<td data-label="Charge">' . $price_display . '</td>';
        $html .= '<td data-label="Start Count"><span class="order-start">' . number_format($order['start_counter']) . '</span></td>';
        $html .= '<td data-label="Quantity"><span class="order-quantity">' . number_format($order['quantity']) . '</span></td>';
        $html .= '<td data-label="Service" class="service-cell"><span class="order-service" title="' . $service_name . '">' . $service_name . '</span></td>';
        $html .= '<td data-label="Status">' . $badge . '</td>';
        $html .= '<td data-label="Remains"><span class="order-remains">' . number_format($order['remains']) . '</span></td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

// جلب الطلبات الأولية
$sql = "SELECT o.*, s.name as service_name
        FROM orders o
        LEFT JOIN services s ON o.service_id = s.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$initial_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$initial_orders_data = [];
foreach ($initial_orders as $order) {
    $initial_orders_data[] = [
        'id' => $order['id'],
        'service_name' => $order['service_name'] ?? 'N/A',
        'link' => $order['link'],
        'quantity' => $order['quantity'],
        'price' => $order['price'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
        'start_counter' => $order['start_counter'] ?? 0,
        'remains' => $order['remains'] ?? 0
    ];
}

// حساب الإحصائيات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_orders = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_orders = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_orders = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$total_spent = $stmt->fetchColumn();

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Orders - SkyLink SMM</title>
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

        /* القائمة المنسدلة الرئيسية مثل dashboard */
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
        .main-dropdown.open {
            max-height: 500px;
        }
        .dropdown-container {
            padding: 16px 24px;
        }

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
        .menu-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }
        .menu-item i {
            width: 24px;
            margin-right: 8px;
            color: var(--primary);
        }
        .menu-item.active {
            color: var(--primary);
            background: rgba(79,70,229,0.05);
        }

        .menu-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 8px 0;
        }

        .main { margin-top: 73px; padding: 20px; max-width: 1400px; margin-left: auto; margin-right: auto; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--primary); }

        .filters-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; background: white; padding: 6px; border-radius: 50px; border: 1px solid var(--gray-200); display: inline-flex; }
        .filter-btn { padding: 8px 20px; border-radius: 40px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; background: transparent; border: none; color: var(--gray-600); }
        .filter-btn:hover { background: var(--gray-100); }
        .filter-btn.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }

        .stats-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-box { background: white; border-radius: 14px; padding: 12px 20px; flex: 1; min-width: 100px; border: 1px solid var(--gray-200); }
        .stat-box-label { font-size: 10px; color: var(--gray-400); margin-bottom: 4px; }
        .stat-box-value { font-size: 20px; font-weight: 800; color: var(--dark); }

        .orders-table-wrapper { background: white; border-radius: 16px; border: 1px solid var(--gray-200); overflow-x: auto; overflow-y: visible; }
        .orders-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .orders-table th { padding: 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); background: var(--gray-50); }
        .orders-table td { padding: 14px; font-size: 12px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .orders-table tr:hover { background: var(--gray-50); }

        .link-cell { max-width: 300px; word-break: break-all; white-space: normal; }
        .order-link { color: var(--primary); text-decoration: none; font-size: 11px; word-break: break-all; display: inline-block; }
        .order-link:hover { text-decoration: underline; }
        .service-cell { max-width: 200px; word-break: break-word; white-space: normal; }
        .order-service { font-size: 11px; font-weight: 500; color: var(--dark); display: inline-block; word-break: break-word; }
        .order-id { font-family: monospace; font-weight: 600; color: var(--primary); }
        .order-price { font-weight: 700; color: var(--dark); }

        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 30px; font-size: 10px; font-weight: 600; white-space: nowrap; }
        .badge-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .badge-processing { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
        .badge-progress { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .badge-completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .badge-partial { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
        .badge-canceled { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3); backdrop-filter: blur(2px); z-index: 1000; display: flex; align-items: center; justify-content: center; display: none; }
        .loading-overlay.show { display: flex; }
        .spinner { width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.3); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(100px); background: var(--dark); color: white; padding: 8px 16px; border-radius: 40px; font-size: 12px; z-index: 200; transition: 0.3s; opacity: 0; }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        @media (max-width: 768px) {
            .main { padding: 16px; margin-top: 70px; }
            .stats-row { gap: 8px; }
            .stat-box { padding: 10px 14px; }
            .stat-box-value { font-size: 16px; }
            .filters-tabs { width: 100%; overflow-x: auto; border-radius: 16px; }
            .orders-table td { padding: 10px; }
            .link-cell { max-width: 180px; }
            .service-cell { max-width: 150px; }
            .dropdown-container { padding: 12px 16px; }
        }
        @media (max-width: 600px) { .orders-table { min-width: 750px; } }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Header مثل dashboard -->
<div class="header">
    <a href="dashboard.php" class="logo"><?php echo htmlspecialchars($site_domain); ?></a>
    <button class="menu-btn" id="menuBtn">
        <i class="fas fa-bars"></i> Menu
    </button>
</div>

<!-- القائمة المنسدلة الرئيسية مثل dashboard -->
<div class="main-dropdown" id="mainDropdown">
    <div class="dropdown-container">
        <a href="dashboard.php" class="menu-item"><i class="fas fa-shopping-cart"></i> New Order</a>
        <a href="orders.php" class="menu-item active"><i class="fas fa-list-alt"></i> Orders</a>
        <a href="services.php" class="menu-item"><i class="fas fa-cogs"></i> Services</a>
        <a href="addfunds.php" class="menu-item"><i class="fas fa-plus-circle"></i> Add Funds</a>
        <a href="api.php" class="menu-item"><i class="fas fa-code"></i> API</a>
        <a href="child-panel.php" class="menu-item"><i class="fas fa-link"></i> Child Panel</a>
        <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
        <div class="menu-divider"></div>
        <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="page-header">
        <div class="page-title"><i class="fas fa-list-alt"></i><span>Orders</span></div>
    </div>

    <div class="filters-tabs">
        <button class="filter-btn active" data-status="all">All</button>
        <button class="filter-btn" data-status="pending">Pending</button>
        <button class="filter-btn" data-status="processing">Processing</button>
        <button class="filter-btn" data-status="in_progress">In Progress</button>
        <button class="filter-btn" data-status="completed">Completed</button>
        <button class="filter-btn" data-status="partial">Partial</button>
        <button class="filter-btn" data-status="canceled">Canceled</button>
    </div>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-box-label">Total Orders</div><div class="stat-box-value"><?php echo number_format($total_orders); ?></div></div>
        <div class="stat-box"><div class="stat-box-label">Completed</div><div class="stat-box-value"><?php echo number_format($completed_orders); ?></div></div>
        <div class="stat-box"><div class="stat-box-label">Pending</div><div class="stat-box-value"><?php echo number_format($pending_orders); ?></div></div>
        <div class="stat-box"><div class="stat-box-label">Total Spent</div><div class="stat-box-value">$<?php echo number_format($total_spent, 2); ?></div></div>
    </div>

    <div id="ordersContainer">
        <?php echo renderOrdersTable($initial_orders_data); ?>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    let currentStatus = 'all';

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function showLoading() { document.getElementById('loadingOverlay').classList.add('show'); }
    function hideLoading() { document.getElementById('loadingOverlay').classList.remove('show'); }

    async function loadOrders(status) {
        showLoading();
        try {
            const formData = new FormData();
            formData.append('action', 'get_orders');
            formData.append('status', status);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('ordersContainer').innerHTML = result.html;
            } else {
                showToast(result.error || 'Failed to load orders', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Network error, please try again', 'error');
        } finally {
            hideLoading();
        }
    }

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentStatus = btn.dataset.status;
            loadOrders(currentStatus);
        });
    });

    // القائمة المنسدلة الرئيسية
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