<?php
use App\Core\FormBuilder as Form;

$title = 'Forgot Password';
$mainClass = 'container d-flex align-items-center justify-content-center min-vh-100';
$showNav = false;

ob_start();
?>

<div class="card shadow" style="max-width: 400px; width: 100%;">
    <div class="card-header text-center">
        <h4 class="mb-0">Forgot Password</h4>
    </div>
    <div class="card-body">
        <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

        <p class="text-muted small mb-3">Enter your email address and we'll send you a link to reset your password.</p>

        <?= Form::open(['action' => '/forgot-password']) ?>

            <?= Form::email('email', [
                'label' => 'Email Address',
                'required' => true,
                'autofocus' => true,
                'autocomplete' => 'email',
            ]) ?>

            <div class="d-grid">
                <?= Form::submit('Send Reset Link', ['class' => 'btn btn-primary w-100']) ?>
            </div>

        <?= Form::close() ?>
    </div>
    <div class="card-footer text-center">
        <a href="/login" class="text-decoration-none">Back to Login</a>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
