<?php /** @var ?array $loginEvent  @var ?string $error  @var string $code  @var string $name */ ?>
<?php if ($loginEvent === null): ?>
    <div class="empty-state"><p class="mb-0">Zurzeit ist kein Anlass aktiv.</p></div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <span class="eyebrow"><?= e($loginEvent['title']) ?></span>
            <h1 class="h3 mb-3">Kampfrichter-Zugang</h1>
            <p class="text-muted">Den Code erhältst du von der Wettkampfleitung. Dein Name wird bei jedem erfassten Resultat gespeichert.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="code">Zugangscode</label>
                    <input class="form-control form-control-lg code-input" id="code" name="code" required autocomplete="off"
                           autocapitalize="characters" spellcheck="false" value="<?= e($code) ?>" <?= $code === '' ? 'autofocus' : '' ?>>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="name">Dein Name</label>
                    <input class="form-control form-control-lg" id="name" name="name" required minlength="2" maxlength="60"
                           autocomplete="name" value="<?= e($name) ?>" <?= $code !== '' ? 'autofocus' : '' ?>>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit" data-once>Los geht's</button>
            </form>
        </div>
    </div>
<?php endif; ?>
