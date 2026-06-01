<?php
session_start();
require_once 'config.php';
require_once 'themes/theme_loader.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    return;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    return;
}

$theme_settings = getThemeSettings($pdo);
$data = [
    'user' => $user,
    'site_title' => 'Dashboard | SkyLink',
    'theme_settings' => $theme_settings
];

ob_start();
?>
<div class='dashboard-content'>
    <h1 style='margin-bottom: 30px;'>Dashboard Overview</h1>

    <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 50px;'>
        <div class='card'>
            <div style='color: var(--text-muted); font-size: 14px; margin-bottom: 10px;'>Available Balance</div>
            <div style='font-size: 32px; font-weight: 800;'>$<?php echo number_format($user['balance'], 2); ?></div>
        </div>
        <div class='card'>
            <div style='color: var(--text-muted); font-size: 14px; margin-bottom: 10px;'>Active Orders</div>
            <div style='font-size: 32px; font-weight: 800;'>12</div>
        </div>
        <div class='card'>
            <div style='color: var(--text-muted); font-size: 14px; margin-bottom: 10px;'>Support Tickets</div>
            <div style='font-size: 32px; font-weight: 800;'>0</div>
        </div>
    </div>

    <div class='card'>
        <h2>Quick Order</h2>
        <div style='margin-top: 20px;'>
            <label style='display:block; margin-bottom:10px;'>Category</label>
            <select style='width:100%; padding:12px; border-radius:8px; border:1px solid #ddd; margin-bottom:20px;'>
                <option>Instagram Followers</option>
                <option>TikTok Likes</option>
            </select>
            <button class='btn-primary' style='width: 100%; padding: 15px;'>Submit</button>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>