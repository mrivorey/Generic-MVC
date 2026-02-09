<?php
$title = 'Manage Roles';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Roles</h1>
        <a href="/admin/roles/create" class="btn btn-primary">Create Role</a>
    </div>

    <?php include dirname(__DIR__, 2) . '/partials/flash-messages.php'; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= $role['id'] ?></td>
                            <td><?= htmlspecialchars($role['name']) ?></td>
                            <td><code><?= htmlspecialchars($role['slug']) ?></code></td>
                            <td><?= htmlspecialchars($role['description'] ?? '') ?></td>
                            <td><?= $userCounts[$role['id']] ?? 0 ?></td>
                            <td>
                                <a href="/admin/roles/<?= $role['id'] ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php if ($role['slug'] !== 'admin'): ?>
                                    <form method="POST" action="/admin/roles/<?= $role['id'] ?>/delete" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this role?')">
                                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/layouts/main.php';
?>
