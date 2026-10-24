<?php
declare(strict_types=1);

use App\Service\ImageUpload;

$tmpDir = static function (): string {
    $dir = sys_get_temp_dir() . '/event-rms-test-' . bin2hex(random_bytes(4));
    mkdir($dir);
    return $dir;
};
$makeImage = static function (string $dir, string $format, int $w, int $h, bool $alpha = false): string {
    $img = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 0, 0, 127));
    } else {
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 60, 120));
    }
    $path = $dir . '/source.' . $format;
    match ($format) {
        'png' => imagepng($img, $path),
        'jpg' => imagejpeg($img, $path, 90),
        'webp' => imagewebp($img, $path),
    };
    return $path;
};
$fails = static function (callable $fn, string $contains): void {
    try {
        $fn();
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), $contains), 'Meldung: ' . $e->getMessage());
        return;
    }
    throw new AssertionFailed('Exception erwartet');
};

return [
    'Foto wird auf max. 1920 px verkleinert und als JPEG gespeichert' => function () use ($tmpDir, $makeImage): void {
        $dir = $tmpDir();
        $rel = ImageUpload::process($makeImage($dir, 'png', 4000, 2000), ImageUpload::KIND_HERO, $dir);
        assert_true(preg_match('#^branding/hero-[0-9a-f]{16}\.jpg$#', $rel) === 1, $rel);
        [$w, $h, $type] = getimagesize($dir . '/' . $rel);
        assert_same([1920, 960, IMAGETYPE_JPEG], [$w, $h, $type]);
    },
    'Kleine Bilder werden nicht vergrössert, WebP wird gelesen' => function () use ($tmpDir, $makeImage): void {
        $dir = $tmpDir();
        $rel = ImageUpload::process($makeImage($dir, 'webp', 800, 600), ImageUpload::KIND_HERO, $dir);
        assert_same([800, 600], array_slice(getimagesize($dir . '/' . $rel), 0, 2));
    },
    'Logo bleibt PNG mit Transparenz' => function () use ($tmpDir, $makeImage): void {
        $dir = $tmpDir();
        $rel = ImageUpload::process($makeImage($dir, 'png', 1200, 400, true), ImageUpload::KIND_LOGO, $dir);
        $img = imagecreatefrompng($dir . '/' . $rel);
        assert_same([600, 200], [imagesx($img), imagesy($img)]);
        assert_same(127, (imagecolorat($img, 10, 10) >> 24) & 0x7F, 'Pixel muss transparent bleiben');
    },
    'Kein Bild (z.B. PHP-Datei mit .jpg-Endung) wird abgelehnt' => function () use ($tmpDir, $fails): void {
        $dir = $tmpDir();
        file_put_contents($dir . '/evil.jpg', "<?php echo 'hacked'; ?>");
        $fails(static fn () => ImageUpload::process($dir . '/evil.jpg', ImageUpload::KIND_HERO, $dir), 'PNG, JPEG oder WebP');
        assert_true(!is_dir($dir . '/branding'), 'nichts gespeichert');
    },
    'GIF/SVG werden nicht akzeptiert' => function () use ($tmpDir, $fails): void {
        $dir = $tmpDir();
        $img = imagecreatetruecolor(10, 10);
        imagegif($img, $dir . '/a.gif');
        $fails(static fn () => ImageUpload::process($dir . '/a.gif', ImageUpload::KIND_LOGO, $dir), 'PNG, JPEG oder WebP');
        file_put_contents($dir . '/a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fails(static fn () => ImageUpload::process($dir . '/a.svg', ImageUpload::KIND_LOGO, $dir), 'PNG, JPEG oder WebP');
    },
    'Neu kodiertes JPEG enthält keine Fremddaten aus dem Original' => function () use ($tmpDir, $makeImage): void {
        $dir = $tmpDir();
        $src = $makeImage($dir, 'jpg', 200, 100);
        // Kommentar-Segment mit «geheimen» Daten hinter den JPEG-Header einschleusen
        $data = file_get_contents($src);
        $marker = "GPS 47.2154N 7.7915E";
        $com = "\xFF\xFE" . pack('n', strlen($marker) + 2) . $marker;
        file_put_contents($src, substr($data, 0, 2) . $com . substr($data, 2));
        assert_true(str_contains((string) file_get_contents($src), $marker), 'Vorbedingung');
        $rel = ImageUpload::process($src, ImageUpload::KIND_HERO, $dir);
        assert_true(!str_contains((string) file_get_contents($dir . '/' . $rel), $marker), 'Metadaten müssen weg sein');
    },
    'delete() löscht nur eigene Dateien in branding/' => function () use ($tmpDir, $makeImage): void {
        $dir = $tmpDir();
        $rel = ImageUpload::process($makeImage($dir, 'png', 50, 50), ImageUpload::KIND_LOGO, $dir);
        file_put_contents($dir . '/setup.lock', 'x');
        ImageUpload::delete('../setup.lock', $dir);
        ImageUpload::delete('setup.lock', $dir);
        ImageUpload::delete('branding/../setup.lock', $dir);
        assert_true(is_file($dir . '/setup.lock'), 'fremde Datei bleibt');
        ImageUpload::delete($rel, $dir);
        assert_true(!is_file($dir . '/' . $rel), 'eigene Datei gelöscht');
    },
];
