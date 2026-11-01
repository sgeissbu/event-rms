<?php
/**
 * Startseiten-Kopf (ab 0.10): randloses Foto bzw. Diashow, Text unten links, darunter eine Info-Leiste
 * mit Datum, Ort und Countdown/Status.
 * @var array $event  @var string $phase  @var ?string $heroImage  @var list<string> $heroSlides  @var array $eventQuery
 * @var bool $registrationOpen  @var DateTimeImmutable $registrationCloses  @var bool $startlistVisible
 */
use App\Domain\EventPhase;

$start = new DateTimeImmutable((string) $event['starts_at']);
$rankingPublic = EventPhase::rankingPublic($event);
// «&» im Titel in der Akzentfarbe (kleines Detail, z.B. «Oldies & Kids Cup»)
$title = str_replace('&amp;', '<span class="hero-amp">&amp;</span>', e((string) $event['title']));
?>
<section class="hero hero--home<?= !empty($heroImage) ? ' hero--photo' : '' ?>">
    <?php if (!empty($heroImage)): ?>
        <div class="hero-photo" style="background-image:url('<?= e($heroImage) ?>')"<?php if (!empty($heroSlides)): ?> data-slides="<?= e(json_encode($heroSlides, JSON_UNESCAPED_SLASHES)) ?>"<?php endif; ?>></div>
        <div class="hero-scrim"></div>
    <?php endif; ?>
    <div class="container">
        <div class="hero-text">
            <?php if (!empty($event['badge'])): ?>
                <span class="sticker"><?= icon('party') ?><?= e($event['badge']) ?></span>
            <?php endif; ?>
            <h1><?= $title ?></h1>
            <?php if (!empty($event['tagline'])): ?>
                <p class="lead"><?= e($event['tagline']) ?></p>
            <?php endif; ?>
            <?php $hasAction = ($phase === EventPhase::UPCOMING && $registrationOpen) || ($phase !== EventPhase::UPCOMING && $rankingPublic) || $startlistVisible; ?>
            <?php if ($hasAction): ?><div class="hero-actions">
                <?php if ($phase === EventPhase::UPCOMING && $registrationOpen): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url('anmeldung.php', $eventQuery)) ?>"><?= e(t('event.register')) ?><?= icon('arrow', 'ico ico-go') ?></a>
                <?php elseif ($phase !== EventPhase::UPCOMING && $rankingPublic): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url('rangliste.php', $eventQuery)) ?>">
                        <?= e(t($phase === EventPhase::LIVE ? 'event.to_live_ranking' : 'event.to_ranking')) ?><?= icon('arrow', 'ico ico-go') ?>
                    </a>
                <?php elseif ($startlistVisible): ?>
                    <a class="btn btn-hero-ghost btn-lg" href="<?= e(url('startliste.php', $eventQuery)) ?>"><?= e(t('nav.startlist')) ?></a>
                <?php endif; ?>
            </div><?php endif; ?>
            <?php if ($phase === EventPhase::UPCOMING && $registrationOpen): ?>
                <p class="hero-note"><?= e(t('event.registration_until', ['date' => format_event_date($registrationCloses)])) ?></p>
            <?php elseif ($phase === EventPhase::UPCOMING): ?>
                <p class="hero-note hero-note--status"><?= e(t($registrationCloses < new DateTimeImmutable() && (int) $event['registration_enabled'] ? 'event.registration_closed' : 'event.registration_soon')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="hero-bar">
    <div class="container">
        <span class="hero-bar-item"><?= icon('calendar') ?><?= e(format_event_date($start)) ?></span>
        <?php if (!empty($event['location'])): ?>
            <span class="hero-bar-item"><?= icon('pin') ?><?= e($event['location']) ?></span>
        <?php endif; ?>
        <?php if ($phase === EventPhase::UPCOMING): ?>
            <div class="countdown" data-countdown="<?= e($start->format(DATE_ATOM)) ?>" role="timer" aria-label="Countdown bis zum Start">
                <?php foreach (['days', 'hours', 'minutes'] as $unit): ?>
                    <span class="countdown-item">
                        <span class="countdown-value" data-unit="<?= $unit ?>">–</span>
                        <span class="countdown-label"><?= e(t('event.countdown.' . $unit)) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="hero-bar-status"><?= $phase === EventPhase::LIVE ? '<span class="live-dot" aria-hidden="true"></span>' : '' ?><?= e(t($phase === EventPhase::LIVE ? 'event.live' : 'event.past')) ?></span>
        <?php endif; ?>
    </div>
</div>
