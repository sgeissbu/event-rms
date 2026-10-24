<?php
declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Eingabe und Anzeige von Messwerten. Zeiten werden in Sekunden gespeichert.
 */
final class ValueFormat
{
    /**
     * Wandelt eine Eingabe in eine Zahl um. Leer → null (kein Resultat, nicht 0!).
     * Zahl: «12.5» oder «12,5». Zeit: «83.45», «1:23.45», «1:02:03» (h:mm:ss).
     * @throws InvalidArgumentException bei ungültiger Eingabe
     */
    public static function parse(string $input, string $format): ?float
    {
        $input = str_replace([',', ' ', "'"], ['.', '', ''], trim($input));
        if ($input === '') {
            return null;
        }
        if ($format !== 'time' || !str_contains($input, ':')) {
            return self::number($input);
        }

        // [h:]m:ss.hh – vorderste Einheit darf beliebig gross sein, die folgenden < 60.
        $parts = explode(':', $input);
        if (count($parts) > 3) {
            throw new InvalidArgumentException('Ungültige Zeit. Format: m:ss.hh');
        }
        $seconds = self::number((string) array_pop($parts));
        if ($seconds < 0 || $seconds >= 60) {
            throw new InvalidArgumentException('Sekunden müssen zwischen 0 und 59.99 liegen.');
        }
        $multiplier = 60;
        foreach (array_reverse($parts) as $i => $part) {
            $isLeading = $i === count($parts) - 1;
            if (!ctype_digit($part) || (!$isLeading && (int) $part >= 60)) {
                throw new InvalidArgumentException('Ungültige Zeit. Format: m:ss.hh');
            }
            $seconds += (int) $part * $multiplier;
            $multiplier *= 60;
        }
        return $seconds;
    }

    /** Anzeige: Zahl mit fixen Nachkommastellen bzw. Zeit als m:ss.hh. */
    public static function format(?float $value, string $format, int $decimals): string
    {
        if ($value === null) {
            return '–';
        }
        if ($format === 'time') {
            $negative = $value < 0;
            $value = abs($value);
            $minutes = (int) floor(round($value, $decimals) / 60);
            $seconds = round($value, $decimals) - $minutes * 60;
            $sec = number_format($seconds, $decimals, '.', '');
            if ($minutes === 0) {
                return ($negative ? '-' : '') . $sec;
            }
            $width = $decimals > 0 ? 3 + $decimals : 2;
            return ($negative ? '-' : '') . $minutes . ':' . str_pad($sec, $width, '0', STR_PAD_LEFT);
        }
        return number_format($value, $decimals, '.', "'");
    }

    /**
     * Wert mit Einheit für die Anzeige. Bei Zeiten im Format m:ss entfällt die Einheit
     * («1:15.97» statt «1:15.97 s»), unter einer Minute bleibt sie («58.20 s»).
     */
    public static function withUnit(?float $value, string $format, int $decimals, string $unit): string
    {
        $text = self::format($value, $format, $decimals);
        if ($value === null || $unit === '' || ($format === 'time' && str_contains($text, ':'))) {
            return $text;
        }
        return $text . ' ' . $unit;
    }

    private static function number(string $s): float
    {
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s) && !preg_match('/^-?\.\d+$/', $s)) {
            throw new InvalidArgumentException('Bitte eine Zahl eingeben.');
        }
        return (float) $s;
    }
}
