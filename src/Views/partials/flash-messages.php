<?php
$typeMap = [
    'success' => 'success',
    'error' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];

$allFlash = \App\Core\Flash::all();
foreach ($allFlash as $type => $messages):
    $bsClass = $typeMap[$type] ?? 'info';
    foreach ($messages as $message):
?>
    <div class="alert alert-<?= $bsClass ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php
    endforeach;
endforeach;
?>
