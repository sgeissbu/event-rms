<?php
/**
 * Haupt-Layout für öffentliche Seiten.
 * @var string $content  @var string $clubName  @var string $clubUrl  @var ?string $logoUrl
 * @var string $logoInitials  @var string $themeCss  @var string $activeNav  @var array $flashes
 * @var ?string $pageTitle  @var ?string $hero  (optional: fertiges Hero-HTML über dem Inhalt)
 * @var array<string, array{0:string,1:string}> $navItems  key => [URL, Label]
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?php $fullTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $clubName : $clubName; ?>
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription ?? '') ?>">
    <meta name="theme-color" content="<?= e($brandHex ?? '#1D4ED8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_CH">
    <meta property="og:site_name" content="<?= e($clubName) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription ?? '') ?>">
    <meta property="og:url" content="<?= e(base_url() . ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <?php if (!empty($shareImage)): ?><meta property="og:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($shareImage) ? 'summary_large_image' : 'summary' ?>">
    <link rel="icon" href="<?= e($faviconHref ?? '') ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <style><?= $themeCss ?></style>
    <noscript><style>.navbar-collapse.collapse { display: block; } .navbar-toggler { display: none; }</style></noscript>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>
<header class="app-header">
    <nav class="navbar navbar-expand-md">
        <div class="container">
            <a class="app-brand" href="<?= e(url('index.php')) ?>">
                <?php $logoAlt = ''; /* Vereinsname steht gleich daneben */ require __DIR__ . '/partials/logo.php'; ?>
                <span><?= e($clubName) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto gap-md-1">
                    <?php foreach ($navItems as $key => [$href, $label]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activeNav === $key ? ' active' : '' ?>"
                               <?= $activeNav === $key ? 'aria-current="page"' : '' ?>
                               href="<?= e($href) ?>"><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<?= $hero ?? '' ?>

<main class="app-main" id="main" tabindex="-1">
    <div class="container">
        <?php require __DIR__ . '/partials/flashes.php'; ?>
        <?= $content ?>
    </div>
</main>

<footer class="app-footer">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span>&copy; <?= date('Y') ?>
            <?php if ($clubUrl !== ''): ?><a href="<?= e($clubUrl) ?>" rel="noopener"><?= e($clubName) ?></a><?php else: ?><?= e($clubName) ?><?php endif; ?>
        </span>
        <span>
            <a href="<?= e(url('judge/index.php')) ?>">Kampfrichter</a> ·
            <?= t('footer.powered', ['link' => '<a href="https://github.com/sgeissbu/event-rms" rel="noopener">event-rms</a>']) ?>
        </span>
    </div>
</footer>
<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/forms.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
