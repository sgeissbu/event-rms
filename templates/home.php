<?php
/**
 * Startseite. Phase 1: Event-Infos; Anmeldung, Zeitplan & Disziplinen folgen in Phase 2.
 * @var ?array $event
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
                <div class="card"><div class="card-body"><?= format_text((string) $event['description']) ?></div></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['schedule_text'])): ?>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body">
                    <h2 class="section-title">Zeitplan</h2>
                    <?= format_text((string) $event['schedule_text']) ?>
                </div></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['bring_text'])): ?>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body">
                    <h2 class="section-title">Mitnehmen</h2>
                    <?= format_text((string) $event['bring_text']) ?>
                </div></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
