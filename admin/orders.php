<?php
session_start();
require_once '../config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// جلب بيانات الأدمن
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// معالجة تحديث حالة الطلب واسترجاع الرصيد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if ($order_id && $new_status) {
        // جلب الطلب قبل التحديث
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if ($order) {
            $old_status = $order['status'];

            // إذا تغيرت الحالة إلى canceled
            if ($new_status === 'canceled') {
                if ($old_status !== 'canceled') {
                    $refund_stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $refund_stmt->execute([$order['price'], $order['user_id']]);

                    $update_stmt = $pdo->prepare("UPDATE orders SET status = 'canceled', price = 0 WHERE id = ?");
                    $update_stmt->execute([$order_id]);

                    $success = "Order #$order_id has been CANCELED. Amount $" . number_format($order['price'], 2) . " refunded to user balance.";
                } else {
                    $success = "Order #$order_id is already canceled.";
                }
            } else {
                $update_stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $update_stmt->execute([$new_status, $order_id]);
                $success = "Order #$order_id status updated to " . ucfirst(str_replace('_', ' ', $new_status));
            }
        }
    }

    // تحديث start_counter و remains
    if (isset($_POST['update_counts'])) {
        $order_id = intval($_POST['order_id']);
        $start_counter = intval($_POST['start_counter'] ?? 0);
        $remains = intval($_POST['remains'] ?? 0);

        $update_stmt = $pdo->prepare("UPDATE orders SET start_counter = ?, remains = ? WHERE id = ?");
        $update_stmt->execute([$start_counter, $remains, $order_id]);
        $success = "Order #$order_id counts updated successfully";
    }

    // إعادة إرسال الطلب للمزود
    if (isset($_POST['resend_order'])) {
        $order_id = intval($_POST['order_id']);
        $success = "Order #$order_id has been resent to provider";
    }
}

// جلب جميع الطلبات مع الفلترة
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT o.*, u.username, s.name as service_name, s.id as service_id, p.name as provider_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN services s ON o.service_id = s.id
        LEFT JOIN api_providers p ON o.provider_id = p.id
        WHERE 1=1";

if ($status_filter !== 'all') {
    $sql .= " AND o.status = '" . addslashes($status_filter) . "'";
}

if (!empty($search)) {
    $sql .= " AND (o.id LIKE '%$search%' OR o.link LIKE '%$search%' OR s.name LIKE '%$search%' OR u.username LIKE '%$search%')";
}

$sql .= " ORDER BY o.created_at DESC";

$orders = $pdo->query($sql)->fetchAll();

// إحصائيات الطلبات
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processing_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

