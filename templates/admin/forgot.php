<?php /** @var bool $sent  @var bool $mailConfigured */ ?>
<div class="card mx-auto auth-card">
    <div class="card-body">
        <span class="eyebrow">Admin</span>
        <h1 class="h3 mb-3">Passwort vergessen</h1>
        <?php if (!$mailConfigured): ?>
            <div class="alert alert-warning">Der Mailversand ist noch nicht eingerichtet. Bitte eine andere Admin-Person bitten, dir ein neues Passwort zu setzen.</div>
        <?php elseif ($sent): ?>
            <div class="alert alert-success">Falls die Adresse registriert ist, ist ein Link zum Zurücksetzen unterwegs. Bitte auch den Spam-Ordner prüfen.</div>
        <?php else: ?>
            <p class="text-muted">Gib deine E-Mail-Adresse ein. Du erhältst einen Link, um ein neues Passwort festzulegen.</p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">E-Mail</label>
                    <input class="form-control" id="email" name="email" type="email" required autocomplete="username" autofocus>
                </div>
                <button class="btn btn-primary w-100" type="submit" data-once>Link anfordern</button>
            </form>
        <?php endif; ?>
        <p class="text-center small mt-3 mb-0"><a href="<?= e(url('admin/login.php')) ?>">Zurück zur Anmeldung</a></p>
    </div>
</div>
