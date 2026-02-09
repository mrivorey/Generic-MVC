<?php
use App\Core\FormBuilder as Form;

$title = 'Create User';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Create User</h5>
                    <a href="/admin/users" class="btn btn-sm btn-outline-secondary">Back</a>
                </div>
                <div class="card-body">
                    <?php include dirname(__DIR__, 2) . '/partials/flash-messages.php'; ?>

                    <?= Form::open(['action' => '/admin/users']) ?>

                        <?= Form::text('username', [
                            'label' => 'Username',
                            'required' => true,
                            'minlength' => 3,
                            'maxlength' => 255,
                        ]) ?>

                        <?= Form::email('email', [
                            'label' => 'Email',
                            'required' => true,
                            'maxlength' => 255,
                        ]) ?>

                        <?= Form::text('name', [
                            'label' => 'Name',
                            'required' => true,
                            'maxlength' => 255,
                        ]) ?>

                        <?= Form::password('password', [
                            'label' => 'Password',
                            'required' => true,
                            'minlength' => 8,
                            'autocomplete' => 'new-password',
                        ]) ?>

                        <?= Form::password('password_confirmation', [
                            'label' => 'Confirm Password',
                            'required' => true,
                            'minlength' => 8,
                            'autocomplete' => 'new-password',
                        ]) ?>

                        <div class="mb-3">
                            <label class="form-label">Roles</label>
                            <?php foreach ($roles as $role): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="role_ids[]" value="<?= $role['id'] ?>"
                                           id="role_<?= $role['id'] ?>">
                                    <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                        <?= htmlspecialchars($role['name']) ?>
                                        <?php if ($role['description']): ?>
                                            <small class="text-muted">— <?= htmlspecialchars($role['description']) ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?= Form::checkbox('is_active', [
                            'label' => 'Active',
                            'checked' => true,
                        ]) ?>

                        <div class="d-grid">
                            <?= Form::submit('Create User') ?>
                        </div>

                    <?= Form::close() ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/layouts/main.php';
?>
