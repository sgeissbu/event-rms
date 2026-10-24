<?php
declare(strict_types=1);

use App\Domain\RankingCalculator;

// --- kleine Bausteine für lesbare Testfälle ---------------------------------------
$entry = static fn (int $id, string $name, array $members = [], int|string|null $category = null): array =>
    ['id' => $id, 'name' => $name, 'category' => $category, 'members' => $members];
$member = static fn (?string $gender = null, ?int $birthYear = null): array => ['gender' => $gender, 'birth_year' => $birthYear];
$disc = static fn (int $id, bool $higher = true, string $mode = 'best', float $female = 1.0, float $age = 1.0): array =>
    ['id' => $id, 'higher_is_better' => $higher, 'attempt_mode' => $mode, 'factor_female' => $female, 'factor_age' => $age];
$res = static function (int $entryId, int $discId, float $value, string $time = '2027-03-20 14:00:00') {
    static $id = 0;
    return ['id' => ++$id, 'entry_id' => $entryId, 'discipline_id' => $discId, 'value' => $value, 'created_at' => $time];
};
$noHandicap = ['handicap_enabled' => false, 'gender_member' => 1, 'age_member' => 1, 'reference_age' => null, 'event_year' => 2027];

/** Kurzform: [Name => Rang] bzw. [Name => Punkte] einer Kategorie. */
$ranks = static fn (array $category): array => array_column($category['rows'], 'rank', 'name');
$points = static fn (array $category): array => array_column($category['rows'], 'points', 'name');

