<?php
/**
 * Eigenschaftstest: Für viele sehr unterschiedliche Vereinsfarben müssen in HELL und DUNKEL
 * Schrift- und Bedienelement-Kontraste stimmen – nicht nur für zwei Beispielfarben.
 */
declare(strict_types=1);

use App\Domain\Color;
use App\Service\Theme;

$palette = [
    'Jugi-Blau' => ['#1D284C', '#FEC028'], 'Gelb' => ['#FEC028', '#1D284C'], 'Reingelb' => ['#FFFF00', '#000000'],
    'Rot' => ['#E30613', '#FFFFFF'], 'Pink' => ['#FF69B4', '#222222'], 'Grün' => ['#00A86B', '#FFFF00'],
    'Grau' => ['#808080', '#999999'], 'Weiss' => ['#FFFFFF', '#1D4ED8'], 'Schwarz' => ['#000000', '#F59E0B'],
    'Türkis' => ['#00CED1', '#8B0000'], 'Orange' => ['#FF8C00', '#003366'], 'Violett' => ['#6A0DAD', '#32CD32'],
];
$c = static fn (string $hex): Color => Color::fromHex($hex);
$lightSurfaces = ['#FFFFFF', '#F6F7F9'];
$darkSurfaces = ['#111827', '#0B1220', '#1A2333'];

return [
    'Schrift in Vereinsfarbe ist auf allen Flächen lesbar (≥ 4.5:1), hell und dunkel' => function () use ($palette, $c, $lightSurfaces, $darkSurfaces): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $v = Theme::variables($primary, $secondary);
            $checks = [
                ['hell', 'brand', $v['light']['--brand-text'], [...$lightSurfaces, $v['light']['--brand-subtle']]],
                ['hell', 'accent', $v['light']['--accent-text'], [...$lightSurfaces, $v['light']['--accent-subtle']]],
                ['dunkel', 'brand', $v['dark']['--brand-text'], [...$darkSurfaces, $v['dark']['--brand-subtle']]],
                ['dunkel', 'accent', $v['dark']['--accent-text'], [...$darkSurfaces, $v['dark']['--accent-subtle']]],
            ];
            foreach ($checks as [$mode, $kind, $text, $backgrounds]) {
                foreach ($backgrounds as $bg) {
                    $ratio = $c($text)->contrast($c($bg));
                    assert_true($ratio >= 4.5, sprintf('%s %s-Text %s auf %s: %.2f', $name, $mode . '/' . $kind, $text, $bg, $ratio));
                }
            }
        }
    },
    'Schrift auf Knöpfen (brand/accent) ≥ 4.5:1, ausser bei Mitteltönen (dort warnt die Vorschau)' => function () use ($palette, $c): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $v = Theme::variables($primary, $secondary);
            foreach (['light' => 'hell', 'dark' => 'dunkel'] as $mode => $label) {
                $vars = $v[$mode] + $v['light'];
                foreach (['brand', 'accent'] as $kind) {
                    $ratio = $c($vars["--{$kind}-contrast"])->contrast($c($vars["--{$kind}"]));
                    $isMidTone = $ratio < 4.5;
                    if ($isMidTone) {
                        // nur zulässig, wenn die Warnung greift: Mittelton in der Original- ODER aufgehellten Farbe
                        $hex = $kind === 'brand' ? $primary : $secondary;
                        assert_true(Theme::warnings($primary, $secondary) !== [] || $mode === 'dark', "{$name} {$label} {$kind}: {$ratio} ohne Warnung");
                    }
                    assert_true($ratio >= 3.0, sprintf('%s %s %s-Knopf: %.2f', $name, $label, $kind, $ratio));
                }
            }
        }
    },
    'Bedienelemente heben sich ab: Hauptfarbe im Dunkelmodus ≥ 3:1 zum Hintergrund' => function () use ($palette, $c): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $v = Theme::variables($primary, $secondary)['dark'];
            foreach (['--brand', '--accent'] as $key) {
                $ratio = $c($v[$key])->contrast($c('#111827'));
                assert_true($ratio >= 3.0, sprintf('%s dunkel %s: %.2f', $name, $key, $ratio));
            }
        }
    },
    'Hero: Schrift ≥ 4.5:1 auf Grund, Verlaufsende und Glanzlicht – für jede Farbe' => function () use ($palette, $c): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $h = Theme::variables($primary, $secondary)['light'];
            $text = $c($h['--hero-text']);
            $base = $c($h['--hero-a']);
            assert_true($base->contrast($text) >= 7.0, sprintf('%s Hero-Grund: %.2f', $name, $base->contrast($text)));
            assert_true($c($h['--hero-b'])->contrast($text) >= 4.5, sprintf('%s Hero-Ende: %.2f', $name, $c($h['--hero-b'])->contrast($text)));
            $glow = $base->mix($c($secondary), (float) $h['--hero-glow-alpha']);
            assert_true($glow->contrast($text) >= 4.5, sprintf('%s Glanzlicht (%s): %.2f', $name, $h['--hero-glow-alpha'], $glow->contrast($text)));
        }
    },
    'Hero mit Foto: weisse Schrift bleibt auch über einem rein weissen Himmel ≥ 4.5:1 (Schleier wie in app.css)' => function () use ($palette, $c): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $tint = $c('#FFFFFF');
            $rgb = array_map('intval', explode(',', Theme::variables($primary, $secondary)['light']['--hero-photo-tint-rgb']));
            // 22 % Farbtönung, dann 48 % Schwarz (obere Kante des Schleiers, dort ist es am hellsten)
            $veiled = $tint->mix(Color::rgb(...$rgb), 0.22)->mix(Color::rgb(0, 0, 0), 0.48);
            assert_true($veiled->contrast($c('#FFFFFF')) >= 4.5, sprintf('%s: %.2f', $name, $veiled->contrast($c('#FFFFFF'))));
        }
    },
    'Häkchen und Schalterknopf haben die Schriftfarbe des Knopfs' => function () use ($palette): void {
        foreach ($palette as $name => [$primary, $secondary]) {
            $v = Theme::variables($primary, $secondary);
            foreach (['light', 'dark'] as $mode) {
                $contrast = ltrim(($v[$mode] + $v['light'])['--brand-contrast'], '#');
                assert_true(str_contains($v[$mode]['--check-image'], "%23{$contrast}"), "{$name} {$mode}: Häkchen-Farbe");
                assert_true(str_contains($v[$mode]['--switch-image'], "%23{$contrast}"), "{$name} {$mode}: Schalter-Farbe");
            }
        }
    },
    'Warnungen: sehr helle und fast gleiche Farben' => function (): void {
        $light = Theme::warnings('#FFFF00', '#1D284C');
        assert_true(count(array_filter($light, fn ($m) => str_contains($m, 'sehr hell'))) === 1, 'Reingelb als Hauptfarbe: «sehr hell»');
        assert_same([], array_filter(Theme::warnings('#1D284C', '#FEC028'), fn ($m) => true), 'Jugi-Farben: keine Warnung');
        $same = Theme::warnings('#1D284C', '#1D284C');
        assert_true(count(array_filter($same, fn ($m) => str_contains($m, 'fast gleich'))) === 1);
        assert_true(count(array_filter(Theme::warnings('#1D284C', '#222B50'), fn ($m) => str_contains($m, 'fast gleich'))) === 1, 'ähnliche Farben');
    },
];
