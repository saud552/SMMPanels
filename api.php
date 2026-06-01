<?php
// =============================================
// api.php - صفحة توثيق API (متاحة للجميع)
// =============================================
session_start();
require_once 'config.php';

$site_domain = $_SERVER['HTTP_HOST'];
$api_base_url = "https://" . $site_domain . "/api/v2";

// التحقق من وجود مستخدم مسجل
$is_logged_in = isset($_SESSION['user_id']);
$user = null;
$masked_api_key = '';

if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && !empty($user['api_key'])) {
        $key = $user['api_key'];
        $masked_api_key = substr($key, 0, 15) . '••••••••••••' . substr($key, -10);
    }
}
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>API Documentation - <?php echo htmlspecialchars($site_domain); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fc;
            color: #1a1a2e;
            line-height: 1.5;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #eef2f6;
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
            color: var(--primary);
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
            color: var(--primary);
        }

        /* Main Content */
        .main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 100px 20px 40px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .page-title i {
            color: var(--primary);
        }

        .page-subtitle {
            color: #64748b;
            font-size: 14px;
        }

        /* API Key Card */
        .api-key-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            border: 1px solid #eef2f6;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .api-key-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .api-key-title {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .api-key-title i {
            color: var(--primary);
        }

        .api-key-box {
            background: linear-gradient(135deg, rgba(79,70,229,0.05), rgba(236,72,153,0.05));
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(79,70,229,0.2);
        }

        .api-key-display {
            background: #1e293b;
            border-radius: 12px;
            padding: 14px;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
            color: #e2e8f0;
            margin-bottom: 16px;
        }

        .api-key-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .api-key-info {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #94a3b8;
        }

        .account-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .account-link:hover {
            text-decoration: underline;
        }

        /* API Documentation Cards */
        .docs-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #eef2f6;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .docs-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef2f6;
        }

        .docs-title i {
            color: var(--primary);
        }

        .endpoint {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .endpoint-method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .method-post {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .method-get {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        .endpoint-url {
            font-family: monospace;
            font-size: 13px;
            color: #1a1a2e;
            word-break: break-all;
        }

        .copy-endpoint {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 13px;
        }

        .copy-endpoint:hover {
            color: var(--primary);
        }

        .param-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .param-table th,
        .param-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .param-table th {
            color: #64748b;
            font-weight: 600;
            width: 120px;
        }

        .code-block {
            background: #1e293b;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            position: relative;
        }

        .code-block pre {
            color: #e2e8f0;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .copy-code {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            color: #e2e8f0;
            font-size: 11px;
            cursor: pointer;
        }

        .copy-code:hover {
            background: var(--primary);
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-required {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-optional {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

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

        @media (max-width: 768px) {
            .main {
                padding: 90px 16px 30px;
            }
            .page-title {
                font-size: 24px;
            }
            .param-table th,
            .param-table td {
                display: block;
                width: 100%;
            }
            .param-table th {
                padding-bottom: 0;
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
<div class="header">
    <a href="index.php" class="logo"><?php echo htmlspecialchars($site_domain); ?><span>SMM</span></a>
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

<div class="main">
    <div class="page-header">
        <div class="page-title">
            <i class="fas fa-code"></i>
            <span>API Documentation</span>
        </div>
        <div class="page-subtitle">RESTful API for integrating our services into your applications</div>
    </div>

    <!-- API Key Card -->
    <div class="api-key-card">
        <div class="api-key-header">
            <div class="api-key-title">
                <i class="fas fa-key"></i>
                <span>API Key</span>
            </div>
        </div>

        <div class="api-key-box">
            <?php if ($is_logged_in && !empty($user['api_key'])): ?>
                <!-- مستخدم مسجل ولديه مفتاح API -->
                <div class="api-key-display" id="apiKeyDisplay">
                    <?php echo htmlspecialchars($masked_api_key); ?>
                </div>
                <div class="api-key-actions">
                    <button class="btn btn-primary" onclick="copyFullApiKey()">
                        <i class="fas fa-copy"></i> Copy Full Key
                    </button>
                </div>
                <div class="api-key-info">
                    <span><i class="fas fa-calendar"></i> Created: <?php echo date('F d, Y \a\t h:i A', strtotime($user['api_key_created_at'])); ?></span>
                    <span><i class="fas fa-shield-alt"></i> Keep your API key secure</span>
                    <span><i class="fas fa-sync-alt"></i> <a href="profile.php" style="color: var(--primary);">Regenerate</a> from profile page</span>
                </div>
            <?php elseif ($is_logged_in && empty($user['api_key'])): ?>
                <!-- مستخدم مسجل ولكن ليس لديه مفتاح API -->
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-key" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px; display: block;"></i>
                    <p style="color: #64748b; margin-bottom: 20px;">You don't have an API key yet.</p>
                    <a href="profile.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Generate API Key
                    </a>
                </div>
            <?php else: ?>
                <!-- غير مسجل دخول -->
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-key" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px; display: block;"></i>
                    <p style="color: #64748b; margin-bottom: 20px;">
                        Get an API key on the
                        <a href="profile.php" class="account-link">Account page</a>
                    </p>
                    <p style="font-size: 12px; color: #94a3b8;">Please <a href="index.php" style="color: var(--primary);">login</a> first if you already have an account.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Base URL -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-globe"></i>
            <span>Base URL</span>
        </div>
        <div class="endpoint">
            <code class="endpoint-url"><?php echo $api_base_url; ?>/</code>
            <button class="copy-endpoint" onclick="copyToClipboard('<?php echo $api_base_url; ?>/')">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>
        <p style="font-size: 13px; color: #64748b; margin-top: 8px;">All API endpoints are relative to this base URL. Use HTTPS only.</p>
    </div>

    <!-- Authentication -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-lock"></i>
            <span>Authentication</span>
        </div>
        <p style="margin-bottom: 16px; font-size: 14px;">All API requests require authentication using your API key.</p>
        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>Headers:
    X-API-Key: YOUR_API_KEY_HERE
    Content-Type: application/json</pre>
        </div>
    </div>

    <!-- Place Order -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-shopping-cart"></i>
            <span>Place Order</span>
        </div>
        <div class="endpoint">
            <span class="endpoint-method method-post">POST</span>
            <code class="endpoint-url">/order</code>
            <button class="copy-endpoint" onclick="copyToClipboard('/order')">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <table class="param-table">
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td>service_id</td><td>int</td><td>Service ID <span class="badge badge-required">Required</span></td></tr>
            <tr><td>link</td><td>string</td><td>Profile/Post URL <span class="badge badge-required">Required</span></td></tr>
            <tr><td>quantity</td><td>int</td><td>Quantity <span class="badge badge-required">Required</span></td></tr>
        </table>

        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>curl -X POST <?php echo $api_base_url; ?>/order \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 1,
    "link": "https://instagram.com/username",
    "quantity": 1000
  }'</pre>
        </div>

        <div class="code-block" style="margin-top: 12px;">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>{
  "success": true,
  "order_id": 12345,
  "service_name": "Instagram Followers",
  "quantity": 1000,
  "price": 2.50,
  "status": "pending",
  "message": "Order placed successfully!"
}</pre>
        </div>
    </div>

    <!-- Get Services -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-list-alt"></i>
            <span>Get Services</span>
        </div>
        <div class="endpoint">
            <span class="endpoint-method method-get">GET</span>
            <code class="endpoint-url">/services</code>
            <button class="copy-endpoint" onclick="copyToClipboard('/services')">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>curl -X GET <?php echo $api_base_url; ?>/services \
  -H "X-API-Key: YOUR_API_KEY"</pre>
        </div>

        <div class="code-block" style="margin-top: 12px;">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>{
  "success": true,
  "services": [
    {
      "id": 1,
      "name": "Instagram Followers",
      "min": 100,
      "max": 10000,
      "price_per_1000": 2.50,
      "status": "active"
    }
  ]
}</pre>
        </div>
    </div>

    <!-- Get Order Status -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-chart-line"></i>
            <span>Get Order Status</span>
        </div>
        <div class="endpoint">
            <span class="endpoint-method method-get">GET</span>
            <code class="endpoint-url">/order/{order_id}</code>
            <button class="copy-endpoint" onclick="copyToClipboard('/order/{order_id}')">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>curl -X GET <?php echo $api_base_url; ?>/order/12345 \
  -H "X-API-Key: YOUR_API_KEY"</pre>
        </div>

        <div class="code-block" style="margin-top: 12px;">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>{
  "success": true,
  "order": {
    "id": 12345,
    "service_name": "Instagram Followers",
    "link": "https://instagram.com/username",
    "quantity": 1000,
    "price": 2.50,
    "status": "completed",
    "created_at": "2024-01-15 10:30:00"
  }
}</pre>
        </div>
    </div>

    <!-- Get Balance -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fas fa-wallet"></i>
            <span>Get Balance</span>
        </div>
        <div class="endpoint">
            <span class="endpoint-method method-get">GET</span>
            <code class="endpoint-url">/balance</code>
            <button class="copy-endpoint" onclick="copyToClipboard('/balance')">
                <i class="fas fa-copy"></i>
            </button>
        </div>

        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>curl -X GET <?php echo $api_base_url; ?>/balance \
  -H "X-API-Key: YOUR_API_KEY"</pre>
        </div>

        <div class="code-block" style="margin-top: 12px;">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>{
  "success": true,
  "balance": 1000.00,
  "formatted": "$1,000.00",
  "currency": "USD"
}</pre>
        </div>
    </div>

    <!-- PHP Example -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fab fa-php"></i>
            <span>PHP Example</span>
        </div>
        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>&lt;?php
$api_key = 'YOUR_API_KEY_HERE';
$api_url = '<?php echo $api_base_url; ?>';

$ch = curl_init($api_url . '/order');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'service_id' => 1,
    'link' => 'https://instagram.com/username',
    'quantity' => 1000
]));

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    echo "Order placed! ID: " . $data['order_id'];
} else {
    echo "Error: " . $data['error'];
}
?&gt;</pre>
        </div>
    </div>

    <!-- JavaScript Example -->
    <div class="docs-card">
        <div class="docs-title">
            <i class="fab fa-js"></i>
            <span>JavaScript Example</span>
        </div>
        <div class="code-block">
            <button class="copy-code" onclick="copyCode(this)">Copy</button>
            <pre>const apiKey = 'YOUR_API_KEY_HERE';
const apiUrl = '<?php echo $api_base_url; ?>';

fetch(apiUrl + '/order', {
    method: 'POST',
    headers: {
        'X-API-Key': apiKey,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        service_id: 1,
        link: 'https://instagram.com/username',
        quantity: 1000
    })
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        console.log('Order placed:', data.order_id);
    } else {
        console.error('Error:', data.error);
    }
});</pre>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const fullApiKey = '<?php echo addslashes($user['api_key'] ?? ''); ?>';
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const apiBaseUrl = '<?php echo $api_base_url; ?>';

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast show`;
        toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    function copyFullApiKey() {
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

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard!', 'success');
        }).catch(() => {
            showToast('Failed to copy', 'error');
        });
    }

    function copyCode(btn) {
        const pre = btn.parentElement.querySelector('pre');
        const text = pre.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const originalText = btn.innerText;
            btn.innerText = 'Copied!';
            setTimeout(() => { btn.innerText = originalText; }, 2000);
        }).catch(() => {
            showToast('Failed to copy', 'error');
        });
    }

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
</script>

</body>
</html>