<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Color;

/**
 * Baut aus dem hochgeladenen Vereinslogo ein quadratisches Favicon (Tab-Symbol) zusammen.
 *
 * Das rohe Logo ist meist breiter als hoch und würde im quadratischen Tab-Symbol
 * verzerrt; auf dunkler Tab-Leiste wäre ein dunkles Logo zudem kaum sichtbar. Darum:
 * - Leere Ränder werden abgeschnitten und das Logo proportional und zentriert auf eine
 *   quadratische Fläche gesetzt – nichts wird gestaucht.
 * - Ein Emblem für beide Modi: Vereinsfarbe als abgerundeter Hintergrund, darauf das
 *   Logo für den Dunkelmodus (die helle Variante); gibt es keins, eine weisse
 *   Silhouette des Logos. Liest sich auf heller wie dunkler Tab-Leiste.
 * - Die Silhouette taugt nur für einfarbige Logos (z. B. ein Tier- oder Schriftzug-Umriss).
 *   Ein mehrfarbiges Logo (Wappen, Logo mit Hintergrundfläche) würde zum weissen Klecks –
 *   es wird ohne Dunkelmodus-Logo darum unverändert, proportional und zentriert auf
 *   durchsichtigen Grund gesetzt.
 * - Ein einziges PNG, weil Safari (Stand Version 26/27) Medienabfragen in SVG-Favicons
 *   ignoriert (WebKit-Fehler 309949) und ein umsichtigendes SVG deshalb immer in der
 *   hellen Variante zeigen würde. Auf Safari-Optimierungen wurde bewusst verzichtet.
 * - Ablage unter uploads/branding/, ausgeliefert direkt vom Webserver (cacherbar).
 *   Neu erzeugt wird nur, wenn sich Logo oder Farbe ändert: Der Dateiname enthält eine
 *   Prüfsumme aus Logos, Änderungszeiten, Hintergrundfarbe und Version.
 */
final class Favicon
{
    /** Kantenlänge des erzeugten Bilds in Pixel (scharf auf Retina-Displays). */
    private const SIZE = 128;

    /** Rand um das Logo, Anteil der Kantenlänge pro Seite. */
    private const MARGIN = 0.05;

    /** Radius der abgerundeten Ecken. */
    private const RADIUS = 20;

    /** Erhöhen, wenn sich die Erzeugung ändert – der Dateiname ändert sich und die Browser holen neu. */
    private const VERSION = 4;

    /**
     * apple-touch-icon (Safari-Favoriten, «Zum Home-Bildschirm» auf iPhone/iPad): 180 px, deckend und eckig –
     * iOS rundet die Ecken selbst; durchsichtige Ränder würden schwarz. Etwas mehr Rand als beim Favicon.
     */
    private const TOUCH_SIZE = 180;
    private const TOUCH_MARGIN = 0.14;

    /**
     * Erzeugt (oder findet) das Favicon-Bild für die beiden Logo-Varianten.
     * Liefert null, wenn kein brauchbares Logo vorhanden ist – die Aufrufstelle
     * fällt dann auf das bisherige Verhalten zurück (Logo direkt oder Initialen).
     *
     * @param string|null $logoRel     helles Logo, Pfad relativ zu uploads/
     * @param string|null $logoDarkRel dunkles Logo (für dunkle Navigation = helle Bildvariante), falls vorhanden
     * @return ?array{png: string, touch: ?string} Pfade relativ zu uploads/ (touch = apple-touch-icon, falls erzeugbar)
     */
    public static function fromLogos(?string $logoRel, ?string $logoDarkRel, string $backgroundHex, string $uploadsDir = APP_ROOT . '/uploads'): ?array
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $lightPath = self::logoPath($logoRel, $uploadsDir);
        if ($lightPath === null) {
            return null;
        }
        $darkPath = self::logoPath($logoDarkRel, $uploadsDir);

        $key = substr(md5(self::VERSION . '|' . $logoRel . '|' . ($logoDarkRel ?? '')
            . '|' . $backgroundHex
            . '|' . (int) filemtime($lightPath)
            . '|' . ($darkPath !== null ? (int) filemtime($darkPath) : '0')), 0, 16);
        $pngRel = 'branding/favicon-' . $key . '.png';
        $pngPath = rtrim($uploadsDir, '/') . '/' . $pngRel;
        $touchRel = 'branding/touch-icon-' . $key . '.png';
        $touchPath = rtrim($uploadsDir, '/') . '/' . $touchRel;
        if (is_file($pngPath) && is_file($touchPath)) {
            return ['png' => $pngRel, 'touch' => $touchRel];
        }

