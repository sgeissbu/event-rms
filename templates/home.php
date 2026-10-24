<?php
/**
 * Startseite.
 * @var ?array $event  @var list<array> $disciplines  @var list<array> $pastEvents
 */
?>
<?php if ($event === null): ?>
    <div class="empty-state">
        <h2 class="h3"><?= e(t('event.none.title')) ?></h2>
        <p><?= e(t('event.none.text')) ?></p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php if (!empty($event['description'])): ?>
            <div class="col-12">
                <div class="card"><div class="card-body prose"><?= format_text((string) $event['description']) ?></div></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['schedule_text'])): ?>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body prose">
                    <h2 class="section-title">Zeitplan</h2>
                    <?= format_text((string) $event['schedule_text']) ?>
                </div></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['bring_text'])): ?>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body prose">
                    <h2 class="section-title">Mitnehmen</h2>
                    <?= format_text((string) $event['bring_text']) ?>
                </div></div>
            </div>
        <?php endif; ?>
        <?php if ($disciplines): ?>
            <div class="col-12">
                <div class="card"><div class="card-body">
                    <h2 class="section-title"><?= count($disciplines) === 1 ? 'Disziplin' : 'Die ' . count($disciplines) . ' Disziplinen' ?></h2>
                    <div class="discipline-grid">
                        <?php foreach ($disciplines as $i => $d): ?>
                            <div class="discipline-item">
                                <span class="discipline-no"><?= $i + 1 ?></span>
                                <div>
                                    <h3 class="h6 mb-1"><?= e($d['name']) ?></h3>
                                    <?php if (!empty($d['description'])): ?>
                                        <p class="small text-muted mb-0"><?= e($d['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (!empty($pastEvents)): ?>
    <section class="card mt-4" aria-labelledby="past-events-title">
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
