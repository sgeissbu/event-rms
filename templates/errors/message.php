<?php
/**
 * Hinweis- oder Fehlerseite. Mit $homeUrl (öffentliche Seiten) gibt es einen Weg zurück.
 * @var string $message  @var ?string $homeUrl
 */
?>
<div class="message-page">
    <span class="home-badge message-badge" aria-hidden="true"><?= icon('info') ?></span>
    <p class="message-text"><?= e($message) ?></p>
    <?php if (!empty($homeUrl)): ?>
        <a class="btn btn-accent btn-lg" href="<?= e($homeUrl) ?>"><?= e(t('error.to_home')) ?><?= icon('arrow', 'ico ico-go') ?></a>
    <?php endif; ?>
</div>
