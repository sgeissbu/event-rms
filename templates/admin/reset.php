<?php /** @var string $token  @var bool $valid  @var ?string $error */ ?>
<div class="card mx-auto auth-card">
    <div class="card-body">
        <span class="eyebrow">Admin</span>
        <h1 class="h3 mb-3">Neues Passwort</h1>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if (!$valid && !$error): ?>
            <div class="alert alert-warning">Der Link ist ungültig oder abgelaufen.</div>
            <a class="btn btn-outline-primary w-100" href="<?= e(url('admin/passwort-vergessen.php')) ?>">Neuen Link anfordern</a>
        <?php elseif ($valid): ?>
            <form method="post" action="<?= e(url('admin/passwort-neu.php', ['token' => $token])) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="password">Neues Passwort</label>
                    <input class="form-control" id="password" name="password" type="password" required minlength="10" autocomplete="new-password" autofocus>
                    <div class="form-text">Mindestens 10 Zeichen.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password2">Wiederholen</label>
                    <input class="form-control" id="password2" name="password2" type="password" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-100" type="submit" data-once>Passwort speichern</button>
            </form>
        <?php endif; ?>
    </div>
</div>
