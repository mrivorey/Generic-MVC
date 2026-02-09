<?php
$title = '404 - Page Not Found';
$showNav = true;
ob_start();
?>
<div class="container py-5 text-center">
    <h1 class="display-1 fw-bold text-muted">404</h1>
    <p class="fs-4 text-secondary mb-1">Page Not Found</p>
    <p class="text-muted">The page you are looking for does not exist or has been moved.</p>
    <a href="/" class="btn btn-primary mt-3">Go Home</a>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
