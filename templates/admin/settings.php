<?php
/**
 * @var array<string,string> $errors  @var array<string,string> $old  @var list<string> $heroImages
 * @var int $maxHero  @var string $uploadLimit  @var ?string $logoUrl
 */
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = static fn (string $key): string => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
$v = static fn (string $key): string => e($old[$key] ?? '');
?>
<h1 class="h2 mb-4">Verein &amp; Design</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">Bitte die markierten Felder prüfen.</div>
<?php endif; ?>

<form method="post" class="card mb-4" novalidate>
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <h2 class="h5 mb-3">Verein</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="club_name">Vereinsname</label>
                <input class="form-control<?= $invalid('club_name') ?>" id="club_name" name="club_name" required maxlength="100" value="<?= $v('club_name') ?>">
                <?= $feedback('club_name') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="club_url">Website des Vereins <span class="text-muted fw-normal">(optional)</span></label>
                <input class="form-control<?= $invalid('club_url') ?>" id="club_url" name="club_url" type="url" placeholder="https://" value="<?= $v('club_url') ?>">
                <?= $feedback('club_url') ?>
            </div>
        </div>

        <h2 class="h5 mb-3">Farben</h2>
        <div class="row g-3 mb-2">
            <div class="col-6 col-md-3">
                <label class="form-label" for="color_primary">Hauptfarbe</label>
                <input class="form-control form-control-color<?= $invalid('color_primary') ?>" type="color" id="color_primary" name="color_primary" value="<?= $v('color_primary') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="color_secondary">Akzentfarbe</label>
                <input class="form-control form-control-color<?= $invalid('color_secondary') ?>" type="color" id="color_secondary" name="color_secondary" value="<?= $v('color_secondary') ?>">
            </div>
        </div>
        <p class="form-text mb-4">Hauptfarbe für Kopfbereich und Buttons, Akzentfarbe für Hervorhebungen. Schriftfarben und Abstufungen werden automatisch gut lesbar berechnet – auch im Dunkelmodus.</p>

        <h2 class="h5 mb-3">E-Mail &amp; Datenschutz</h2>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="mail_from">Absender-Adresse</label>
                <input class="form-control<?= $invalid('mail_from') ?>" id="mail_from" name="mail_from" type="email" value="<?= $v('mail_from') ?>" placeholder="anlass@meinverein.ch">
                <?= $feedback('mail_from') ?>
                <div class="form-text">Für Anmeldebestätigungen und Passwort-Reset. Leer = kein Mailversand. SMTP-Zugang (empfohlen) in der <code>.env</code>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail_from_name">Absender-Name</label>
                <input class="form-control" id="mail_from_name" name="mail_from_name" maxlength="100" value="<?= $v('mail_from_name') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="privacy_contact">Kontakt für Datenschutzfragen</label>
                <input class="form-control<?= $invalid('privacy_contact') ?>" id="privacy_contact" name="privacy_contact" maxlength="200" value="<?= $v('privacy_contact') ?>" placeholder="z.B. praesidium@meinverein.ch">
                <?= $feedback('privacy_contact') ?>
                <div class="form-text">Erscheint im Einwilligungstext der Anmeldung.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="timezone">Zeitzone</label>
                <input class="form-control<?= $invalid('timezone') ?>" id="timezone" name="timezone" value="<?= $v('timezone') ?>" list="timezones">
                <datalist id="timezones">
                    <?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::EUROPE) as $tz): ?><option value="<?= e($tz) ?>"><?php endforeach; ?>
                </datalist>
                <?= $feedback('timezone') ?>
            </div>
        </div>

        <button class="btn btn-primary" type="submit" data-once>Speichern</button>
    </div>
</form>

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Logo</h2>
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div class="logo-preview">
                <?php if ($logoUrl): ?>
                    <img src="<?= e($logoUrl) ?>" alt="Aktuelles Logo">
                <?php else: ?>
                    <span class="logo-placeholder" style="height:4rem;font-size:1.5rem"><?= e($logoInitials) ?></span>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_logo">
                <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required aria-label="Logo-Datei">
                <button class="btn btn-primary" type="submit" data-once>Hochladen</button>
            </form>
            <?php if ($logoUrl): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_logo">
                    <button class="btn btn-outline-primary" type="submit" data-once>Entfernen</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="form-text mt-3 mb-0">PNG mit transparentem Hintergrund eignet sich am besten (auch JPEG oder WebP). Wird automatisch auf max. 600 px verkleinert.</p>
    </div>
</section>

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-1">Fotos für den Kopfbereich</h2>
        <p class="text-muted small">Bei jedem Seitenaufruf erscheint zufällig eines davon. Ohne Fotos wird ein Farbverlauf aus den Vereinsfarben gezeigt.
            Bilder werden auf max. 1920 px verkleinert; Standort- und Kameradaten werden entfernt.</p>
        <div class="alert alert-info small">Bitte nur Fotos verwenden, für die eine Einwilligung zur Veröffentlichung vorliegt – besonders bei Kindern.</div>

        <?php if ($heroImages): ?>
            <div class="hero-thumbs mb-3">
                <?php foreach ($heroImages as $file): ?>
                    <figure class="hero-thumb">
                        <img src="<?= e(asset('uploads/' . $file)) ?>" alt="" loading="lazy">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_hero">
                            <input type="hidden" name="file" value="<?= e($file) ?>">
                            <button class="btn btn-sm btn-light" type="submit" data-once aria-label="Foto entfernen">Entfernen</button>
                        </form>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($heroImages) < $maxHero): ?>
            <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_hero">
                <input class="form-control" type="file" name="hero[]" accept="image/png,image/jpeg,image/webp" multiple required aria-label="Foto-Dateien">
                <button class="btn btn-primary" type="submit" data-once>Hochladen</button>
            </form>
            <p class="form-text mb-0">Noch <?= $maxHero - count($heroImages) ?> von <?= $maxHero ?> möglich. Max. Dateigrösse laut Server: <?= e($uploadLimit) ?> pro Datei.</p>
        <?php else: ?>
            <p class="form-text mb-0">Maximum von <?= $maxHero ?> Fotos erreicht.</p>
        <?php endif; ?>
    </div>
</section>
