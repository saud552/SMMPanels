<?php renderThemePart('header', $pdo, $data); ?>

<div class="dark-asymmetric-layout">
    <div class="asymmetric-header">
        <?php renderThemePart('navigation', $pdo, $data); ?>
    </div>
    <div class="content-wrapper">
        <aside class="asymmetric-sidebar">
            <!-- Alternative Navigation -->
        </aside>
        <main class="main-content">
            <?php echo $content; ?>
        </main>
    </div>
</div>

<?php renderThemePart('footer', $pdo, $data); ?>
