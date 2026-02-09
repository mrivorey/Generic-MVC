<?php
use App\Core\FormBuilder as Form;

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
        <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

        <?= Form::open(['action' => '/login']) ?>

            <?= Form::text('username', [
                'label' => 'Username',
                'required' => true,
                'autofocus' => true,
                'autocomplete' => 'username',
            ]) ?>

            <?= Form::password('password', [
                'label' => 'Password',
                'required' => true,
                'autocomplete' => 'current-password',
            ]) ?>

            <?= Form::checkbox('remember_me', [
                'label' => 'Remember me for 90 days',
            ]) ?>

            <div class="d-grid">
                <?= Form::submit('Login', ['class' => 'btn btn-primary w-100']) ?>
            </div>

            <div class="text-center mt-3">
                <a href="/forgot-password" class="text-decoration-none small">Forgot your password?</a>
            </div>

        <?= Form::close() ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
