<?php
/**
 * Schlankes Layout ohne Navigation (Installer, Fehlerseiten, später Logins).
 * @var string $content  @var string $clubName  @var string $themeCss  @var array $flashes  @var ?string $pageTitle
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title><?= e(isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $clubName : $clubName) ?></title>
    <meta name="theme-color" content="<?= e($brandHex ?? '#1D4ED8') ?>">
    <?php if (!empty($faviconHref)): ?><link rel="icon" href="<?= e($faviconHref) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style><?= $themeCss ?></style>
</head>
<body>
<main class="app-main">
    <div class="container container-narrow">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?>
    </div>
</main>
</body>
</html>
