<?php
/**
 * Golden-Test: neue Wertung gegen die echte OKC-Rangliste 2026.
 * Die Daten (tests/fixtures/private/okc2026.json) sind nicht im Repository;
 * ohne sie wird der Test übersprungen.
 *
 * Abbildung OKC → event-rms:
 *   Kategorie = Geschlecht Kind, Mitglied 1 = Kind, Mitglied 2 = erwachsene Person,
 *   Handicap: Geschlecht Mitglied 2, Alter Mitglied 1, Referenzalter = Jahr − 2010,
 *   Faktoren < 1 bei «tiefer ist besser» werden zu 1/f.
 */
declare(strict_types=1);

use App\Domain\RankingCalculator;

$load = static function (): array {
    $file = __DIR__ . '/fixtures/private/okc2026.json';
    if (!is_file($file)) {
        skip('keine OKC-Daten (tests/fixtures/private/okc2026.json)');
    }
    $f = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    $entries = array_map(static fn (array $t): array => [
        'id' => $t['id'],
        'name' => $t['name'],
        'category' => $t['kind_geschlecht'],
        'members' => [
            1 => ['gender' => $t['kind_geschlecht'], 'birth_year' => $t['kind_jahrgang']],
            2 => ['gender' => $t['erwachsene_geschlecht'], 'birth_year' => null],
        ],
    ], $f['teams']);

    $invert = static fn (float $factor, int $higher): float => $higher ? $factor : 1 / $factor;
    $disciplines = array_map(static fn (array $d): array => [
        'id' => $d['id'],
        'higher_is_better' => (bool) $d['hoch_ist_besser'],
        'attempt_mode' => 'best',
        'factor_female' => $invert($d['bonus_frau'], $d['hoch_ist_besser']),
        'factor_age' => $invert($d['bonus_alter'], $d['hoch_ist_besser']),
    ], $f['disciplines']);

    $results = array_map(static fn (array $r): array => [
        'id' => $r['id'], 'entry_id' => $r['team_id'], 'discipline_id' => $r['discipline_id'],
        'value' => $r['value'], 'created_at' => $r['created_at'],
    ], $f['results']);

    $rules = [
        'handicap_enabled' => true, 'gender_member' => 2, 'age_member' => 1,
        'reference_age' => $f['year'] - 2010, 'event_year' => $f['year'],
    ];

    return [$f, RankingCalculator::rank($entries, $disciplines, $results, $rules)];
};

return [
    'Alle Faktoren sind nach Umrechnung ≥ 1' => function () use ($load): void {
        [$f] = $load();
        foreach ($f['disciplines'] as $d) {
            foreach (['bonus_frau', 'bonus_alter'] as $key) {
                $value = $d['hoch_ist_besser'] ? $d[$key] : 1 / $d[$key];
                assert_true($value >= 1 - 1e-9, "{$d['name']} {$key} = {$value}");
            }
        }
    },
    'Disziplin-Ränge identisch mit OKC' => function () use ($load): void {
        [$f, $ours] = $load();
        foreach ($f['okc_ranking'] as $category => $okcRows) {
            $byId = array_column($ours[$category]['rows'], null, 'entry_id');
            foreach ($okcRows as $okc) {
                foreach ($okc['ranks'] as $discId => $okcRank) {
                    $mine = $byId[$okc['team_id']]['details'][(int) $discId]['rank'] ?? null;
                    assert_same($okcRank, $mine, "Kategorie {$category}, Team {$okc['team_id']}, Disziplin {$discId}");
                }
            }
        }
    },
    'Gesamtpunkte identisch mit OKC' => function () use ($load): void {
        [$f, $ours] = $load();
        foreach ($f['okc_ranking'] as $category => $okcRows) {
            $mine = array_column($ours[$category]['rows'], 'points', 'entry_id');
            foreach ($okcRows as $okc) {
                assert_same($okc['points'], $mine[$okc['team_id']] ?? null, "Kategorie {$category}, Team {$okc['team_id']}");
            }
            assert_same(count($okcRows), count($mine), "Anzahl Teams {$category}");
        }
    },
    'Rangfolge identisch (bis auf bewusste Gleichstand-Regel)' => function () use ($load): void {
        [$f, $ours] = $load();
        foreach ($f['okc_ranking'] as $category => $okcRows) {
            // OKC nummeriert fortlaufend; wir vergeben bei Punktgleichheit denselben Rang.
            $expected = [];
            foreach ($okcRows as $i => $row) {
                $expected[$row['team_id']] = ($i > 0 && $okcRows[$i - 1]['points'] === $row['points'])
                    ? $expected[$okcRows[$i - 1]['team_id']] : $i + 1;
            }
            $mine = array_column($ours[$category]['rows'], 'rank', 'entry_id');
            ksort($expected);
            ksort($mine);
            assert_same($expected, $mine, "Kategorie {$category}");
        }
    },
];
