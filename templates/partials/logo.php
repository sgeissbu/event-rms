<?php /** @var ?string $logoUrl  @var ?string $logoDarkUrl  @var string $logoInitials  @var string $clubName */ ?>
<?php if (!empty($logoUrl)): ?>
    <?php if (!empty($logoDarkUrl)): /* Zwei Logos: Der Wechsel im Dunkelmodus passiert rein per CSS (ohne Flackern). */ ?>
        <img class="logo-light" src="<?= e($logoUrl) ?>" alt="<?= e($logoAlt ?? $clubName) ?>">
        <img class="logo-dark" src="<?= e($logoDarkUrl) ?>" alt="" aria-hidden="true">
    <?php else: ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($logoAlt ?? $clubName) ?>">
    <?php endif; ?>
<?php else: ?>
    <span class="logo-placeholder" aria-hidden="true"><?= e($logoInitials ?? '?') ?></span>
<?php endif; ?>
