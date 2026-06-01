<?php renderThemePart('header', $pdo, $data); ?>

<div class="classic-layout">
    <div class="sidebar">
        <!-- Sidebar Navigation -->
        <?php renderThemePart('navigation', $pdo, $data); ?>
    </div>
    <main class="main-content">
        <?php echo $content; ?>
    </main>
</div>

<?php renderThemePart('footer', $pdo, $data); ?>
