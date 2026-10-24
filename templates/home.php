<?php
/**
 * Startseite.
 * @var ?array $event  @var list<array> $disciplines  @var bool $registrationOpen
 */
use App\Http\App;

$closes = $event !== null ? new DateTimeImmutable((string) ($event['registration_closes_at'] ?: $event['starts_at'])) : null;
?>
<?php if ($event === null): ?>
    <div class="empty-state">
        <h2 class="h3"><?= e(t('event.none.title')) ?></h2>
        <p><?= e(t('event.none.text')) ?></p>
    </div>
<?php else: ?>
    <?php if ($registrationOpen): ?>
        <div class="cta-banner mb-4">
            <div>
                <strong>Anmeldung offen</strong>
                <span class="text-muted d-block">bis <?= e(format_event_date($closes)) ?></span>
            </div>
            <a class="btn btn-primary" href="<?= e(url('anmeldung.php', App::eventQuery($event))) ?>">Jetzt anmelden</a>
        </div>
    <?php endif; ?>

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
