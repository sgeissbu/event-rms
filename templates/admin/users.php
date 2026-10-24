<?php
/**
 * @var list<array> $list  @var int $meId  @var array<string,string> $errors  @var array $old  @var ?array $resetTarget
 */
use App\Http\Form;

$f = new Form($errors, $old);
?>
<h1 class="h2 mb-2">Admins</h1>
<p class="text-muted mb-4">Admins verwalten Anlässe, Teilnehmende und Einstellungen. Kampfrichter brauchen kein Konto – sie melden sich mit dem Code des Anlasses an.</p>

<?php if ($resetTarget !== null): ?>
    <section class="card mb-4" style="max-width: 36rem">
        <div class="card-body">
            <h2 class="h5">Neues Passwort für «<?= e($resetTarget['name']) ?>»</h2>
            <p class="text-muted small">Für den Fall, dass kein Mailversand eingerichtet ist oder die Person ihr Passwort verloren hat. Bitte persönlich weitergeben.</p>
            <?php if (isset($errors['set_password'])): ?><div class="alert alert-danger" role="alert"><?= e($errors['set_password']) ?></div><?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_password">
                <input type="hidden" name="id" value="<?= (int) $resetTarget['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="rp1">Neues Passwort</label>
                        <input class="form-control" id="rp1" name="password" type="password" required autocomplete="new-password" autofocus>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="rp2">Wiederholen</label>
                        <input class="form-control" id="rp2" name="password2" type="password" required autocomplete="new-password">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary" type="submit" data-once>Passwort setzen</button>
                    <a class="btn btn-outline-primary" href="<?= e(url('admin/benutzer.php')) ?>">Abbrechen</a>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card mb-4">
    <div class="table-responsive">
        <table class="table admin-table mb-0">
            <thead><tr><th>Name</th><th>E-Mail</th><th>Letzte Anmeldung</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($list as $u): $isMe = $u['id'] === $meId; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['name']) ?><?= $isMe ? ' <span class="badge-soft ms-1">du</span>' : '' ?></td>
                        <td class="text-muted"><?= e($u['email']) ?></td>
                        <td class="text-muted small text-nowrap"><?= $u['last_login_at'] ? e((new DateTimeImmutable($u['last_login_at']))->format('d.m.Y H:i')) : '–' ?></td>
                        <td class="text-end text-nowrap">
                            <?php if (!$isMe): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/benutzer.php', ['reset' => $u['id']])) ?>">Passwort setzen</a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit" data-once>Entfernen</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" class="card h-100" novalidate>
            <div class="card-body">
                <h2 class="h5 mb-3">Neuen Admin anlegen</h2>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control<?= $f->invalid('name') ?>" id="name" name="name" required maxlength="100" value="<?= $f->value('name') ?>" autocomplete="off">
                    <?= $f->feedback('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">E-Mail</label>
                    <input class="form-control<?= $f->invalid('email') ?>" id="email" name="email" type="email" required value="<?= $f->value('email') ?>" autocomplete="off">
                    <?= $f->feedback('email') ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="password">Passwort</label>
                        <input class="form-control<?= $f->invalid('password') ?>" id="password" name="password" type="password" required autocomplete="new-password">
                        <?= $f->feedback('password') ?>
                        <div class="form-text">Mindestens 10 Zeichen.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password2">Wiederholen</label>
                        <input class="form-control<?= $f->invalid('password2') ?>" id="password2" name="password2" type="password" required autocomplete="new-password">
                        <?= $f->feedback('password2') ?>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit" data-once>Anlegen</button>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" class="card h-100" novalidate>
            <div class="card-body">
                <h2 class="h5 mb-3">Mein Passwort ändern</h2>
                <?php if (isset($errors['change_own'])): ?><div class="alert alert-danger" role="alert"><?= e($errors['change_own']) ?></div><?php endif; ?>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_own">
                <div class="mb-3">
                    <label class="form-label" for="current">Aktuelles Passwort</label>
                    <input class="form-control" id="current" name="current" type="password" required autocomplete="current-password">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="own1">Neues Passwort</label>
                        <input class="form-control" id="own1" name="password" type="password" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="own2">Wiederholen</label>
                        <input class="form-control" id="own2" name="password2" type="password" required autocomplete="new-password">
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit" data-once>Passwort ändern</button>
            </div>
        </form>
    </div>
</div>
