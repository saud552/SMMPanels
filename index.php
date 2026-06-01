<?php
session_start();
require_once 'config.php';
require_once 'themes/theme_loader.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    return;
}

$theme_settings = getThemeSettings($pdo);
$site_title = 'Welcome to SkyLink';

$data = [
    'site_title' => $site_title,
    'theme_settings' => $theme_settings,
    'footer_text' => 'Best SMM Panel'
];

ob_start();
?>
<div class='hero-section'>
    <h1 style='font-size: 3rem; margin-bottom: 20px;'>Level Up Your Social Media</h1>
    <p style='font-size: 1.2rem; color: var(--text-muted); margin-bottom: 40px;'>The most advanced SMM panel with radical UI technology.</p>

    <div class='login-card card' style='max-width: 400px; margin: 0 auto;'>
        <h2>Login to Access</h2>
        <form method='POST'>
            <input type='text' name='email' placeholder='Username' style='width: 100%; padding: 10px; margin-bottom: 10px;'>
            <input type='password' name='password' placeholder='Password' style='width: 100%; padding: 10px; margin-bottom: 20px;'>
            <button type='submit' name='login' class='btn-primary' style='width: 100%; padding: 12px;'>Login Now</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

renderThemePart('layout', $pdo, array_merge($data, ['content' => $content]));
?>