<?php
/** @var Throwable $exception */
/** @var int $code */
$exceptionClass = get_class($exception);
$file = $exception->getFile();
$line = $exception->getLine();
$message = $exception->getMessage();
$trace = $exception->getTrace();

// Read source file for code snippet
$sourceLines = [];
if (is_readable($file)) {
    $sourceLines = file($file);
}
$startLine = max(0, $line - 8);
$endLine = min(count($sourceLines), $line + 7);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> - <?= htmlspecialchars($exceptionClass) ?></title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .code-block {
            background: #1a1a2e;
            border-radius: 8px;
            overflow: hidden;
            font-size: 0.875rem;
        }
        .code-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            line-height: 1.6;
        }
        .code-table td {
            padding: 0 12px;
            white-space: pre;
            vertical-align: top;
        }
        .code-gutter {
            width: 1px;
            text-align: right;
            color: #6c757d;
            user-select: none;
            border-right: 1px solid #2d2d44;
            padding-right: 12px;
            background: #16162a;
        }
        .code-line-error {
            background: rgba(220, 53, 69, 0.2);
        }
        .code-line-error .code-gutter {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.15);
            font-weight: bold;
        }
        .trace-row:nth-child(even) {
            background: rgba(255, 255, 255, 0.03);
        }
        .trace-row:nth-child(odd) {
            background: rgba(255, 255, 255, 0.06);
        }
        .error-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #2d1b2e 100%);
            border-bottom: 3px solid #dc3545;
        }
        .param-masked {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body class="bg-dark">
    <div class="error-header py-4">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="badge bg-danger fs-5 px-3 py-2"><?= $code ?></span>
                <h1 class="mb-0 fs-4 text-danger"><?= htmlspecialchars($exceptionClass) ?></h1>
            </div>
            <p class="fs-5 mb-2 text-light"><?= htmlspecialchars($message) ?></p>
            <p class="text-secondary mb-0">
                <code class="text-info"><?= htmlspecialchars($file) ?></code> : <strong class="text-warning"><?= $line ?></strong>
            </p>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <?php if (!empty($sourceLines)): ?>
        <div class="mb-4">
            <h5 class="text-secondary mb-3">Source Code</h5>
            <div class="code-block">
                <table class="code-table">
                    <tbody>
                        <?php for ($i = $startLine; $i < $endLine; $i++): ?>
                            <tr class="<?= ($i + 1 === $line) ? 'code-line-error' : '' ?>">
                                <td class="code-gutter"><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars(rtrim($sourceLines[$i])) ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-4">
            <h5 class="text-secondary mb-3">Stack Trace</h5>
            <div class="rounded overflow-hidden" style="font-size: 0.85rem;">
                <?php foreach ($trace as $index => $frame): ?>
                    <div class="trace-row px-3 py-2 font-monospace">
                        <span class="text-muted me-2">#<?= $index ?></span>
                        <?php if (isset($frame['file'])): ?>
                            <span class="text-info"><?= htmlspecialchars($frame['file']) ?></span><span class="text-muted">:</span><span class="text-warning"><?= $frame['line'] ?? '?' ?></span>
                        <?php else: ?>
                            <span class="text-muted">[internal]</span>
                        <?php endif; ?>
                        <span class="text-muted"> &mdash; </span>
                        <?php if (isset($frame['class'])): ?>
                            <span class="text-light"><?= htmlspecialchars($frame['class']) ?><?= htmlspecialchars($frame['type'] ?? '::') ?></span>
                        <?php endif; ?>
                        <span class="text-success"><?= htmlspecialchars($frame['function'] ?? '') ?>()</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-4">
            <h5 class="text-secondary mb-3">Request Details</h5>
            <div class="accordion" id="requestDetails">

                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse" data-bs-target="#reqBasic">
                            Request
                        </button>
                    </h2>
                    <div id="reqBasic" class="accordion-collapse collapse" data-bs-parent="#requestDetails">
                        <div class="accordion-body font-monospace small">
                            <p><strong class="text-secondary">Method:</strong> <?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A') ?></p>
                            <p class="mb-0"><strong class="text-secondary">URI:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($_GET)): ?>
                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse" data-bs-target="#reqGet">
                            GET Parameters
                        </button>
                    </h2>
                    <div id="reqGet" class="accordion-collapse collapse" data-bs-parent="#requestDetails">
                        <div class="accordion-body font-monospace small">
                            <?php foreach ($_GET as $key => $value): ?>
                                <p class="mb-1"><strong class="text-secondary"><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars(print_r($value, true)) ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($_POST)): ?>
                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse" data-bs-target="#reqPost">
                            POST Parameters
                        </button>
                    </h2>
                    <div id="reqPost" class="accordion-collapse collapse" data-bs-parent="#requestDetails">
                        <div class="accordion-body font-monospace small">
                            <?php
                            $sensitiveKeys = ['password', 'token', 'secret'];
                            foreach ($_POST as $key => $value):
                                $isSensitive = false;
                                foreach ($sensitiveKeys as $s) {
                                    if (stripos($key, $s) !== false) {
                                        $isSensitive = true;
                                        break;
                                    }
                                }
                            ?>
                                <p class="mb-1">
                                    <strong class="text-secondary"><?= htmlspecialchars($key) ?>:</strong>
                                    <?php if ($isSensitive): ?>
                                        <span class="param-masked">********</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(print_r($value, true)) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse" data-bs-target="#reqHeaders">
                            Headers
                        </button>
                    </h2>
                    <div id="reqHeaders" class="accordion-collapse collapse" data-bs-parent="#requestDetails">
                        <div class="accordion-body font-monospace small">
                            <?php
                            foreach ($_SERVER as $key => $value):
                                if (str_starts_with($key, 'HTTP_')):
                                    $headerName = str_replace('_', '-', substr($key, 5));
                            ?>
                                <p class="mb-1"><strong class="text-secondary"><?= htmlspecialchars($headerName) ?>:</strong> <?= htmlspecialchars($value) ?></p>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION) && !empty($_SESSION)): ?>
                <div class="accordion-item bg-dark border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button" data-bs-toggle="collapse" data-bs-target="#reqSession">
                            Session
                        </button>
                    </h2>
                    <div id="reqSession" class="accordion-collapse collapse" data-bs-parent="#requestDetails">
                        <div class="accordion-body font-monospace small">
                            <pre class="mb-0 text-light"><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>
