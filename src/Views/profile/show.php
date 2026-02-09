<?php
$title = 'Profile';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Profile</h5>
                </div>
                <div class="card-body">
                    <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Username</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['username']) ?></dd>

                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['name'] ?? '') ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['email'] ?? '') ?></dd>

                        <dt class="col-sm-4">Roles</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($roles)): ?>
                                <?php foreach ($roles as $role): ?>
                                    <span class="badge bg-primary"><?= htmlspecialchars($role['name']) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                None
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Member Since</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['created_at']) ?></dd>

                        <dt class="col-sm-4">Last Login</dt>
                        <dd class="col-sm-8"><?= $user['last_login_at'] ? htmlspecialchars($user['last_login_at']) : 'N/A' ?></dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <a href="/change-password" class="btn btn-outline-primary">Change Password</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
