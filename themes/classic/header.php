<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/classic/style.css">
    <?php if(!empty($theme_settings['custom_css'])): ?>
    <style><?php echo $theme_settings['custom_css']; ?></style>
    <?php endif; ?>
</head>
<body class="theme-classic">
    <header class="header">
        <div class="theme-container">
            <div class="header-inner">
                <a href="index.php" class="logo"><span>Sky</span>Link</a>
                <nav class="nav">
                    <!-- Nav items -->
                </nav>
            </div>
        </div>
    </header>
