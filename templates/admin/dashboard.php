<?php
/**
 * @var ?array $event  @var ?array $stats  @var list<string> $warnings
 * Nur mit Event: @var string $phase  @var bool $registrationOpen  @var bool $isActive  @var string $judgeUrl  @var list<array> $perCategory
 */
use App\Domain\EventPhase;

$phaseLabel = [EventPhase::UPCOMING => 'Bevorstehend', EventPhase::LIVE => 'Läuft gerade', EventPhase::PAST => 'Vorbei'];
?>
<?php if ($event === null): ?>
    <div class="empty-state">
        <h1 class="h3">Noch kein Anlass</h1>
        <p>Lege zuerst einen Anlass an.</p>
    </div>
<?php else: ?>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <span class="eyebrow"><?= e($phaseLabel[$phase]) ?><?= $event['archived_at'] ? ' · archiviert' : '' ?><?= $event['anonymized_at'] ? ' · anonymisiert' : '' ?></span>
            <h1 class="h2 mb-1"><?= e($event['title']) ?></h1>
            <p class="text-muted mb-0"><?= e(format_event_date(new DateTimeImmutable((string) $event['starts_at']))) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
        </div>
        <?php if ($isActive): ?>
            <span class="badge-soft">Aktiv auf der Website</span>
        <?php elseif (!$event['archived_at']): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate">
                <button class="btn btn-outline-primary" type="submit" data-once>Auf der Website anzeigen</button>
            </form>
        <?php endif; ?>
    </div>

    <?php foreach ($warnings as $warning): ?>
        <div class="alert alert-warning"><?= e($warning) ?></div>
    <?php endforeach; ?>

    <div class="stat-grid mb-4">
        <div class="stat"><span class="stat-value"><?= $stats['entries'] ?></span><span class="stat-label"><?= (int) $event['team_size'] > 1 ? 'Teams' : 'Anmeldungen' ?></span></div>
        <div class="stat"><span class="stat-value"><?= $stats['results'] ?></span><span class="stat-label">Resultate</span></div>
        <div class="stat"><span class="stat-value"><?= $stats['judges'] ?></span><span class="stat-label">Kampfrichter</span></div>
        <div class="stat"><span class="stat-value"><?= $registrationOpen ? 'offen' : 'zu' ?></span><span class="stat-label">Anmeldung</span></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card h-100 print-area">
                <div class="card-body">
                    <h2 class="h5">Kampfrichter-Zugang</h2>
                    <p class="text-muted small print-hide">QR-Code an den Posten aufhängen oder den Link verschicken. Kampfrichter geben dann nur noch ihren Namen ein.</p>
                    <div class="judge-access">
                        <div class="qr" data-qr="<?= e($judgeUrl) ?>" role="img" aria-label="QR-Code für den Kampfrichter-Zugang"></div>
                        <div>
                            <div class="small text-muted">Code</div>
                            <div class="code-display mb-2"><?= e($event['judge_code']) ?></div>
                            <div class="small text-muted print-only"><?= e($event['title']) ?></div>
                            <div class="small text-break"><?= e($judgeUrl) ?></div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3 print-hide">
                        <button class="btn btn-outline-primary btn-sm" type="button" data-copy="<?= e($judgeUrl) ?>">Link kopieren</button>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-print>Drucken</button>
                        <form method="post" class="ms-auto">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="regenerate_code">
                            <button class="btn btn-outline-primary btn-sm" type="submit" data-once
                                    title="Alle angemeldeten Kampfrichter werden abgemeldet">Neuen Code erzeugen</button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Anmeldungen pro Kategorie</h2>
                    <?php if ($perCategory === []): ?>
                        <p class="text-muted small mb-0">Keine Kategorien – alle in einer Gesamtrangliste.</p>
                    <?php else: ?>
                        <ul class="check-list">
                            <?php foreach ($perCategory as $row): ?>
                                <li class="justify-content-between"><span><?= e($row['name']) ?></span><strong><?= (int) $row['n'] ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($stats['voided'] > 0): ?>
                        <p class="small text-muted mt-3 mb-0"><?= $stats['voided'] ?> Resultat(e) annulliert.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
<?php endif; ?>
