<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Prüft das Kategorie-Formular im Admin.
 */
final class CategoryValidator
{
    public const GENDERS = ['any' => 'egal', 'F' => 'weiblich', 'M' => 'männlich', 'mixed' => 'gemischt (Teams)'];

    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input, int $eventYear): array
    {
        $str = static fn (string $key): string => trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        $errors = [];

        $name = $str('name');
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Bitte einen Namen angeben (max. 100 Zeichen).';
        }

        $gender = $str('gender') ?: 'any';
        if (!isset(self::GENDERS[$gender])) {
            $errors['gender'] = 'Ungültige Auswahl.';
        }

        $years = [];
        foreach (['birth_year_from', 'birth_year_to'] as $key) {
            $raw = $str($key);
            if ($raw === '') {
                $years[$key] = null;
            } elseif (ctype_digit($raw) && (int) $raw >= $eventYear - 110 && (int) $raw <= $eventYear) {
                $years[$key] = (int) $raw;
            } else {
                $errors[$key] = 'Bitte einen Jahrgang zwischen ' . ($eventYear - 110) . ' und ' . $eventYear . ' angeben oder leer lassen.';
                $years[$key] = null;
            }
        }
        if ($years['birth_year_from'] !== null && $years['birth_year_to'] !== null && $years['birth_year_from'] > $years['birth_year_to']) {
            $errors['birth_year_to'] = '«bis» muss gleich oder später sein als «von».';
        }

        $sort = $str('sort');
        return [
            'values' => [
                'name' => $name,
                'gender' => $gender,
                'birth_year_from' => $years['birth_year_from'],
                'birth_year_to' => $years['birth_year_to'],
                'sort' => preg_match('/^-?\d{1,4}$/', $sort) ? (int) $sort : 0,
            ],
            'errors' => $errors,
        ];
    }

    /** Lesbare Regel, z.B. «weiblich, Jg. 2015–2016». */
    public static function describe(array $category): string
    {
        $parts = [];
        if (($category['gender'] ?? 'any') !== 'any') {
            $parts[] = self::GENDERS[$category['gender']] ?? $category['gender'];
        }
        $from = $category['birth_year_from'];
        $to = $category['birth_year_to'];
        if ($from !== null && $to !== null) {
            $parts[] = $from === $to ? "Jg. {$from}" : "Jg. {$from}–{$to}";
        } elseif ($from !== null) {
            $parts[] = "Jg. {$from} und jünger";
        } elseif ($to !== null) {
            $parts[] = "Jg. {$to} und älter";
        }
        return $parts === [] ? 'alle (manuelle Wahl)' : implode(', ', $parts);
    }
}
