<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Color;

/**
 * Leitet aus den zwei Vereinsfarben alle Theme-Variablen ab (hell + dunkel).
 * Die festen Design-Tokens (Abstände, Radien, Schatten …) stehen in assets/css/app.css.
 *
 * Grundsatz: Eine Vereinsfarbe wird nie «blind» verwendet. Schrift darauf bzw. Schrift in dieser Farbe
 * wird gegen ALLE Flächen geprüft, auf denen sie vorkommen kann (Seite, Karte, getönte Fläche).
 */
final class Theme
{
    public const FALLBACK_PRIMARY = '#1D4ED8';
    public const FALLBACK_SECONDARY = '#F59E0B';

    // Flächen aus app.css – müssen mit den Tokens --surface, --surface-alt, --surface-raised übereinstimmen.
    private const LIGHT_SURFACES = ['#FFFFFF', '#F6F7F9'];
    private const DARK_SURFACES = ['#111827', '#0B1220', '#1A2333'];
    private const INK = '#111827';

    /** Mindestkontrast für Schrift (WCAG AA) bzw. für Bedienelemente/Rahmen (WCAG 1.4.11). */
    public const MIN_TEXT = 4.5;
    public const MIN_UI = 3.0;

    /**
     * $nameLight/$nameDark: optional eine eigene Farbe für den Vereinsnamen pro Modus.
     * Leer/gelassen = Verhalten wie bisher (Schrift in berechneter Markenfarbe --brand-text).
     * @return array{light: array<string,string>, dark: array<string,string>}
     */
    public static function variables(string $primaryHex, string $secondaryHex, ?string $nameLight = null, ?string $nameDark = null): array
    {
        $primary = Color::fromHex($primaryHex) ?? Color::fromHex(self::FALLBACK_PRIMARY);
        $secondary = Color::fromHex($secondaryHex) ?? Color::fromHex(self::FALLBACK_SECONDARY);
        $white = Color::rgb(255, 255, 255);
        $ink = Color::fromHex(self::INK);
        $black = Color::rgb(0, 0, 0);
        $lightSurfaces = array_map([Color::class, 'fromHex'], self::LIGHT_SURFACES);
        $darkSurfaces = array_map([Color::class, 'fromHex'], self::DARK_SURFACES);
        $darkBase = $darkSurfaces[0];

        // Hover: sehr dunkle Farben werden heller, alle anderen dunkler.
        $hover = static fn (Color $c): Color => $c->luminance() < 0.05 ? $c->mix($white, 0.18) : $c->mix($black, 0.15);

        // Getönte Flächen (Badges, Hinweise, aktive Menüeinträge) – darauf steht Schrift in der Vereinsfarbe.
        $brandSubtle = $primary->mix($white, 0.90);
        $accentSubtle = $secondary->mix($white, 0.85);
        $brandSubtleDark = $primary->mix($darkBase, 0.80);
        $accentSubtleDark = $secondary->mix($darkBase, 0.80);

        $brandText = $primary->readableOnAll([...$lightSurfaces, $brandSubtle], self::MIN_TEXT);
        $accentText = $secondary->readableOnAll([...$lightSurfaces, $accentSubtle], self::MIN_TEXT);

        $light = self::hero($primary, $secondary) + [
            '--brand' => $primary->hex(),
            '--brand-rgb' => $primary->rgbList(),
            '--brand-hover' => $hover($primary)->hex(),
            '--brand-contrast' => $primary->bestTextColor($ink, $white)->hex(),
            '--brand-text' => $brandText->hex(),
            '--brand-text-rgb' => $brandText->rgbList(),
            '--brand-subtle' => $brandSubtle->hex(),
            '--brand-muted' => $primary->mix($white, 0.75)->hex(),
            '--accent' => $secondary->hex(),
            '--accent-rgb' => $secondary->rgbList(),
            '--accent-hover' => $hover($secondary)->hex(),
            '--accent-contrast' => $secondary->bestTextColor($ink, $white)->hex(),
            '--accent-text' => $accentText->hex(),
            '--accent-subtle' => $accentSubtle->hex(),
        ];
        $light += self::checkImages($primary->bestTextColor($ink, $white));

        // Im Dunkelmodus verschwinden sehr dunkle Vereinsfarben auf dem Hintergrund (Knöpfe, aktive Reiter).
        // Dann wird die Farbe aufgehellt, bis sie sich mit 3:1 abhebt (WCAG für Bedienelemente).
        $visibleOnDark = static fn (Color $c): Color => $c->contrast($darkBase) < self::MIN_UI ? $c->readableOn($darkBase, self::MIN_UI) : $c;
        $darkHover = static fn (Color $c): Color => $c->mix($white, 0.12);
        $primaryDark = $visibleOnDark($primary);
        $secondaryDark = $visibleOnDark($secondary);
        $brandTextDark = $primary->readableOnAll([...$darkSurfaces, $brandSubtleDark], self::MIN_TEXT);
        $accentTextDark = $secondary->readableOnAll([...$darkSurfaces, $accentSubtleDark], self::MIN_TEXT);
        $brandContrastDark = $primaryDark->bestTextColor($ink, $white);

        $darkVars = [
            '--brand' => $primaryDark->hex(),
            '--brand-rgb' => $primaryDark->rgbList(),
            '--brand-hover' => $darkHover($primaryDark)->hex(),
            '--brand-contrast' => $brandContrastDark->hex(),
            '--accent' => $secondaryDark->hex(),
            '--accent-rgb' => $secondaryDark->rgbList(),
            '--accent-hover' => $darkHover($secondaryDark)->hex(),
            '--accent-contrast' => $secondaryDark->bestTextColor($ink, $white)->hex(),
            '--brand-text' => $brandTextDark->hex(),
            '--brand-text-rgb' => $brandTextDark->rgbList(),
            '--brand-subtle' => $brandSubtleDark->hex(),
            '--brand-muted' => $primary->mix($darkBase, 0.60)->hex(),
            '--accent-text' => $accentTextDark->hex(),
            '--accent-subtle' => $accentSubtleDark->hex(),
        ] + self::checkImages($brandContrastDark);

        // Eigene Farbe des Vereinsnamens: nur da setzen, wo sie angegeben ist – sonst gilt --brand-text weiter.
        $nameLight = $nameLight !== null && $nameLight !== '' ? Color::fromHex($nameLight) : null;
        $nameDark = $nameDark !== null && $nameDark !== '' ? Color::fromHex($nameDark) : null;
        if ($nameLight !== null) {
            $light['--club-name-color'] = $nameLight->hex();
        }
        if ($nameDark !== null) {
            $darkVars['--club-name-color'] = $nameDark->hex();
        } elseif ($nameLight !== null) {
            // Nur Hellmodus gesetzt: im Dunkelblock ausdrücklich auf die berechnete Marke zurücksetzen,
            // sonst würde die CSS-Variable aus dem :root in den Dunkelmodus durchsickern.
            $darkVars['--club-name-color'] = $brandTextDark->hex();
        }

        return ['light' => $light, 'dark' => $darkVars];
    }

