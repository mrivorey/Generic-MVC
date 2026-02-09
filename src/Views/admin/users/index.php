<?php
$title = 'Manage Users';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Users</h1>
        <a href="/admin/users/create" class="btn btn-primary">Create User</a>
    </div>

    <?php include dirname(__DIR__, 2) . '/partials/flash-messages.php'; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Roles</th>
                        <th>Active</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['name'] ?? '') ?></td>
                            <td>
                                <?php
                                $roles = $userRolesMap[$user['id']] ?? [];
                                if (!empty($roles)):
                                    foreach ($roles as $role): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($role['name']) ?></span>
                                    <?php endforeach;
                                else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $user['last_login_at'] ? htmlspecialchars($user['last_login_at']) : '<span class="text-muted">Never</span>' ?></td>
                            <td>
                                <a href="/admin/users/<?= $user['id'] ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php if ($user['id'] !== ($_SESSION['user_id'] ?? null)): ?>
                                    <form method="POST" action="/admin/users/<?= $user['id'] ?>/delete" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this user?')">
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
