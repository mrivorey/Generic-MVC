<?php
use App\Core\FormBuilder as Form;

$title = 'Reset Password';
$mainClass = 'container d-flex align-items-center justify-content-center min-vh-100';
$showNav = false;

ob_start();
?>

<div class="card shadow" style="max-width: 400px; width: 100%;">
    <div class="card-header text-center">
        <h4 class="mb-0">Reset Password</h4>
    </div>
    <div class="card-body">
        <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

        <?= Form::open(['action' => '/reset-password']) ?>

            <?= Form::hidden('token', $token) ?>

            <?= Form::password('password', [
                'label' => 'New Password',
                'required' => true,
                'minlength' => 8,
                'autofocus' => true,
                'autocomplete' => 'new-password',
            ]) ?>

            <?= Form::password('password_confirmation', [
                'label' => 'Confirm New Password',
                'required' => true,
                'minlength' => 8,
                'autocomplete' => 'new-password',
            ]) ?>

            <div class="d-grid">
                <?= Form::submit('Reset Password', ['class' => 'btn btn-primary w-100']) ?>
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
