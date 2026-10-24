<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Teilt einen Eintrag anhand seiner Mitglieder einer Kategorie zu.
 *
 * Regeln pro Kategorie: gender any|M|F|mixed, birth_year_from/to (je optional).
 * Geprüft wird das Mitglied `category_member`; «mixed» heisst: nicht alle Mitglieder
 * haben dasselbe Geschlecht. Es gewinnt die erste passende Kategorie (nach sort).
 */
final class CategoryMatcher
{
    /**
     * Haben die Kategorien keinerlei Regeln, wählen Anmeldende selbst.
     * @param list<array<string, mixed>> $categories
     */
    public static function isManual(array $categories): bool
    {
        foreach ($categories as $c) {
            if (self::hasRule($c)) {
                return false;
            }
        }
        return $categories !== [];
    }

    /**
     * @param list<array<string, mixed>> $categories  sortiert
     * @param array<int, array{gender:?string, birth_year:?int}> $members  Position => Daten
     */
    public static function match(array $categories, array $members, int $categoryMember): ?int
    {
        $member = $members[$categoryMember] ?? ['gender' => null, 'birth_year' => null];
        foreach ($categories as $c) {
            if (self::matches($c, $member, $members)) {
                return (int) $c['id'];
            }
        }
        return null;
    }

    /** @param array<string, mixed> $category */
    private static function hasRule(array $category): bool
    {
        return ($category['gender'] ?? 'any') !== 'any'
            || $category['birth_year_from'] !== null
            || $category['birth_year_to'] !== null;
    }

    private static function matches(array $c, array $member, array $members): bool
    {
        $gender = $c['gender'] ?? 'any';
        if ($gender === 'mixed') {
            $genders = array_unique(array_filter(array_column($members, 'gender'), static fn ($g): bool => $g === 'M' || $g === 'F'));
            if (count($genders) < 2) {
                return false;
            }
        } elseif ($gender !== 'any' && ($member['gender'] ?? null) !== $gender) {
            return false;
        }

        $year = $member['birth_year'] ?? null;
        if ($c['birth_year_from'] !== null || $c['birth_year_to'] !== null) {
            if ($year === null) {
                return false;
            }
            if ($c['birth_year_from'] !== null && $year < (int) $c['birth_year_from']) {
                return false;
            }
            if ($c['birth_year_to'] !== null && $year > (int) $c['birth_year_to']) {
                return false;
            }
        }
        return true;
    }
}
