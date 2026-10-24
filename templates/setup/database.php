<?php
/**
 * @var list<array{label:string,ok:bool,detail:string,blocking:bool}> $checks
 * @var ?string $error  @var array $old
 */
use App\Install\Installer;

$canContinue = Installer::requirementsMet($checks);
?>
<?php require __DIR__ . '/_stepper.php'; ?>

<div class="card mb-4">
    <div class="card-body">
        <h1 class="h4 mb-3">Voraussetzungen</h1>
        <ul class="check-list">
            <?php foreach ($checks as $c): ?>
                <li>
                    <span class="check-icon <?= $c['ok'] ? 'ok' : ($c['blocking'] ? 'fail' : 'warn') ?>"><?= $c['ok'] ? '✓' : ($c['blocking'] ? '✕' : '!') ?></span>
                    <span><strong><?= e($c['label']) ?></strong><br><small class="text-muted"><?= e($c['detail']) ?></small></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h4">Datenbank</h2>
        <p class="text-muted">Lege im Hosting-Panel eine <strong>leere</strong> MySQL- oder MariaDB-Datenbank an und trage hier die Zugangsdaten ein.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('setup.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="database">
            <div class="row g-3">
                <div class="col-8">
                    <label class="form-label" for="db_host">Host</label>
                    <input class="form-control" id="db_host" name="db_host" required value="<?= e($old['host'] ?? 'localhost') ?>">
                </div>
                <div class="col-4">
                    <label class="form-label" for="db_port">Port</label>
                    <input class="form-control" id="db_port" name="db_port" inputmode="numeric" value="<?= e($old['port'] ?? '3306') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="db_name">Datenbankname</label>
                    <input class="form-control" id="db_name" name="db_name" required value="<?= e($old['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_user">Benutzer</label>
                    <input class="form-control" id="db_user" name="db_user" required autocomplete="off" value="<?= e($old['user'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="db_pass">Passwort</label>
                    <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password">
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-4" type="submit" <?= $canContinue ? '' : 'disabled' ?>>Verbindung prüfen und weiter</button>
            <?php if (!$canContinue): ?>
                <p class="form-text text-center mt-2">Bitte zuerst die rot markierten Voraussetzungen beheben (Hosting-Support fragen).</p>
            <?php endif; ?>
        </form>
    </div>
</div>
