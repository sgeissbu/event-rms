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
        $target = $background->luminance() > 0.5 ? self::rgb(0, 0, 0) : self::rgb(255, 255, 255);
        for ($step = 0; $step <= 20; $step++) {
            $candidate = $this->mix($target, $step * 0.05);
            if ($candidate->contrast($background) >= $min) {
                return $candidate;
            }
        }
        return $target;
    }

    /** Schwarz-ähnlich oder Weiss – was auf dieser Fläche besser lesbar ist. */
    public function bestTextColor(self $dark, self $light): self
    {
        return $this->contrast($light) >= $this->contrast($dark) ? $light : $dark;
    }
}
