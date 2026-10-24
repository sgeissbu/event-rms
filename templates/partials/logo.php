<?php /** @var ?string $logoUrl  @var string $logoInitials  @var string $clubName */ ?>
<?php if (!empty($logoUrl)): ?>
    <img src="<?= e($logoUrl) ?>" alt="<?= e($logoAlt ?? $clubName) ?>">
<?php else: ?>
    <span class="logo-placeholder" aria-hidden="true"><?= e($logoInitials ?? '?') ?></span>
<?php endif; ?>