// عرض تفاصيل الطلب في مودال
if (isset($_GET['get_order_details']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $order = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($order);
    exit;
}

// عرض API Response
if (isset($_GET['view_api_response']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT api_response, api_error, api_order_id FROM orders WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $api_data = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($api_data);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/admin-style.css">
    <style>
        /* Stats Mini */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-mini {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .stat-mini:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .stat-mini-icon {
            width: 48px;
            height: 48px;
            background: rgba(79,70,229,0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-mini-icon i {
            font-size: 22px;
            color: #4f46e5;
        }

        .stat-mini-info {
            flex: 1;
        }

        .stat-mini-number {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-mini-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Search Box */
        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            min-width: 200px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #4f46e5;
        }

        /* Status Filters */
        .status-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            background: #e2e8f0;
            color: #475569;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #cbd5e1;
        }

        .filter-btn.active {
            background: #4f46e5;
            color: white;
        }

        /* Table Styles */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            padding: 12px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .orders-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .orders-table tr:hover {
            background: #f8fafc;
        }

        .service-cell {
            max-width: 220px;
            word-break: break-word;
        }

        .link-cell {
            max-width: 200px;
            word-break: break-all;
        }

        .link-cell a {
            color: #4f46e5;
            text-decoration: none;
            font-size: 11px;
        }

        .link-cell a:hover {
            text-decoration: underline;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pending { background: #fed7aa; color: #92400e; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-in_progress { background: #e9d5ff; color: #6b21a5; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-canceled { background: #f1f5f9; color: #475569; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1001;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 28px;
            max-width: 550px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1e293b;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .info-row {
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .info-row strong {
            display: inline-block;
            width: 110px;
            color: #475569;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        hr {
            margin: 16px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }

        .api-response {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .btn-icon {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .text-muted {
            color: #64748b;
            font-size: 11px;
        }

        .api-order-id {
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main">

        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-shopping-cart"></i> Manage Orders</h1>
            <div class="admin-user">
                <i class="fas fa-bell"></i>
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
            </div>
        </div>

        <!-- Stats Mini -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-number" style="color:#4f46e5;"><?php echo number_format($total_orders); ?></div>
                    <div class="stat-mini-label">Total Orders</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-number" style="color:#f59e0b;"><?php echo number_format($pending_orders); ?></div>
                    <div class="stat-mini-label">Pending</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon"><i class="fas fa-spinner"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-number" style="color:#3b82f6;"><?php echo number_format($processing_orders); ?></div>
                    <div class="stat-mini-label">Processing</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-mini-info">
                    <div class="stat-mini-number" style="color:#10b981;"><?php echo number_format($completed_orders); ?></div>
                    <div class="stat-mini-label">Completed</div>
                </div>
            </div>
        </div>

        <!-- Alert -->
        <?php if (isset($success)): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <!-- Status Filters -->
        <div class="status-filters">
            <a href="?status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=processing" class="filter-btn <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">Processing</a>
            <a href="?status=in_progress" class="filter-btn <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?status=completed" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?status=failed" class="filter-btn <?php echo $status_filter == 'failed' ? 'active' : ''; ?>">Failed</a>
            <a href="?status=canceled" class="filter-btn <?php echo $status_filter == 'canceled' ? 'active' : ''; ?>">Canceled</a>
        </div>

        <!-- Search Box -->
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by Order ID, Link, Service, or User..." value="<?php echo htmlspecialchars($search); ?>">
            <button onclick="searchOrders()" class="btn-icon btn-primary">Search</button>
            <?php if (!empty($search)): ?>
            <a href="?status=<?php echo $status_filter; ?>" class="btn-icon btn-secondary">Clear</a>
            <?php endif; ?>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Orders</h3>
                <span class="text-muted">Total: <?php echo count($orders); ?> orders</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Service</th>
                            <th>Provider</th>
                            <th>Link</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Start</th>
                            <th>Remains</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr class="danger">
                            <td>#<?php echo $order['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($order['username'] ?? 'N/A'); ?></strong></span>
                            <td class="service-cell">
                                <strong><?php echo htmlspecialchars(substr($order['service_name'] ?? 'N/A', 0, 30)); ?></strong>
                                <br><span class="text-muted">ID: <?php echo $order['service_id'] ?? 'N/A'; ?></span>
                             </span>
                            <td class="danger"><?php echo htmlspecialchars($order['provider_name'] ?? '-'); ?></span>
                            <td class="link-cell">
                                <a href="<?php echo htmlspecialchars($order['link']); ?>" target="_blank" title="<?php echo htmlspecialchars($order['link']); ?>">
                                    <?php echo htmlspecialchars(substr($order['link'], 0, 35)) . (strlen($order['link']) > 35 ? '...' : ''); ?>
                                </a>
                             </span>
                            <td class="danger"><?php echo number_format($order['quantity']); ?></span>
                            <td class="danger"><strong>$<?php echo number_format($order['price'], 2); ?></strong></span>
                            <td class="danger"><?php echo number_format($order['start_counter'] ?? 0); ?></span>
                            <td class="danger"><?php echo number_format($order['remains'] ?? 0); ?></span>
                            <td class="danger"><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></span>
                            <td class="danger">
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <button class="btn-icon btn-primary" onclick="showOrderModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-icon btn-warning" onclick="viewApiResponse(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-code"></i> API
                                    </button>
                                </div>
                             </span>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?>
                        <tr class="danger">
                            <td colspan="11" style="text-align: center; padding: 50px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #94a3b8; margin-bottom: 10px; display: block;"></i>
                                No orders found
                             </span>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal لتحديث حالة الطلب -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-bottom: 20px;"><i class="fas fa-edit"></i> Update Order #<span id="modalOrderIdDisplay"></span></h3>

        <div id="orderDetails" style="margin-bottom: 20px;"></div>

        <form method="POST" id="orderForm">
            <input type="hidden" name="order_id" id="modal_order_id">
            <input type="hidden" name="action" value="update_status">
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="modal_status" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="canceled">Canceled</option>
                </select>
                <small style="color: #dc2626;">⚠️ Note: Changing to "Canceled" will refund the full amount to the user</small>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn-icon btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-icon btn-primary">Update Status</button>
            </div>
        </form>

        <hr>

        <form method="POST" id="resendForm">
            <input type="hidden" name="order_id" id="resend_order_id">
            <input type="hidden" name="resend_order" value="1">
            <button type="submit" class="btn-icon btn-warning" style="width:100%;">
                <i class="fas fa-paper-plane"></i> Resend Order to Provider
            </button>
        </form>

        <hr>

        <div class="form-group">
            <label>Update Start Counter & Remains</label>
            <div class="info-row" id="countsInfo" style="margin-bottom: 10px;"></div>
            <div style="display: flex; gap: 10px;">
                <input type="number" id="edit_start_counter" placeholder="Start Counter" class="form-control" style="flex:1;">
                <input type="number" id="edit_remains" placeholder="Remains" class="form-control" style="flex:1;">
                <button type="button" class="btn-icon btn-primary" onclick="updateCounts()">Update</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal لعرض API Response -->
<div id="apiModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-code"></i> API Response Details</h3>
        <div id="apiResponseContent" class="api-response"></div>
        <button class="btn-icon btn-secondary" onclick="closeApiModal()" style="margin-top: 16px;">Close</button>
    </div>
</div>

<script>
let currentOrderId = 0;

function searchOrders() {
    const searchValue = document.getElementById('searchInput').value;
    const currentStatus = '<?php echo $status_filter; ?>';
    window.location.href = '?status=' + currentStatus + '&search=' + encodeURIComponent(searchValue);
}

function showOrderModal(id, status) {
    currentOrderId = id;
    document.getElementById('modal_order_id').value = id;
    document.getElementById('resend_order_id').value = id;
    document.getElementById('modalOrderIdDisplay').textContent = id;
    document.getElementById('modal_status').value = status;

    fetch('?get_order_details=1&id=' + id)
        .then(res => res.json())
        .then(data => {
            let detailsHtml = `
                <div class="info-row"><strong>Service:</strong> ${escapeHtml(data.service_name || 'N/A')}</div>
                <div class="info-row"><strong>Link:</strong> <a href="${escapeHtml(data.link)}" target="_blank" style="word-break:break-all;">${escapeHtml(data.link.substring(0, 60))}${data.link.length > 60 ? '...' : ''}</a></div>
                <div class="info-row"><strong>Quantity:</strong> ${Number(data.quantity).toLocaleString()}</div>
                <div class="info-row"><strong>Price:</strong> $${Number(data.price).toFixed(2)}</div>
                <div class="info-row"><strong>Start Counter:</strong> ${Number(data.start_counter || 0).toLocaleString()}</div>
                <div class="info-row"><strong>Remains:</strong> ${Number(data.remains || 0).toLocaleString()}</div>
                <div class="info-row"><strong>API Order ID:</strong> <code class="api-order-id">${escapeHtml(data.api_order_id || 'N/A')}</code></div>
            `;
            document.getElementById('orderDetails').innerHTML = detailsHtml;
            document.getElementById('countsInfo').innerHTML = `<strong>Current:</strong> Start=${Number(data.start_counter || 0).toLocaleString()} | Remains=${Number(data.remains || 0).toLocaleString()}`;
            document.getElementById('edit_start_counter').value = data.start_counter || 0;
            document.getElementById('edit_remains').value = data.remains || 0;
        });

    document.getElementById('orderModal').classList.add('show');
}

function closeModal() {
    document.getElementById('orderModal').classList.remove('show');
}

function updateCounts() {
    const startCounter = document.getElementById('edit_start_counter').value;
    const remains = document.getElementById('edit_remains').value;

    const formData = new FormData();
    formData.append('order_id', currentOrderId);
    formData.append('start_counter', startCounter);
    formData.append('remains', remains);
    formData.append('update_counts', '1');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(() => {
        location.reload();
    });
}

function viewApiResponse(id) {
    fetch('?view_api_response=1&id=' + id)
        .then(res => res.json())
        .then(data => {
            let html = '';
            html += '<strong>API Order ID:</strong><br>' + (data.api_order_id || 'No API Order ID') + '<br><br>';
            html += '<strong>API Response:</strong><br>' + (data.api_response || 'No response saved') + '<br><br>';
            if (data.api_error) html += '<strong>Error:</strong><br>' + data.api_error;
            document.getElementById('apiResponseContent').innerHTML = html;
            document.getElementById('apiModal').classList.add('show');
        })
        .catch(error => {
            document.getElementById('apiResponseContent').innerHTML = '<strong>Error loading API response</strong>';
            document.getElementById('apiModal').classList.add('show');
        });
}

function closeApiModal() {
    document.getElementById('apiModal').classList.remove('show');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// Search on Enter key
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchOrders();
    }
});
</script>

</body>
</html>