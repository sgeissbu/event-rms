<?php
declare(strict_types=1);

use App\Service\Favicon;

/**
 * Favicon aus dem Vereinslogo: quadratisch, Logo nie gestaucht.
 * Die Logos werden in einem temporären uploads-Ordner mit GD erzeugt.
 */

/** Temporärer uploads-Ordner mit branding/. */
$tmpUploads = static function (): string {
    $dir = sys_get_temp_dir() . '/erms-favicon-' . bin2hex(random_bytes(4));
    mkdir($dir . '/branding', 0755, true);
    return $dir;
};

/** Breites Logo (300×100) auf durchsichtigem Grund; $colors = Füllfarben der Streifen von links nach rechts. */
$makeLogo = static function (string $uploads, string $name, array $colors, bool $opaqueBackground = false): string {
    $img = imagecreatetruecolor(300, 100);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $bg = $opaqueBackground ? imagecolorallocate($img, 255, 255, 255) : imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefilledrectangle($img, 0, 0, 299, 99, $bg);
    $w = intdiv(300, count($colors));
    foreach ($colors as $i => [$r, $g, $b]) {
        imagefilledrectangle($img, $i * $w, 0, ($i + 1) * $w - 1, 99, imagecolorallocate($img, $r, $g, $b));
    }
    $rel = 'branding/' . $name;
    imagepng($img, $uploads . '/' . $rel);
    return $rel;
};

/** Sichtbarer Bereich (Alpha < 64) eines Bilds als [Breite, Höhe], optional nur Punkte einer Farbe. */
$visibleSize = static function (GdImage $img, ?array $rgb = null): array {
    $minX = $minY = PHP_INT_MAX;
    $maxX = $maxY = -1;
    for ($y = 0; $y < imagesy($img); $y++) {
        for ($x = 0; $x < imagesx($img); $x++) {
            $c = imagecolorat($img, $x, $y);
            if ((($c >> 24) & 0x7F) >= 64) {
                continue;
            }
            if ($rgb !== null && abs((($c >> 16) & 0xFF) - $rgb[0]) + abs((($c >> 8) & 0xFF) - $rgb[1]) + abs(($c & 0xFF) - $rgb[2]) > 30) {
                continue;
            }
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }
    }
    return $maxX < 0 ? [0, 0] : [$maxX - $minX + 1, $maxY - $minY + 1];
};

$alphaAt = static fn (GdImage $img, int $x, int $y): int => (imagecolorat($img, $x, $y) >> 24) & 0x7F;

$requireGd = static function (): void {
    if (!extension_loaded('gd')) {
        skip('GD nicht installiert');
    }
};

return [
    'Einfarbiges breites Logo: quadratisch, weisse Silhouette im Seitenverhältnis 3:1 auf Vereinsfarbe' => function () use ($tmpUploads, $makeLogo, $visibleSize, $alphaAt, $requireGd): void {
        $requireGd();
        $uploads = $tmpUploads();
        $logo = $makeLogo($uploads, 'logo-00000000000000a1.png', [[20, 40, 80]]);
        $result = Favicon::fromLogos($logo, null, '#1D4ED8', $uploads);
        assert_true($result !== null, 'Favicon erzeugt');
        $img = imagecreatefrompng($uploads . '/' . $result['png']);
        assert_same([128, 128], [imagesx($img), imagesy($img)]);
        [$w, $h] = $visibleSize($img, [255, 255, 255]);
        assert_near(3.0, $w / $h, 0.15, "Silhouette {$w}×{$h} nicht gestaucht");
        assert_same(127, $alphaAt($img, 0, 0), 'abgerundete Ecke durchsichtig');
        assert_true($alphaAt($img, 64, 5) === 0, 'Vereinsfarbe als Fläche');
    },
    'Mehrfarbiges Logo ohne Dunkelmodus-Logo: unverändert, proportional, ohne Farbfläche' => function () use ($tmpUploads, $makeLogo, $visibleSize, $alphaAt, $requireGd): void {
        $requireGd();
        $uploads = $tmpUploads();
        $logo = $makeLogo($uploads, 'logo-00000000000000a2.png', [[200, 30, 30], [30, 160, 60], [30, 60, 200]]);
        $result = Favicon::fromLogos($logo, null, '#1D4ED8', $uploads);
        $img = imagecreatefrompng($uploads . '/' . $result['png']);
        [$w, $h] = $visibleSize($img);
        assert_near(3.0, $w / $h, 0.15, "Logo {$w}×{$h} nicht gestaucht");
        assert_same(127, $alphaAt($img, 64, 5), 'keine Farbfläche über/unter dem Logo');
        $c = imagecolorat($img, 64, 64);
        assert_true((($c >> 8) & 0xFF) > 120, 'Originalfarben (grüne Mitte) bleiben');
    },
    'Logo mit weissem Hintergrund wird nicht zum weissen Rechteck' => function () use ($tmpUploads, $makeLogo, $requireGd): void {
        $requireGd();
        $uploads = $tmpUploads();
        $logo = $makeLogo($uploads, 'logo-00000000000000a3.png', [[255, 255, 255], [0, 0, 0], [255, 255, 255]], true);
        $result = Favicon::fromLogos($logo, null, '#1D4ED8', $uploads);
        $img = imagecreatefrompng($uploads . '/' . $result['png']);
        $c = imagecolorat($img, 64, 64);
        assert_true(((($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF)) < 100, 'schwarze Mitte bleibt sichtbar');
    },
    'Mit Dunkelmodus-Logo: dieses wird proportional auf die Vereinsfarbe gesetzt' => function () use ($tmpUploads, $makeLogo, $visibleSize, $alphaAt, $requireGd): void {
        $requireGd();
        $uploads = $tmpUploads();
        $logo = $makeLogo($uploads, 'logo-00000000000000a4.png', [[20, 40, 80]]);
        $dark = $makeLogo($uploads, 'logo-00000000000000a5.png', [[254, 192, 40]]);
        $result = Favicon::fromLogos($logo, $dark, '#1D284C', $uploads);
        $img = imagecreatefrompng($uploads . '/' . $result['png']);
        [$w, $h] = $visibleSize($img, [254, 192, 40]);
        assert_near(3.0, $w / $h, 0.15, "Dunkelmodus-Logo {$w}×{$h} nicht gestaucht");
        assert_true($alphaAt($img, 64, 5) === 0, 'Vereinsfarbe als Fläche');
    },
    'Fremde Pfade werden nicht gelesen' => function () use ($tmpUploads, $requireGd): void {
        $requireGd();
        $uploads = $tmpUploads();
        assert_same(null, Favicon::fromLogos('../../etc/passwd', null, '#1D4ED8', $uploads));
        assert_same(null, Favicon::fromLogos('branding/logo-zz.png', null, '#1D4ED8', $uploads));
    },
];
