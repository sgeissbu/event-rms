<?php
/**
 * @var array $event  @var string $phase  @var ?string $heroImage
 */
use App\Domain\EventPhase;

$start = new DateTimeImmutable((string) $event['starts_at']);
?>
<section class="hero text-center">
    <?php if (!empty($heroImage)): ?>
        <div class="hero-photo" style="background-image:url('<?= e($heroImage) ?>')"></div>
        <div class="hero-scrim"></div>
    <?php endif; ?>
    <div class="container">
        <h1><?= e($event['title']) ?></h1>
        <?php if (!empty($event['tagline'])): ?>
            <p class="lead"><?= e($event['tagline']) ?></p>
        <?php endif; ?>

        <div class="hero-meta">
            <span class="hero-chip"><?= e(format_event_date($start)) ?></span>
            <?php if (!empty($event['location'])): ?>
                <span class="hero-chip"><?= e($event['location']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($phase === EventPhase::UPCOMING): ?>
            <div class="countdown" data-countdown="<?= e($start->format(DATE_ATOM)) ?>" aria-label="Countdown bis zum Start">
                <?php foreach (['days', 'hours', 'minutes', 'seconds'] as $unit): ?>
                    <div class="countdown-item">
                        <span class="countdown-value" data-unit="<?= $unit ?>">–</span>
                        <span class="countdown-label"><?= e(t('event.countdown.' . $unit)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="mt-4 mb-3 fw-semibold"><?= e(t($phase === EventPhase::LIVE ? 'event.live' : 'event.past')) ?></p>
            <?php if (EventPhase::rankingPublic($event)): ?>
                <a class="btn btn-accent btn-lg" href="<?= e(url('rangliste.php')) ?>">
                    <?= e(t($phase === EventPhase::LIVE ? 'event.to_live_ranking' : 'event.to_ranking')) ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
