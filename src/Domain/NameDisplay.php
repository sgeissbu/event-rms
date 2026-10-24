<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Öffentliche Namensanzeige gemäss events.name_display (Datenschutz):
 * full = wie erfasst, short = «Vorname N.», start_no = nur Startnummer.
 * Teamnamen sind selbst gewählt und werden bei full/short gezeigt.
 */
final class NameDisplay
{
    /** @param array<string, mixed> $entry  mit name, start_no */
    public static function entry(array $entry, string $mode, int $teamSize): string
    {
        return match ($mode) {
            'start_no' => 'Startnr. ' . (int) $entry['start_no'],
            'short' => $teamSize === 1 ? self::short((string) $entry['name']) : (string) $entry['name'],
            default => (string) $entry['name'],
        };
    }

    /** Mitgliedernamen eines Teams für die Startliste; leer, wenn sie nicht gezeigt werden sollen. */
    public static function member(?string $name, string $mode): string
    {
        if ($name === null || $name === '' || $mode === 'start_no') {
            return '';
        }
        return $mode === 'short' ? self::short($name) : $name;
    }

    /** «Anna Maria Muster» → «Anna M.», «Anna» → «Anna». */
    public static function short(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($parts) < 2) {
            return trim($name);
        }
        $last = (string) end($parts);
        return $parts[0] . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.';
    }
}
