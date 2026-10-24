<?php
/** @var array $event  @var array{entries:int, emails:int} $counts  @var ?string $error */
$isTeam = (int) $event['team_size'] > 1;
?>
<span class="eyebrow"><?= e($event['title']) ?></span>
<h1 class="h2 mb-4">Anlass abschliessen</h1>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5">1. Archivieren</h2>
        <?php if ($event['archived_at']): ?>
            <p class="mb-3">Archiviert seit <?= e(format_event_date(new DateTimeImmutable((string) $event['archived_at']))) ?>.
                Anmeldung und Kampfrichter-Zugang sind geschlossen; Start- und Rangliste bleiben unter dem Archiv-Link sichtbar.</p>
            <?php if (!$event['anonymized_at']): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unarchive">
                    <button class="btn btn-outline-primary" type="submit" data-once>Wieder öffnen</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Schliesst Anmeldung und Kampfrichter-Zugang. Die Rangliste bleibt öffentlich, der Anlass erscheint nicht mehr auf der Startseite. Kann rückgängig gemacht werden.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="archive">
                <button class="btn btn-primary" type="submit" data-once>Archivieren</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="card danger-zone">
    <div class="card-body">
        <h2 class="h5">2. Personendaten löschen (anonymisieren)</h2>
        <?php if ($event['anonymized_at']): ?>
            <p class="mb-0">Anonymisiert am <?= e(format_event_date(new DateTimeImmutable((string) $event['anonymized_at']))) ?>. Es sind keine Personendaten mehr gespeichert.</p>
        <?php else: ?>
            <p>Datenschutz: Personendaten sollen nicht länger als nötig aufbewahrt werden. Dabei wird gelöscht:</p>
            <ul>
                <li>Namen, Geschlecht und Jahrgang aller <?= $isTeam ? 'Teammitglieder' : 'Teilnehmenden' ?> (<?= $counts['entries'] ?> <?= $isTeam ? 'Teams' : 'Anmeldungen' ?>)</li>
                <li>E-Mail-Adressen (<?= $counts['emails'] ?>)</li>
                <li>Namen der Kampfrichter</li>
                <?php if (!$isTeam): ?><li>Namen auf Start- und Rangliste – sie werden zu «Startnr. 1», «Startnr. 2», …</li><?php endif; ?>
            </ul>
            <p>Die Rangliste wird vorher eingefroren und bleibt unverändert sichtbar. <strong>Das kann nicht rückgängig gemacht werden.</strong></p>

            <form method="post" class="mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="anonymize">
                <?php if ($isTeam): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="replace_team_names" name="replace_team_names" value="1">
                        <label class="form-check-label" for="replace_team_names">Auch Teamnamen durch Startnummern ersetzen
                            <span class="d-block small text-muted">Empfohlen, wenn Teamnamen Personennamen enthalten (z.B. «Familie Muster»).</span></label>
                    </div>
                <?php endif; ?>
                <label class="form-label" for="confirm">Zur Bestätigung den Namen des Anlasses eintippen: <strong><?= e($event['title']) ?></strong></label>
                <input class="form-control mb-3" id="confirm" name="confirm" autocomplete="off" required>
                <button class="btn btn-danger" type="submit" data-once>Endgültig anonymisieren</button>
            </form>
        <?php endif; ?>
    </div>
</section>
