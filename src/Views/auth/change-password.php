<?php
use App\Core\FormBuilder as Form;

$title = 'Change Password';
$mainClass = 'container py-4';
$showNav = true;

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

                <?= Form::open(['action' => '/change-password']) ?>

                    <?= Form::password('current_password', [
                        'label' => 'Current Password',
                        'required' => true,
                        'autocomplete' => 'current-password',
                    ]) ?>

                    <?= Form::password('new_password', [
                        'label' => 'New Password',
                        'required' => true,
                        'minlength' => 8,
                        'autocomplete' => 'new-password',
                        'help' => 'Minimum 8 characters',
                    ]) ?>

                    <?= Form::password('new_password_confirmation', [
                        'label' => 'Confirm New Password',
                        'required' => true,
                        'minlength' => 8,
                        'autocomplete' => 'new-password',
                    ]) ?>

                    <div class="d-grid gap-2">
                        <?= Form::submit('Change Password') ?>
                        <a href="/" class="btn btn-outline-secondary">Back</a>
                    </div>

                <?= Form::close() ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
