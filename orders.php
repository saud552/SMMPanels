<?php
// =============================================
// orders.php - SMM Panel Orders Page (مع تصميم جديد مثل dashboard)
// =============================================
session_start();
require_once 'config.php';
require_once 'themes/theme_loader.php';

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

$theme_settings = getThemeSettings($pdo);
$data = [
    'user' => $user ?? null,
    'site_title' => 'Orders | SkyLink',
    'theme_settings' => $theme_settings
];

ob_start();
?>
<div class='orders-content'>
    <h1>Orders</h1>
    <div class='card'>
        <p>This is the Orders page. Full content integration in progress.</p>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>