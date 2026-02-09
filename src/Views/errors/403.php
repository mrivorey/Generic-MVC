<?php
$title = '403 - Access Denied';
$showNav = true;
ob_start();
?>
<div class="container py-5 text-center">
    <h1 class="display-1 fw-bold text-muted">403</h1>
    <p class="fs-4 text-secondary mb-1">Access Denied</p>
    <p class="text-muted">You do not have permission to access this page.</p>
    <a href="/" class="btn btn-primary mt-3">Go Home</a>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
