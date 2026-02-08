<?php
$title = 'Home';
$showNav = true;
ob_start();
?>

<div class="container py-4">
    <h1>Welcome</h1>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