    /**
     * Hero: grosse Fläche in der Vereinsfarbe. Wahl der Schriftfarbe nach der Farbe selbst:
     * dunkle/kräftige Farben bekommen weisse Schrift, helle (Gelb, Hellgrün …) dunkle Schrift – statt sie
     * zu einem schlammigen Braun abzudunkeln. Die Farbe wird nur so weit angepasst, dass die Schrift ≥ 7:1 hat
     * (Reserve für Überlagerungen). Das Glanzlicht in der Akzentfarbe wird so begrenzt, dass die Schrift
     * auch an der hellsten Stelle noch ≥ 4.5:1 hat.
     * @return array<string, string>
     */
    private static function hero(Color $primary, Color $secondary): array
    {
        $white = Color::rgb(255, 255, 255);
        $ink = Color::fromHex(self::INK);
        $black = Color::rgb(0, 0, 0);

        $forWhite = $primary->darkenForText($white, 7.0);
        $forInk = $primary->lightenForText($ink, 7.0);
        // Die Variante, die der Vereinsfarbe näher bleibt (kleinere Helligkeitsänderung), gewinnt.
        $useInk = abs($forInk->luminance() - $primary->luminance()) < abs($forWhite->luminance() - $primary->luminance());
        $base = $useInk ? $forInk : $forWhite;
        $text = $useInk ? $ink : $white;

        if ($useInk) {
            $end = $base->mix($white, 0.22);
        } else {
            $end = $base->luminance() < 0.05 ? $base->mix($white, 0.14) : $base->mix($black, 0.25);
        }

        $alpha = 0.40;
        while ($alpha > 0.0 && $base->mix($secondary, $alpha)->contrast($text) < self::MIN_TEXT) {
            $alpha = round($alpha - 0.02, 2);
        }

        return [
            '--hero-a' => $base->hex(),
            '--hero-a-rgb' => $base->rgbList(),
            // Tönung des Foto-Schleiers: immer die für weisse Schrift abgedunkelte Variante (auch bei Gelb, wo der Hero selbst hell bleibt)
            '--hero-photo-tint-rgb' => $forWhite->rgbList(),
            '--hero-b' => $end->hex(),
            '--hero-text' => $text->hex(),
            '--hero-text-rgb' => $text->rgbList(),
            '--hero-chip-bg' => $useInk ? 'rgba(255, 255, 255, 0.45)' : 'rgba(0, 0, 0, 0.22)',
            '--hero-glow-rgb' => $secondary->rgbList(),
            '--hero-glow-alpha' => (string) max(0.0, $alpha),
        ];
    }

    /**
     * Häkchen und Schalterknopf in der Schriftfarbe des Knopfs (statt fest Weiss: Weiss auf Gelb = 1.6:1).
     * Als Variablen mit Data-URI, damit CSS und Vorschau sie wie Farben behandeln können.
     * @return array<string, string>
     */
    private static function checkImages(Color $color): array
    {
        $hex = '%23' . ltrim($color->hex(), '#');
        return [
            '--check-image' => "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='{$hex}' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e\")",
            '--switch-image' => "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='{$hex}'/%3e%3c/svg%3e\")",
        ];
    }

