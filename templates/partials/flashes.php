<?php /** @var array $flashes */ ?>
<?php foreach ($flashes ?? [] as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> flash" role="<?= $flash['type'] === 'danger' ? 'alert' : 'status' ?>"<?= $flash['type'] === 'success' ? ' data-autohide="8000"' : '' ?>><?= e($flash['message']) ?></div>
<?php endforeach; ?>