return [
    'Einzel: höher ist besser, Ränge = Rangpunkte' => function () use ($entry, $disc, $res, $noHandicap, $ranks, $points): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben'), $entry(3, 'Cara')],
            [$disc(10)],
            [$res(1, 10, 3.2), $res(2, 10, 4.1), $res(3, 10, 2.0)],
            $noHandicap
        )[''];
        assert_same(['Ben' => 1, 'Anna' => 2, 'Cara' => 3], $ranks($out));
        assert_same(['Ben' => 1, 'Anna' => 2, 'Cara' => 3], $points($out));
        assert_same([10], $out['counted']);
    },
    'Tiefer ist besser: bester Versuch = kleinster Wert (OKC-Bug MAX behoben)' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben')],
            [$disc(10, false)],
            [$res(1, 10, 15.0), $res(1, 10, 11.0), $res(2, 10, 12.0)],
            $noHandicap
        )[''];
        assert_same('Anna', $out['rows'][0]['name']);
        assert_near(11.0, $out['rows'][0]['details'][10]['raw']);
    },
    'Modus «letzter Versuch» nimmt das jüngste Resultat, auch wenn schlechter' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna')],
            [$disc(10, true, 'last')],
            [$res(1, 10, 2.0, '2027-03-20 14:05:00'), $res(1, 10, 5.0, '2027-03-20 14:00:00')],
            $noHandicap
        )[''];
        assert_near(2.0, $out['rows'][0]['details'][10]['raw']);
    },
    'Gleichstand in einer Disziplin: Wettbewerbsrang 1, 1, 3' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben'), $entry(3, 'Cara')],
            [$disc(10)],
            [$res(1, 10, 5.0), $res(2, 10, 5.0), $res(3, 10, 4.0)],
            $noHandicap
        )[''];
        $byName = array_column($out['rows'], null, 'name');
        assert_same(1, $byName['Anna']['details'][10]['rank']);
        assert_same(1, $byName['Ben']['details'][10]['rank']);
        assert_same(3, $byName['Cara']['details'][10]['rank']);
    },
    'Gleichstand toleriert Float-Rundung (0.1+0.2 = 0.3)' => function () use ($entry, $disc, $res, $noHandicap, $ranks): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben')],
            [$disc(10)],
            [$res(1, 10, 0.1 + 0.2), $res(2, 10, 0.3)],
            $noHandicap
        )[''];
        assert_same(['Anna' => 1, 'Ben' => 1], $ranks($out));
    },
    'Fehlendes Resultat: Strafrang = Anzahl mit Resultat + 1' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben'), $entry(3, 'Cara')],
            [$disc(10), $disc(11)],
            [$res(1, 10, 5.0), $res(2, 10, 4.0), $res(3, 10, 3.0), $res(1, 11, 1.0), $res(2, 11, 2.0)],
            $noHandicap
        )[''];
        $cara = array_column($out['rows'], null, 'name')['Cara'];
        assert_same(['raw' => null, 'adjusted' => null, 'rank' => 3, 'penalty' => true], $cara['details'][11]);
        assert_same(3 + 3, $cara['points']);
    },
    'Disziplin ganz ohne Resultate zählt nicht' => function () use ($entry, $disc, $res, $noHandicap, $points): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben')],
            [$disc(10), $disc(11)],
            [$res(1, 10, 5.0), $res(2, 10, 4.0)],
            $noHandicap
        )[''];
        assert_same([10], $out['counted']);
        assert_same(['Anna' => 1, 'Ben' => 2], $points($out));
        assert_true(!isset($out['rows'][0]['details'][11]));
    },
    'Gesamt-Gleichstand: gleicher Rang, alphabetisch sortiert' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Zora'), $entry(2, 'anna'), $entry(3, 'Ben')],
            [$disc(10), $disc(11)],
            [$res(1, 10, 9), $res(2, 10, 5), $res(3, 10, 1), $res(1, 11, 1), $res(2, 11, 5), $res(3, 11, 9)],
            $noHandicap
        )[''];
        // Zora 1+3=4, anna 2+2=4, Ben 3+1=4 → alle Rang 1
        assert_same(['anna', 'Ben', 'Zora'], array_column($out['rows'], 'name'));
        assert_same([1, 1, 1], array_column($out['rows'], 'rank'));
    },
    'Gesamtrang nach Gleichstand springt (1, 1, 3)' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna'), $entry(2, 'Ben'), $entry(3, 'Cara')],
            [$disc(10)],
            [$res(1, 10, 5), $res(2, 10, 5), $res(3, 10, 1)],
            $noHandicap
        )[''];
        assert_same([1, 1, 3], array_column($out['rows'], 'rank'));
    },
    'Kategorien werden getrennt gewertet (inkl. Strafrang)' => function () use ($entry, $disc, $res, $noHandicap): void {
        $out = RankingCalculator::rank(
            [$entry(1, 'Anna', [], 'F'), $entry(2, 'Ben', [], 'M'), $entry(3, 'Cara', [], 'F'), $entry(4, 'Dario', [], 'M')],
            [$disc(10)],
            [$res(1, 10, 1.0), $res(2, 10, 9.0), $res(3, 10, 2.0)],
            $noHandicap
        );
        assert_same(['Cara', 'Anna'], array_column($out['F']['rows'], 'name'));
        assert_same(['Ben' => 1, 'Dario' => 2], array_column($out['M']['rows'], 'points', 'name'));
    },
    'Keine Einträge → leeres Ergebnis' => function () use ($disc, $noHandicap): void {
        assert_same([], RankingCalculator::rank([], [$disc(10)], [], $noHandicap));
    },
    'Handicap: Frauenfaktor nur für das festgelegte Mitglied' => function () use ($entry, $member, $disc): void {
        $rules = ['handicap_enabled' => true, 'gender_member' => 2, 'age_member' => 1, 'reference_age' => null, 'event_year' => 2027];
        $d = $disc(10, true, 'best', 1.2);
        assert_near(1.2, RankingCalculator::factor($entry(1, 'A', [1 => $member('M'), 2 => $member('F')]), $d, $rules));
        assert_near(1.0, RankingCalculator::factor($entry(2, 'B', [1 => $member('F'), 2 => $member('M')]), $d, $rules));
        assert_near(1.0, RankingCalculator::factor($entry(3, 'C', [1 => $member('F'), 2 => $member('X')]), $d, $rules));
        assert_near(1.0, RankingCalculator::factor($entry(4, 'D', [1 => $member('F')]), $d, $rules), 1e-9, 'Mitglied fehlt');
    },
    'Handicap: Altersfaktor pro Jahr unter Referenzalter, nie Nachteil' => function () use ($entry, $member, $disc): void {
        $rules = ['handicap_enabled' => true, 'gender_member' => 1, 'age_member' => 1, 'reference_age' => 16, 'event_year' => 2027];
        $d = $disc(10, true, 'best', 1.0, 1.05);
        // Jahrgang 2017 → 10 Jahre alt → 6 Jahre unter 16
        assert_near(1.05 ** 6, RankingCalculator::factor($entry(1, 'A', [1 => $member('M', 2017)]), $d, $rules));
        // Jahrgang 2005 → 22 Jahre alt → kein negativer Exponent
        assert_near(1.0, RankingCalculator::factor($entry(2, 'B', [1 => $member('M', 2005)]), $d, $rules));
        assert_near(1.0, RankingCalculator::factor($entry(3, 'C', [1 => $member('M', null)]), $d, $rules), 1e-9, 'ohne Jahrgang');
    },
    'Handicap: tiefer ist besser wird durch den Faktor geteilt' => function () use ($entry, $member, $disc, $res): void {
        $rules = ['handicap_enabled' => true, 'gender_member' => 1, 'age_member' => 1, 'reference_age' => null, 'event_year' => 2027];
        $out = RankingCalculator::rank(
            [$entry(1, 'Frau', [1 => $member('F')]), $entry(2, 'Mann', [1 => $member('M')])],
            [$disc(10, false, 'best', 1.1)],
            [$res(1, 10, 12.0), $res(2, 10, 11.0)],
            $rules
        )[''];
        // 12.0 / 1.1 = 10.909… schlägt 11.0
        assert_same('Frau', $out['rows'][0]['name']);
        assert_near(12.0 / 1.1, $out['rows'][0]['details'][10]['adjusted']);
    },
    'Handicap aus: Faktoren werden ignoriert' => function () use ($entry, $member, $disc, $noHandicap): void {
        assert_near(1.0, RankingCalculator::factor($entry(1, 'A', [1 => $member('F', 2018)]), $disc(10, true, 'best', 1.5, 1.1), $noHandicap));
    },
    'Teams à 4: Handicap über Mitglied 3, Wertung wie Einzel' => function () use ($entry, $member, $disc, $res): void {
        $rules = ['handicap_enabled' => true, 'gender_member' => 3, 'age_member' => 3, 'reference_age' => 12, 'event_year' => 2027];
        $team = static fn (int $id, string $name, ?string $g3, int $by3) => $entry($id, $name,
            [1 => $member('M', 2000), 2 => $member('M', 2000), 3 => $member($g3, $by3), 4 => $member('M', 2000)]);
        $out = RankingCalculator::rank(
            [$team(1, 'Blitz', 'F', 2017), $team(2, 'Donner', 'M', 2015)],
            [$disc(10, true, 'best', 1.1, 1.02)],
            [$res(1, 10, 10.0), $res(2, 10, 11.5)],
            $rules
        )[''];
        // Blitz: 10 · 1.1 · 1.02^(12−10) = 11.4444  < Donner: 11.5 · 1.02^(12−12) = 11.5
        assert_same(['Donner', 'Blitz'], array_column($out['rows'], 'name'));
        assert_near(10.0 * 1.1 * 1.02 ** 2, $out['rows'][1]['details'][10]['adjusted']);
    },
];
