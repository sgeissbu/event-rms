<?php /** @var string $envContents */ ?>
<?php require __DIR__ . '/_stepper.php'; ?>
<div class="card">
    <div class="card-body">
        <h1 class="h4">Konfigurationsdatei manuell hochladen</h1>
        <p>Die Verbindung zur Datenbank klappt. Dein Hosting erlaubt es aber nicht, die Datei <code>.env</code> automatisch zu schreiben.</p>
        <ol>
            <li>Inhalt unten kopieren und als Datei mit dem Namen <code>.env</code> speichern.</li>
            <li>Per FTP in den Hauptordner von event-rms hochladen (neben <code>index.php</code>).</li>
            <li>Diese Seite neu laden.</li>
        </ol>
        <pre class="env-preview" id="envContents"><?= e($envContents) ?></pre>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary" type="button" id="copyEnv">Inhalt kopieren</button>
            <a class="btn btn-outline-primary" download=".env"
               href="data:text/plain;charset=utf-8,<?= e(rawurlencode($envContents)) ?>">Als Datei herunterladen</a>
            <a class="btn btn-primary ms-auto" href="<?= e(url('setup.php')) ?>">Hochgeladen – weiter</a>
        </div>
        <p class="form-text mt-3">Die Datei enthält das Datenbank-Passwort. Nicht per E-Mail verschicken und nach dem Hochladen lokal löschen.</p>
    </div>
</div>
<script>
document.getElementById('copyEnv').addEventListener('click', function () {
    navigator.clipboard.writeText(document.getElementById('envContents').textContent)
        .then(() => { this.textContent = 'Kopiert ✓'; });
});
</script>
