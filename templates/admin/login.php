<?php /** @var ?string $error  @var string $email */ ?>
<div class="card mx-auto auth-card">
    <div class="card-body">
        <span class="eyebrow">Admin</span>
        <h1 class="h3 mb-4">Anmelden</h1>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">E-Mail</label>
                <input class="form-control" id="email" name="email" type="email" required autocomplete="username" value="<?= e($email) ?>" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Passwort</label>
                <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary w-100" type="submit" data-once>Anmelden</button>
        </form>
        <p class="text-center small mt-3 mb-0"><a href="<?= e(url('admin/passwort-vergessen.php')) ?>">Passwort vergessen?</a></p>
    </div>
</div>
