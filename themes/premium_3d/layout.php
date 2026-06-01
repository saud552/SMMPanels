<?php renderThemePart('header', $pdo, $data); ?>

<div class="premium-3d-layout">
    <nav class="top-nav-3d">
        <?php renderThemePart('navigation', $pdo, $data); ?>
    </nav>
    <main class="main-content-3d">
        <div class="glass-container">
            <?php echo $content; ?>
        </div>
    </main>
</div>

<?php renderThemePart('footer', $pdo, $data); ?>
