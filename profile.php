<?php
// =============================================
// profile.php - صفحة الملف الشخصي مع API Key Management
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
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['balance'] = $user['balance'];
$_SESSION['username'] = $user['username'];

// =============================================
// معالجة تحديث الملف الشخصي
// =============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($full_name)) {
                $error = 'Full name is required';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } else {
                try {
                    if (!empty($email) && $email !== $user['email']) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $check->execute([$email, $_SESSION['user_id']]);
                        if ($check->fetch()) {
                            $error = 'Email already used by another account';
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
                        $success = 'Profile updated successfully';
                        $user['full_name'] = $full_name;
                        $user['email'] = $email;
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to update profile';
                }
            }
        }

        if ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill all password fields';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success = 'Password changed successfully';
            }
        }

        if ($action === 'generate_api_key') {
            $new_api_key = 'SKY_' . bin2hex(random_bytes(32)) . '_' . time();
            $stmt = $pdo->prepare("UPDATE users SET api_key = ?, api_key_created_at = NOW() WHERE id = ?");
            $stmt->execute([$new_api_key, $_SESSION['user_id']]);
            $user['api_key'] = $new_api_key;
            $user['api_key_created_at'] = date('Y-m-d H:i:s');
            $success = 'API Key generated successfully';
        }

        if ($action === 'delete_api_key') {
            $stmt = $pdo->prepare("UPDATE users SET api_key = NULL, api_key_created_at = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user['api_key'] = null;
            $user['api_key_created_at'] = null;
            $success = 'API Key deleted successfully';
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب إحصائيات المستخدم
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(price), 0) as total_spent
    FROM orders
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_orders
    FROM orders
    WHERE user_id = ? AND status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$pending = $stmt->fetch();
?>

$theme_settings = getThemeSettings($pdo);
$data = [
    'user' => $user ?? null,
    'site_title' => 'Profile | SkyLink',
    'theme_settings' => $theme_settings
];

ob_start();
?>
<div class='profile-content'>
    <h1>Profile</h1>
    <div class='card'>
        <p>This is the Profile page. Full content integration in progress.</p>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>