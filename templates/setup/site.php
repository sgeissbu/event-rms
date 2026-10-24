<?php
/** @var array<string,string> $errors  @var array<string,string> $old */
use App\Repository\EventRepository;

$field = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' is-invalid' : '';
};
$err = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<div class="invalid-feedback">' . e($errors[$name]) . '</div>' : '';
};
$v = static fn (string $name, string $default = ''): string => e($old[$name] ?? $default);
?>
<?php require __DIR__ . '/_stepper.php'; ?>

<?php if (isset($errors['_'])): ?>
    <div class="alert alert-danger"><?= e($errors['_']) ?></div>
<?php elseif ($errors): ?>
    <div class="alert alert-danger">Bitte die markierten Felder prüfen.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('setup.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="install">

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Verein</h2>
            <div class="mb-3">
                <label class="form-label" for="club_name">Vereinsname</label>
                <input class="form-control<?= $field('club_name') ?>" id="club_name" name="club_name" required value="<?= $v('club_name') ?>" placeholder="z.B. TV Musterhausen">
                <?= $err('club_name') ?>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="color_primary">Hauptfarbe</label>
                    <input class="form-control form-control-color<?= $field('color_primary') ?>" type="color" id="color_primary" name="color_primary" value="<?= $v('color_primary', '#1D4ED8') ?>">
                    <?= $err('color_primary') ?>
                </div>
                <div class="col-6">
                    <label class="form-label" for="color_secondary">Akzentfarbe</label>
                    <input class="form-control form-control-color<?= $field('color_secondary') ?>" type="color" id="color_secondary" name="color_secondary" value="<?= $v('color_secondary', '#F59E0B') ?>">
                    <?= $err('color_secondary') ?>
                </div>
            </div>
            <p class="form-text mb-0 mt-2">Logo und Fotos kannst du später im Admin-Bereich hochladen.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Dein Admin-Zugang</h2>
            <div class="mb-3">
                <label class="form-label" for="admin_name">Name</label>
                <input class="form-control<?= $field('admin_name') ?>" id="admin_name" name="admin_name" required autocomplete="name" value="<?= $v('admin_name') ?>">
                <?= $err('admin_name') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="admin_email">E-Mail</label>
                <input class="form-control<?= $field('admin_email') ?>" type="email" id="admin_email" name="admin_email" required autocomplete="email" value="<?= $v('admin_email') ?>">
                <?= $err('admin_email') ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="admin_password">Passwort</label>
                    <input class="form-control<?= $field('admin_password') ?>" type="password" id="admin_password" name="admin_password" required minlength="10" autocomplete="new-password">
                    <?= $err('admin_password') ?>
                    <div class="form-text">Mindestens 10 Zeichen.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="admin_password2">Passwort wiederholen</label>
                    <input class="form-control<?= $field('admin_password2') ?>" type="password" id="admin_password2" name="admin_password2" required autocomplete="new-password">
                    <?= $err('admin_password2') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Erster Anlass</h2>
            <div class="mb-3">
                <label class="form-label" for="event_title">Name des Anlasses</label>
                <input class="form-control<?= $field('event_title') ?>" id="event_title" name="event_title" required value="<?= $v('event_title') ?>" placeholder="z.B. Jugi-Cup 2027">
                <?= $err('event_title') ?>
            </div>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="event_start">Beginn</label>
                    <input class="form-control<?= $field('event_start') ?>" type="datetime-local" id="event_start" name="event_start" required value="<?= $v('event_start') ?>">
                    <?= $err('event_start') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="team_size">Format</label>
                    <select class="form-select<?= $field('team_size') ?>" id="team_size" name="team_size">
                        <?php for ($n = 1; $n <= EventRepository::MAX_TEAM_SIZE; $n++): ?>
                            <option value="<?= $n ?>" <?= ($old['team_size'] ?? '1') === (string) $n ? 'selected' : '' ?>>
                                <?= $n === 1 ? 'Einzelwettkampf' : 'Teams à ' . $n . ' Personen' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <?= $err('team_size') ?>
                </div>
            </div>
            <p class="form-text mb-0 mt-2">Ort, Texte, Disziplinen und Kategorien richtest du danach im Admin-Bereich ein.</p>
        </div>
    </div>

    <button class="btn btn-primary btn-lg w-100" type="submit">Einrichtung abschliessen</button>
</form>