    /** Fertiger CSS-Block für ein <style>-Element im <head>. */
    public static function css(string $primaryHex, string $secondaryHex, ?string $nameLight = null, ?string $nameDark = null): string
    {
        $vars = self::variables($primaryHex, $secondaryHex, $nameLight, $nameDark);
        $decl = static function (array $v): string {
            $out = '';
            foreach ($v as $name => $value) {
                $out .= $name . ':' . $value . ';';
            }
            return $out;
        };
        return ':root{' . $decl($vars['light']) . '}'
            . '@media (prefers-color-scheme: dark){:root{' . $decl($vars['dark']) . '}}';
    }

    /**
     * Hinweise für Admins, wenn die gewählten Farben schlecht lesbar oder schlecht unterscheidbar sind.
     * @return list<string>
     */
    public static function warnings(string $primaryHex, string $secondaryHex): array
    {
        $warnings = [];
        $white = Color::rgb(255, 255, 255);
        $ink = Color::fromHex(self::INK);
        $colors = [[Color::fromHex($primaryHex), 'Hauptfarbe'], [Color::fromHex($secondaryHex), 'Akzentfarbe']];

        foreach ($colors as [$color, $label]) {
            if ($color === null) {
                continue;
            }
            $best = max($color->contrast($white), $color->contrast($ink));
            if ($best < self::MIN_TEXT) {
                $warnings[] = sprintf(
                    'Die %s ist ein Mittelton: Schrift darauf ist schwer lesbar (Kontrast %.1f:1, empfohlen mindestens %.1f:1). Besser etwas dunkler oder heller wählen.',
                    $label,
                    floor($best * 10) / 10, // abrunden: 4.49 darf nicht als «4.5» neben «mindestens 4.5» stehen
                    self::MIN_TEXT
                );
            }
        }

        $primary = $colors[0][0];
        $secondary = $colors[1][0];
        if ($primary !== null && $primary->contrast($white) < self::MIN_UI) {
            $warnings[] = 'Die Hauptfarbe ist sehr hell: Unterstreichungen, Rahmen und Reiter sind auf weissem Grund kaum zu sehen. '
                . 'Knöpfe und Hero bleiben lesbar – für den Rest wäre eine etwas kräftigere Farbe besser.';
        }
        if ($primary !== null && $secondary !== null && $primary->contrast($secondary) < 1.5) {
            $warnings[] = 'Haupt- und Akzentfarbe sind fast gleich: Hervorhebungen und der «Jetzt anmelden»-Knopf heben sich kaum ab.';
        }
        return $warnings;
    }

    /**
     * Daten für die Live-Vorschau im Admin: beide Modi und Hinweise.
     * @return array{light: array<string,string>, dark: array<string,string>, warnings: list<string>}
     */
    public static function preview(string $primaryHex, string $secondaryHex, ?string $nameLight = null, ?string $nameDark = null): array
    {
        $vars = self::variables($primaryHex, $secondaryHex, $nameLight, $nameDark);
        // Der Dunkelmodus erbt alles, was er nicht überschreibt – für die Vorschau ausdrücklich zusammenführen.
        return [
            'light' => $vars['light'],
            'dark' => $vars['dark'] + $vars['light'],
            'warnings' => self::warnings($primaryHex, $secondaryHex),
        ];
    }

    /**
     * Hinweis fürs Admin-Feld «Farbe des Vereinsnamens»: zu wenig Kontrast auf den Flächen des
     * jeweiligen Modus (unter 4.5:1). Leere oder ungültige Eingabe ergibt keinen Hinweis –
     * leer bedeutet «automatisch berechnete Markenfarbe», und ungültig wird ohnehin abgelehnt.
     */
    public static function nameColorWarning(?string $hex, bool $dark): ?string
    {
        $color = $hex !== null && trim($hex) !== '' ? Color::fromHex($hex) : null;
        if ($color === null) {
            return null;
        }
        $surfaces = array_map([Color::class, 'fromHex'], $dark ? self::DARK_SURFACES : self::LIGHT_SURFACES);
        $worst = min(array_map(static fn (Color $s): float => $color->contrast($s), $surfaces));
        if ($worst >= self::MIN_TEXT) {
            return null;
        }
        return sprintf(
            'Diese Farbe ist auf dem Hintergrund schlecht lesbar (Kontrast %.1f:1, empfohlen mindestens %.1f:1).',
            floor($worst * 10) / 10, // abrunden wie bei warnings(): 4.49 darf nicht als «4.5» neben «mindestens 4.5» stehen
            self::MIN_TEXT
        );
    }

    /** Normalisiert eine Eingabe auf #RRGGBB oder null bei ungültigem Wert. */
    public static function normalizeHex(string $value): ?string
    {
        $color = Color::fromHex($value);
        return $color?->hex();
    }
}
