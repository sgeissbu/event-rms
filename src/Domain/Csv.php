<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * CSV für Excel (Schweiz/Deutschland): UTF-8 mit BOM, Semikolon, CRLF.
 * Schützt vor Formel-Injektion: Zellen, die mit = + - @ oder Tab/CR beginnen, werden mit «'» entschärft
 * (Anmeldedaten stammen von Besuchern, Admins öffnen die Datei in Excel).
 */
final class Csv
{
    public const BOM = "\xEF\xBB\xBF";

    /** @param list<scalar|null> $cells */
    public static function row(array $cells): string
    {
        return implode(';', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }

    /** @param scalar|null $value */
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false && !is_numeric($text)) {
            $text = "'" . $text;
        }
        if ($text !== '' && strpbrk($text, ";\"\r\n") !== false) {
            return '"' . str_replace('"', '""', $text) . '"';
        }
        return $text;
    }
}
