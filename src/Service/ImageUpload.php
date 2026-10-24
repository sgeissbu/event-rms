<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Verarbeitet Logo- und Foto-Uploads sicher:
 * - Typ wird am Inhalt erkannt (PNG, JPEG, WebP), nicht an der Endung
 * - Pixelzahl und Speicherbedarf werden vor dem Laden geprüft
 * - JPEG-Fotos werden gemäss EXIF gedreht
 * - jedes Bild wird mit GD neu gespeichert → keine Metadaten (z.B. GPS), kein eingeschleuster Code
 * - zufälliger Dateiname, Ablage unter uploads/branding/
 */
final class ImageUpload
{
    public const KIND_LOGO = 'logo';
    public const KIND_HERO = 'hero';

    private const MAX_BYTES = 20 * 1024 * 1024;
    private const MAX_PIXELS = 50_000_000;
    private const LIMITS = [
        self::KIND_LOGO => ['max_side' => 600, 'format' => 'png'],
        self::KIND_HERO => ['max_side' => 1920, 'format' => 'jpg'],
    ];

    /**
     * @param array<string, mixed> $file  ein Eintrag aus $_FILES
     * @return string Pfad relativ zu uploads/ (z.B. «branding/ab12….jpg»)
     * @throws RuntimeException mit verständlicher Meldung
     */
    public static function fromRequest(array $file, string $kind, string $uploadsDir = APP_ROOT . '/uploads'): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist grösser als der Server erlaubt (' . ini_get('upload_max_filesize') . '). Bitte ein kleineres Bild wählen.',
                UPLOAD_ERR_NO_FILE => 'Bitte eine Bilddatei auswählen.',
                UPLOAD_ERR_PARTIAL => 'Der Upload wurde unterbrochen. Bitte nochmals versuchen.',
                default => 'Upload fehlgeschlagen (Code ' . $error . '). Bitte den Hosting-Support fragen.',
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Ungültiger Upload.');
        }
        return self::process($tmp, $kind, $uploadsDir);
    }

    /** Kern ohne $_FILES-Bezug (testbar). */
    public static function process(string $path, string $kind, string $uploadsDir): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Auf diesem Server fehlt die PHP-Erweiterung GD. Bitte beim Hosting aktivieren lassen.');
        }
        $limits = self::LIMITS[$kind] ?? throw new RuntimeException('Unbekannte Bildart.');

        $size = (int) @filesize($path);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Das Bild ist zu gross (max. ' . (self::MAX_BYTES / 1024 / 1024) . ' MB).');
        }
        $info = @getimagesize($path);
        $type = $info[2] ?? null;
        if (!in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Bitte ein Bild im Format PNG, JPEG oder WebP hochladen.');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException(sprintf('Das Bild hat zu viele Pixel (%.0f MP). Bitte vorher verkleinern.', $width * $height / 1e6));
        }
        self::assertEnoughMemory($width, $height, $limits['max_side']);

        $image = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };
        if ($image === false) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden (beschädigt oder Format vom Server nicht unterstützt).');
        }
        if ($type === IMAGETYPE_JPEG) {
            $image = self::applyExifOrientation($image, $path);
        }

        $image = self::fitInto($image, $limits['max_side']);

        $dir = rtrim($uploadsDir, '/') . '/branding';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Ordner uploads/branding konnte nicht angelegt werden (Schreibrechte prüfen).');
        }
        $name = $kind . '-' . bin2hex(random_bytes(8)) . '.' . $limits['format'];
        $target = $dir . '/' . $name;
        $ok = $limits['format'] === 'png' ? imagepng($image, $target, 6) : imagejpeg($image, $target, 82);
        if (!$ok) {
            throw new RuntimeException('Das Bild konnte nicht gespeichert werden (Schreibrechte für uploads/ prüfen).');
        }
        return 'branding/' . $name;
    }

    /** Löscht eine früher hochgeladene Datei – nur innerhalb von uploads/branding/. */
    public static function delete(string $relative, string $uploadsDir = APP_ROOT . '/uploads'): void
    {
        if (!preg_match('#^branding/(logo|hero)-[0-9a-f]{16}\.(png|jpg)$#', $relative)) {
            return;
        }
        $file = rtrim($uploadsDir, '/') . '/' . $relative;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * GD braucht ca. 5 Byte pro Pixel (RGBA + Overhead) – für Original und Ziel.
     * Schützt vor «Allowed memory size exhausted» bei grossen Handyfotos.
     */
    private static function assertEnoughMemory(int $width, int $height, int $maxSide): void
    {
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return; // unbegrenzt
        }
        $scale = min(1.0, $maxSide / max($width, $height));
        $needed = (int) (($width * $height + $width * $height * $scale * $scale) * 5 * 1.3);
        $available = $limit - memory_get_usage(true);
        if ($needed > $available && !@ini_set('memory_limit', (string) (memory_get_usage(true) + $needed + 16 * 1024 * 1024))) {
            throw new RuntimeException(sprintf(
                'Das Bild ist für diesen Server zu gross (%d × %d Pixel). Bitte auf ca. 3000 Pixel Breite verkleinern.',
                $width,
                $height
            ));
        }
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    /** Verkleinert proportional auf max. $maxSide (nie vergrössern), Transparenz bleibt erhalten. */
    private static function fitInto(\GdImage $image, int $maxSide): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1.0, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }
}
