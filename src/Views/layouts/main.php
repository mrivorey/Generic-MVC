<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'App') ?></title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body>
    <?php if (isset($showNav) && $showNav): ?>
        <?php include dirname(__DIR__) . '/partials/navbar.php'; ?>
    <?php endif; ?>

    <main class="<?= $mainClass ?? 'container-fluid py-3' ?>">
        <?= $content ?? '' ?>
    </main>

    <script src="/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
