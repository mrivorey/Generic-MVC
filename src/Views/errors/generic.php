<?php
/** @var int $code */
$title = "Error {$code}";
$showNav = true;
ob_start();
?>
<div class="container py-5 text-center">
    <h1 class="display-1 fw-bold text-muted"><?= $code ?></h1>
    <p class="fs-4 text-secondary mb-1">Error <?= $code ?></p>
    <p class="text-muted">An unexpected error occurred. Please try again later.</p>
    <a href="/" class="btn btn-primary mt-3">Go Home</a>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
