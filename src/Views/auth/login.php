<?php
$title = 'Login';
$mainClass = 'container d-flex align-items-center justify-content-center min-vh-100';
$showNav = false;

ob_start();
?>

<div class="card shadow" style="max-width: 400px; width: 100%;">
    <div class="card-header text-center">
        <h4 class="mb-0">Login</h4>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['csrf_error'])): ?>
            <div class="alert alert-warning" role="alert">
                <?= htmlspecialchars($_SESSION['csrf_error']) ?>
            </div>
            <?php unset($_SESSION['csrf_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['rate_limit_error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($_SESSION['rate_limit_error']) ?>
            </div>
            <?php unset($_SESSION['rate_limit_error']); ?>
        <?php endif; ?>

        <form method="POST" action="/login">
            <?= \App\Middleware\CsrfMiddleware::field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       required autofocus autocomplete="username">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me">Remember me for 90 days</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
