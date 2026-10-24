<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Berechnet Ranglisten nach Rangpunkten (siehe CONCEPT.md, Abschnitt «Wertung»).
 * Reine Funktion ohne DB-Zugriff – Eingaben kommen aus dem Repository bzw. aus Tests.
 *
 * Eingaben:
 *   entries:     [['id' => int, 'name' => string, 'category' => int|string|null,
 *                  'members' => [position => ['gender' => 'M'|'F'|'X'|null, 'birth_year' => ?int]]], …]
 *   disciplines: [['id' => int, 'higher_is_better' => bool, 'attempt_mode' => 'best'|'last',
 *                  'factor_female' => float, 'factor_age' => float], …]   (in Anzeige-Reihenfolge)
 *   results:     [['id' => int, 'entry_id' => int, 'discipline_id' => int, 'value' => float,
 *                  'created_at' => 'Y-m-d H:i:s'], …]                      (nur nicht annullierte)
 *   rules:       ['handicap_enabled' => bool, 'gender_member' => int, 'age_member' => int,
 *                  'reference_age' => ?int, 'event_year' => int]
 *
 * Ausgabe pro Kategorie-Schlüssel ('' = ohne Kategorie):
 *   ['counted' => [discipline_id, …],
 *    'rows' => [['entry_id', 'name', 'rank', 'points',
 *                'details' => [discipline_id => ['raw' => ?float, 'adjusted' => ?float, 'rank' => int, 'penalty' => bool]]], …]]
 */
final class RankingCalculator
{
    /** Relative Toleranz für Gleichstand (Rundung/Float), siehe Konzept. */
    private const REL_EPSILON = 1e-9;

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $disciplines
     * @param list<array<string, mixed>> $results
     * @param array<string, mixed> $rules
     * @return array<int|string, array{counted: list<int>, rows: list<array<string, mixed>>}>
     */
    public static function rank(array $entries, array $disciplines, array $results, array $rules): array
    {
        $raw = self::rawValues($results, $disciplines);

        $byCategory = [];
        foreach ($entries as $entry) {
            $byCategory[(string) ($entry['category'] ?? '')][] = $entry;
        }

        $out = [];
        foreach ($byCategory as $key => $categoryEntries) {
            $out[$key] = self::rankCategory($categoryEntries, $disciplines, $raw, $rules);
        }
        return $out;
    }

    /**
     * Massgebender Rohwert pro (Eintrag, Disziplin): bester bzw. letzter Versuch.
     * @return array<int, array<int, float>> [entry_id][discipline_id] => Wert
     */
    private static function rawValues(array $results, array $disciplines): array
    {
        $meta = [];
        foreach ($disciplines as $d) {
            $meta[(int) $d['id']] = $d;
        }
        // Stabile Reihenfolge für «letzter Versuch»: Erfassungszeit, dann ID.
        usort($results, static fn (array $a, array $b): int => [$a['created_at'], $a['id']] <=> [$b['created_at'], $b['id']]);

        $raw = [];
        foreach ($results as $r) {
            $d = $meta[(int) $r['discipline_id']] ?? null;
            if ($d === null) {
                continue;
            }
            $entryId = (int) $r['entry_id'];
            $discId = (int) $r['discipline_id'];
            $value = (float) $r['value'];
            $current = $raw[$entryId][$discId] ?? null;
            if ($current === null || ($d['attempt_mode'] ?? 'best') === 'last') {
                $raw[$entryId][$discId] = $value;
            } else {
                $raw[$entryId][$discId] = $d['higher_is_better'] ? max($current, $value) : min($current, $value);
            }
        }
        return $raw;
    }

