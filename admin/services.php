<?php
// =============================================
// admin/services.php - إدارة الخدمات (مع وصف الخدمة وبحث مباشر)
// =============================================
require_once '../config.php';

// التحقق من صلاحيات المشرف
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// التأكد من وجود فئات في قاعدة البيانات
$check_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
if ($check_categories == 0) {
    $pdo->exec("
        INSERT INTO `categories` (`name`, `platform_id`, `sort_order`, `status`) VALUES
        ('Instagram Followers', 1, 1, 1),
        ('Instagram Likes', 1, 2, 1),
        ('Instagram Views', 1, 3, 1),
        ('TikTok Followers', 2, 1, 1),
        ('TikTok Likes', 2, 2, 1),
        ('TikTok Views', 2, 3, 1),
        ('YouTube Subscribers', 3, 1, 1),
        ('YouTube Views', 3, 2, 1),
        ('YouTube Likes', 3, 3, 1),
        ('Telegram Members', 4, 1, 1),
        ('Telegram Post Views', 4, 2, 1),
        ('Twitter Followers', 5, 1, 1),
        ('Twitter Likes', 5, 2, 1),
        ('Facebook Page Likes', 6, 1, 1),
        ('Facebook Post Likes', 6, 2, 1)
    ");
}

$success = '';
$error = '';

// =============================================
// تحديث الخدمة (مع الوصف)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $service_id = intval($_POST['service_id']);
    $price_per_1000 = floatval($_POST['price_per_1000']);
    $min_qty = intval($_POST['min_qty']);
    $max_qty = intval($_POST['max_qty']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare("UPDATE services SET name = ?, min_qty = ?, max_qty = ?, price_per_1000 = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $min_qty, $max_qty, $price_per_1000, $description, $service_id]);
    $success = "Service updated successfully";
}

// حذف خدمة
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: services.php');
    exit;
}

// تعطيل/تفعيل خدمة
if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("UPDATE services SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
    $stmt->execute([$_GET['toggle']]);
    header('Location: services.php');
    exit;
}

// إضافة خدمة جديدة (مع الوصف)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $min_qty = intval($_POST['min'] ?? 100);
    $max_qty = intval($_POST['max'] ?? 10000);
    $price = floatval($_POST['price_per_1000'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($category_id <= 0) {
        $error = "Please select a category.";
    } elseif (empty($name)) {
        $error = "Service name is required.";
    } else {
        $check = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $check->execute([$category_id]);
        if (!$check->fetch()) {
            $error = "Selected category does not exist.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (category_id, name, min_qty, max_qty, price_per_1000, description, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$category_id, $name, $min_qty, $max_qty, $price, $description]);
            $success = "Service added successfully";
        }
    }
}

// جلب الخدمات
$services = $pdo->query("
    SELECT s.*, c.name as category_name, p.name as provider_name
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.id
    LEFT JOIN api_providers p ON s.provider_id = p.id
    ORDER BY c.name, s.name
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// تجميع الخدمات حسب الفئة
$services_by_category = [];
foreach ($services as $service) {
    $cat_name = $service['category_name'] ?? 'Uncategorized';
    if (!isset($services_by_category[$cat_name])) {
        $services_by_category[$cat_name] = [];
    }
    $services_by_category[$cat_name][] = $service;
}

$site_domain = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - <?php echo htmlspecialchars($site_domain); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }

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
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        /* Admin Main Content */
        .admin-main {
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
                padding: 80px 16px 16px;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 24px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .page-title i { color: var(--primary); }
        .page-subtitle {
            color: var(--gray-500);
            font-size: 13px;
        }

        /* Action Buttons Row */
        .action-buttons-row {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-1px); }

        /* Search Box */
        .search-box {
            background: white;
            border-radius: 12px;
            padding: 4px;
            display: flex;
            align-items: center;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        .search-box i {
            padding: 0 12px;
            color: var(--gray-400);
        }
        .search-box input {
            flex: 1;
            padding: 12px 0;
            border: none;
            font-size: 14px;
            outline: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 3px solid #ef4444; }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            display: none;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }
        .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-header h3 i { color: var(--primary); }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-400);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .form-group label i { margin-right: 4px; color: var(--primary); }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 13px;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .btn-save {
            flex: 1;
            padding: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-cancel {
            flex: 1;
            padding: 10px;
            background: var(--gray-100);
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }

        /* Category Section */
        .category-section {
            margin-bottom: 30px;
        }
        .category-header {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .category-header i { color: var(--primary); font-size: 16px; }
        .category-header span { font-size: 14px; }

        /* Services Table */
        .services-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .services-table th,
        .services-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }
        .services-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-500);
            font-size: 11px;
        }
        .services-table tr:hover { background: var(--gray-50); }

        .service-id {
            font-family: monospace;
            font-size: 11px;
            color: var(--gray-500);
        }
        .service-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }
        .service-description {
            font-size: 10px;
            color: var(--gray-400);
            margin-top: 2px;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }

        .action-icons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .action-icon {
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .action-icon.edit { background: var(--primary); color: white; }
        .action-icon.edit:hover { background: var(--primary-dark); }
        .action-icon.delete { background: rgba(239,68,68,0.1); color: #dc2626; }
        .action-icon.delete:hover { background: rgba(239,68,68,0.2); }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-400);
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        @media (max-width: 768px) {
            .services-table { min-width: 650px; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<?php require_once 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="admin-main">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <i class="fas fa-cogs"></i>
            <span>Manage Services</span>
        </div>
        <div class="page-subtitle">Manage your social media marketing services</div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons-row">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add Service
        </button>
        <a href="api_categories.php" class="btn btn-success">
            <i class="fas fa-cloud-download-alt"></i> Import from API
        </a>
        <a href="?sync_prices=1" class="btn btn-warning">
            <i class="fas fa-sync-alt"></i> Sync API Prices
        </a>
    </div>

    <!-- Search Box -->
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search by Service ID or Name..." autocomplete="off">
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- Services by Category -->
    <div id="servicesContainer">
        <?php if (empty($services_by_category)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No services found</h3>
            <p>Click "Add Service" to create your first service</p>
        </div>
        <?php else: ?>
            <?php foreach ($services_by_category as $category_name => $category_services): ?>
            <div class="category-section" data-category="<?php echo htmlspecialchars($category_name); ?>">
                <div class="category-header">
                    <i class="fas fa-folder"></i>
                    <span><?php echo htmlspecialchars($category_name); ?></span>
                    <span style="font-size: 11px; color: var(--gray-400);">(<?php echo count($category_services); ?> services)</span>
                </div>

                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="services-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Service Name</th>
                                <th>Min/Max</th>
                                <th>Price/1K</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_services as $service): ?>
                            <tr class="service-row" data-id="<?php echo $service['id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>">
                                <td class="service-id"><?php echo $service['id']; ?></td>
                                <td>
                                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                    <?php if (!empty($service['description'])): ?>
                                    <div class="service-description" title="<?php echo htmlspecialchars($service['description']); ?>">
                                        <?php echo htmlspecialchars(substr($service['description'], 0, 40)) . (strlen($service['description']) > 40 ? '...' : ''); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($service['min_qty']); ?> / <?php echo number_format($service['max_qty']); ?></td>
                                <td><strong>$<?php echo number_format($service['price_per_1000'], 2); ?></strong></td>
                                <td>
                                    <?php if ($service['provider_name']): ?>
                                        <span style="font-size: 10px; background: var(--gray-200); padding: 2px 8px; border-radius: 20px;">
                                            <?php echo htmlspecialchars($service['provider_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 11px;">Local</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $service['status'] == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($service['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <button class="action-icon edit" onclick="openEditModal(
                                            <?php echo $service['id']; ?>,
                                            '<?php echo addslashes($service['name']); ?>',
                                            <?php echo $service['min_qty']; ?>,
                                            <?php echo $service['max_qty']; ?>,
                                            <?php echo $service['price_per_1000']; ?>,
                                            '<?php echo addslashes($service['description'] ?? ''); ?>'
                                        )">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?toggle=<?php echo $service['id']; ?>" class="action-icon" style="background:<?php echo $service['status'] == 'active' ? '#ef4444' : '#10b981'; ?>; color:white;">
                                            <i class="fas <?php echo $service['status'] == 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            <?php echo $service['status'] == 'active' ? 'Disable' : 'Enable'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $service['id']; ?>" class="action-icon delete" onclick="return confirm('Delete this service?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="empty-state" id="noResults" style="display: none;">
        <i class="fas fa-search"></i>
        <h3>No matching services found</h3>
        <p>Try searching with different keywords</p>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Service</h3>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-folder"></i> Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Service Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down"></i> Min Quantity</label>
                    <input type="number" name="min" class="form-control" value="100" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-up"></i> Max Quantity</label>
                    <input type="number" name="max" class="form-control" value="10000" required>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-dollar-sign"></i> Price per 1000 ($)</label>
                <input type="number" name="price_per_1000" step="0.01" class="form-control" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description (optional)</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Service description..."></textarea>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="add_service" class="btn-save">Add Service</button>
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Service</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="service_id" id="edit_service_id">
            <input type="hidden" name="update_service" value="1">

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Service Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down"></i> Min Quantity</label>
                    <input type="number" name="min_qty" id="edit_min" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-up"></i> Max Quantity</label>
                    <input type="number" name="max_qty" id="edit_max" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-dollar-sign"></i> Price per 1000 ($)</label>
                <input type="number" name="price_per_1000" id="edit_price" step="0.01" class="form-control" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Service description..."></textarea>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn-save">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Search functionality - Live search
const searchInput = document.getElementById('searchInput');
const categorySections = document.querySelectorAll('.category-section');
const noResultsDiv = document.getElementById('noResults');
const servicesContainer = document.getElementById('servicesContainer');

function filterServices() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    let hasVisibleServices = false;

    categorySections.forEach(section => {
        const rows = section.querySelectorAll('.service-row');
        let sectionHasVisible = false;

        rows.forEach(row => {
            const serviceId = row.dataset.id || '';
            const serviceName = row.dataset.name || '';
            const matches = serviceId.includes(searchTerm) || serviceName.includes(searchTerm);

            if (searchTerm === '') {
                row.style.display = '';
                sectionHasVisible = true;
            } else if (matches) {
                row.style.display = '';
                sectionHasVisible = true;
            } else {
                row.style.display = 'none';
            }
        });

        if (sectionHasVisible && searchTerm !== '') {
            section.style.display = 'block';
            hasVisibleServices = true;
        } else if (searchTerm === '') {
            section.style.display = 'block';
            hasVisibleServices = true;
        } else {
            section.style.display = 'none';
        }
    });

    if (searchTerm !== '' && !hasVisibleServices) {
        noResultsDiv.style.display = 'block';
        servicesContainer.style.display = 'none';
    } else {
        noResultsDiv.style.display = 'none';
        servicesContainer.style.display = 'block';
    }
}

searchInput.addEventListener('input', filterServices);

// Add Modal functions
function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

// Edit Modal functions
function openEditModal(id, name, minQty, maxQty, price, description) {
    document.getElementById('edit_service_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_min').value = minQty;
    document.getElementById('edit_max').value = maxQty;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_description').value = description || '';
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

// Close modals when clicking outside
document.getElementById('addModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

</body>
</html>