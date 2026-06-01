<?php
// =============================================
// create_admin.php - إنشاء حساب أدمن جديد
// =============================================

require_once '../config.php';

// كلمة المرور الجديدة
$username = 'admin';
$password = 'admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // حذف القديم
    $pdo->exec("DELETE FROM admin_users WHERE username = 'admin'");

    // إضافة الجديد
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, role, created_at) VALUES (?, ?, ?, 'super_admin', NOW())");
    $stmt->execute([$username, $hashed_password, 'admin@skylink.com']);

    echo "✅ Admin user created successfully!<br>";
    echo "Username: <strong>admin</strong><br>";
    echo "Password: <strong>admin</strong><br>";
    echo "<a href='login.php'>Go to Login</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>