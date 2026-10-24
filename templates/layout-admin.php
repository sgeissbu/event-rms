<?php
/**
 * Admin-Layout: Seitenleiste auf Desktop, horizontale Navigation auf Mobile.
 * @var string $content  @var string $clubName  @var ?string $logoUrl  @var string $logoInitials
 * @var string $themeCss  @var array $flashes  @var string $pageTitle  @var string $activeNav
 * @var array $adminNav  @var array $admin  @var ?array $event  @var list<array> $allEvents  @var int $activeEventId
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle . ' · Admin · ' . $clubName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style><?= $themeCss ?></style>
</head>
<body class="admin-body">
<header class="app-header">
    <div class="container-fluid admin-topbar">
        <a class="app-brand" href="<?= e(url('admin/index.php')) ?>">
            <?php require __DIR__ . '/partials/logo.php'; ?>
            <span class="d-none d-sm-inline"><?= e($clubName) ?></span>
            <span class="admin-tag">Admin</span>
        </a>
        <?php if ($allEvents): ?>
            <form method="get" class="admin-event-switch">
                <label class="visually-hidden" for="event-switch">Anlass</label>
                <select class="form-select form-select-sm" id="event-switch" name="event_id" onchange="this.form.submit()">
                    <?php foreach ($allEvents as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>" <?= $event !== null && (int) $ev['id'] === (int) $event['id'] ? 'selected' : '' ?>>
                            <?= e($ev['title']) ?><?= (int) $ev['id'] === $activeEventId ? ' (aktiv)' : '' ?><?= $ev['archived_at'] ? ' (archiviert)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm btn-outline-primary" type="submit">Wechseln</button></noscript>
            </form>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a class="btn btn-sm btn-outline-primary d-none d-md-inline-flex" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Website ansehen</a>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary" type="submit" title="Angemeldet als <?= e($admin['email']) ?>">Abmelden</button>
            </form>
        </div>
    </div>
</header>
<div class="admin-shell">
    <nav class="admin-nav" aria-label="Admin-Navigation">
        <?php foreach ($adminNav as $key => [$href, $label]): ?>
            <a class="<?= $activeNav === $key ? 'active' : '' ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?> href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <main class="admin-main">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?>
    </main>
</div>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/forms.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
