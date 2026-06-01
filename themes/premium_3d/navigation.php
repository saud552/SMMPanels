<div class="nav-3d-inner" style="display: flex; justify-content: space-between; align-items: center;">
    <a href="dashboard.php" class="logo-3d" style="font-weight: 800; color: var(--primary); font-size: 20px; text-decoration: none;">SKYLINK 3D</a>
    <div class="nav-links" style="display: flex; gap: 30px;">
        <a href="dashboard.php" style="color: white; text-decoration: none; font-size: 14px; font-weight: 500;">DASHBOARD</a>
        <a href="services.php" style="color: white; text-decoration: none; font-size: 14px; font-weight: 500;">SERVICES</a>
        <a href="orders.php" style="color: white; text-decoration: none; font-size: 14px; font-weight: 500;">ORDERS</a>
    </div>
    <div class="nav-user">
        <span style="font-size: 13px; color: var(--primary); font-weight: 600;">$<?php echo number_format($user['balance'], 2); ?></span>
    </div>
</div>
