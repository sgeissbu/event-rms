<?php
declare(strict_types=1);

use App\Core\Settings;

/** Rendert das Logo-Partial mit den üblichen Template-Variablen. */
$render = static function (?string $logoUrl, ?string $logoDarkUrl): string {
    $logoInitials = 'MV';
    $clubName = 'Mein Verein';
    $logoAlt = 'Mein Verein';
    ob_start();
    require APP_ROOT . '/templates/partials/logo.php';
    return (string) ob_get_clean();
};

return [
    'Ohne Logo zeigt der Platzhalter (unverändert)' => function () use ($render): void {
        $html = $render(null, null);
        assert_true(str_contains($html, 'logo-placeholder'), $html);
        assert_true(!str_contains($html, '<img'), $html);
    },
    'Ein Logo: genau ein Bild ohne Zusatzklassen (Regression)' => function () use ($render): void {
        $html = $render('uploads/branding/logo.png', null);
        assert_same(1, substr_count($html, '<img'), $html);
        assert_true(str_contains($html, 'alt="Mein Verein"'), $html);
        assert_true(!str_contains($html, 'logo-dark'), 'kein zweites Bild ohne Dunkelmodus-Logo');
    },
    'Zwei Logos: dunkles Bild ist für Screenreader versteckt' => function () use ($render): void {
        $html = $render('uploads/branding/logo.png', 'uploads/branding/logo-dark.png');
        assert_same(2, substr_count($html, '<img'), $html);
        assert_true(str_contains($html, 'class="logo-light"'), $html);
        assert_true(str_contains($html, 'class="logo-dark"'), $html);
        assert_true(str_contains($html, 'alt="" aria-hidden="true"'), 'Dunkelbild: alt="" + aria-hidden');
    },
    'Settings: logo_file_dark ist standardmässig leer' => function (): void {
        assert_true(array_key_exists('logo_file_dark', Settings::DEFAULTS));
        assert_same('', Settings::DEFAULTS['logo_file_dark']);
    },
];
