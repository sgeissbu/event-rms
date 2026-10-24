<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Unveränderliche sRGB-Farbe mit den Berechnungen, die das Theme braucht
 * (Mischen, relative Luminanz und Kontrast nach WCAG 2.x).
 */
final class Color
{
    private function __construct(
        public readonly int $r,
        public readonly int $g,
        public readonly int $b
    ) {
    }

    public static function fromHex(string $hex): ?self
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }
        return new self((int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)));
    }

    public static function rgb(int $r, int $g, int $b): self
    {
        $clamp = static fn (int $v): int => max(0, min(255, $v));
        return new self($clamp($r), $clamp($g), $clamp($b));
    }

    public function hex(): string
    {
        return sprintf('#%02X%02X%02X', $this->r, $this->g, $this->b);
    }

    /** "r, g, b" für rgba(var(--x-rgb), alpha) */
    public function rgbList(): string
    {
        return $this->r . ', ' . $this->g . ', ' . $this->b;
    }

    /** Mischt mit $other; $amount = Anteil von $other (0..1). */
    public function mix(self $other, float $amount): self
    {
        $amount = max(0.0, min(1.0, $amount));
        $m = static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $amount);
        return self::rgb($m($this->r, $other->r), $m($this->g, $other->g), $m($this->b, $other->b));
    }

    public function luminance(): float
    {
        $lin = static function (int $c): float {
            $s = $c / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $lin($this->r) + 0.7152 * $lin($this->g) + 0.0722 * $lin($this->b);
    }

    public function contrast(self $other): float
    {
        $a = $this->luminance();
        $b = $other->luminance();
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Verdunkelt bzw. erhellt die Farbe schrittweise, bis sie auf $background
     * mindestens $min Kontrast hat (Richtung: weg von der Hintergrundhelligkeit).
     */
    public function readableOn(self $background, float $min = 4.5): self
    {
        return $this->readableOnAll([$background], $min);
    }

    /**
     * Wie readableOn(), aber lesbar auf allen genannten Hintergründen gleichzeitig
     * (z.B. Seite, Karte und getönte Fläche, auf denen derselbe Link stehen kann).
     * Helligkeit wird im HSL-Raum verschoben: Farbton und Sättigung bleiben, ein dunkles Blau
     * wird so zu einem kräftigen helleren Blau statt zu Grau.
     * @param list<self> $backgrounds
     */
    public function readableOnAll(array $backgrounds, float $min = 4.5): self
    {
        $average = array_sum(array_map(static fn (self $b): float => $b->luminance(), $backgrounds)) / max(1, count($backgrounds));
        $lighten = $average <= 0.5;
        [$h, $s, $l] = $this->toHsl();
        for ($i = 0; $i <= 100; $i++) {
            $candidate = self::fromHsl($h, $s, $lighten ? min(1.0, $l + $i * 0.01) : max(0.0, $l - $i * 0.01));
            $ok = true;
            foreach ($backgrounds as $background) {
                if ($candidate->contrast($background) < $min) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $candidate;
            }
        }
        return $lighten ? self::rgb(255, 255, 255) : self::rgb(0, 0, 0);
    }

    /** @return array{0: float, 1: float, 2: float} Farbton 0–360, Sättigung 0–1, Helligkeit 0–1 */
    public function toHsl(): array
    {
        $r = $this->r / 255;
        $g = $this->g / 255;
        $b = $this->b / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h * 60, $s, $l];
    }

    public static function fromHsl(float $h, float $s, float $l): self
    {
        $h = fmod(fmod($h, 360) + 360, 360) / 360;
        $s = max(0.0, min(1.0, $s));
        $l = max(0.0, min(1.0, $l));
        if ($s === 0.0) {
            $v = (int) round($l * 255);
            return self::rgb($v, $v, $v);
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = static function (float $t) use ($p, $q): int {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
            return (int) round($v * 255);
        };
        return self::rgb($channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3));
    }

    /**
     * Dunkelt die Farbe ab, bis $text (z.B. Weiss) darauf mindestens $min Kontrast hat.
     * Für Flächen mit fester heller Schrift (Hero), die auch im Dunkelmodus ihre Farbe behalten sollen.
     */
    public function darkenForText(self $text, float $min = 4.5): self
    {
        $black = self::rgb(0, 0, 0);
        for ($step = 0; $step <= 20; $step++) {
            $candidate = $this->mix($black, $step * 0.05);
            if ($candidate->contrast($text) >= $min) {
                return $candidate;
            }
        }
        return $black;
    }

    /** Hellt die Farbe auf, bis $text (z.B. dunkle Schrift) darauf mindestens $min Kontrast hat. */
    public function lightenForText(self $text, float $min = 4.5): self
    {
        $white = self::rgb(255, 255, 255);
        for ($step = 0; $step <= 20; $step++) {
            $candidate = $this->mix($white, $step * 0.05);
            if ($candidate->contrast($text) >= $min) {
                return $candidate;
            }
        }
        return $white;
    }

    /** Schwarz-ähnlich oder Weiss – was auf dieser Fläche besser lesbar ist. */
    public function bestTextColor(self $dark, self $light): self
    {
        return $this->contrast($light) >= $this->contrast($dark) ? $light : $dark;
    }
}
