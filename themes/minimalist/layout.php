<?php renderThemePart('header', $pdo, $data); ?>

<div class="minimalist-layout">
    <header class="minimal-header">
        <?php renderThemePart('navigation', $pdo, $data); ?>
    </header>
    <main class="minimal-main">
        <?php echo $content; ?>
    </main>
</div>

<?php renderThemePart('footer', $pdo, $data); ?>
