<?php /** @var ?string $error  @var ?bool $blocked */ ?>
<?php require __DIR__ . '/_stepper.php'; ?>
<div class="card">
    <div class="card-body">
        <h1 class="h4">Einrichtung fortsetzen</h1>
        <p class="text-muted">Es existiert bereits eine Konfiguration (<code>.env</code>). Zur Sicherheit bitte das dort hinterlegte Datenbank-Passwort eingeben.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (empty($blocked)): ?>
            <form method="post" action="<?= e(url('setup.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <label class="form-label" for="db_pass">Datenbank-Passwort</label>
                <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="current-password" autofocus>
                <button class="btn btn-primary w-100 mt-3" type="submit">Bestätigen</button>
            </form>
        <?php endif; ?>
    </div>
</div>