        $dir = rtrim($uploadsDir, '/') . '/branding';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        if (!is_file($pngPath)) {
            $icon = self::build($lightPath, $darkPath, $backgroundHex, self::SIZE, self::MARGIN, self::RADIUS, false);
            $data = $icon !== null ? self::encodePng($icon) : '';
            if ($data === '' || !self::writeFile($pngPath, $data)) {
                return null;
            }
        }
        $touch = self::build($lightPath, $darkPath, $backgroundHex, self::TOUCH_SIZE, self::TOUCH_MARGIN, 0, true);
        $touchData = $touch !== null ? self::encodePng($touch) : '';
        $touchOk = $touchData !== '' && self::writeFile($touchPath, $touchData);
        self::removeOutdated($dir, [$pngRel, $touchRel]);

        return ['png' => $pngRel, 'touch' => $touchOk ? $touchRel : null];
    }

    /**
     * Setzt das Emblem zusammen: Vereinsfarbe als Fläche, Logo darüber.
     */
    private static function build(string $lightPath, ?string $darkPath, string $backgroundHex, int $size, float $margin, int $radius, bool $opaque): ?\GdImage
    {
        $light = self::readPng($lightPath);
        $lightBox = $light !== null ? self::visibleBox($light) : null;
        if ($lightBox === null) {
            return null;
        }

        $icon = self::createCanvas($size);
        $dark = $darkPath !== null ? self::readPng($darkPath) : null;
        $darkBox = $dark !== null ? self::visibleBox($dark) : null;
        if ($darkBox === null && !self::isMonochrome($light, $lightBox)) {
            // Mehrfarbiges Logo: unverändert. Das Touch-Icon braucht trotzdem eine deckende Fläche – dann weiss.
            if ($opaque) {
                self::drawChip($icon, Color::rgb(255, 255, 255), $size, 0);
            }
            self::placeCentered($icon, $light, $lightBox, $size, $margin);
            return $icon;
        }
        self::drawChip($icon, Color::fromHex($backgroundHex) ?? Color::fromHex(Theme::FALLBACK_PRIMARY), $size, $radius);

        // Logo für den Dunkelmodus (für dunklen Grund gemacht, also hell) unverändert,
        // sonst eine weiss eingefärbte Silhouette – auf der Vereinsfarbe stets sichtbar.
        if ($darkBox === null) {
            $dark = self::tintWhite($light, $lightBox);
            $darkBox = [0, 0, $lightBox[2], $lightBox[3]];
        }
        self::placeCentered($icon, $dark, $darkBox, $size, $margin);

        return $icon;
    }

    /**
     * Liest nur PNG-Bilder mit Plausibilitätsprüfung (Grösse), nie Skripte.
     */
    private static function readPng(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false || (int) ($info[2] ?? 0) !== IMAGETYPE_PNG || (int) $info[0] * (int) $info[1] > 12_000_000) {
            return null;
        }
        $image = @imagecreatefrompng($path);
        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * Akzeptiert nur die eigenen Dateinamen unter branding/ – kein Pfad von aussen.
     */
    private static function logoPath(?string $rel, string $uploadsDir): ?string
    {
        if ($rel === null || $rel === '' || preg_match('#^branding/logo-[0-9a-f]{16}\.png$#', $rel) !== 1) {
            return null;
        }
        $path = rtrim($uploadsDir, '/') . '/' . $rel;
        return is_file($path) ? $path : null;
    }

    /**
     * Sichtbarer Bereich: Umrandung aller praktisch nicht durchsichtigen Bildpunkte
     * (entspricht einem automatischen Zurechtschneiden des leeren Rands).
     * @return ?array{0: int, 1: int, 2: int, 3: int} [x, y, Breite, Höhe], null wenn nichts sichtbar
     */
    private static function visibleBox(\GdImage $src): ?array
    {
        $width = imagesx($src);
        $height = imagesy($src);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($src, $x, $y) >> 24) & 0x7F) > 110) {
                    continue; // praktisch durchsichtig
                }
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0 || $maxY < 0) {
            return null;
        }
        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }

    /**
     * Einfarbig: alle deutlich sichtbaren Bildpunkte haben (nahezu) dieselbe Farbe.
     * Geprüft wird ein Raster von höchstens ~100×100 Punkten im sichtbaren Bereich.
     * @param array{0: int, 1: int, 2: int, 3: int} $box
     */
    private static function isMonochrome(\GdImage $src, array $box): bool
    {
        [$x0, $y0, $width, $height] = $box;
        $step = max(1, intdiv(max($width, $height), 100));
        $first = null;
        for ($y = $y0; $y < $y0 + $height; $y += $step) {
            for ($x = $x0; $x < $x0 + $width; $x += $step) {
                $c = imagecolorat($src, $x, $y);
                if ((($c >> 24) & 0x7F) > 40) {
                    continue; // durchsichtig oder Kantenglättung
                }
                $rgb = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
                if ($first === null) {
                    $first = $rgb;
                } elseif (abs($rgb[0] - $first[0]) + abs($rgb[1] - $first[1]) + abs($rgb[2] - $first[2]) > 60) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Quadratische Fläche mit vollständig durchsichtigem Hintergrund.
     */
    private static function createCanvas(int $size): \GdImage
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, $transparent);
        imagealphablending($canvas, true);
        return $canvas;
    }

    /**
     * Farbige Fläche mit abgerundeten Ecken (ausserhalb der Rundung bleibt durchsichtig).
     * Ohne Mischung malen, sonst würde das Durchsichtige nicht überschrieben.
     */
    private static function drawChip(\GdImage $canvas, Color $background, int $size, int $radius): void
    {
        imagealphablending($canvas, false);
        $chip = imagecolorallocate($canvas, $background->r, $background->g, $background->b);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, $chip);
        $r = $radius;
        if ($r <= 0) {
            imagealphablending($canvas, true);
            return; // eckig (Touch-Icon)
        }
        $half = intdiv($size, 2);
        $centers = [[$r, $r], [$size - 1 - $r, $r], [$r, $size - 1 - $r], [$size - 1 - $r, $size - 1 - $r]];
        foreach ($centers as [$cx, $cy]) {
            $x0 = $cx < $half ? 0 : $size - $r - 1;
            $y0 = $cy < $half ? 0 : $size - $r - 1;
            for ($y = $y0; $y <= $y0 + $r; $y++) {
                for ($x = $x0; $x <= $x0 + $r; $x++) {
                    if (($x - $cx) ** 2 + ($y - $cy) ** 2 > $r ** 2) {
                        imagesetpixel($canvas, $x, $y, $clear);
                    }
                }
            }
        }
        imagealphablending($canvas, true);
    }

    /**
     * Logo proportional und zentriert einsetzen – das Seitenverhältnis bleibt erhalten.
     * @param array{0: int, 1: int, 2: int, 3: int} $box
     */
    private static function placeCentered(\GdImage $canvas, \GdImage $logo, array $box, int $size, float $margin): void
    {
        [$x, $y, $width, $height] = $box;
        $inner = $size - 2 * ($size * $margin);
        $scale = min($inner / $width, $inner / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        imagecopyresampled(
            $canvas,
            $logo,
            intdiv($size - $targetWidth, 2),
            intdiv($size - $targetHeight, 2),
            $x,
            $y,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );
    }

    /**
     * Silhouette: alle sichtbaren Bildpunkte weiss einfärben, der Alphakanal bleibt.
     * @param array{0: int, 1: int, 2: int, 3: int} $box
     */
    private static function tintWhite(\GdImage $src, array $box): \GdImage
    {
        [$x0, $y0, $width, $height] = $box;
        $out = imagecreatetruecolor($width, $height);
        imagesavealpha($out, true);
        imagealphablending($out, false);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $width - 1, $height - 1, $transparent);
        $whites = [];
        for ($a = 0; $a < 127; $a++) {
            $whites[$a] = imagecolorallocatealpha($out, 255, 255, 255, $a);
        }
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($src, $x0 + $x, $y0 + $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha < 127) {
                    imagesetpixel($out, $x, $y, $whites[$alpha]);
                }
            }
        }
        return $out;
    }

    /**
     * PNG in den Speicher schreiben (Rohdaten für die Datei).
     */
    private static function encodePng(\GdImage $image): string
    {
        $tmp = @tempnam(sys_get_temp_dir(), 'favicon');
        if ($tmp === false) {
            return '';
        }
        $ok = imagepng($image, $tmp, 9);
        $data = $ok ? (string) @file_get_contents($tmp) : '';
        @unlink($tmp);
        return $data;
    }

    /**
     * Atomar schreiben (über eine Hilfsdatei), damit kein halbes Bild ausgeliefert wird.
     */
    private static function writeFile(string $target, string $data): bool
    {
        $tmp = @tempnam(dirname($target), 'favicon');
        if ($tmp === false) {
            return false;
        }
        $written = $data !== '' && @file_put_contents($tmp, $data) !== false;
        if (!$written || !@rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }
        @chmod($target, 0644);
        return true;
    }

    /**
     * Löscht Favicon-Dateien früherer Logo-Stände, damit uploads/branding/ nicht voll läuft.
     * @param string[] $keep Pfade relativ zu uploads/, die behalten werden
     */
    private static function removeOutdated(string $dir, array $keep): void
    {
        $keepNames = array_map('basename', $keep);
        foreach ([$dir . '/favicon-*.png', $dir . '/favicon-*.svg', $dir . '/touch-icon-*.png'] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (!in_array(basename($file), $keepNames, true)) {
                    @unlink($file);
                }
            }
        }
    }
}
