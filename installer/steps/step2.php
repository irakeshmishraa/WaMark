<?php /** Step 2: Server Requirements Check */ ?>
<h4 class="mb-4"><i class="bi bi-gear text-primary"></i> Server Requirements</h4>
<p class="text-muted mb-4">Checking your server configuration for compatibility.</p>

<div class="requirements-list">
    <?php foreach ($requirements as $key => $check): ?>
        <div class="check-item">
            <div class="check-icon <?= $check['status'] ? 'pass' : ($check['required'] ? 'fail' : 'warn') ?>">
                <i class="bi <?= $check['status'] ? 'bi-check' : ($check['required'] ? 'bi-x' : 'bi-exclamation') ?>"></i>
            </div>
            <div class="flex-grow-1">
                <span class="fw-medium"><?= $check['name'] ?></span>
            </div>
            <span class="badge bg-<?= $check['status'] ? 'success' : ($check['required'] ? 'danger' : 'warning') ?>">
                <?= $check['current'] ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-4 d-flex justify-content-between">
    <a href="?step=1" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    
    <?php if ($allPassed): ?>
        <a href="?step=3" class="btn btn-primary">
            Continue <i class="bi bi-arrow-right"></i>
        </a>
    <?php else: ?>
        <div>
            <span class="text-danger me-2"><i class="bi bi-exclamation-triangle"></i> Fix required items first</span>
            <a href="?step=2" class="btn btn-outline-primary">Re-check</a>
        </div>
    <?php endif; ?>
</div>
