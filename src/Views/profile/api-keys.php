<?php
$title = 'API Keys';
$showNav = true;

ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">API Keys</h5>
                    <a href="/profile" class="btn btn-sm btn-outline-secondary">Back to Profile</a>
                </div>
                <div class="card-body">
                    <?php include dirname(__DIR__) . '/partials/flash-messages.php'; ?>

                    <?php if (!empty($newKey)): ?>
                        <div class="alert alert-warning">
                            <strong>Your new API key:</strong>
                            <code class="d-block mt-2 p-2 bg-dark rounded user-select-all"><?= htmlspecialchars($newKey) ?></code>
                            <small class="d-block mt-2">Copy this key now. It will not be shown again.</small>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($keys)): ?>
                        <p class="text-muted mb-0">No API keys yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Created</th>
                                        <th>Last Used</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keys as $key): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($key['name']) ?></td>
                                            <td><?= htmlspecialchars($key['created_at']) ?></td>
                                            <td><?= $key['last_used_at'] ? htmlspecialchars($key['last_used_at']) : '<span class="text-muted">Never</span>' ?></td>
                                            <td>
                                                <form method="POST" action="/profile/api-keys/<?= $key['id'] ?>/revoke"
                                                      onsubmit="return confirm('Revoke this API key?')">
                                                    <?= \App\Middleware\CsrfMiddleware::field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header">
                    <h6 class="mb-0">Generate New API Key</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/profile/api-keys">
                        <?= \App\Middleware\CsrfMiddleware::field() ?>
                        <div class="row g-3 align-items-end">
                            <div class="col">
                                <label for="name" class="form-label">Key Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       placeholder="e.g. My App" required maxlength="255">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Generate</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/main.php';
?>
