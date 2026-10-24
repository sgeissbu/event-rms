<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Color;

/**
 * Leitet aus den zwei Vereinsfarben alle Theme-Variablen ab (hell + dunkel).
 * Die festen Design-Tokens (Abstände, Radien, Schatten …) stehen in assets/css/app.css.
 */
final class Theme
{
    public const FALLBACK_PRIMARY = '#1D4ED8';
    public const FALLBACK_SECONDARY = '#F59E0B';

    // Seitenhintergründe aus app.css – müssen mit --surface (hell/dunkel) übereinstimmen.
    private const LIGHT_BG = '#FFFFFF';
    private const DARK_BG = '#111827';
    private const INK = '#111827';

    /** @return array{light: array<string,string>, dark: array<string,string>} */
    public static function variables(string $primaryHex, string $secondaryHex): array
    {
        $primary = Color::fromHex($primaryHex) ?? Color::fromHex(self::FALLBACK_PRIMARY);
        $secondary = Color::fromHex($secondaryHex) ?? Color::fromHex(self::FALLBACK_SECONDARY);
        $white = Color::fromHex(self::LIGHT_BG);
        $dark = Color::fromHex(self::DARK_BG);
        $ink = Color::fromHex(self::INK);
        $black = Color::rgb(0, 0, 0);

        // Hover: sehr dunkle Farben werden heller, alle anderen dunkler.
        $hover = static fn (Color $c): Color => $c->luminance() < 0.05 ? $c->mix($white, 0.18) : $c->mix($black, 0.15);

        $light = [
            '--brand' => $primary->hex(),
            '--brand-rgb' => $primary->rgbList(),
            '--brand-hover' => $hover($primary)->hex(),
            '--brand-contrast' => $primary->bestTextColor($ink, $white)->hex(),
            '--brand-text' => $primary->readableOn($white)->hex(),
            '--brand-subtle' => $primary->mix($white, 0.90)->hex(),
            '--brand-muted' => $primary->mix($white, 0.75)->hex(),
            '--accent' => $secondary->hex(),
            '--accent-rgb' => $secondary->rgbList(),
            '--accent-hover' => $hover($secondary)->hex(),
            '--accent-contrast' => $secondary->bestTextColor($ink, $white)->hex(),
            '--accent-text' => $secondary->readableOn($white)->hex(),
            '--accent-subtle' => $secondary->mix($white, 0.85)->hex(),
        ];

        // Im Dunkelmodus verschwinden sehr dunkle Vereinsfarben auf dem Hintergrund (Buttons,
        // aktive Reiter). Dann wird die Farbe aufgehellt, bis sie sich mit 3:1 abhebt (WCAG für UI-Elemente).
        $visibleOnDark = static fn (Color $c): Color => $c->contrast($dark) < 3.0 ? $c->readableOn($dark, 3.0) : $c;
        $darkHover = static fn (Color $c): Color => $c->mix($white, 0.12);
        $primaryDark = $visibleOnDark($primary);
        $secondaryDark = $visibleOnDark($secondary);

        $darkVars = [
            '--brand' => $primaryDark->hex(),
            '--brand-rgb' => $primaryDark->rgbList(),
            '--brand-hover' => $darkHover($primaryDark)->hex(),
            '--brand-contrast' => $primaryDark->bestTextColor($ink, $white)->hex(),
            '--accent' => $secondaryDark->hex(),
            '--accent-rgb' => $secondaryDark->rgbList(),
            '--accent-hover' => $darkHover($secondaryDark)->hex(),
            '--accent-contrast' => $secondaryDark->bestTextColor($ink, $white)->hex(),
            '--brand-text' => $primary->readableOn($dark)->hex(),
            '--brand-subtle' => $primary->mix($dark, 0.80)->hex(),
            '--brand-muted' => $primary->mix($dark, 0.60)->hex(),
            '--accent-text' => $secondary->readableOn($dark)->hex(),
            '--accent-subtle' => $secondary->mix($dark, 0.80)->hex(),
        ];

        return ['light' => $light, 'dark' => $darkVars];
    }

    /** Fertiger CSS-Block für ein <style>-Element im <head>. */
    public static function css(string $primaryHex, string $secondaryHex): string
    {
        $vars = self::variables($primaryHex, $secondaryHex);
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

    /** Normalisiert eine Eingabe auf #RRGGBB oder null bei ungültigem Wert. */
    public static function normalizeHex(string $value): ?string
    {
        $color = Color::fromHex($value);
        return $color?->hex();
    }
}