    private static function rankCategory(array $entries, array $disciplines, array $raw, array $rules): array
    {
        $rows = [];
        foreach ($entries as $e) {
            $rows[(int) $e['id']] = ['entry_id' => (int) $e['id'], 'name' => (string) $e['name'], 'rank' => 0, 'points' => 0, 'details' => []];
        }
        $counted = [];

        foreach ($disciplines as $d) {
            $discId = (int) $d['id'];
            $higher = (bool) $d['higher_is_better'];

            $scores = [];
            foreach ($entries as $e) {
                $value = $raw[(int) $e['id']][$discId] ?? null;
                if ($value !== null) {
                    $scores[] = [
                        'entry_id' => (int) $e['id'],
                        'raw' => $value,
                        'adjusted' => self::adjust($value, $higher, self::factor($e, $d, $rules)),
                    ];
                }
            }
            if ($scores === []) {
                continue; // Disziplin ohne jedes Resultat in dieser Kategorie zählt nicht.
            }
            $counted[] = $discId;

            usort($scores, static function (array $a, array $b) use ($higher): int {
                if (self::equal($a['adjusted'], $b['adjusted'])) {
                    return 0;
                }
                return $higher ? $b['adjusted'] <=> $a['adjusted'] : $a['adjusted'] <=> $b['adjusted'];
            });

            foreach (self::competitionRanks(array_column($scores, 'adjusted')) as $i => $rank) {
                $s = $scores[$i];
                $rows[$s['entry_id']]['points'] += $rank;
                $rows[$s['entry_id']]['details'][$discId] = ['raw' => $s['raw'], 'adjusted' => $s['adjusted'], 'rank' => $rank, 'penalty' => false];
            }

            $penalty = count($scores) + 1;
            foreach ($rows as $id => $row) {
                if (!isset($row['details'][$discId])) {
                    $rows[$id]['points'] += $penalty;
                    $rows[$id]['details'][$discId] = ['raw' => null, 'adjusted' => null, 'rank' => $penalty, 'penalty' => true];
                }
            }
        }

        $rows = array_values($rows);
        usort($rows, static fn (array $a, array $b): int => [$a['points'], self::sortName($a['name'])] <=> [$b['points'], self::sortName($b['name'])]);
        $previous = null;
        foreach ($rows as $i => $row) {
            $rows[$i]['rank'] = ($previous !== null && $previous['points'] === $row['points']) ? $previous['rank'] : $i + 1;
            $previous = $rows[$i];
        }

        return ['counted' => $counted, 'rows' => $rows];
    }

    /** Handicap-Faktor F ≥ 1 für einen Eintrag in einer Disziplin (1 = kein Vorteil). */
    public static function factor(array $entry, array $discipline, array $rules): float
    {
        if (empty($rules['handicap_enabled'])) {
            return 1.0;
        }
        $members = $entry['members'] ?? [];

        $female = 1.0;
        $genderMember = $members[(int) ($rules['gender_member'] ?? 1)] ?? null;
        if (($genderMember['gender'] ?? null) === 'F') {
            $female = (float) $discipline['factor_female'];
        }

        $age = 1.0;
        $ageMember = $members[(int) ($rules['age_member'] ?? 1)] ?? null;
        $birthYear = $ageMember['birth_year'] ?? null;
        $referenceAge = $rules['reference_age'] ?? null;
        if ($birthYear !== null && $referenceAge !== null) {
            $yearsBelow = max(0, (int) $referenceAge - ((int) $rules['event_year'] - (int) $birthYear));
            $age = ((float) $discipline['factor_age']) ** $yearsBelow;
        }

        return $female * $age;
    }

    /** Vorteil anwenden: höher-ist-besser multipliziert, tiefer-ist-besser dividiert. */
    public static function adjust(float $raw, bool $higherIsBetter, float $factor): float
    {
        return $higherIsBetter ? $raw * $factor : $raw / $factor;
    }

    /**
     * Wettbewerbsrang (1, 1, 3, …) für bereits sortierte Werte.
     * @param list<float> $sortedValues
     * @return list<int>
     */
    private static function competitionRanks(array $sortedValues): array
    {
        $ranks = [];
        foreach ($sortedValues as $i => $value) {
            $ranks[$i] = ($i > 0 && self::equal($value, $sortedValues[$i - 1])) ? $ranks[$i - 1] : $i + 1;
        }
        return $ranks;
    }

    private static function equal(float $a, float $b): bool
    {
        return abs($a - $b) <= self::REL_EPSILON * max(1.0, abs($a), abs($b));
    }

    private static function sortName(string $name): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    }
}
