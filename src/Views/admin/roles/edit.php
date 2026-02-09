<?php
use App\Core\FormBuilder as Form;

$title = 'Edit Role';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Role: <?= htmlspecialchars($role['name']) ?></h5>
                    <a href="/admin/roles" class="btn btn-sm btn-outline-secondary">Back</a>
                </div>
                <div class="card-body">
                    <?php include dirname(__DIR__, 2) . '/partials/flash-messages.php'; ?>

                    <?= Form::open(['action' => '/admin/roles/' . $role['id'], 'method' => 'PUT']) ?>

                        <?= Form::text('name', [
                            'label' => 'Name',
                            'required' => true,
                            'maxlength' => 255,
                            'value' => $role['name'],
                        ]) ?>

                        <?= Form::text('slug', [
                            'label' => 'Slug',
                            'required' => true,
                            'maxlength' => 255,
                            'value' => $role['slug'],
                            'help' => 'URL-friendly identifier (e.g. "content-editor")',
                        ]) ?>

                        <?= Form::textarea('description', [
                            'label' => 'Description',
                            'rows' => 2,
                            'maxlength' => 1000,
                            'value' => $role['description'] ?? '',
                        ]) ?>

                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <?php foreach ($permissions as $permission): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="permissions[]" value="<?= $permission['id'] ?>"
                                           id="perm_<?= $permission['id'] ?>"
                                           <?= in_array($permission['id'], $rolePermissionIds) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="perm_<?= $permission['id'] ?>">
                                        <?= htmlspecialchars($permission['name']) ?>
                                        <small class="text-muted">(<code><?= htmlspecialchars($permission['slug']) ?></code>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-grid">
                            <?= Form::submit('Update Role') ?>
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
