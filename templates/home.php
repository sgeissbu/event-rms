<?php
/**
 * Startseite (Design ab 0.10): «Worum geht's?» mit Teaser, Kurzpunkten und Foto, Disziplinen als Bildkarten,
 * Zeitplan als Zeitleiste, «Mitnehmen» mit Symbolen, Schluss-Aufruf zur Anmeldung.
 * @var ?array $event  @var list<array> $disciplines  @var list<array> $pastEvents
 * @var ?string $introImage  URL  @var list<string> $photoFiles  gültige Foto-Pfade (relativ zu uploads/)
 * @var bool $registrationOpen  @var array $eventQuery
 */
use App\Domain\HomeContent;

?>
<?php if ($event === null): ?>
    <div class="empty-state">
        <h2 class="h3"><?= e(t('event.none.title')) ?></h2>
        <p><?= e(t('event.none.text')) ?></p>
    </div>
<?php else: ?>
    <?php
    $intro = HomeContent::splitDescription($event['description'] ?? null);
    $points = $event['key_points'] ?? [];
    $schedule = HomeContent::schedule($event['schedule_text'] ?? null);
    $bring = HomeContent::bring($event['bring_text'] ?? null);
    $photoOf = static fn (?string $file): ?string => $file !== null && in_array($file, $photoFiles, true) ? asset('uploads/' . $file) : null;
    ?>
    <div class="home">
        <?php if ($intro['teaser'] !== '' || $intro['rest'] !== '' || $points !== []): ?>
            <section class="home-intro<?= $introImage ? '' : ' home-intro--text' ?>" aria-labelledby="intro-title">
                <div>
                    <h2 class="home-h" id="intro-title"><span class="home-badge"><?= icon('info') ?></span><?= e(t('home.intro')) ?></h2>
                    <?php if ($intro['teaser'] !== ''): ?>
                        <div class="home-teaser"><?= format_text($intro['teaser']) ?></div>
                    <?php endif; ?>
                    <?php if ($intro['rest'] !== ''): ?>
                        <div class="prose"><?= format_text($intro['rest']) ?></div>
                    <?php endif; ?>
                    <?php if ($points !== []): ?>
                        <ul class="home-points">
                            <?php foreach ($points as $point): ?>
                                <li><?= icon($point['icon']) ?><span><?= e($point['text']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if ($introImage): ?>
                    <figure class="home-snap"><img src="<?= e($introImage) ?>" alt="" loading="lazy"></figure>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($disciplines): ?>
            <section aria-labelledby="disc-title">
                <h2 class="home-h" id="disc-title"><span class="home-badge"><?= icon('trophy') ?></span><?= count($disciplines) === 1 ? 'Disziplin' : 'Die ' . count($disciplines) . ' Disziplinen' ?></h2>
                <div class="disc-cards">
                    <?php foreach ($disciplines as $i => $d): $photo = $photoOf($d['image_file'] ?? null); ?>
                        <article class="disc-card<?= $photo ? ' disc-card--photo' : '' ?>">
                            <span class="disc-no" aria-hidden="true"><?= $i + 1 ?></span>
                            <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" loading="lazy"><?php endif; ?>
                            <div class="disc-text">
                                <h3><span class="visually-hidden"><?= $i + 1 ?>. </span><?= e($d['name']) ?></h3>
                                <?php if (!empty($d['description'])): ?><p><?= e($d['description']) ?></p><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($disciplines) > 1): ?><p class="disc-swipe" aria-hidden="true">← wischen für alle Disziplinen →</p><?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($event['schedule_text']) || !empty($event['bring_text'])): ?>
            <div class="home-two">
                <?php if (!empty($event['schedule_text'])): ?>
                    <section class="card home-card" aria-labelledby="schedule-title">
                        <h2 class="home-h" id="schedule-title"><span class="home-badge"><?= icon('clock') ?></span>Zeitplan</h2>
                        <?php if ($schedule !== null): ?>
                            <ol class="timeline">
                                <?php foreach ($schedule as $row): ?>
                                    <li<?= $row['strong'] ? ' class="is-strong"' : '' ?>><time><?= e($row['time']) ?></time><span><?= e($row['text']) ?></span></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <div class="prose"><?= format_text((string) $event['schedule_text']) ?></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
                <?php if (!empty($event['bring_text'])): ?>
                    <section class="card home-card" aria-labelledby="bring-title">
                        <h2 class="home-h" id="bring-title"><span class="home-badge"><?= icon('backpack') ?></span>Mitnehmen</h2>
                        <?php if ($bring['items'] !== []): ?>
                            <ul class="bring-list">
                                <?php foreach ($bring['items'] as $item): ?>
                                    <li><?= icon($item['icon']) ?><span><?= preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($item['text'])) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php foreach ($bring['notes'] as $note): ?>
                                <p class="bring-note"><?= icon(HomeContent::bringIcon($note) === 'check' ? 'info' : HomeContent::bringIcon($note)) ?><span><?= preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($note)) ?></span></p>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="prose"><?= format_text((string) $event['bring_text']) ?></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($registrationOpen): ?>
            <section class="home-cta" aria-labelledby="cta-title">
                <h2 id="cta-title"><?= e(t('home.cta.title')) ?></h2>
                <p><?= e(t('home.cta.text')) ?></p>
                <a class="btn btn-accent btn-lg" href="<?= e(url('anmeldung.php', $eventQuery)) ?>"><?= e(t('event.register')) ?><?= icon('arrow', 'ico ico-go') ?></a>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (!empty($pastEvents)): ?>
    <section class="card mt-5" aria-labelledby="past-events-title">
        <div class="card-body">
            <h2 class="section-title" id="past-events-title">Frühere Anlässe</h2>
            <ul class="past-events">
                <?php foreach ($pastEvents as $past): ?>
                    <li>
                        <a href="<?= e(url('rangliste.php', ['event' => $past['slug']])) ?>"><?= e($past['title']) ?></a>
                        <span class="text-muted small"><?= e(format_event_date(new DateTimeImmutable((string) $past['starts_at']), false)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>
