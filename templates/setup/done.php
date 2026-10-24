<?php
/** @var string $judgeCode  @var string $adminEmail  @var array{status:string,detail:string} $protection */
?>
<?php require __DIR__ . '/_stepper.php'; ?>
<div class="card mb-4">
    <div class="card-body text-center py-5">
        <h1 class="h3">Fertig eingerichtet 🎉</h1>
        <p class="text-muted">Du kannst dich jetzt mit <strong><?= e($adminEmail) ?></strong> im Admin-Bereich anmelden.</p>
        <p class="mb-2">Zugangscode für Kampfrichter:</p>
        <div class="code-display mb-2"><?= e($judgeCode) ?></div>
        <p class="form-text">Den Code (und einen QR-Code dazu) findest du jederzeit im Admin-Bereich.</p>
        <a class="btn btn-primary mt-3" href="<?= e(url('index.php')) ?>">Zur Startseite</a>
    </div>
</div>

<div class="alert alert-<?= $protection['status'] === 'ok' ? 'success' : 'warning' ?>">
    <strong>Sicherheitsprüfung:</strong> <?= e($protection['detail']) ?>
</div>
