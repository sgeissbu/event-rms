<?php
/**
 * Layout für Kampfrichter: kompakt, mobil zuerst, ohne öffentliche Navigation.
 * @var string $content  @var string $clubName  @var string $themeCss  @var array $flashes
 * @var ?string $pageTitle  @var ?array $judge  @var string $activeNav
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title><?= e(($pageTitle ?? 'Kampfrichter') . ' · ' . $clubName) ?></title>
    <meta name="theme-color" content="<?= e($brandHex ?? '#1D4ED8') ?>">
    <?php if (!empty($faviconHref)): ?><link rel="icon" href="<?= e($faviconHref) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style><?= $themeCss ?></style>
</head>
<body class="judge-body">
<header class="judge-header">
    <div class="container container-narrow d-flex align-items-center gap-3">
        <div class="flex-grow-1 min-w-0">
            <div class="judge-event text-truncate"><?= e($judge['event']['title'] ?? $clubName) ?></div>
            <?php if (!empty($judge)): ?>
                <div class="judge-name text-truncate">Kampfrichter: <?= e($judge['name']) ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($judge)): ?>
            <form method="post" action="<?= e(url('judge/logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary" type="submit">Abmelden</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (!empty($judge)): ?>
        <nav class="container container-narrow judge-tabs">
            <a class="<?= $activeNav === 'capture' ? 'active' : '' ?>" <?= $activeNav === 'capture' ? 'aria-current="page"' : '' ?> href="<?= e(url('judge/erfassen.php')) ?>">Erfassen</a>
            <a class="<?= $activeNav === 'ranking' ? 'active' : '' ?>" <?= $activeNav === 'ranking' ? 'aria-current="page"' : '' ?> href="<?= e(url('rangliste.php', \App\Http\App::eventQuery($judge['event']))) ?>">Rangliste</a>
        </nav>
    <?php endif; ?>
</header>
<main class="app-main pt-4">
    <div class="container container-narrow">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?>
    </div>
</main>
<script src="<?= e(asset('assets/js/forms.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
